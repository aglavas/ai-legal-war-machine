<?php

namespace App\GraphQL\AutoDiscovery;

use App\GraphQL\AutoDiscovery\Exceptions\GraphQLQueryException;
use App\GraphQL\AutoDiscovery\Introspection\GraphQLIntrospectionService;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Http;

class GraphQLAutoClient
{
    public function __construct(
        protected GraphQLIntrospectionService $introspector,
        protected SchemaCacheRepository $cache,
        protected QueryBuilder $builder,
        protected Repository $config
    ) {}

    public function warmUp(): void
    {
        $payload = $this->cache->get();
        if (!$payload) {
            try {
                $schema = $this->introspector->introspect(); // fixed
                $this->cache->put($schema);
            } catch (\Throwable $e) {
                logger()->warning('GraphQL warm-up introspection failed: ' . $e->getMessage());
            }
        }
    }

    protected function ensureSchemaLoaded(): void
    {
        $payload = $this->cache->get();
        if (!$payload) {
            $schema = $this->introspector->introspect();
            $this->cache->put($schema);
            $payload = $this->cache->get();
        }
        $this->builder->setSchemaData($payload['schema'] ?? $payload);
    }

    public function run(
        string $rootFieldName,
        array $variables = [],
        ?array $selection = null,
        array $options = []
    ): array {
        try {
            $this->ensureSchemaLoaded();
        } catch (\Throwable $e) {
            $fallback = (bool) $this->config->get('graphql_client.fallback.enabled', false);
            if (!$fallback) {
                throw $e;
            }
            $this->builder->setSchemaData([]); // builder will use hints
        }

        $alias = $options['alias'] ?? null;
        $opName = $options['operationName'] ?? null;

        $doc = $this->builder->buildQueryDocument($rootFieldName, $variables, $selection, $alias, $opName);

        $response = $this->post($doc['query'], $doc['variables'], $doc['operationName']);

        if (isset($response['errors'])) {
            $message = $this->formatGraphQLErrors($response['errors']);
            throw new GraphQLQueryException($message, $response['errors']);
        }

        $data = $response['data'] ?? [];

        if ($alias && isset($data[$alias])) {
            return $data[$alias];
        }
        if (isset($data[$rootFieldName])) {
            return $data[$rootFieldName];
        }
        return $data;
    }

    protected function post(string $query, array $variables = [], ?string $operationName = null): array
    {
        $endpoint = (string) $this->config->get('graphql_client.endpoint', '');
        if ($endpoint === '') {
            throw new \RuntimeException('GraphQL endpoint is not configured.');
        }

        $headers = (array) $this->config->get('graphql_client.headers', []);
        $authHeader = (string) $this->config->get('graphql_client.auth.header', 'Authorization');
        $bearer = $this->config->get('graphql_client.auth.bearer');

        if (!empty($bearer)) {
            $headers[$authHeader] = 'Bearer ' . $bearer;
        }

        $timeout = (int) $this->config->get('graphql_client.timeout', 15);
        $connectTimeout = (int) $this->config->get('graphql_client.connect_timeout', 5);

        $payload = array_filter([
            'query' => $query,
            'variables' => (object) $variables,
            'operationName' => $operationName,
        ], fn ($v) => $v !== null);

        $resp = Http::withHeaders($headers)
            ->timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->retry(2, 200, fn ($e, $r) => in_array(optional($r)->status(), [429, 500, 502, 503, 504], true))
            ->post($endpoint, $payload);

        if (!$resp->ok()) {
            throw new \RuntimeException('GraphQL HTTP error: ' . $resp->status() . ' ' . $resp->body());
        }

        return $resp->json() ?? [];
    }

    protected function formatGraphQLErrors(array $errors): string
    {
        $parts = [];
        foreach ($errors as $e) {
            $msg = $e['message'] ?? 'Unknown error';
            $path = isset($e['path']) ? implode('.', (array) $e['path']) : null;
            $parts[] = $path ? "{$msg} (path: {$path})" : $msg;
        }
        return implode('; ', $parts);
    }
}
