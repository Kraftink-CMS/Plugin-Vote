@extends('admin.layouts.layout')
@section('title', __('vote::admin.callbacks.title'))
@section('content')
    <section class="card">
        <div class="card-body">
            <h2 class="h5">{{ $site->name }}</h2>
            <p>{{ __('vote::admin.callbacks.url_help') }}</p>
            <label class="form-label" for="vote-callback-url">{{ __('vote::admin.callbacks.url') }}</label>
            <input class="form-control font-monospace" id="vote-callback-url" readonly value="{{ $callbackUrl }}"
                autocomplete="off">
            <p class="form-text">{{ __('vote::admin.callbacks.private') }}</p>
        </div>
        <div class="card-footer">
            <a class="btn btn-outline-secondary"
                href="{{ route('plugins.vote.admin.sites.edit', $site->id) }}">{{ __('vote::messages.verification.back') }}</a>
        </div>
    </section>
    <form class="card mt-4" method="post" action="{{ route('plugins.vote.admin.sites.callback-url', $site->id) }}"
        data-ajax="true" data-redirect-url="response">
        @csrf
        <input type="hidden" name="rotate" value="1">
        <div class="card-body">
            <h2 class="h5">{{ __('vote::admin.callbacks.rotate') }}</h2>
            <p>{{ __('vote::admin.callbacks.rotate_help') }}</p>
            <label class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="confirm_rotation" value="1" required>
                {{ __('vote::admin.callbacks.rotate_confirm') }}
            </label>
            @include('partials.forms.sensitive-fields', [
                'user' => auth()->user(),
                'formKey' => 'vote_callback_rotate',
            ])
        </div>
        <div class="card-footer">
            <button class="btn btn-outline-danger" type="submit">{{ __('vote::admin.callbacks.rotate') }}</button>
        </div>
    </form>
@endsection
