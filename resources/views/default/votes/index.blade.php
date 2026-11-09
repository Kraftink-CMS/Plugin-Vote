@use('KraftInk\Plugins\Vote\Models\Rewards\Reward')
@extends('default.layouts.layout')
@section('content')
    <section>
        <header class="page-heading">
            <h1 class="h2">{{ __('vote::messages.site.title') }}</h1>
            <p class="text-body-secondary mb-0">{{ __('vote::messages.site.intro') }}</p>
        </header>
        <div id="vote-sites" data-ajax-pagination tabindex="-1" class="vstack gap-3">
            @if ($sites->isNotEmpty())
                <div class="row g-4">
                    @foreach ($sites as $site)
                        <div class="col-md-6 col-xl-4">
                            <article class="card content-card h-100">
                                <div class="card-body p-4 d-flex flex-column align-items-start">
                                    <i class="bi bi-hand-thumbs-up text-primary fs-3 mb-3" aria-hidden="true"></i>
                                    <h2 class="h5">{{ $site->name }}</h2>
                                    <p class="text-body-secondary mb-4">
                                        {{ trans_choice('vote::messages.site.points', $site->reward_points, ['count' => $site->reward_points]) }}
                                    </p>
                                    @auth
                                        <form class="w-100 mt-auto"
                                            data-ajax="{{ $site->provider !== 'custom' ? 'true' : 'false' }}"
                                            data-redirect-url="response" method="post"
                                            action="{{ route('plugins.vote.public.votes.start', $site->id) }}">
                                            @csrf
                                            @include('vote::default.votes.partials.server_choice')
                                            <button class="btn btn-primary w-100"
                                                @disabled(!$voteControls[$site->id]['canVote'])>{{ $voteControls[$site->id]['canVote'] ? __('vote::messages.site.vote') : __('vote::messages.site.cooldown') }}</button>
                                        </form>
                                        @if (!$voteControls[$site->id]['canVote'])
                                            <small
                                                class="text-body-secondary mt-2">{{ __('vote::messages.verification.available_at', ['date' => $voteControls[$site->id]['availableAt']]) }}</small>
                                        @endif
                                    @else
                                        @if ($site->allow_guests)
                                            <form class="w-100 mt-auto"
                                                data-ajax="{{ $site->provider !== 'custom' ? 'true' : 'false' }}"
                                                data-redirect-url="response" method="post"
                                                action="{{ route('plugins.vote.public.guests.start', $site->id) }}">
                                                @csrf
                                                <label class="form-label"
                                                    for="guest-player-{{ $site->id }}">{{ __($guestIdentityLabel) }}</label>
                                                <input class="form-control mb-3" id="guest-player-{{ $site->id }}"
                                                    name="player" value="{{ old('player') }}" required maxlength="60"
                                                    autocomplete="off">
                                                @include('vote::default.votes.partials.server_choice')
                                                <p class="form-text">{{ __('vote::messages.guests.help') }}</p>
                                                <button class="btn btn-primary w-100"
                                                    type="submit">{{ __('vote::messages.guests.vote') }}</button>
                                            </form>
                                        @else
                                            <a class="btn btn-outline-primary w-100 mt-auto"
                                                href="{{ route('login') }}">{{ __('vote::messages.site.login') }}</a>
                                        @endif
                                    @endauth
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>
            @endif
            {{ $sites->links() }}
        </div>
        @if ($hasRanking)
            <section class="card mt-5">
                <header class="card-header p-3 p-lg-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <h2 class="h5 mb-0"><i class="bi bi-trophy me-2 text-primary"
                            aria-hidden="true"></i>{{ __('vote::messages.site.ranking') }}</h2>
                    <nav class="nav nav-pills gap-1" aria-label="{{ __('vote::messages.site.ranking') }}">
                        <a @class(['nav-link py-2', 'active' => request('period') !== 'all'])
                            href="{{ route('plugins.vote.public.votes.index') }}">{{ __('vote::messages.site.month') }}</a>
                        <a @class(['nav-link py-2', 'active' => request('period') === 'all'])
                            href="{{ route('plugins.vote.public.votes.index', ['period' => 'all']) }}">{{ __('vote::messages.site.all') }}</a>
                    </nav>
                </header>
                <ol class="list-group list-group-flush list-group-numbered">
                    @foreach ($scores as $score)
                        <li class="list-group-item d-flex align-items-center gap-3 py-3 px-4">
                            <span class="fw-semibold me-auto">{{ $score->getAttribute('username') }}</span>
                            <span class="small text-body-secondary">{{ $score->points }}
                                {{ __('vote::messages.site.score') }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
        @if ($goals)
            <h2 class="h4 section-heading mt-5 mb-3">
                {{ __('vote::rewards.goals') }}</h2>
            <div class="row g-3">
                @foreach ($goals as $goal)
                    <div class="col-md-6">
                        <article class="card card-body h-100 p-4">
                            <h3 class="h5">{{ Reward::localizedTitle($goal['titles']) }}</h3>
                            <p class="text-body-secondary">{{ __('vote::rewards.types.' . $goal['type']) }} ·
                                {{ __('vote::rewards.metrics.' . $goal['metric']) }}</p>
                            <label class="small fw-semibold mb-2" for="vote-goal-{{ $goal['id'] }}">{{ $goal['current'] }} /
                                {{ $goal['target'] }}</label>
                            <progress id="vote-goal-{{ $goal['id'] }}" class="w-100"
                                value="{{ min($goal['current'], $goal['target']) }}"
                                max="{{ $goal['target'] }}">{{ $goal['current'] }} / {{ $goal['target'] }}</progress>
                        </article>
                    </div>
                @endforeach
            </div>
        @endif
        @if ($claims->isNotEmpty())
            <h2 class="h4 section-heading mt-5 mb-3">
                {{ __('vote::rewards.my_rewards') }}</h2>
            <ul class="list-group">
                @foreach ($claims as $claim)
                    @php
                        $snapshot = ($claim->snapshot ?? []);
                    @endphp
                    <li class="list-group-item d-flex flex-wrap justify-content-between gap-3 py-3 px-4">
                        <span class="fw-semibold">{{ Reward::localizedTitle($snapshot['titles'] ?? []) }}</span><span
                            class="small text-body-secondary">{{ __('vote::rewards.states.' . $claim->status) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
        @if (session('vote.guest_browser'))
            <form data-ajax="true" data-redirect-url="response" method="post"
                action="{{ route('plugins.vote.public.guests.forget') }}" class="mt-4">
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-secondary" type="submit">{{ __('vote::messages.guests.forget') }}</button>
                <p class="form-text">{{ __('vote::messages.guests.privacy') }}</p>
            </form>
        @endif
    </section>
@endsection
