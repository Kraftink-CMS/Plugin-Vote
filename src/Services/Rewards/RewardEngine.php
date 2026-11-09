<?php

namespace KraftInk\Plugins\Vote\Services\Rewards;

use App\Kraft\Auth\PermissionAccess;
use App\Kraft\Games\GameRegistry;
use App\Kraft\Notifications\Notifications;
use App\Kraft\System\OperationLock;
use App\Kraft\Wallet\Wallet;
use App\Models\Accounts\Role;
use App\Models\Accounts\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use KraftInk\Plugins\Vote\Models\Delivery\Delivery;
use KraftInk\Plugins\Vote\Models\Rewards\Reward;
use KraftInk\Plugins\Vote\Models\Rewards\RewardClaim;
use KraftInk\Plugins\Vote\Models\Rewards\RewardRun;
use KraftInk\Plugins\Vote\Models\Rewards\RoleGrant;
use KraftInk\Plugins\Vote\Models\Voting\Vote;
use App\Kraft\Link\CommandIdentity;
use RuntimeException;
use Throwable;
use DateTimeInterface;
use InvalidArgumentException;

class RewardEngine
{
    public function hasProgress(?User $user): bool
    {
        return $this->progressRewards($user)->exists();
    }

    public function progress(?User $user): array
    {
        $progress = [];
        foreach ($this->progressRewards($user)->limit(30)->get() as $reward) {
            /** @var Reward $reward */
            [$start, $end] = $this->periodBounds($reward->period);
            $progress[] = [
                'id' => $reward->id,
                'titles' => $reward->titles ?? [],
                'type' => $reward->type,
                'metric' => $reward->metric,
                'target' => (int) $reward->threshold,
                'current' => $this->metric($reward, $start, $end, $reward->type === 'milestone' ? $user->id : null),
            ];
        }

        return $progress;
    }

    private function progressRewards(?User $user): Builder
    {
        $access = app(PermissionAccess::class);
        $canReachMilestones = $user && !$user->isBanned() && !$user->anonymized_at && !$user->import_locked;
        $query = $this->active()->whereIn('type', $canReachMilestones ? ['goal', 'milestone'] : ['goal']);

        return $access->filterQuery($query, 'permission', $access->permissions($user));
    }

    public function voted(object $vote, User $user, bool $perVote = true): void
    {
        $operationLease = app(OperationLock::class)->acquireShared();
        try {
            $rewards = $this->active()
                ->where(fn ($query) => $query->whereNull('site_id')->orWhere('site_id', $vote->site_id))
                ->orderBy('id')
                ->get();
            foreach ($perVote ? $rewards->where('type', 'vote') : [] as $reward) {
                /** @var Reward $reward */
                if ($this->eligible($reward, $user) && random_int(1, 10000) <= $reward->chance_bps) {
                    $this->claim($reward, $user, $vote, 'vote:'.$vote->id);
                }
            }
            foreach ($perVote ? $rewards->where('type', 'random')->groupBy('pool') : [] as $pool) {
                $pool = $pool->filter(fn ($reward) => $this->eligible($reward, $user));
                $total = (int) $pool->sum('weight');
                if ($total < 1) {
                    continue;
                }
                $draw = random_int(1, $total);
                foreach ($pool as $reward) {
                    $draw -= $reward->weight;
                    if ($draw <= 0) {
                        if (random_int(1, 10000) <= $reward->chance_bps) {
                            $this->claim($reward, $user, $vote, 'vote:'.$vote->id);
                        }
                        break;
                    }
                }
            }
            foreach ($rewards->where('type', 'milestone') as $reward) {
                /** @var Reward $reward */
                [$start, $end, $key] = $this->periodBounds($reward->period, $vote->created_at);
                if (
                    $this->eligible($reward, $user) &&
                    $this->metric($reward, $start, $end, $user->id) >= $reward->threshold
                ) {
                    $this->claim($reward, $user, $vote, $key);
                }
            }
            foreach ($rewards->where('type', 'goal') as $reward) {
                /** @var Reward $reward */
                [$start, $end, $key] = $this->periodBounds($reward->period, $vote->created_at);
                if ($this->metric($reward, $start, $end) >= $reward->threshold) {
                    $this->run($reward, $key, $start, $end);
                }
            }
        } finally {
            $operationLease->release();
        }
    }

