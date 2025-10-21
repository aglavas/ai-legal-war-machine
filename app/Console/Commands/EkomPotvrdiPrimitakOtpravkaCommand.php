<?php

namespace App\Console\Commands;

use App\Services\EkomService;
use Illuminate\Console\Command;

class EkomPotvrdiPrimitakOtpravkaCommand extends Command
{
    protected $signature = 'ekom:otpravci:potvrdi {id}';
    protected $description = 'Confirm receipt of a dispatch (Otpravak) by ID';

    public function handle(EkomService $service): int
    {
        $id = (int) $this->argument('id');
        $service->potvrdiPrimitakOtpravka($id);
        $this->info("Potvrda primitka sent for otpravak {$id}.");
        return self::SUCCESS;
    }
}
