<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Clients\Ekom\EkomApiClient;
use App\Clients\Ekom\EkomApiClientInterface;

class EkomServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('ekom.php'), 'ekom');

        $this->app->singleton(EkomApiClientInterface::class, function () {
            return new EkomApiClient(
                baseUrl: config('ekom.base_url'),
                token: config('ekom.token'),
                timeout: (int) config('ekom.timeout'),
                retries: (int) config('ekom.retries'),
                retryDelayMs: (int) config('ekom.retry_delay_ms'),
                userAgent: (string) config('ekom.user_agent'),
            );
        });
    }

    public function boot(): void
    {
        // Nothing to boot currently.
    }
}
