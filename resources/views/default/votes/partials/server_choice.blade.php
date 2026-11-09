@if (($serverChoices[$site->id] ?? collect())->isNotEmpty())
    <label class="form-label d-block" for="vote-server-{{ $site->id }}">
        {{ __('vote::messages.verification.reward_server') }}</label>
    <select class="form-select mb-3" id="vote-server-{{ $site->id }}" name="server_id" required>
        <option value="">{{ __('vote::messages.verification.select_server') }}</option>
        @foreach ($serverChoices[$site->id] as $server)
            <option value="{{ $server->id }}">{{ $server->name }}</option>
        @endforeach
    </select>
@endif
