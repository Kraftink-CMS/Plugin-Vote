@use('KraftInk\Plugins\Vote\Models\Rewards\Reward')
@extends('admin.layouts.layout')
@section('title', __('vote::rewards.title'))
@section('description', __('vote::rewards.help'))
@section('actions')
    <a class="btn btn-outline-secondary" href="{{ route('admin.servers.deliveries.index', ['source' => 'vote']) }}">
        {{ __('vote::rewards.delivery.view') }}</a>
    <a class="btn btn-primary" href="{{ route('plugins.vote.admin.rewards.create') }}">
        {{ __('vote::rewards.new') }}</a>
@endsection
@section('content')
    <div id="vote-admin-rewards" data-ajax-pagination data-table tabindex="-1" class="vstack gap-3">
        <div class="card">
            <x-tables.controls :paginator="$rewards" container-class="card-body" />
            <div class="card-body p-0 table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="text-nowrap">
                        <tr>
                            <th>{{ __('vote::rewards.name') }}</th>
                            <th scope="col" data-table-sort="type">{{ __('vote::rewards.type') }}</th>
                            <th scope="col" data-table-sort="enabled">{{ __('vote::admin.sites.status') }}</th>
                            <th class="text-end">{{ __('vote::rewards.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rewards as $reward)
                            <tr>
                                <td>{{ Reward::localizedTitle($reward->titles ?? []) }}</td>
                                <td>{{ __('vote::rewards.types.' . $reward->type) }}</td>
                                <td>{{ $reward->enabled ? __('vote::rewards.enabled') : __('vote::rewards.disabled') }}</td>
                                <td>
                                    <div class="d-flex gap-2 justify-content-end">
                                        <a class="btn btn-sm btn-outline-primary"
                                            href="{{ route('plugins.vote.admin.rewards.edit', $reward->id) }}">{{ __('vote::rewards.edit') }}</a>
                                        <form data-ajax="true" data-redirect-url="response" method="post" action="{{ route('plugins.vote.admin.rewards.destroy', $reward->id) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                class="btn btn-sm btn-outline-danger">{{ __('vote::admin.sites.delete') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <x-tables.empty :colspan="4" :message="__('vote::rewards.empty')" :action="__('vote::rewards.new')" :href="route('plugins.vote.admin.rewards.create')" />
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                {{ $rewards->links() }}
            </div>
        </div>
    </div>
    <h2 class="h4">{{ __('vote::rewards.history') }}</h2>
    <p class="text-body-secondary">{{ __('vote::rewards.scheduler_help') }}</p>
    <div id="vote-admin-claims" data-ajax-pagination data-table tabindex="-1" class="vstack gap-3">
        <div class="card">
            <x-tables.controls :paginator="$claims" container-class="card-body" />
            <div class="card-body p-0 table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="text-nowrap">
                        <tr>
                            <th scope="col" data-table-sort="username">{{ __('vote::rewards.member') }}</th>
                            <th>{{ __('vote::rewards.name') }}</th>
                            <th scope="col" data-table-sort="period_key">{{ __('vote::rewards.period') }}</th>
                            <th scope="col" data-table-sort="status">{{ __('vote::admin.sites.status') }}</th>
                            <th>{{ __('vote::rewards.delivery.queue_title') }}</th>
                            <th>{{ __('vote::rewards.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($claims as $claim)
                            @php
                                $snapshot = ($claim->snapshot ?? []);
                            @endphp
                            <tr>
                                <td>{{ $claim->getAttribute('username') }}</td>
                                <td>{{ Reward::localizedTitle($snapshot['titles'] ?? []) }}</td>
                                <td>{{ $claim->period_key }}</td>
                                <td>{{ __('vote::rewards.states.' . $claim->status) }}
                                    @if ($claim->error)
                                        <small
                                            class="d-block text-danger">{{ __('vote::rewards.errors.' . $claim->error) }}</small>
                                    @endif
                                </td>
                                <td>
                                    <ul class="list-unstyled mb-0 vstack gap-2">
                                        @forelse ($deliveries->get($claim->id, collect()) as $delivery)
                                            <li>
                                                <code class="d-block text-break">{{ $delivery->command }}</code>
                                                <a href="{{ route('admin.servers.deliveries.index', ['source' => 'vote', 'search' => $delivery->id]) }}">
                                                    {{ __('vote::rewards.delivery.states.' . $delivery->status) }}</a>
                                                @if ($delivery->last_error)
                                                    <small class="d-block text-danger text-break">{{ $delivery->last_error }}</small>
                                                @endif
                                            </li>
                                        @empty
                                            <li class="text-body-secondary">{{ __('vote::rewards.delivery.no_commands') }}</li>
                                        @endforelse
                                    </ul>
                                </td>
                                <td>
                                    @if ($claim->status === 'error')
                                        <form data-ajax="true" data-redirect-url="response" method="post" action="{{ route('plugins.vote.admin.claims.retry', $claim->id) }}">
                                            @csrf
                                            <button
                                                class="btn btn-sm btn-outline-primary">{{ __('vote::rewards.retry') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-tables.empty :colspan="6" :message="__('vote::rewards.history_empty')" />
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                {{ $claims->links() }}
            </div>
        </div>
    </div>
@endsection
