<?php

namespace KraftInk\Plugins\Vote\Http\Controllers;

use App\Kraft\Games\GameRegistry;
use App\Models\Servers\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use KraftInk\Plugins\Vote\Models\Rewards\RewardClaim;
use KraftInk\Plugins\Vote\Models\Voting\Challenge;
use KraftInk\Plugins\Vote\Models\Voting\Vote;
use KraftInk\Plugins\Vote\Services\Cooldown;
use KraftInk\Plugins\Vote\Services\Rewards\RewardEngine;
use KraftInk\Plugins\Vote\Services\VoteService;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use App\Kraft\SiteDates;
use KraftInk\Plugins\Vote\Services\ChallengePayload;
use KraftInk\Plugins\Vote\Models\Voting\Site;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VoteController
{
    public function index(Request $request): View
    {
        $games = app(GameRegistry::class);
        $game = $games->activeId();
        $guestIdentityLabel = $games->find($game)?->metadata()['identity_label'] ?? 'vote::messages.guests.player';
        $scores = Vote::query()
            ->ranking($request->input('period') !== 'all')
            ->selectRaw('COUNT(*) AS total')
            ->limit(50)
            ->get();
        $sites = Site::query()
            ->available()
            ->orderBy('id')
            ->paginate(24, ['*'], 'sites_page')
            ->withQueryString();
        $hasRanking = $sites->total() > 0 || Vote::query()->scores()->exists();
        $lastVotes = $request->user()
            ? Vote::query()
                ->where('user_id', $request->user()->id)
                ->select('site_id')
                ->selectRaw('MAX(created_at) AS voted_at')
                ->groupBy('site_id')
                ->pluck('voted_at', 'site_id')
            : collect();
        $nextVotes = $sites->mapWithKeys(
            fn (Site $site) => [$site->id => app(Cooldown::class)->next($site, $lastVotes[$site->id] ?? null)],
        );
        $voteControls = $nextVotes->map(
            fn ($availableAt) => [
                'canVote' => !$availableAt?->isFuture(),
                'availableAt' => app(SiteDates::class)->format($availableAt),
            ],
        );
        $claims = $request->user()
            ? RewardClaim::query()->where('user_id', $request->user()->id)->latest('id')->limit(20)->get()
            : collect();
        $sites->getCollection()->load([
            'servers' => fn ($servers) => $servers
                ->where('servers.game', $game)
                ->select('servers.id', 'servers.name', 'servers.translations')
                ->orderBy('servers.name'),
        ]);
        $serverChoices = $sites->getCollection()->mapWithKeys(
            fn (Site $site) => [
                $site->id => $site->servers->map(
                    fn (Server $server) => (object) ['id' => $server->id, 'name' => $server->displayText('name')],
                ),
            ],
        );
        $goals = app(RewardEngine::class)->progress($request->user());

        return view(
            'vote::default.votes.index',
            compact(
                'sites',
                'scores',
                'lastVotes',
                'nextVotes',
                'voteControls',
                'claims',
                'serverChoices',
                'goals',
                'guestIdentityLabel',
                'hasRanking',
            ) + [
                'seo' => ['page_parameter' => $sites->getPageName()],
                'seoVariables' => ['voting_sites_count' => $sites->total()],
            ],
        );
    }

    public function start(Request $request, int $id): RedirectResponse
    {
        /** @var Site|null $site */
        $site = Site::query()->available()->find($id);
        if ($site === null) {
            abort(404);
        }
        $data = $request->validate(['server_id' => ['nullable', 'integer']]);
        $result = app(VoteService::class)->start(
            $site,
            $request->user(),
            $request->ip(),
            !empty($data['server_id']) ? (int) $data['server_id'] : null,
        );
        if ($site->provider === 'custom') {
            return redirect()->away($result['url']);
        }

        return redirect()->route('plugins.vote.public.challenges.show', $result['challenge']->id);
    }

    public function challenge(Request $request, int $id): View
    {
        $challenge = Challenge::query()->where('user_id', $request->user()->id)->find($id);
        if ($challenge === null) {
            abort(404);
        }
        /** @var Site|null $site */
        $site = Site::query()->available()->find($challenge->site_id);
        if (!($site && $site->provider !== 'custom')) {
            abort(404);
        }
        $result = app(VoteService::class)->resume($site, $challenge);

        return view('vote::default.votes.challenge', [
            'site' => $site,
            'challenge' => $challenge,
            'url' => $result['url'],
            'publicCode' => $challenge->isOpen() ? ChallengePayload::publicCode($challenge) : null,
        ]);
    }

    public function verify(Request $request, int $id): RedirectResponse
    {
        $challenge = Challenge::query()->where('user_id', $request->user()->id)->find($id);
        if ($challenge === null) {
            abort(404);
        }
        /** @var Site|null $site */
        $site = Site::query()->available()->find($challenge->site_id);
        if (!($site && $site->provider !== 'custom')) {
            abort(404);
        }
        app(VoteService::class)->verify($site, $challenge, $request->user());

        return redirect()
            ->route('plugins.vote.public.votes.index')
            ->with('status', __('vote::messages.verification.confirmed'));
    }

    public function callback(Request $request, int $id): JsonResponse
    {
        /** @var Site|null $site */
        $site = Site::query()->activeGame()->where('enabled', true)->where('provider', 'custom')->find($id);
        if ($site === null) {
            abort(404);
        }
        $timestamp = $request->header('X-KraftInk-Timestamp', '');
        if (!(ctype_digit($timestamp) && abs(time() - (int) $timestamp) <= 300)) {
            throw new HttpException(401);
        }
        $signature = hash_hmac(
            'sha256',
            $timestamp.'.'.$request->getContent(),
            Crypt::decryptString($site->callback_secret),
        );
        if (!hash_equals($signature, $request->header('X-KraftInk-Signature', ''))) {
            throw new HttpException(401);
        }
        $data = $request->validate([
            'token' => ['required', 'string', 'size:64'],
            'event_id' => ['required', 'string', 'max:180'],
        ]);
        $challenge = Challenge::query()->where('site_id', $id)->where('token', hash('sha256', $data['token']))->first();
        if ($challenge === null) {
            throw new HttpException(422);
        }
        app(VoteService::class)->complete($site, $challenge, $data['event_id']);

        return response()->json(['received' => true]);
    }
}
