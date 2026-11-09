<?php

namespace KraftInk\Plugins\Vote\Services\Providers;

use App\Kraft\Secrets;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

abstract class HttpProvider
{
    abstract public function fields(): array;

    abstract public function id(): string;

    protected function configuration(object $site): array
    {
        try {
            $configuration = $site->provider_config
                ? json_decode(Crypt::decryptString($site->provider_config), true, 16, JSON_THROW_ON_ERROR)
                : [];
            foreach ($this->fields() as $field => $definition) {
                if ($definition['type'] === 'secret') {
                    $configuration[$field] = app(Secrets::class)->get(
                        'vote.providers.'.$site->id.'.'.$this->id().'.'.$field,
                    );
                }
                $configuration[$field] ??= $definition['default'] ?? null;
                if (!isset($configuration[$field]) || $configuration[$field] === '') {
                    throw new RuntimeException('Vote provider credential is missing.');
                }
            }

            return $configuration;
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'vote' => __('vote::messages.verification.configuration_invalid'),
            ]);
        }
    }

    protected function request(
        string $method,
        string $url,
        array $query = [],
        array $headers = [],
        ?array $body = null,
        bool $form = false,
    ): Response {
        if (!in_array($method, ['GET', 'POST'], true) || !$this->httpsHost($url, $this->apiHosts())) {
            throw ValidationException::withMessages(['vote' => __('vote::messages.verification.destination_invalid')]);
        }
        $lock = null;
        try {
            if (parse_url($url, PHP_URL_HOST) === 'www.serveurs-minecraft.org') {
                $candidate = Cache::lock('vote:provider:serveurs-minecraft:request', 10);
                if (!$candidate->get()) {
                    throw ValidationException::withMessages(['vote' => __('vote::messages.verification.rate_limit')]);
                }
                $lock = $candidate;
                if (RateLimiter::tooManyAttempts('vote:provider:serveurs-minecraft:rate', 1)) {
                    throw ValidationException::withMessages(['vote' => __('vote::messages.verification.rate_limit')]);
                }
                RateLimiter::hit('vote:provider:serveurs-minecraft:rate', 1);
            }

            return $this->send($method, $url, $query, $headers, $body, $form);
        } finally {
            $lock?->release();
        }
    }

    private function send(string $method, string $url, array $query, array $headers, ?array $body, bool $form): Response
    {
        try {
            $response = Http::timeout(8)
                ->connectTimeout(3)
                ->withOptions(['allow_redirects' => false])
                ->acceptJson()
                ->withHeaders($headers)
                ->send(
                    $method,
                    $url,
                    ['query' => $query] + ($body === null ? [] : [$form ? 'form_params' : 'json' => $body]),
                );
            if (strlen($response->body()) > 65536 || (!$response->successful() && $response->status() !== 404)) {
                throw new RuntimeException('Vote service response is unavailable.');
            }

            return $response;
        } catch (Throwable) {
            throw ValidationException::withMessages(['vote' => __('vote::messages.verification.service_unavailable')]);
        }
    }

    protected function assertConfirmed(bool $confirmed): void
    {
        if (!$confirmed) {
            throw ValidationException::withMessages(['vote' => __('vote::messages.verification.not_confirmed')]);
        }
    }

    protected function apiHosts(): array
    {
        return ['serveur-prive.net', 'minecraft-mp.com', 'www.serveurs-minecraft.org'];
    }

    protected function httpsHost(string $url, array $hosts): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) &&
            parse_url($url, PHP_URL_SCHEME) === 'https' &&
            in_array(strtolower(parse_url($url, PHP_URL_HOST) ?? ''), $hosts, true) &&
            !parse_url($url, PHP_URL_USER) &&
            !parse_url($url, PHP_URL_PASS) &&
            !parse_url($url, PHP_URL_PORT) &&
            !parse_url($url, PHP_URL_FRAGMENT);
    }
}
