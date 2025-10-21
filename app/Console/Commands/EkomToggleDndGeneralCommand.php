<?php

namespace App\Console\Commands;

use App\Services\EkomService;
use Illuminate\Console\Command;

class EkomToggleDndGeneralCommand extends Command
{
    protected $signature = 'ekom:dnd:general {action : on|off}';
    protected $description = "Toggle general 'Do Not Disturb'";

    public function handle(EkomService $service): int
    {
        $action = $this->argument('action');

        if ($action === 'on') {
            $dto = $service->turnOnGeneralDnd();
            $this->info('General DND ON: ' . json_encode($dto));
        } elseif ($action === 'off') {
            $dto = $service->turnOffGeneralDnd();
            $this->info('General DND OFF: ' . json_encode($dto));
        } else {
            $this->error('Action must be on|off');
            return self::INVALID;
        }

        return self::SUCCESS;
    }
}
