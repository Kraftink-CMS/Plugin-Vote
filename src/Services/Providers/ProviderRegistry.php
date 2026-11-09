<?php

namespace KraftInk\Plugins\Vote\Services\Providers;

use KraftInk\Plugins\Vote\Contracts\VoteProvider;
use InvalidArgumentException;

class ProviderRegistry
{
    private array $providers = [];

    public function register(string $id, string $class): void
    {
        if (
            !preg_match('/^[a-z][a-z0-9_-]{1,39}$/D', $id) ||
            !is_subclass_of($class, VoteProvider::class) ||
            (isset($this->providers[$id]) && $this->providers[$id] !== $class)
        ) {
            throw new InvalidArgumentException('Invalid vote provider registration.');
        }
        $this->providers[$id] = $class;
    }

    public function get(string $id): VoteProvider
    {
        $value = $this->providers[$id] ?? throw new InvalidArgumentException('Vote provider is unavailable.');
        $provider = $value instanceof VoteProvider ? $value : app($value);
        if ($provider->id() !== $id) {
            throw new InvalidArgumentException('Vote provider identifier mismatch.');
        }

        return $provider;
    }

    public function all(): array
    {
        return array_map(fn ($value) => $value instanceof VoteProvider ? $value : app($value), $this->providers);
    }

    public function registerProvider(VoteProvider $provider): void
    {
        if (!preg_match('/^[a-z][a-z0-9_-]{1,39}$/D', $provider->id()) || isset($this->providers[$provider->id()])) {
            throw new InvalidArgumentException('Invalid vote provider registration.');
        }
        $this->providers[$provider->id()] = $provider;
    }

    public function has(string $id): bool
    {
        return isset($this->providers[$id]);
    }
}
