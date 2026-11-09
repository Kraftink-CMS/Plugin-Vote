<?php

namespace KraftInk\Plugins\Vote\Http\Controllers\Admin;

use App\Kraft\System\TableQuery;
use App\Kraft\System\SensitiveAction;
use App\Kraft\Games\GameRegistry;
use App\Kraft\Secrets;
use App\Models\Servers\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator as V;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use KraftInk\Plugins\Vote\Models\Rewards\Reward;
use KraftInk\Plugins\Vote\Models\Voting\Challenge;
use KraftInk\Plugins\Vote\Models\Voting\Site;
use KraftInk\Plugins\Vote\Models\Voting\SiteServer;
use KraftInk\Plugins\Vote\Models\Voting\Vote;
use KraftInk\Plugins\Vote\Services\Providers\ProviderRegistry;
use KraftInk\Plugins\Vote\Services\Rewards\RewardPolicy;
use KraftInk\Plugins\Vote\Services\Votifier\VotifierDeliveries;
use App\Kraft\Site\SiteIdentity;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SiteController
{
    public function index(): View
    {
        return view('vote::admin.sites.index', [
            'sites' => TableQuery::paginate(
                Site::query()->activeGame()->orderBy('name')->orderBy('id'),
                [
                    'name' => 'name',
                    'provider' => 'provider',
                    'reward_points' => 'reward_points',
                    'enabled' => 'enabled',
                ],
                pageName: 'page',
                search: ['name'],
            )->withQueryString(),
        ]);
    }

    public function edit(?int $id = null): View
    {
        /** @var Site|null $site */
        $site = $id ? Site::query()->activeGame()->find($id) : null;
        if ($id && !$site) {
            abort(404);
        }
        $providers = array_filter(
            app(ProviderRegistry::class)->all(),
            fn ($provider) => !$provider->games() ||
                in_array(app(GameRegistry::class)->activeId(), $provider->games(), true),
        );
        $configuration = $site?->provider_config ? json_decode(Crypt::decryptString($site->provider_config), true) : [];
        $votifier = app(VotifierDeliveries::class)->capabilities();
        $configured = [];
        foreach ($providers as $provider) {
            foreach ($provider->fields() as $key => $definition) {
                if ($definition['type'] === 'secret') {
                    $configured[$provider->id()][$key] =
                        $site &&
                        app(Secrets::class)->get('vote.providers.'.$site->id.'.'.$provider->id().'.'.$key) !==
                            '';
                }
            }
        }

        return view('vote::admin.sites.edit', [
            'site' => $site,
            'providers' => $providers,
            'configuration' => $configuration,
            'configured' => $configured,
            'confirmationState' => [
                'new' => !$site,
                'provider' => $site?->provider ?? 'custom',
                'url' => $site?->url ?? '',
                'server_id' => $configuration['server_id'] ?? '',
            ] +
                ($votifier
                    ? [
                        'delivery_mode' => $site?->delivery_mode ?? 'command',
                        'target_server' => (string) ($site?->server_id ?? ''),
                        'votifier_port' => (string) ($site?->votifier_port ?? 8192),
                        'votifier_website_name' => $site?->votifier_website_name ?? $this->websiteName(),
                    ]
                    : []),
            'serverChoices' => $site ? SiteServer::query()->where('site_id', $site->id)->pluck('server_id')->all() : [],
            'servers' => Server::query()->where('game', app(GameRegistry::class)->activeId())->orderBy('name')->get(),
            'votifierName' => $this->websiteName(),
            'votifier' => $votifier,
        ]);
    }

    public function save(Request $request, ?int $id = null): RedirectResponse
    {
        /** @var Site|null $site */
        $site = $id ? Site::query()->activeGame()->find($id) : null;
        if ($id && !$site) {
            abort(404);
        }
        app(RewardPolicy::class)->assertSite($request->user(), $id);
        $ids = array_map(fn ($provider) => $provider->id(), app(ProviderRegistry::class)->all());
        $votifier = app(VotifierDeliveries::class)->capabilities();
        $votifierFields = [
            'votifier_port' => ['sometimes', 'integer', 'between:1,65535'],
            'votifier_website_name' => ['sometimes', 'string', 'max:64', 'regex:/^[A-Za-z0-9_. -]{1,64}$/D'],
            'credentials.votifier.token' => ['nullable', 'string', 'min:16', 'max:200', 'not_regex:/[\x00-\x20\x7F]/'],
        ];
        if (!$votifier) {
            $votifierFields = array_fill_keys(array_keys($votifierFields), ['prohibited']);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url:https', 'max:2000'],
            'provider' => ['required', Rule::in(['custom', ...$ids])],
            'cooldown_minutes' => ['required', 'integer', 'min:1', 'max:525600'],
            'reward_points' => ['required', 'integer', 'min:1', 'max:100000'],
            'callback_secret' => ['nullable', 'string', 'min:32', 'max:200'],
            'commands' => ['nullable', 'string', 'max:10000'],
            'server_id' => ['nullable', 'integer', 'exists:servers,id'],
            'reset_time' => ['nullable', 'date_format:H:i'],
            'reset_timezone' => ['required', 'timezone'],
            'allow_guests' => ['sometimes', 'boolean'],
            'ip_mode' => ['sometimes', Rule::in(['any', 'ipv4', 'ipv6'])],
            'delivery_mode' => ['sometimes', Rule::in($votifier ? ['command', 'votifier'] : ['command'])],
            ...$votifierFields,
        ]);
        $data['delivery_mode'] ??= $site?->delivery_mode ?? 'command';
        $data['votifier_port'] ??= $site?->votifier_port ?? 8192;
        $data['votifier_website_name'] ??= $site?->votifier_website_name ?? $this->websiteName();
        $currentToken = $site?->votifier_token ? Crypt::decryptString($site->votifier_token) : '';
        $token = $request->input('credentials.votifier.token') ?: $currentToken;
        $data['votifier_token'] = $token !== '' ? Crypt::encryptString($token) : null;
        unset($data['credentials']);
        $requiresConfirmation = !$site || $site->provider !== $data['provider'] || $site->url !== $data['url'];
        foreach (['delivery_mode', 'votifier_port', 'votifier_website_name'] as $field) {
            $requiresConfirmation =
                $requiresConfirmation || ($site && (string) $site->$field !== (string) $data[$field]);
        }
        $requiresConfirmation =
            $requiresConfirmation ||
            $token !== $currentToken ||
            ($site &&
                $data['delivery_mode'] === 'votifier' &&
                (int) $site->server_id !== (int) ($data['server_id'] ?? 0));
        if ($data['delivery_mode'] === 'votifier') {
            if (!$votifier || empty($data['server_id']) || $request->input('server_choices')) {
                throw ValidationException::withMessages(['server_id' => __('vote::votifier.server_required')]);
            }
            if ($token === '') {
                throw ValidationException::withMessages([
                    'credentials.votifier.token' => __('vote::votifier.token_required'),
                ]);
            }
        }
        if (
            !empty($data['callback_secret']) &&
            (!$site || !hash_equals(Crypt::decryptString($site->callback_secret), $data['callback_secret']))
        ) {
            $requiresConfirmation = true;
        }
        $provider = $data['provider'] !== 'custom' ? app(ProviderRegistry::class)->get($data['provider']) : null;
        if (
            $site &&
            $site->provider !== $data['provider'] &&
            Challenge::query()
                ->where('site_id', $site->id)
                ->whereNotNull('provider_claimed_at')
                ->whereNull('used_at')
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'provider' => __('vote::messages.verification.provider_change_pending'),
            ]);
        }
        if (!$provider && !$site && empty($data['callback_secret'])) {
            throw ValidationException::withMessages([
                'callback_secret' => __('vote::messages.verification.callback_secret_required'),
            ]);
        }
        if (
            $provider &&
            (!$provider->validUrl($data['url']) ||
                ($provider->games() && !in_array(app(GameRegistry::class)->activeId(), $provider->games(), true)))
        ) {
            throw ValidationException::withMessages(['url' => __('vote::messages.verification.url_invalid')]);
        }
        if ($provider && ($data['cooldown_minutes'] < $provider->minimumCooldown() || !empty($data['reset_time']))) {
            throw ValidationException::withMessages([
                'cooldown_minutes' => __('vote::messages.verification.cooldown_minimum', [
                    'minutes' => $provider->minimumCooldown(),
                ]),
            ]);
        }
        if (!empty($data['server_id'])) {
            if (Server::query()->findOrFail($data['server_id'])->game !== app(GameRegistry::class)->activeId()) {
                throw new HttpException(422, __('vote::rewards.servers.invalid_server'));
            }
        }
        $commands = array_values(array_filter(array_map('trim', preg_split('/\R/', $data['commands'] ?? ''))));
        if ($data['delivery_mode'] === 'votifier') {
            $commands = json_decode($site?->commands ?: '[]', true, 32, JSON_THROW_ON_ERROR);
        }
        foreach ($commands as $command) {
            if (strlen($command) > 2048 || preg_match('/[\x00-\x1F\x7F]/', $command)) {
                throw new HttpException(422, __('vote::rewards.servers.invalid_command'));
            }
        }
        $configuration =
            $site && $site->provider === $data['provider'] && $site->provider_config
                ? json_decode(Crypt::decryptString($site->provider_config), true)
                : [];
        $secrets = [];
        foreach ($provider?->fields() ?? [] as $key => $definition) {
            $value = $request->input('credentials.'.$provider->id().'.'.$key);
            if ($definition['type'] === 'secret') {
                $current = $site
                    ? app(Secrets::class)->get('vote.providers.'.$site->id.'.'.$provider->id().'.'.$key)
                    : '';
                $value = $request->boolean('clear_credentials.'.$provider->id().'.'.$key)
                    ? ''
                    : ($value ?:
                    $current);
                if ($value !== '' || !$request->boolean('clear_credentials.'.$provider->id().'.'.$key)) {
                    $validator = V::make(
                        ['value' => $value],
                        ['value' => $definition['rules']],
                        [],
                        ['value' => __($definition['label'])],
                    );
                    $validator->validate();
                }
                $secrets[$key] = $value;
                $requiresConfirmation = $requiresConfirmation || $value !== $current;
            } else {
                $validator = V::make(
                    ['value' => $value],
                    ['value' => $definition['rules']],
                    [],
                    ['value' => __($definition['label'])],
                );
                $validator->validate();
                if ($key === 'server_id' && (string) ($configuration[$key] ?? '') !== (string) $value) {
                    $requiresConfirmation = true;
                }
                $configuration[$key] = $value;
            }
        }
        $data['commands'] = json_encode($commands, JSON_THROW_ON_ERROR);
        $validator = V::make($request->all(), [
            'server_choices' => ['nullable', 'array', 'max:100'],
            'server_choices.*' => [
                'integer',
                'distinct',
                Rule::exists('servers', 'id')->where('game', app(GameRegistry::class)->activeId()),
            ],
        ]);
        $choices = $validator->validate()['server_choices'] ?? [];
        if ($requiresConfirmation) {
            app(SensitiveAction::class)->confirm($request);
        }
        $data['enabled'] = $request->boolean('enabled') && !in_array('', $secrets, true);
        $data['requires_online'] = $data['delivery_mode'] === 'command' && $request->boolean('requires_online');
        $data['allow_guests'] = $request->boolean('allow_guests');
        $data['ip_mode'] ??= $site?->ip_mode ?? 'any';
        $data['game'] = app(GameRegistry::class)->activeId();
        $data['provider_config'] = Crypt::encryptString(json_encode($configuration, JSON_THROW_ON_ERROR));
        $data['updated_at'] = now();
        $data['callback_secret'] = !empty($data['callback_secret'])
            ? Crypt::encryptString($data['callback_secret'])
            : $site?->callback_secret ?? Crypt::encryptString(Str::random(64));
        DB::transaction(function () use ($id, $data, $secrets, $provider, $choices) {
            if ($id) {
                Site::query()->where('id', $id)->update($data);
                Challenge::query()
                    ->where('site_id', $id)
                    ->whereNull('used_at')
                    ->whereNull('provider_claimed_at')
                    ->update(['expires_at' => now()]);
            } else {
                $id = Site::query()->insertGetId($data + ['created_at' => now()]);
            }
            foreach ($secrets as $key => $value) {
                app(Secrets::class)->set('vote.providers.'.$id.'.'.$provider->id().'.'.$key, $value);
            }
            SiteServer::query()->where('site_id', $id)->delete();
            foreach ($choices as $server) {
                SiteServer::query()->insert(['site_id' => $id, 'server_id' => $server]);
            }
        }, 3);

        return redirect()->route('plugins.vote.admin.sites.index')->with('status', __('vote::admin.sites.saved'));
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        app(RewardPolicy::class)->assertSite($request->user(), $id);
        if (Vote::query()->where('site_id', $id)->exists()) {
            throw new HttpException(422, __('vote::admin.sites.used'));
        }
        if (Reward::query()->where('site_id', $id)->exists()) {
            throw new HttpException(422, __('vote::rewards.site_used'));
        }
        if (
            Challenge::query()
                ->where('site_id', $id)
                ->whereNotNull('provider_claimed_at')
                ->whereNull('used_at')
                ->exists()
        ) {
            throw new HttpException(422, __('vote::messages.verification.provider_change_pending'));
        }
        foreach (app(ProviderRegistry::class)->all() as $provider) {
            foreach ($provider->fields() as $key => $definition) {
                if ($definition['type'] === 'secret') {
                    app(Secrets::class)->set('vote.providers.'.$id.'.'.$provider->id().'.'.$key, '');
                }
            }
        }
        Site::query()->where('id', $id)->delete();

        return redirect()->route('plugins.vote.admin.sites.index');
    }

    private function websiteName(): string
    {
        return substr(Str::slug(app(SiteIdentity::class)->text('name')), 0, 64) ?: 'KraftInk';
    }
}
