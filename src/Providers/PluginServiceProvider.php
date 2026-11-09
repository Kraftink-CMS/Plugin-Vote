<?php

namespace KraftInk\Plugins\Vote\Providers;

use App\Events\AcknowledgingLinkCommand;
use App\Events\CollectingLinkCommands;
use App\Events\CollectingLinkDeliverySources;
use App\Events\CollectingSeoPages;
use App\Events\ResolvingUserRoles;
use App\Kraft\Events\PluginEvents;
use App\Kraft\Features;
use App\Kraft\Migration\MappingRegistry;
use App\Kraft\Notifications\NotificationRegistry;
use App\Kraft\Widgets\WidgetRegistry;
use App\Models\Accounts\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use KraftInk\Plugins\Vote\Console\Commands\ProcessRewards;
use KraftInk\Plugins\Vote\Listeners\DataRetention;
use KraftInk\Plugins\Vote\Listeners\Link\DeliveryListener;
use KraftInk\Plugins\Vote\Listeners\Link\DeliveryMonitor;
use KraftInk\Plugins\Vote\Listeners\RoleAssignments;
use KraftInk\Plugins\Vote\Models\Rewards\RewardClaim;
use KraftInk\Plugins\Vote\Models\Voting\Vote;
use KraftInk\Plugins\Vote\Services\Imports\ImportMappings;
use KraftInk\Plugins\Vote\Services\Providers\CallbackProvider;
use KraftInk\Plugins\Vote\Services\Providers\ListForge;
use KraftInk\Plugins\Vote\Services\Providers\ListeServMinecraft;
use KraftInk\Plugins\Vote\Services\Providers\ListingApi;
use KraftInk\Plugins\Vote\Services\Providers\MinecraftMp;
use KraftInk\Plugins\Vote\Services\Providers\ProviderRegistry;
use KraftInk\Plugins\Vote\Services\Providers\ServerMinecraft;
use KraftInk\Plugins\Vote\Services\Providers\ServeurPrive;
use KraftInk\Plugins\Vote\Services\Providers\ServeursMinecraft;
use KraftInk\Plugins\Vote\Services\VotePage;
use App\Events\CollectingRoleAssignments;
use App\Events\PruningData;

class PluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/votifier.php', 'vote.votifier');
        $this->app->singleton(ProviderRegistry::class, function () {
            $registry = new ProviderRegistry();
            $registry->register('serveur-prive', ServeurPrive::class);
            $registry->register('minecraft-mp', MinecraftMp::class);
            $registry->register('serveurs-minecraft', ServeursMinecraft::class);
            $registry->register('serveur-minecraft-com', ServerMinecraft::class);
            $registry->register('liste-serv-minecraft', ListeServMinecraft::class);
            foreach (array_keys(ListForge::SERVICES) as $id) {
                $registry->registerProvider(new ListForge($id));
            }
            foreach (array_keys(ListingApi::SITES) as $id) {
                $registry->registerProvider(new ListingApi($id));
            }
            foreach (array_keys(CallbackProvider::SERVICES) as $id) {
                $registry->registerProvider(new CallbackProvider($id));
            }

            return $registry;
        });
    }

    public function boot(): void
    {
        $this->registerEvents(app(PluginEvents::class));
        $this->registerNotifications(app(NotificationRegistry::class));
        $this->registerWidgets(app(WidgetRegistry::class));
        app(Features::class)->register('vote', fn (?User $user) => app(VotePage::class)->available($user));
        Event::listen(CollectingRoleAssignments::class, [RoleAssignments::class, 'collect']);
        Event::listen(ResolvingUserRoles::class, [RoleAssignments::class, 'roles']);
        Event::listen(PruningData::class, [DataRetention::class, 'prune']);
        $this->commands([ProcessRewards::class]);
        $this->app->afterResolving(
            Schedule::class,
            fn ($schedule) => $schedule->command('vote:rewards')->everyMinute()->withoutOverlapping(10),
        );
        Event::listen(CollectingLinkCommands::class, [DeliveryListener::class, 'collect']);
        Event::listen(CollectingLinkDeliverySources::class, [DeliveryMonitor::class, 'collect']);
        Event::listen(AcknowledgingLinkCommand::class, [DeliveryListener::class, 'acknowledge']);
        app(ImportMappings::class)->register(app(MappingRegistry::class));
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'vote');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'vote');
        Event::listen(CollectingSeoPages::class, function (CollectingSeoPages $event): void {
            $event->add(
                'plugins.vote.public.votes.index',
                __('vote::seo.pages.index'),
                [
                    'voting_sites_count' => __('vote::seo.variables.voting_sites_count'),
                ],
                defaults: [
                    'title' => __('vote::messages.site.title'),
                    'description' => __('vote::messages.site.intro'),
                ],
            );
        });
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/admin.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
    }

    private function registerEvents(PluginEvents $events): void
    {
        $events->define('vote.confirmed');
    }

    private function registerNotifications(NotificationRegistry $registry): void
    {
        $registry->register(
            'vote',
            'vote.reward',
            'vote::rewards.notification_title',
            function (array $data): array {
                $claim = RewardClaim::query()->find($data['claim_id']);
                $reward = $claim->snapshot ?? [];
                $title = \KraftInk\Plugins\Vote\Models\Rewards\Reward::localizedTitle($reward['titles'] ?? []);

                return [
                    'title' => __('vote::rewards.notification_title'),
                    'body' => e(__('vote::rewards.notification_body', ['reward' => $title])),
                    'url' => route('plugins.vote.public.votes.index'),
                ];
            },
            fn (?User $user, array $data): bool => $user &&
                RewardClaim::query()
                    ->where('id', $data['claim_id'])
                    ->where('user_id', $user->id)
                    ->whereIn('status', ['pending', 'processed'])
                    ->exists(),
        );
    }

    private function registerWidgets(WidgetRegistry $widgets): void
    {
        $widgets->register(
            owner: 'vote',
            id: 'top-voters',
            label: 'vote::widgets.top_voters',
            render: fn (array $context, array $config) => view('vote::widgets.top-voters', [
                'scores' => Vote::query()
                    ->ranking($config['period'] === 'month')
                    ->limit($config['limit'])
                    ->get(),
            ]),
            fields: [
                'limit' => ['type' => 'integer', 'label' => 'vote::widgets.count', 'min' => 1, 'max' => 12],
                'period' => [
                    'type' => 'choice',
                    'label' => 'vote::widgets.period',
                    'options' => ['month' => 'vote::widgets.month', 'all' => 'vote::widgets.all'],
                ],
            ],
            permission: 'vote.view',
            zones: ['home'],
            defaults: ['limit' => 5, 'period' => 'month'],
        );
    }
}
