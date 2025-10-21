<?php

namespace App\GraphQL\AutoDiscovery\Introspection;

use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GraphQLIntrospectionService
{
    public function __construct(protected Repository $config) {}

    public function introspect(): array
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

        $payload = ['query' => $this->introspectionQuery()];

        $response = Http::withHeaders($headers)
            ->timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->retry(2, 200, fn ($e, $r) => in_array(optional($r)->status(), [429, 500, 502, 503, 504], true))
            ->post($endpoint, $payload);

        if (!$response->ok()) {
            throw new \RuntimeException('Introspection HTTP error: ' . $response->status() . ' ' . $response->body());
        }

        $json = $response->json();

        if (isset($json['errors'])) {
            throw new \RuntimeException('Introspection GraphQL errors: ' . json_encode($json['errors']));
        }

        if (!isset($json['data']['__schema'])) {
            throw new \RuntimeException('Invalid introspection response: __schema missing.');
        }

        return $json['data'];
    }

    protected function introspectionQuery(): string
    {
        return <<<'GRAPHQL'
query IntrospectionQuery {
  __schema {
    queryType { name }
    mutationType { name }
    subscriptionType { name }
    types {
      ...FullType
    }
    directives {
      name
      description
      locations
      args {
        ...InputValue
      }
    }
  }
}
fragment FullType on __Type {
  kind
  name
  description
  fields(includeDeprecated: true) {
    name
    description
    args {
      ...InputValue
    }
    type {
      ...TypeRef
    }
    isDeprecated
    deprecationReason
  }
  inputFields {
    ...InputValue
  }
  interfaces {
    ...TypeRef
  }
  enumValues(includeDeprecated: true) {
    name
    description
    isDeprecated
    deprecationReason
  }
  possibleTypes {
    ...TypeRef
  }
}
fragment InputValue on __InputValue {
  name
  description
  type { ...TypeRef }
  defaultValue
}
fragment TypeRef on __Type {
  kind
  name
  ofType {
    kind
    name
    ofType {
      kind
      name
      ofType {
        kind
        name
        ofType {
          kind
          name
          ofType {
            kind
            name
          }
        }
      }
    }
  }
}
GRAPHQL;
    }
}
