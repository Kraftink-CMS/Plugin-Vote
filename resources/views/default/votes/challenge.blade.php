@use('App\Kraft\SiteDates')
@extends('default.layouts.layout')
@section('title', $site->name)
@section('content')
    <section class="card card-body p-4 p-lg-5 my-4 mx-auto" style="max-width: 44rem">
        <h1 class="h2 mb-4">{{ $site->name }}</h1>
        @if ($challenge->verification_state === 'rejected')
            <p class="alert alert-warning">{{ __('vote::messages.verification.rejected') }}</p>
        @elseif($challenge->used_at)
            <p class="alert alert-success">{{ __('vote::messages.verification.confirmed') }}</p>
        @elseif(now()->gte($challenge->expires_at))
            <p class="alert alert-warning">{{ __('vote::messages.verification.expired') }}</p>
        @else
            <p>{{ __('vote::messages.verification.instructions') }}</p>
            @if ($publicCode)
                <div class="card card-body my-4">
                    <p class="mb-2">{{ __('vote::messages.verification.code_help') }}</p>
                    <code class="fs-4 user-select-all">{{ $publicCode }}</code>
                </div>
            @endif
            <p class="text-body-secondary">
                {{ __('vote::messages.verification.expires', ['date' => app(SiteDates::class)->format($challenge->expires_at)]) }}
            </p>
            <a class="btn btn-primary mb-3" href="{{ $url }}" target="_blank"
                rel="noopener noreferrer">{{ __('vote::messages.verification.open_site') }}</a>
            <form data-ajax="true" data-redirect-url="response" method="post"
                action="{{ route($verifyRoute ?? 'plugins.vote.public.challenges.verify', $challenge->id) }}">
                @csrf
                <button class="btn btn-outline-primary">{{ __('vote::messages.verification.verify') }}</button>
            </form>
            @if ($challenge->provider_claimed_at)
                <p class="alert alert-warning mt-3">{{ __('vote::messages.verification.claim_uncertain') }}</p>
            @endif
        @endif
        <a class="btn btn-outline-secondary mt-3"
            href="{{ route('plugins.vote.public.votes.index') }}">{{ __('vote::messages.verification.back') }}</a>
    </section>
@endsection
