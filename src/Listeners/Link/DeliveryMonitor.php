<?php

namespace KraftInk\Plugins\Vote\Listeners\Link;

use App\Events\CollectingLinkDeliverySources;
use App\Kraft\Games\GameRegistry;
use App\Kraft\SiteDates;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use KraftInk\Plugins\Vote\Models\Delivery\Delivery;
use KraftInk\Plugins\Vote\Services\Votifier\VotifierDeliveries;

class DeliveryMonitor
{
    public function collect(CollectingLinkDeliverySources $event): void
    {
        $cast = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true) ? 'CHAR' : 'VARCHAR(255)';
        $event->add(
            'vote',
            __('vote::messages.site.title'),
            'vote.manage',
            fn (): Builder => Delivery::query()
                ->toBase()
                ->where('game', app(GameRegistry::class)->activeId())
                ->select([
                    'id',
                    'game',
                    'server_id',
                    'player_name',
                    'player_uuid',
                    'command',
                    'status',
                    'attempts',
                    'requires_online',
                    'lease_expires_at',
                    'last_error',
                    'created_at',
                    'delivered_at',
                ])
                ->selectRaw("COALESCE(CAST(vote_id AS {$cast}), '') AS reference"),
            fn (object $row): array => [
                'reference' => $row->reference !== ''
                        ? __('vote::rewards.delivery.vote_reference', ['id' => $row->reference])
                        : __('vote::rewards.delivery.reward_reference'),
                'url' => null,
                'status_label' => $row->status === 'sent' ? __('vote::votifier.sent') : null,
                'delivered_label' => $row->status === 'sent' && $row->delivered_at
                        ? __('vote::votifier.sent_at', ['date' => app(SiteDates::class)->format($row->delivered_at)])
                        : null,
                'retry_url' => route('plugins.vote.admin.deliveries.retry', $row->id),
                'cancel_url' => $row->status === 'pending' && !$row->delivered_at
                        ? route('plugins.vote.admin.deliveries.cancel', $row->id)
                        : null,
            ],
            statuses: app(VotifierDeliveries::class)->capabilities() ? ['sent' => __('vote::votifier.sent')] : [],
        );
    }
}
