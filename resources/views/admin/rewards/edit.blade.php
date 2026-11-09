@use('App\Kraft\Wallet\Money')
@use('KraftInk\Plugins\Vote\Models\Rewards\Reward')
@extends('admin.layouts.layout')
@section('title', $reward ? __('vote::rewards.edit') : __('vote::rewards.new'))
@section('content')
    <form data-ajax="true"
        data-redirect-url="{{ $reward ? route('plugins.vote.admin.rewards.edit', $reward->id) : 'response' }}" class="card"
        method="post" data-vote-reward-form data-vote-reward-original="{{ json_encode($confirmationState) }}"
        data-vote-wallet-currency="{{ $currency }}"
        action="{{ $reward ? route('plugins.vote.admin.rewards.update', $reward->id) : route('plugins.vote.admin.rewards.store') }}">
        @csrf
        @if ($reward)
            @method('PUT')
        @endif
        @php
            $titles = $reward ? $reward->titles ?? [] : [];
            $rewardTypes = ['vote', 'random', 'milestone', 'goal', 'monthly'];
            $rewardPeriods = ['day', 'month', 'all'];
            $selectedType = old('type', $reward?->type ?? 'vote');
            $selectedPeriod = old('period', $reward?->period ?? 'month');
            $selectedType = in_array($selectedType, $rewardTypes, true) ? $selectedType : 'vote';
            $selectedPeriod = in_array($selectedPeriod, $rewardPeriods, true) ? $selectedPeriod : 'month';
        @endphp
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="reward-title">{{ __('vote::rewards.name') }}</label>
                    <input class="form-control" id="reward-title" name="title"
                        value="{{ old('title', Reward::localizedTitle($titles, app(\App\Kraft\LocalePolicy::class)->defaultLocale())) }}"
                        required maxlength="160">
                    <x-admin::translation-fields id="reward-title-translations" :fields="['title' => ['label' => __('vote::rewards.name'), 'max' => 160]]" :values="array_map(fn($title) => ['title' => $title], $titles)" />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="reward-type">{{ __('vote::rewards.type') }}</label>
                    <select class="form-select" id="reward-type" name="type" data-select-help="reward-type-help"
                        aria-describedby="reward-type-help">
                        @foreach ($rewardTypes as $type)
                            <option value="{{ $type }}" data-help="{{ __('vote::rewards.type_help.' . $type) }}"
                                @selected($selectedType === $type)>
                                {{ __('vote::rewards.types.' . $type) }}</option>
                        @endforeach
                    </select>
                    <p class="form-text" id="reward-type-help" aria-live="polite">
                        {{ __('vote::rewards.type_help.' . $selectedType) }}</p>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="reward-site">{{ __('vote::rewards.site') }}</label>
                    <select class="form-select" id="reward-site" name="site_id">
                        <option value="">{{ __('vote::rewards.all_sites') }}</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" @selected(old('site_id', $reward?->site_id) == $site->id)>{{ $site->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6" data-vote-reward-types="milestone goal">
                    <label class="form-label" for="reward-period">{{ __('vote::rewards.period') }}</label>
                    <select class="form-select" id="reward-period" name="period" data-select-help="reward-period-help"
                        aria-describedby="reward-period-help">
                        @foreach ($rewardPeriods as $period)
                            <option value="{{ $period }}"
                                data-help="{{ __('vote::rewards.period_help.' . $period) }}" @selected($selectedPeriod === $period)>
                                {{ __('vote::rewards.periods.' . $period) }}</option>
                        @endforeach
                    </select>
                    <p class="form-text" id="reward-period-help" aria-live="polite">
                        {{ __('vote::rewards.period_help.' . $selectedPeriod) }}</p>
                </div>
                <div class="col-md-6" data-vote-reward-types="milestone goal monthly">
                    <label class="form-label" for="reward-metric">{{ __('vote::rewards.metric') }}</label>
                    <select class="form-select" id="reward-metric" name="metric">
                        @foreach (['votes', 'points'] as $metric)
                            <option value="{{ $metric }}" @selected(old('metric', $reward?->metric ?? 'votes') === $metric)>
                                {{ __('vote::rewards.metrics.' . $metric) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6" data-vote-reward-types="milestone goal monthly">
                    <label class="form-label" for="reward-threshold">{{ __('vote::rewards.threshold') }}</label>
                    <input class="form-control" id="reward-threshold" name="threshold" type="number" min="1"
                        max="1000000000" value="{{ old('threshold', $reward?->threshold ?? 1) }}" required>
                    <p class="form-text" data-vote-reward-types="milestone goal"
                        @if (!in_array($selectedType, ['milestone', 'goal'], true)) hidden @endif>{{ __('vote::rewards.threshold_help') }}</p>
                    <p class="form-text" data-vote-reward-types="monthly" @if ($selectedType !== 'monthly') hidden @endif>
                        {{ __('vote::rewards.rank_from_help') }}</p>
                </div>
                <div class="col-md-6" data-vote-reward-types="monthly">
                    <label class="form-label" for="reward-rank">{{ __('vote::rewards.rank_to') }}</label>
                    <input class="form-control" id="reward-rank" name="rank_to" type="number" min="1" max="1000"
                        value="{{ old('rank_to', $reward?->rank_to) }}">
                </div>
                <div class="col-md-4" data-vote-reward-types="random">
                    <label class="form-label" for="reward-pool">{{ __('vote::rewards.pool') }}</label>
                    <input class="form-control" id="reward-pool" name="pool" maxlength="80"
                        value="{{ old('pool', $reward?->pool) }}">
                </div>
                <div class="col-md-4" data-vote-reward-types="random">
                    <label class="form-label" for="reward-weight">{{ __('vote::rewards.weight') }}</label>
                    <input class="form-control" id="reward-weight" name="weight" type="number" min="1"
                        max="1000000" value="{{ old('weight', $reward?->weight ?? 1) }}" required>
                </div>
                <div class="col-md-4" data-vote-reward-types="vote random">
                    <label class="form-label" for="reward-chance">{{ __('vote::rewards.chance') }}</label>
                    <input class="form-control" id="reward-chance" name="chance_bps" type="number" min="0"
                        max="10000" value="{{ old('chance_bps', $reward?->chance_bps ?? 10000) }}" required>
                    <p class="form-text">{{ __('vote::rewards.chance_help') }}</p>
                </div>
                <div class="col-12">
                    <label class="form-label" for="reward-permission">{{ __('vote::rewards.permission') }}</label>
                    <input class="form-control" id="reward-permission" name="permission" maxlength="100"
                        value="{{ old('permission', $reward?->permission) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="reward-role">{{ __('vote::rewards.role') }}</label>
                    <select class="form-select" id="reward-role" name="role_id">
                        <option value="">{{ __('vote::rewards.no_role') }}</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected(old('role_id', $reward?->role_id) == $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="reward-days">{{ __('vote::rewards.role_days') }}</label>
                    <input class="form-control" id="reward-days" name="role_days" type="number" min="1"
                        max="3650" value="{{ old('role_days', $reward?->role_days) }}">
                    <p class="form-text">{{ __('vote::rewards.role_days_help') }}</p>
                </div>
                @if (auth()->user()->hasPermission('wallet.manage'))
                    <div class="col-12">
                        <label class="form-label"
                            for="reward-wallet">{{ __('vote::rewards.wallet', ['currency' => $currency]) }}</label>
                        <input class="form-control" id="reward-wallet" type="text" inputmode="decimal"
                            pattern="[0-9]+([.,][0-9]{1,2})?" maxlength="30" name="wallet_amount"
                            value="{{ old('wallet_amount', Money::decimal($reward?->wallet_minor ?? 0)) }}" required>
                    </div>
                @else
                    <input type="hidden" name="wallet_minor" value="{{ $reward?->wallet_minor ?? 0 }}">
                @endif
                <div class="col-12">
                    @include('vote::admin.deliveries.partials.fields', ['site' => $reward])
                </div>
                <div class="col-12">
                    <label class="form-label" for="reward-commands">{{ __('vote::admin.sites.commands') }}</label>
                    <textarea class="form-control font-monospace" id="reward-commands" name="commands" rows="5">{{ old('commands', implode("\n", json_decode($reward?->commands ?: '[]', true))) }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-check">
                        <input class="form-check-input" name="enabled" type="checkbox" value="1"
                            @checked(old('enabled', $reward?->enabled ?? true))>
                        {{ __('vote::rewards.enabled') }}</label>
                </div>
                @if (auth()->user()->hasPermission('wallet.manage'))
                    <div hidden data-vote-confirmation>
                        @include('partials.forms.sensitive-fields', [
                            'user' => auth()->user(),
                            'formKey' => 'vote_reward',
                            'confirmationRequired' => false,
                        ])
                    </div>
                @endif
            </div>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2">
            <button class="btn btn-primary" type="submit">{{ __('vote::admin.sites.save') }}</button>
            <a class="btn btn-outline-secondary"
                href="{{ route('plugins.vote.admin.rewards.index') }}">{{ __('vote::messages.verification.back') }}</a>
        </div>
    </form>
@endsection
@push('scripts')
    <script src="{{ route('site.extensions.assets.show', ['id' => 'vote', 'asset' => 'admin.js']) }}"
        defer></script>
@endpush
