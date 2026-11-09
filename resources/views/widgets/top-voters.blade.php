<section class="card h-100">
    <div class="card-body">
        <h2 class="h5 card-title mb-3">{{ __('vote::widgets.top_voters') }}</h2>
        <ol class="mb-0 ps-3">
            @forelse ($scores as $score)
                <li class="mb-2">
                    <span>{{ $score->getAttribute('username') }}</span>
                    <span class="text-body-secondary">{{ trans_choice('vote::widgets.points', (int) $score->points, ['count' => (int) $score->points]) }}</span>
                </li>
            @empty
                <li class="text-body-secondary">{{ __('vote::widgets.empty') }}</li>
            @endforelse
        </ol>
    </div>
</section>
