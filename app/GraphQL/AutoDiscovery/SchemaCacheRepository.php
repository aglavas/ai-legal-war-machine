<?php

namespace App\GraphQL\AutoDiscovery;

use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Cache;

class SchemaCacheRepository
{
    public function __construct(protected Repository $config) {}

    protected function cacheKey(): string
    {
        $endpoint = (string) $this->config->get('graphql_client.endpoint', '');
        $hash = sha1($endpoint);
        return "graphql:schema:$hash";
    }

    public function get(): ?array
    {
        $store = $this->config->get('graphql_client.cache.store');
        return Cache::store($store)->get($this->cacheKey());
    }

    public function put(array $schema): void
    {
        $store = $this->config->get('graphql_client.cache.store');
        $ttl = (int) $this->config->get('graphql_client.cache.ttl', 86400);
        $payload = [
            'schema' => $schema,
            'hash' => sha1(json_encode($schema)),
            'fetched_at' => now()->toIso8601String(),
        ];
        Cache::store($store)->put($this->cacheKey(), $payload, $ttl);
    }

    public function clear(): void
    {
        $store = $this->config->get('graphql_client.cache.store');
        Cache::store($store)->forget($this->cacheKey());
    }

    public function isEmpty(): bool
    {
        return $this->get() === null;
    }
}
