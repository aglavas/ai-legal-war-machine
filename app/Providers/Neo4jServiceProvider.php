<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laudis\Neo4j\Client;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Contracts\ClientInterface;

class Neo4jServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClientInterface::class, function () {
            $cfg = config('neo4j');

            $scheme = $cfg['scheme'] ?? 'bolt';
            if (!empty($cfg['tls']) && !str_contains($scheme, '+s')) {
                $scheme .= '+s';
            }

            $uri = sprintf('%s://%s:%d', $scheme, $cfg['host'], $cfg['port']);

            return ClientBuilder::create()
                ->withDriver('default', $uri, Authenticate::basic($cfg['user'], $cfg['password']))
                ->withDefaultDriver('default')
                ->build();
        });

        // Optional: also bind the concrete class if you typehint Client instead of the interface anywhere
        $this->app->singleton(\Laudis\Neo4j\Client::class, function ($app) {
            return $app->make(ClientInterface::class);
        });
    }
}
