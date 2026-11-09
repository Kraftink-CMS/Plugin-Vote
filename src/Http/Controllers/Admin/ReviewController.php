<?php

namespace KraftInk\Plugins\Vote\Http\Controllers\Admin;

use App\Kraft\System\TableQuery;
use App\Kraft\Audit;
use App\Kraft\System\SensitiveAction;
use App\Models\Accounts\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use KraftInk\Plugins\Vote\Models\Voting\Challenge;
use KraftInk\Plugins\Vote\Models\Voting\Guest;
use KraftInk\Plugins\Vote\Models\Voting\Review;
use KraftInk\Plugins\Vote\Models\Voting\Site;
use KraftInk\Plugins\Vote\Services\VoteService;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class ReviewController
{
    public function index(): View
    {
        return view('vote::admin.reviews.index', [
            'challenges' => TableQuery::paginate(
                Challenge::query()
                    ->join('vote__sites', 'vote__sites.id', '=', 'vote__challenges.site_id')
                    ->leftJoin('users', 'users.id', '=', 'vote__challenges.user_id')
                    ->leftJoin('vote__guests', 'vote__guests.id', '=', 'vote__challenges.guest_id')
                    ->whereNotNull('provider_claimed_at')
                    ->whereNull('used_at')
                    ->select('vote__challenges.id', 'vote__challenges.created_at', 'vote__sites.name as site_name')
                    ->selectRaw('COALESCE(users.username, vote__guests.player_name) AS username')
                    ->orderBy('vote__challenges.id'),
                ['site_name' => 'vote__sites.name', 'created_at' => 'vote__challenges.created_at'],
                pageName: 'page',
                search: ['vote__sites.name', 'users.username', 'vote__guests.player_name'],
            ),
        ]);
    }

    public function confirm(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'min:20', 'max:2000'],
            'decision' => ['required', 'in:approved,rejected'],
            'confirm_evidence' => ['accepted'],
        ]);
        app(SensitiveAction::class)->confirm($request);
        $original = Challenge::query()->find($id);
        if ($original === null) {
            abort(404);
        }
        DB::transaction(function () use ($request, $data, $id, $original) {
            $site = Site::query()->activeGame()->where('id', $original->site_id)->lockForUpdate()->first();
            if (!$site) {
                abort(404);
            }
            if ($original->guest_id) {
                if (Guest::query()->where('id', $original->guest_id)->lockForUpdate()->first() === null) {
                    abort(404);
                }
            } else {
                User::query()->whereKey($original->user_id)->lockForUpdate()->firstOrFail();
            }
            $challenge = Challenge::query()->where('id', $id)->lockForUpdate()->first();
            if (!($challenge && $challenge->provider_claimed_at)) {
                abort(404);
            }
            if ($challenge->used_at) {
                return;
            }
            $vote = null;
            if ($data['decision'] === 'approved') {
                $expires = now()->addMinutes(5);
                Challenge::query()
                    ->where('id', $id)
                    ->update(['expires_at' => $expires]);
                $challenge->expires_at = $expires;
                $vote = app(VoteService::class)->complete($site, $challenge, 'manual:challenge:'.$id);
            } else {
                Challenge::query()
                    ->where('id', $id)
                    ->update(['used_at' => now(), 'verification_state' => 'rejected']);
            }
            Review::query()->insert([
                'challenge_id' => $id,
                'user_id' => $challenge->user_id,
                'guest_id' => $challenge->guest_id,
                'actor_id' => $request->user()->id,
                'vote_id' => $vote,
                'note' => $data['note'],
                'decision' => $data['decision'],
                'created_at' => now(),
            ]);
            Audit::record(
                'vote.reviewed',
                ['challenge_id' => $id, 'vote_id' => $vote, 'decision' => $data['decision']],
                $request->user(),
            );
        }, 3);

        return back()->with('status', __('vote::messages.verification.reviewed'));
    }
}
