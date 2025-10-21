<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Separate schedules for e-Oglasna monitoring
        $schedule->command('eoglasna:watch-keywords')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('eoglasna:watch-osijek')->hourly()->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
