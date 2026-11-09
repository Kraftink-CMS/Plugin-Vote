@use('KraftInk\Plugins\Vote\Services\Providers\ProviderRegistry')
@extends('admin.layouts.layout')
@section('title', __('vote::messages.site.title'))
@section('description', __('vote::messages.verification.admin_help'))
@section('actions')
    <a class="btn btn-outline-secondary" href="{{ route('admin.servers.deliveries.index', ['source' => 'vote']) }}">
        {{ __('vote::rewards.delivery.view') }}</a>
    <a class="btn btn-primary" href="{{ route('plugins.vote.admin.sites.create') }}">
        {{ __('vote::admin.sites.new') }}</a>
@endsection
@section('content')
    <div id="vote-admin-sites" data-ajax-pagination data-table tabindex="-1" class="vstack gap-3">
        <div class="card">
            <x-tables.controls :paginator="$sites" container-class="card-body" />
            <div class="card-body p-0 table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="text-nowrap">
                        <tr>
                            <th scope="col" data-table-sort="name">{{ __('vote::admin.sites.name') }}</th>
                            <th scope="col" data-table-sort="provider">{{ __('vote::messages.verification.provider') }}</th>
                            <th scope="col" data-table-sort="reward_points">{{ __('vote::admin.sites.reward') }}</th>
                            <th scope="col" data-table-sort="enabled">{{ __('vote::admin.sites.status') }}</th>
                            <th class="text-end">{{ __('vote::rewards.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sites as $site)
                            <tr>
                                <td>{{ $site->name }}</td>
                                <td>{{ $site->provider === 'custom' ? __('vote::messages.verification.custom') : (app(ProviderRegistry::class)->has($site->provider) ? app(ProviderRegistry::class)->get($site->provider)->name() : __('vote::messages.verification.unavailable')) }}
                                </td>
                                <td>{{ $site->reward_points }}</td>
                                <td><span
                                        class="badge {{ $site->enabled ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $site->enabled ? __('vote::rewards.enabled') : __('vote::rewards.disabled') }}</span>
                                </td>
                                <td>
                                    <div class="d-flex justify-content-end gap-2">
                                        <a class="btn btn-sm btn-outline-primary"
                                            href="{{ route('plugins.vote.admin.sites.edit', $site->id) }}">{{ __('vote::rewards.edit') }}</a>
                                        <form data-ajax="true" data-redirect-url="response" method="post" action="{{ route('plugins.vote.admin.sites.destroy', $site->id) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                class="btn btn-sm btn-outline-danger">{{ __('vote::admin.sites.delete') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <x-tables.empty :colspan="5" :message="__('vote::messages.site.empty')" :description="__('vote::messages.verification.admin_help')" :action="__('vote::admin.sites.new')"
                                :href="route('plugins.vote.admin.sites.create')" />
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                {{ $sites->links() }}
            </div>
        </div>
    </div>
@endsection
