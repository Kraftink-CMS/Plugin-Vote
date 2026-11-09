@use('App\Kraft\SiteDates')
@extends('admin.layouts.layout')
@section('title', __('vote::messages.verification.reviews'))
@section('description', __('vote::messages.verification.review_help'))
@section('content')
    <div id="vote-admin-reviews" data-ajax-pagination data-table tabindex="-1" class="vstack gap-3">
        <div class="card">
            <x-tables.controls :paginator="$challenges" container-class="card-body" />
            <div class="card-body p-0 table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="text-nowrap">
                        <tr>
                            <th>{{ __('vote::rewards.member') }}</th>
                            <th scope="col" data-table-sort="site_name">{{ __('vote::rewards.site') }}</th>
                            <th>{{ __('vote::rewards.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($challenges as $challenge)
                            <tr>
                                <td>{{ $challenge->getAttribute('username') }}</td>
                                <td>{{ $challenge->getAttribute('site_name') }}<small
                                        class="d-block text-body-secondary">{{ app(SiteDates::class)->format($challenge->created_at) }}</small>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                        data-bs-target="#review-{{ $challenge->id }}">{{ __('vote::messages.verification.review') }}</button>
                                </td>
                            </tr>
                        @empty
                            <x-tables.empty :colspan="3" :message="__('vote::messages.verification.reviews_empty')" />
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                {{ $challenges->links() }}
            </div>
        </div>
    </div>
    @foreach ($challenges as $challenge)
        <x-forms.modal :id="'review-' . $challenge->id" :title="__('vote::messages.verification.review')" :action="route('plugins.vote.admin.reviews.confirm', $challenge->id)" :submit="__('vote::messages.verification.review_confirm')">
            <p>{{ __('vote::messages.verification.review_help') }}</p>
            <label class="form-label"
                for="review-decision-{{ $challenge->id }}">{{ __('vote::messages.verification.decision') }}</label>
            <select class="form-select mb-3" id="review-decision-{{ $challenge->id }}" name="decision" required>
                <option value="approved">{{ __('vote::messages.verification.approve') }}</option>
                <option value="rejected">{{ __('vote::messages.verification.reject') }}</option>
            </select>
            <label class="form-label" for="review-note-{{ $challenge->id }}">{{ __('vote::messages.verification.evidence') }}</label>
            <textarea class="form-control mb-3" id="review-note-{{ $challenge->id }}" name="note" required minlength="20"
                maxlength="2000" rows="3"></textarea>
            <label class="form-check mb-3">
                <input class="form-check-input" name="confirm_evidence" type="checkbox" value="1" required>
                {{ __('vote::messages.verification.evidence_confirm') }}</label>
            @include('partials.forms.sensitive-fields', [
                'user' => auth()->user(),
                'formKey' => 'vote-review-' . $challenge->id,
            ])
        </x-forms.modal>
    @endforeach
@endsection
