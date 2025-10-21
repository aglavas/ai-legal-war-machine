<?php

namespace App\Console\Commands;

use App\Services\EkomService;
use Illuminate\Console\Command;

class EkomDndAllOffCommand extends Command
{
    protected $signature = 'ekom:dnd:all-off';
    protected $description = "Turn OFF 'Do Not Disturb' on all Predmeti";

    public function handle(EkomService $service): int
    {
        $service->dndAllOff();
        $this->info('DND OFF for all Predmeti executed.');
        return self::SUCCESS;
    }
}
