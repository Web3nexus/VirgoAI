<?php

namespace App\Ai;

use App\Ai\Contracts\ProviderAdapter;
use App\Ai\Exceptions\ProviderNotConfigured;

class AdapterRegistry
{
    /** @var array<string, class-string<ProviderAdapter>> */
    protected array $adapters = [];

    public function __construct()
    {
        foreach ((array) config('virgoai.providers', []) as $key => $definition) {
            if (isset($definition['adapter'])) {
                $this->adapters[$key] = $definition['adapter'];
            }
        }
    }

    /**
     * @param  class-string<ProviderAdapter>  $adapter
     */
    public function register(string $key, string $adapter): void
    {
        $this->adapters[$key] = $adapter;
    }

    public function has(string $key): bool
    {
        return isset($this->adapters[$key]);
    }

    public function resolve(string $key): ProviderAdapter
    {
        if (! $this->has($key)) {
            throw ProviderNotConfigured::forProvider($key);
        }

        return app($this->adapters[$key]);
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->adapters);
    }
}
