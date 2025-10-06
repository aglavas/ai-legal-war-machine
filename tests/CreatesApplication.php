<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;

trait CreatesApplication
{
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(ConsoleKernel::class)->bootstrap();
        return $app;
    }
}
