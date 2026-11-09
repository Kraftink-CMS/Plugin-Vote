<?php

namespace KraftInk\Plugins\Vote\Http\Controllers;

use Illuminate\Http\Request;
use KraftInk\Plugins\Vote\Services\ChallengePayload;
use KraftInk\Plugins\Vote\Services\GuestVoting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use KraftInk\Plugins\Vote\Models\Voting\Site;

class GuestController
{
    public function start(Request $request, int $id): RedirectResponse
    {
        /** @var Site|null $site */
        $site = Site::query()->available()->where('allow_guests', true)->find($id);
        if ($site === null) {
            abort(404);
        }
        $data = $request->validate([
            'player' => ['required', 'string', 'max:60'],
            'server_id' => ['nullable', 'integer'],
        ]);
        $result = app(GuestVoting::class)->start(
            $site,
            $request,
            $data['player'],
            !empty($data['server_id']) ? (int) $data['server_id'] : null,
        );

        return $site->provider === 'custom'
            ? redirect()->away($result['url'])
            : redirect()->route('plugins.vote.public.guests.show', $result['challenge']->id);
    }

    public function show(Request $request, int $id): View
    {
        $challenge = app(GuestVoting::class)->owned($request, $id);
        /** @var Site|null $site */
        $site = Site::query()
            ->available()
            ->where('allow_guests', true)
            ->find($challenge->site_id);
        if (!($site && $site->provider !== 'custom')) {
            abort(404);
        }

        return view('vote::default.votes.challenge', [
            'site' => $site,
            'challenge' => $challenge,
            'url' => $challenge->provider_token !== null ? ChallengePayload::url($challenge) : $site->url,
            'verifyRoute' => 'plugins.vote.public.guests.verify',
            'publicCode' => $challenge->isOpen() ? ChallengePayload::publicCode($challenge) : null,
        ]);
    }

    public function verify(Request $request, int $id): RedirectResponse
    {
        $challenge = app(GuestVoting::class)->owned($request, $id);
        /** @var Site|null $site */
        $site = Site::query()
            ->available()
            ->where('allow_guests', true)
            ->find($challenge->site_id);
        if (!($site && $site->provider !== 'custom')) {
            abort(404);
        }
        app(GuestVoting::class)->verify($site, $challenge, $request);

        return redirect()
            ->route('plugins.vote.public.votes.index')
            ->with('status', __('vote::messages.verification.confirmed'));
    }

    public function forget(Request $request): RedirectResponse
    {
        app(GuestVoting::class)->forget($request);

        return back()->with('status', __('vote::messages.guests.forgotten'));
    }
}
