<?php

namespace KraftInk\Plugins\Vote\Http\Controllers\Admin;

use App\Kraft\System\TableQuery;
use App\Kraft\System\SensitiveAction;
use App\Kraft\LocalePolicy;
use App\Kraft\TextTranslations;
use App\Kraft\Auth\RoleHierarchy;
use App\Kraft\Games\GameRegistry;
use App\Kraft\Wallet\Wallet;
use App\Models\Accounts\Role;
use App\Models\Servers\Server;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use KraftInk\Plugins\Vote\Models\Delivery\Delivery;
use KraftInk\Plugins\Vote\Models\Rewards\Reward;
use KraftInk\Plugins\Vote\Models\Rewards\RewardClaim;
use KraftInk\Plugins\Vote\Models\Rewards\RewardRun;
use KraftInk\Plugins\Vote\Services\Rewards\RewardEngine;
use KraftInk\Plugins\Vote\Services\Rewards\RewardPolicy;
use KraftInk\Plugins\Vote\Models\Voting\Site;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use App\Kraft\Wallet\Money;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RewardController
{
    public function index(): View
    {
        $claims = TableQuery::paginate(
            RewardClaim::query()
                ->join('users', 'users.id', '=', 'vote__reward_claims.user_id')
                ->whereIn('reward_id', $this->query()->select('id'))
                ->select('vote__reward_claims.*', 'users.username')
                ->latest('vote__reward_claims.id'),
            [
                'username' => 'users.username',
                'reward_id' => 'vote__reward_claims.reward_id',
                'period_key' => 'vote__reward_claims.period_key',
                'status' => 'vote__reward_claims.status',
            ],
            pageName: 'page',
            search: ['users.username'],
        )->withQueryString();

        return view('vote::admin.rewards.index', [
            'rewards' => TableQuery::paginate(
                $this->query()->orderBy('id'),
                ['type' => 'type', 'enabled' => 'enabled'],
                pageName: 'rewards_page',
                search: [],
            )->withQueryString(),
            'claims' => $claims,
            'deliveries' => Delivery::query()
                ->where('game', app(GameRegistry::class)->activeId())
                ->whereIn('reward_claim_id', $claims->pluck('id'))
                ->orderBy('created_at')
                ->select('id', 'reward_claim_id', 'command', 'status', 'last_error')
                ->get()
                ->groupBy('reward_claim_id'),
        ]);
    }

    public function edit(?int $id = null): View
    {
        $reward = $id ? $this->query()->find($id) : null;
        if ($id && !$reward) {
            abort(404);
        }

        return view('vote::admin.rewards.edit', [
            'reward' => $reward,
            'roles' => Role::query()
                ->orderByDesc('position')
                ->get()
                ->filter(
                    fn ($role) => !in_array('*', $role->effectivePermissions(), true) &&
                        app(RoleHierarchy::class)->canManageRole(auth()->user(), $role),
                ),
            'sites' => Site::query()->activeGame()->get(),
            'servers' => Server::query()->where('game', app(GameRegistry::class)->activeId())->get(),
            'currency' => app(Wallet::class)->currency(),
            'confirmationState' => $reward?->only([
                'type',
                'site_id',
                'period',
                'metric',
                'threshold',
                'rank_to',
                'pool',
                'weight',
                'chance_bps',
                'permission',
                'role_id',
                'role_days',
                'wallet_minor',
                'wallet_currency',
                'commands',
                'server_id',
                'game',
                'enabled',
                'requires_online',
            ]),
        ]);
    }

    public function save(Request $request, ?int $id = null): RedirectResponse
    {
        if ($request->has('wallet_amount')) {
            $request->validate(['wallet_amount' => 'required|string|max:30']);
            try {
                $request->merge(['wallet_minor' => Money::parse($request->input('wallet_amount'))]);
            } catch (ValidationException) {
                throw ValidationException::withMessages([
                    'wallet_amount' => __('vote::rewards.wallet_amount_invalid'),
                ]);
            }
        }
        $reward = $id ? $this->query()->find($id) : null;
        if ($id && !$reward) {
            abort(404);
        }
        app(RewardPolicy::class)->assert($request->user(), $reward, false);
        $type = $request->input('type');
        if ($type !== 'random') {
            $request->merge(['pool' => null, 'weight' => 1]);
        }
        if (!in_array($type, ['vote', 'random'], true)) {
            $request->merge(['chance_bps' => 10000]);
        }
        if (!in_array($type, ['milestone', 'goal'], true)) {
            $request->merge(['period' => 'month']);
        }
        if (!in_array($type, ['milestone', 'goal', 'monthly'], true)) {
            $request->merge(['metric' => 'votes', 'threshold' => 1]);
        }
        if ($type !== 'monthly') {
            $request->merge(['rank_to' => null]);
        }
        $data = $request->validate(
            [
                'title' => ['required', 'string', 'max:160'],
                'type' => ['required', Rule::in(['vote', 'random', 'milestone', 'goal', 'monthly'])],
                'site_id' => ['nullable', 'integer', 'exists:vote__sites,id'],
                'period' => ['required', Rule::in(['day', 'month', 'all'])],
                'metric' => ['required', Rule::in(['votes', 'points'])],
                'threshold' => ['required', 'integer', 'min:1', 'max:1000000000'],
                'rank_to' => ['nullable', 'required_if:type,monthly', 'integer', 'min:1', 'max:1000', 'gte:threshold'],
                'pool' => ['nullable', 'required_if:type,random', 'string', 'max:80'],
                'weight' => ['required', 'integer', 'min:1', 'max:1000000'],
                'chance_bps' => ['required', 'integer', 'min:0', 'max:10000'],
                'permission' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z][a-zA-Z0-9_.:-]*$/D'],
                'role_id' => ['nullable', 'integer', 'exists:roles,id'],
                'role_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
                'wallet_minor' => ['required', 'integer', 'min:0', 'max:1000000000'],
                'commands' => ['nullable', 'string', 'max:10000'],
                'server_id' => ['nullable', 'integer', 'exists:servers,id'],
            ] + app(TextTranslations::class)->rules(['title' => ['max' => 160]]),
        );
        $data += [
            'role_id' => null,
            'site_id' => null,
            'server_id' => null,
            'role_days' => null,
            'permission' => null,
            'rank_to' => null,
            'pool' => null,
        ];
        if ($data['type'] === 'monthly') {
            $data['period'] = 'month';
        }
        if (
            $reward &&
            (RewardClaim::query()->where('reward_id', $id)->exists() ||
                RewardRun::query()->where('reward_id', $id)->exists())
        ) {
            foreach (['type', 'period', 'metric', 'threshold', 'rank_to', 'site_id', 'pool'] as $field) {
                if ((string) ($reward->$field ?? '') !== (string) ($data[$field] ?? '')) {
                    throw ValidationException::withMessages([
                        $field => __('vote::rewards.trigger_locked'),
                    ]);
                }
            }
        }
        if (
            !(
                empty($data['server_id']) ||
                Server::query()->findOrFail($data['server_id'])->game === app(GameRegistry::class)->activeId()
            )
        ) {
            throw new HttpException(422, __('vote::rewards.servers.invalid_server'));
        }
        if (!(empty($data['site_id']) || Site::query()->activeGame()->where('id', $data['site_id'])->exists())) {
            throw new HttpException(422);
        }
        $commands = array_values(array_filter(array_map('trim', preg_split('/\R/', $data['commands'] ?? ''))));
        foreach ($commands as $command) {
            if (strlen($command) > 2048 || preg_match('/[\x00-\x1F\x7F]/', $command)) {
                throw new HttpException(422, __('vote::rewards.servers.invalid_command'));
            }
        }
        if (!($commands || $data['role_id'] || $data['wallet_minor'] > 0)) {
            throw new HttpException(422, __('vote::rewards.empty_effect'));
        }
        $locale = app(LocalePolicy::class)->defaultLocale();
        $existingTitles = $reward?->titles ?? [];
        $translations = app(TextTranslations::class)->normalize(
            $data['translations'] ?? [],
            array_map(fn ($title) => ['title' => $title], $existingTitles),
            ['title' => ['max' => 160]],
        );
        $titles = array_map(fn ($translation) => $translation['title'] ?? '', $translations);
        $titles[$locale] = trim($data['title']);
        unset($data['title'], $data['translations']);
        $data['titles'] = json_encode($titles, JSON_THROW_ON_ERROR);
        $data['commands'] = json_encode($commands, JSON_THROW_ON_ERROR);
        $data['wallet_currency'] = app(Wallet::class)->currency();
        $data['game'] = app(GameRegistry::class)->activeId();
        $data['enabled'] = $request->boolean('enabled');
        $data['requires_online'] = $request->boolean('requires_online');
        $data['updated_at'] = now();
        $data['role_permissions'] = $data['role_id']
            ? json_encode(Role::query()->findOrFail($data['role_id'])->effectivePermissions(), JSON_THROW_ON_ERROR)
            : '[]';
        $presentationOnly = app(RewardPolicy::class)->presentationOnly($reward, $data);
        if (!$presentationOnly) {
            app(RewardPolicy::class)->assert($request->user(), $reward);
        }
        app(RewardPolicy::class)->assert($request->user(), (object) $data, !$presentationOnly);
        if (!$presentationOnly && ($data['wallet_minor'] > 0 || ($reward?->wallet_minor ?? 0) > 0)) {
            app(SensitiveAction::class)->confirm($request);
        }
        if ($id) {
            Reward::query()->where('id', $id)->update($data);
        } else {
            Reward::query()->insert($data + ['created_at' => now()]);
        }

        return redirect()->route('plugins.vote.admin.rewards.index')->with('status', __('vote::rewards.saved'));
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $reward = $this->query()->find($id);
        if ($reward === null) {
            abort(404);
        }
        app(RewardPolicy::class)->assert($request->user(), $reward);
        if (
            RewardClaim::query()->where('reward_id', $id)->exists() ||
            RewardRun::query()->where('reward_id', $id)->exists()
        ) {
            throw new HttpException(422, __('vote::rewards.used'));
        }
        Reward::query()->where('id', $id)->delete();

        return back();
    }

    public function retry(Request $request, int $id): RedirectResponse
    {
        $claim = RewardClaim::query()->find($id);
        if ($claim === null) {
            abort(404);
        }
        app(RewardPolicy::class)->assert($request->user(), (object) ($claim->snapshot ?? []));
        if ($claim->status !== 'error') {
            throw new HttpException(422);
        }
        RewardClaim::query()
            ->where('id', $id)
            ->where('status', 'error')
            ->update(['status' => 'pending', 'error' => null]);
        app(RewardEngine::class)->process($id);

        return back();
    }

    private function query(): Builder
    {
        return Reward::query()->where('game', app(GameRegistry::class)->activeId());
    }
}