    public function tick(int $limit = 200): int
    {
        $operationLease = app(OperationLock::class)->acquireShared();
        try {
            $notified = app(\KraftInk\Plugins\Vote\Services\Votifier\VotifierDeliveries::class)->run($limit);
            foreach ($this->active()->where('type', 'goal')->get() as $reward) {
                /** @var Reward $reward */
                [$start, $end, $key] = $this->periodBounds($reward->period);
                if ($this->metric($reward, $start, $end) >= $reward->threshold) {
                    $this->run($reward, $key, $start, $end);
                }
            }
            foreach ($this->active()->where('type', 'monthly')->get() as $reward) {
                /** @var Reward $reward */
                $latest = RewardRun::query()
                    ->where('reward_id', $reward->id)
                    ->where('period_key', 'like', '____-__')
                    ->max('period_key');
                $month = $latest
                    ? CarbonImmutable::parse($latest.'-01', 'UTC')->addMonth()
                    : CarbonImmutable::parse($reward->created_at, 'UTC')->startOfMonth();
                $last = CarbonImmutable::now('UTC')->startOfMonth();
                for ($i = 0; $month->lessThan($last) && $i < 120; $i++, $month = $month->addMonth()) {
                    $this->run($reward, $month->format('Y-m'), $month, $month->addMonth());
                }
            }
            $processed = 0;
            foreach (RewardRun::query()->where('status', 'pending')->orderBy('id')->limit(10)->get() as $run) {
                /** @var RewardRun $run */
                $processed += $this->processRun((int) $run->id, max(1, $limit - $processed));
                if ($processed >= $limit) {
                    break;
                }
            }
            foreach (
                RewardClaim::query()->where('status', 'pending')->orderBy('id')->limit($limit)->pluck('id') as $id
            ) {
                $this->process((int) $id);
            }

            return $processed + $notified;
        } finally {
            $operationLease->release();
        }
    }

    public function claim(object $reward, User $user, object $vote, string $period): ?int
    {
        $operationLease = app(OperationLock::class)->acquireShared();
        try {
            if (!$this->eligible($reward, $user)) {
                return null;
            }
            $key = hash('sha256', $reward->id.':'.$user->id.':'.$period);
            $snapshot = $this->snapshot($reward);
            $snapshot['server_id'] ??= Vote::query()->where('id', $vote->id)->value('server_id');
            RewardClaim::query()->insertOrIgnore([
                'reward_id' => $reward->id,
                'user_id' => $user->id,
                'vote_id' => $vote->id,
                'claim_key' => $key,
                'period_key' => $period,
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'status' => 'pending',
                'created_at' => now(),
            ]);

            return (int) RewardClaim::query()->where('claim_key', $key)->value('id');
        } finally {
            $operationLease->release();
        }
    }

