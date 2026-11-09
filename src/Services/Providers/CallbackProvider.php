<?php

namespace KraftInk\Plugins\Vote\Services\Providers;

use Illuminate\Support\Facades\Crypt;
use KraftInk\Plugins\Vote\Contracts\VoteProvider;
use KraftInk\Plugins\Vote\Data\ProviderChallenge;
use KraftInk\Plugins\Vote\Data\VoteContext;
use KraftInk\Plugins\Vote\Data\VoteProof;
use KraftInk\Plugins\Vote\Models\Voting\CallbackReceipt;
use InvalidArgumentException;

class CallbackProvider extends HttpProvider implements VoteProvider
{
    public const array SERVICES = [
        'topg' => ['topg.org', 'TopG', 360, []],
        'arena-top100' => ['arena-top100.com', 'Arena Top100', 720, []],
        'gtop100' => ['gtop100.com', 'GTop100', 1440, []],
        'mineserv' => ['mineserv.top', 'MineServ', 1440, ['minecraft']],
        'topcraft' => ['topcraft.club', 'Topcraft', 1440, ['minecraft']],
        'hotmc' => ['hotmc.ru', 'HotMC', 1440, ['minecraft']],
        'minecraft-rating' => ['minecraftrating.ru', 'MinecraftRating', 1440, ['minecraft']],
        'xtremetop100' => ['xtremetop100.com', 'XtremeTop100', 720, []],
    ];

    private array $service;

    public function __construct(private readonly string $identifier)
    {
        $this->service =
            self::SERVICES[$identifier] ?? throw new InvalidArgumentException('Unknown callback vote service.');
    }

    public function id(): string
    {
        return $this->identifier;
    }

    public function name(): string
    {
        return $this->service[1];
    }

    public function games(): array
    {
        return $this->service[3];
    }

    public function mode(): string
    {
        return 'callback';
    }

    public function minimumCooldown(): int
    {
        return $this->service[2];
    }

    public function fields(): array
    {
        $fields = [];
        if (!in_array($this->id(), ['topg', 'xtremetop100'], true)) {
            $fields['callback_key'] = [
                'type' => 'secret',
                'label' => 'vote::admin.callbacks.key',
                'rules' => ['required', 'string', 'min:8', 'max:200', 'not_in:TEST'],
            ];
        }
        if (in_array($this->id(), ['mineserv', 'gtop100', 'xtremetop100'], true)) {
            $fields['server_id'] = [
                'type' => 'text',
                'label' => 'vote::admin.callbacks.server_id',
                'rules' => ['required', 'integer', 'min:1'],
            ];
        }

        return $fields;
    }

    public function validUrl(string $url): bool
    {
        if (!$this->httpsHost($url, [$this->service[0], 'www.'.$this->service[0]])) {
            return false;
        }
        $query = [];
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
        if ($this->id() === 'topg') {
            return preg_match('~^/[A-Za-z0-9-]+/(?:server|in)-[0-9]+/?$~D', parse_url($url, PHP_URL_PATH) ?? '') ===
                1 && !$query;
        }
        if ($this->id() === 'arena-top100') {
            return parse_url($url, PHP_URL_PATH) === '/index.php' &&
                ($query['a'] ?? null) === 'in' &&
                is_string($query['u'] ?? null) &&
                preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $query['u']) === 1 &&
                count($query) === 2;
        }
        if ($this->id() === 'xtremetop100') {
            return parse_url($url, PHP_URL_PATH) === '/in.php' &&
                is_string($query['site'] ?? null) &&
                ctype_digit($query['site']) &&
                count($query) === 1;
        }

        return !$query;
    }

    public function prepare(object $site, VoteContext $context): ProviderChallenge
    {
        $this->assertConfirmed($this->validUrl($site->url));
        $configuration = $this->configuration($site);
        if ($this->id() === 'xtremetop100') {
            parse_str(parse_url($site->url, PHP_URL_QUERY), $query);
            $this->assertConfirmed((int) $query['site'] === (int) $configuration['server_id']);
        }
        $url = match ($this->id()) {
            'topg' => rtrim($site->url, '/').'-'.$context->nonce,
            'arena-top100' => $site->url.'&'.http_build_query(['id' => $context->nonce]),
            'gtop100' => $site->url.'?'.http_build_query(['vote' => 1, 'pingUsername' => $context->nonce]),
            'xtremetop100' => $site->url.'&'.http_build_query(['postback' => $context->nonce]),
            default => $site->url,
        };

        return new ProviderChallenge($url, $context->nonce);
    }

    public function verify(object $site, object $challenge): VoteProof
    {
        $receipt = CallbackReceipt::query()
            ->where('site_id', $site->id)
            ->where('challenge_id', $challenge->id)
            ->first();
        $this->assertConfirmed($receipt !== null);

        return new VoteProof($receipt->provider_event);
    }

    public function settings(object $site): array
    {
        return $this->configuration($site);
    }

    public function endpointSecret(object $site): string
    {
        return hash_hmac(
            'sha256',
            'vote-callback:'.$site->id.':'.$this->id(),
            Crypt::decryptString($site->callback_secret),
        );
    }
}
