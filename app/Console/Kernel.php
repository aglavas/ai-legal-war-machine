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

        // Autonomous agent scheduled research - runs weekly on Sunday at 02:00
        $schedule->command('agent:research-scheduled --max-iterations=15 --time-limit=1800')
            ->weekly()
            ->sundays()
            ->at('02:00')
            ->withoutOverlapping()
            ->runInBackground();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