    public function process(int $id): void
    {
        $operationLease = app(OperationLock::class)->acquireShared();
        try {
            try {
                DB::transaction(function () use ($id) {
                    $claim = RewardClaim::query()->where('id', $id)->lockForUpdate()->first();
                    if (!$claim || $claim->status !== 'pending') {
                        return;
                    }
                    $user = User::query()->whereKey($claim->user_id)->lockForUpdate()->first();
                    if (
                        !$user ||
                        $user->anonymized_at ||
                        $user->isBanned() ||
                        $user->import_locked ||
                        !$claim->vote_id
                    ) {
                        RewardClaim::query()
                            ->where('id', $id)
                            ->update(['status' => 'cancelled', 'processed_at' => now()]);

                        return;
                    }
                    $reward = (object) ($claim->snapshot ?? []);
                    if ($reward->game !== app(GameRegistry::class)->activeId()) {
                        throw new RuntimeException('game_changed');
                    }
                    $snapshot = app(CommandIdentity::class)->resolve(
                        $user,
                        $reward->server_id,
                        $reward->commands,
                        $reward->requires_online,
                    );
                    if ($reward->wallet_minor > 0) {
                        if ($reward->wallet_currency !== app(Wallet::class)->currency()) {
                            throw new RuntimeException('currency_changed');
                        }
                        app(Wallet::class)->credit($user, $reward->wallet_minor, 'vote:claim:'.$id, 'vote.reward', [
                            'reward' => $claim->reward_id,
                            'claim' => $id,
                        ]);
                    }
                    if ($reward->role_id) {
                        $role = Role::query()->find($reward->role_id);
                        if (
                            !$role ||
                            $role->slug === 'owner' ||
                            in_array('*', $role->effectivePermissions(), true) ||
                            !app(RewardPolicy::class)->matchesRolePermissions($role, $reward->role_permissions)
                        ) {
                            throw new RuntimeException('role_changed');
                        }
                        RoleGrant::query()->insertOrIgnore([
                            'user_id' => $user->id,
                            'role_id' => $role->id,
                            'claim_id' => $id,
                            'permissions' => json_encode($reward->role_permissions, JSON_THROW_ON_ERROR),
                            'expires_at' => $reward->role_days ? now()->addDays($reward->role_days) : null,
                            'created_at' => now(),
                        ]);
                    }
                    foreach ($reward->commands as $command) {
                        Delivery::query()->insert(
                            [
                                'id' => (string) Str::uuid(),
                                'vote_id' => $claim->vote_id,
                                'user_id' => $user->id,
                                'reward_claim_id' => $id,
                                'command' => app(CommandIdentity::class)->expand((object) $snapshot, $command),
                                'status' => 'pending',
                                'attempts' => 0,
                                'available_at' => now(),
                                'created_at' => now(),
                            ] + Arr::except($snapshot, 'command_identity'),
                        );
                    }
                    app(Notifications::class)->notify(
                        'vote.reward',
                        $user,
                        ['claim_id' => $id],
                        'claim:'.$id,
                        resource: 'claim:'.$id,
                    );
                    RewardClaim::query()
                        ->where('id', $id)
                        ->update(['status' => 'processed', 'error' => null, 'processed_at' => now()]);
                }, 3);
            } catch (Throwable $exception) {
                $code = in_array($exception->getMessage(), ['game_changed', 'currency_changed', 'role_changed'], true)
                    ? $exception->getMessage()
                    : 'processing_failed';
                RewardClaim::query()
                    ->where('id', $id)
                    ->where('status', 'pending')
                    ->update(['status' => 'error', 'error' => $code]);
                report($exception);
            }
        } finally {
            $operationLease->release();
        }
    }

