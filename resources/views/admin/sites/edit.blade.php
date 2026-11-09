@use('KraftInk\Plugins\Vote\Services\Providers\CallbackProvider')
@use('KraftInk\Plugins\Vote\Services\Providers\ProviderRegistry')
@extends('admin.layouts.layout')
@section('title', $site?->name ?? __('vote::admin.sites.new'))
@section('content')
    <form data-ajax="true" data-redirect-url="response" method="post"
        action="{{ $site ? route('plugins.vote.admin.sites.update', $site->id) : route('plugins.vote.admin.sites.store') }}"
        class="vstack gap-4" data-vote-site-form data-vote-site-original="{{ json_encode($confirmationState) }}">
        @csrf
        @if ($site)
            @method('PUT')
        @endif
        <section class="card">
            <div class="card-header">
                <h2 class="card-title">{{ __('vote::admin.sites.sections.site') }}</h2>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="vote-name">{{ __('vote::admin.sites.name') }}</label>
                        <input class="form-control" id="vote-name" name="name" value="{{ old('name', $site?->name) }}"
                            required maxlength="120">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="vote-url">{{ __('vote::messages.verification.vote_url') }}</label>
                        <input class="form-control" type="url" id="vote-url" name="url"
                            value="{{ old('url', $site?->url) }}" required maxlength="2000" placeholder="https://">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"
                            for="vote-provider">{{ __('vote::messages.verification.provider') }}</label>
                        <select class="form-select" id="vote-provider" name="provider" data-vote-provider>
                            <option value="custom" @selected(old('provider', $site?->provider ?? 'custom') === 'custom')>
                                {{ __('vote::messages.verification.custom') }}
                            </option>
                            @foreach ($providers as $provider)
                                <option value="{{ $provider->id() }}" data-minimum="{{ $provider->minimumCooldown() }}"
                                    @selected(old('provider', $site?->provider) === $provider->id())>{{ $provider->name() }}</option>
                            @endforeach
                        </select>
                    </div>
                    @foreach ($providers as $provider)
                        <div class="col-md-6" data-vote-provider-fields="{{ $provider->id() }}">
                            <div class="vstack gap-3">
                                @foreach ($provider->fields() as $key => $definition)
                                    <div>
                                        <label class="form-label"
                                            for="credential-{{ $provider->id() }}-{{ $key }}">{{ __($definition['label']) }}</label>
                                        <input class="form-control"
                                            id="credential-{{ $provider->id() }}-{{ $key }}"
                                            type="{{ $definition['type'] === 'secret' ? 'password' : 'text' }}"
                                            name="credentials[{{ $provider->id() }}][{{ $key }}]"
                                            autocomplete="{{ $definition['type'] === 'secret' ? 'new-password' : 'off' }}"
                                            value="{{ $definition['type'] === 'secret' ? '' : $configuration[$key] ?? ($definition['default'] ?? '') }}">
                                        @if ($definition['type'] === 'secret')
                                            <p class="form-text mb-0">
                                                {{ !empty($configured[$provider->id()][$key]) ? __('vote::messages.verification.secret_kept') : __('vote::messages.verification.secret_help') }}
                                            </p>
                                            @if (!empty($configured[$provider->id()][$key]))
                                                <label class="form-check mt-2 mb-0">
                                                    <input class="form-check-input" type="checkbox"
                                                        name="clear_credentials[{{ $provider->id() }}][{{ $key }}]"
                                                        value="1">
                                                    {{ __('vote::messages.verification.clear_secret') }}</label>
                                            @endif
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    <div class="col-md-6" data-vote-provider-fields="custom">
                        <label class="form-label" for="vote-secret">{{ __('vote::admin.sites.secret') }}</label>
                        <input class="form-control" type="password" id="vote-secret" name="callback_secret" minlength="32"
                            autocomplete="new-password">
                        <p class="form-text">{{ __('vote::admin.sites.callback_help') }}</p>
                        @if ($site)
                            <p class="mb-0">{{ __('vote::admin.sites.callback') }}
                                <code
                                    class="text-break">{{ route('plugins.vote.webhooks.sites.handle', $site->id) }}</code>
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </section>
        <section class="card">
            <div class="card-header">
                <h2 class="card-title">{{ __('vote::admin.sites.sections.rules') }}</h2>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="vote-delay">{{ __('vote::admin.sites.cooldown_minutes') }}</label>
                        <input class="form-control" type="number" id="vote-delay" name="cooldown_minutes"
                            value="{{ old('cooldown_minutes', $site?->cooldown_minutes ?? 90) }}" min="1"
                            max="525600" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"
                            for="vote-timezone">{{ __('vote::messages.verification.timezone') }}</label>
                        <select class="form-select" id="vote-timezone" name="reset_timezone">
                            @foreach (timezone_identifiers_list() as $timezone)
                                <option @selected(old('reset_timezone', $site?->reset_timezone ?? 'UTC') === $timezone)>{{ $timezone }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6" data-vote-provider-fields="custom">
                        <label class="form-label"
                            for="vote-reset">{{ __('vote::messages.verification.reset_time') }}</label>
                        <input class="form-control" type="time" id="vote-reset" name="reset_time"
                            value="{{ old('reset_time', $site?->reset_time) }}">
                        <p class="form-text">{{ __('vote::messages.verification.reset_help') }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="vote-ip-mode">{{ __('vote::messages.guests.ip_mode') }}</label>
                        <select class="form-select" name="ip_mode" id="vote-ip-mode">
                            @foreach (['any', 'ipv4', 'ipv6'] as $mode)
                                <option value="{{ $mode }}" @selected(old('ip_mode', $site?->ip_mode ?? 'any') === $mode)>
                                    {{ __('vote::messages.guests.ip_' . $mode) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="allow_guests" value="1"
                                @checked(old('allow_guests', $site?->allow_guests ?? false))>
                            {{ __('vote::messages.guests.allow') }}
                        </label>
                        <p class="form-text">{{ __('vote::messages.guests.admin_help') }}</p>
                    </div>
                </div>
            </div>
        </section>
        <section class="card">
            <div class="card-header">
                <h2 class="card-title">{{ __('vote::admin.sites.sections.rewards') }}</h2>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="vote-points">{{ __('vote::admin.sites.reward') }}</label>
                        <input class="form-control" type="number" id="vote-points" name="reward_points"
                            value="{{ old('reward_points', $site?->reward_points ?? 1) }}" min="1" max="100000"
                            required>
                    </div>
                    @if ($votifier)
                        <div class="col-md-6">
                            <label class="form-label"
                                for="vote-delivery-mode">{{ __('vote::votifier.delivery_mode') }}</label>
                            <select class="form-select" id="vote-delivery-mode" name="delivery_mode"
                                data-vote-delivery-mode>
                                <option value="command" @selected(old('delivery_mode', $site?->delivery_mode ?? 'command') === 'command')>{{ __('vote::votifier.commands') }}
                                </option>
                                <option value="votifier" @selected(old('delivery_mode', $site?->delivery_mode) === 'votifier')>{{ __('vote::votifier.title') }}
                                </option>
                            </select>
                            <p class="form-text">{{ __('vote::votifier.mode_help') }}</p>
                        </div>
                    @endif
                    <div class="col-12">
                        @include('vote::admin.deliveries.partials.fields')
                    </div>
                    @if ($votifier)
                        <div class="col-12" data-vote-delivery-fields="votifier"
                            @if (old('delivery_mode', $site?->delivery_mode ?? 'command') !== 'votifier') hidden @endif>
                            <h3 class="h6 mb-3">{{ __('vote::votifier.connection') }}</h3>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label"
                                        for="vote-votifier-port">{{ __('vote::votifier.port') }}</label>
                                    <input class="form-control" id="vote-votifier-port" name="votifier_port"
                                        type="number" min="1" max="65535"
                                        value="{{ old('votifier_port', $site?->votifier_port ?? 8192) }}"
                                        data-votifier-port>
                                    <p class="form-text">{{ __('vote::votifier.port_help') }}</p>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label"
                                        for="vote-votifier-website-name">{{ __('vote::votifier.website_name') }}</label>
                                    <input class="form-control" id="vote-votifier-website-name"
                                        name="votifier_website_name" maxlength="64"
                                        value="{{ old('votifier_website_name', $site?->votifier_website_name ?? $votifierName) }}"
                                        data-votifier-website-name>
                                    <p class="form-text">{{ __('vote::votifier.website_name_help') }}</p>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label"
                                        for="vote-votifier-token">{{ __('vote::votifier.token') }}</label>
                                    <input class="form-control" id="vote-votifier-token"
                                        name="credentials[votifier][token]" type="password" autocomplete="new-password"
                                        minlength="16" maxlength="200">
                                    <p class="form-text">
                                        {{ $site?->votifier_token ? __('vote::votifier.token_kept') : __('vote::votifier.token_help') }}
                                    </p>
                                </div>
                            </div>
                            <p class="form-text">{{ __($votifier['connection_help']) }}</p>
                            <p>{{ __($votifier['setup_help']) }}</p>
                            <pre class="bg-body-tertiary border rounded p-3 mb-0"><code data-votifier-config data-votifier-template="{{ $votifier['template'] }}" data-token-placeholder="{{ __('vote::votifier_games.token_placeholder') }}"></code></pre>
                            <button class="btn btn-outline-secondary mt-2" type="button" data-copy="" data-votifier-copy
                                data-copied="{{ __('site.home.copied') }}">{{ __('common.actions.copy') }}</button>
                        </div>
                    @endif
                    <div class="col-md-6" data-vote-delivery-fields="command">
                        <label class="form-label" for="vote-commands">{{ __('vote::admin.sites.commands') }}</label>
                        <textarea class="form-control font-monospace" id="vote-commands" name="commands" rows="5">{{ old('commands', implode("\n", json_decode($site?->commands ?: '[]', true))) }}</textarea>
                    </div>
                    <div class="col-md-6" data-vote-delivery-fields="command">
                        <label class="form-label"
                            for="vote-server-choices">{{ __('vote::messages.verification.server_choices') }}</label>
                        <select class="form-select" id="vote-server-choices" name="server_choices[]" multiple
                            size="4">
                            @foreach ($servers as $server)
                                <option value="{{ $server->id }}" @selected(in_array($server->id, old('server_choices', $serverChoices)))>{{ $server->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="form-text">{{ __('vote::messages.verification.server_choices_help') }}</p>
                    </div>
                    <div class="col-12">
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="enabled" value="1"
                                @checked(old('enabled', $site?->enabled ?? true))>
                            {{ __('vote::admin.sites.enabled') }}</label>
                    </div>
                    <div hidden data-vote-confirmation>
                        @include('partials.forms.sensitive-fields', [
                            'user' => auth()->user(),
                            'formKey' => 'vote_site',
                            'confirmationRequired' => false,
                        ])
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit">{{ __('vote::admin.sites.save') }}</button>
                <a class="btn btn-outline-secondary"
                    href="{{ route('plugins.vote.admin.sites.index') }}">{{ __('vote::messages.verification.back') }}</a>
            </div>
        </section>

    </form>
    @if (
        $site &&
            app(ProviderRegistry::class)->has($site->provider) &&
            app(ProviderRegistry::class)->get($site->provider) instanceof CallbackProvider)
        <form class="card mt-4" method="post" action="{{ route('plugins.vote.admin.sites.callback-url', $site->id) }}"
            data-ajax="true" data-redirect-url="response">
            @csrf
            <div class="card-header">
                <h2 class="card-title">{{ __('vote::admin.callbacks.title') }}</h2>
            </div>
            <div class="card-body">
                <p>{{ __('vote::admin.callbacks.url_help') }}</p>
                @include('partials.forms.sensitive-fields', [
                    'user' => auth()->user(),
                    'formKey' => 'vote_callback_url',
                ])
            </div>
            <div class="card-footer">
                <button class="btn btn-outline-primary" type="submit">{{ __('vote::admin.callbacks.reveal') }}</button>
            </div>
        </form>
    @endif
@endsection
@push('scripts')
    <script src="{{ route('site.extensions.assets.show', ['id' => 'vote', 'asset' => 'admin.js']) }}"
        defer></script>
@endpush
