<?php

namespace App\Console\Commands;

use App\Services\EkomService;
use Illuminate\Console\Command;

class EkomToggleDndPredmetCommand extends Command
{
    protected $signature = 'ekom:dnd:predmet {predmetId} {action : on|off}';
    protected $description = "Toggle 'Do Not Disturb' for a specific Predmet (case)";

    public function handle(EkomService $service): int
    {
        $predmetId = (int) $this->argument('predmetId');
        $action = $this->argument('action');

        if ($action === 'on') {
            $res = $service->turnOnDndPredmet($predmetId);
            $this->info("DND ON for predmet {$predmetId}: " . ($res ? 'true' : 'false'));
        } elseif ($action === 'off') {
            $res = $service->turnOffDndPredmet($predmetId);
            $this->info("DND OFF for predmet {$predmetId}: " . ($res ? 'true' : 'false'));
        } else {
            $this->error('Action must be on|off');
            return self::INVALID;
        }

        return self::SUCCESS;
    }
}
