<div class="mb-3">
    <label class="form-label d-block mb-2">
        @if (!empty($votifier))
            <span data-vote-delivery-fields="command">{{ __('vote::rewards.servers.target_server') }}</span>
            <span data-vote-delivery-fields="votifier">{{ __('vote::votifier.server') }}</span>
        @else
            {{ __('vote::rewards.servers.target_server') }}
        @endif
        <select class="form-select" name="server_id">
            <option value="">{{ __('vote::rewards.servers.default_server') }}</option>
            @foreach ($servers as $deliveryServer)
                <option value="{{ $deliveryServer->id }}" @selected((int) old('server_id', $site?->server_id) === $deliveryServer->id)>{{ $deliveryServer->name }} ·
                    {{ $deliveryServer->host }} ·
                    {{ config('kraft.games.' . $deliveryServer->game . '.name', $deliveryServer->game) }}</option>
            @endforeach
        </select>
    </label>
    @if (!empty($votifier))
        <small data-vote-delivery-fields="command">{{ __('vote::rewards.servers.target_help') }}</small>
        <small data-vote-delivery-fields="votifier">{{ __('vote::votifier.server_help') }}</small>
    @else
        <small>{{ __('vote::rewards.servers.target_help') }}</small>
    @endif
</div>
<div data-vote-delivery-fields="command">
    <input type="hidden" name="requires_online" value="0">
    <label class="form-check d-block mb-2">
        <input class="form-check-input" type="checkbox" name="requires_online" value="1"
            @checked(old('requires_online', $site?->requires_online ?? false))>
        {{ __('vote::rewards.servers.requires_online') }}</label>
</div>