    private function run(object $reward, string $key, CarbonImmutable $start, ?CarbonImmutable $end): void
    {
        if (RewardRun::query()->where('reward_id', $reward->id)->where('period_key', $key)->exists()) {
            return;
        }
        $snapshot = $this->snapshot($reward);
        $snapshot['last_vote_id'] = (int) $this->votes($reward, $start, $end)->max('id');
        if ($reward->type === 'monthly') {
            $snapshot['winners'] = $this->votes($reward, $start, $end)
                ->where('id', '<=', $snapshot['last_vote_id'])
                ->select('user_id')
                ->selectRaw(
                    'MAX(id) AS vote_id, '.($reward->metric === 'points' ? 'SUM(points)' : 'COUNT(*)').' AS score',
                )
                ->groupBy('user_id')
                ->orderByDesc('score')
                ->orderBy('user_id')
                ->limit($reward->rank_to)
                ->get()
                ->slice($reward->threshold - 1)
                ->values()
                ->all();
        }
        RewardRun::query()->insertOrIgnore([
            'reward_id' => $reward->id,
            'period_key' => $key,
            'starts_at' => $start,
            'ends_at' => $end,
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function processRun(int $id, int $limit): int
    {
        return DB::transaction(function () use ($id, $limit) {
            $run = RewardRun::query()->where('id', $id)->lockForUpdate()->first();
            if (!$run || $run->status !== 'pending') {
                return 0;
            }
            $reward = (object) ($run->snapshot ?? []);
            $query = $this->votes($reward, $run->starts_at, $run->ends_at)
                ->where('id', '<=', $reward->last_vote_id)
                ->select('user_id')
                ->selectRaw(
                    'MAX(id) AS vote_id, '.($reward->metric === 'points' ? 'SUM(points)' : 'COUNT(*)').' AS score',
                )
                ->groupBy('user_id');
            if ($reward->type === 'monthly') {
                $winners = collect($reward->winners)
                    ->map(fn ($winner) => (object) $winner)
                    ->where('user_id', '>', $run->cursor_user_id)
                    ->sortBy('user_id')
                    ->take($limit);
            } else {
                $winners = $query
                    ->where('user_id', '>', $run->cursor_user_id)
                    ->orderBy('user_id')
                    ->limit($limit)
                    ->toBase()
                    ->get();
            }
            foreach ($winners as $winner) {
                $user = User::query()->find($winner->user_id);
                if ($user) {
                    $this->claim($reward, $user, (object) ['id' => $winner->vote_id], $run->period_key);
                }
            }
            RewardRun::query()
                ->where('id', $id)
                ->update([
                    'cursor_user_id' => $winners->max('user_id') ?? $run->cursor_user_id,
                    'status' => $winners->count() < $limit ? 'complete' : 'pending',
                    'updated_at' => now(),
                ]);

            return $winners->count();
        }, 3);
    }

    private function snapshot(object $reward): array
    {
        $values = $reward instanceof Reward ? $reward->getAttributes() : (array) $reward;
        $values['titles'] = is_string($reward->titles)
            ? json_decode($reward->titles, true, 16, JSON_THROW_ON_ERROR)
            : $reward->titles;
        $values['commands'] = is_string($reward->commands)
            ? json_decode($reward->commands ?: '[]', true, 16, JSON_THROW_ON_ERROR)
            : $reward->commands ?? [];
        $permissions =
            $reward->role_permissions ??
            ($reward->role_id ? Role::query()->find($reward->role_id)?->effectivePermissions() ?? [] : []);
        $values['role_permissions'] = is_string($permissions)
            ? json_decode($permissions, true, 16, JSON_THROW_ON_ERROR)
            : $permissions;
        $values['server_id'] = $reward->server_id ? (int) $reward->server_id : null;
        $values['requires_online'] = (bool) $reward->requires_online;

        return $values;
    }

    private function periodBounds(string $period, string|DateTimeInterface|null $at = null): array
    {
        $date = $at ? CarbonImmutable::parse($at, 'UTC') : CarbonImmutable::now('UTC');

        return match ($period) {
            'day' => [$date->startOfDay(), $date->startOfDay()->addDay(), $date->format('Y-m-d')],
            'month' => [$date->startOfMonth(), $date->startOfMonth()->addMonth(), $date->format('Y-m')],
            'all' => [CarbonImmutable::parse('1970-01-01', 'UTC'), null, 'all'],
            default => throw new InvalidArgumentException('Invalid reward period.'),
        };
    }

    private function active(): Builder
    {
        return Reward::query()->active();
    }

    private function eligible(object $reward, User $user): bool
    {
        return !$user->isBanned() &&
            !$user->anonymized_at &&
            !$user->import_locked &&
            (!$reward->permission || $user->hasPermission($reward->permission));
    }

    private function metric(object $reward, $start, $end, ?int $user = null): int
    {
        $query = $this->votes($reward, $start, $end)->when($user, fn ($query) => $query->where('user_id', $user));

        return (int) ($reward->metric === 'points' ? $query->sum('points') : $query->count());
    }

    private function votes(object $reward, $start, $end): Builder
    {
        return Vote::query()->rewardEligible($reward, $start, $end);
    }
}
