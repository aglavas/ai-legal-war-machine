<?php

namespace App\Console\Commands;

use App\Models\EoglasnaOsijekMonitoring;
use App\Repositories\EoglasnaOsijekMonitoringRepository;
use Illuminate\Console\Command;

class EoglasnaNormalizeParticipants extends Command
{
    protected $signature = 'eoglasna:normalize-participants {--chunk=500 : Process records per chunk}';

    protected $description = 'Normalize participants JSON (UTF-8 decode, slash cleanup) and re-fill parsed columns for existing eoglasna_osijek_monitoring rows.';

    public function handle(EoglasnaOsijekMonitoringRepository $repo): int
    {
        $chunk = (int) $this->option('chunk');
        $count = 0;
        EoglasnaOsijekMonitoring::query()
            ->orderBy('id')
            ->chunkById($chunk, function ($rows) use (&$count, $repo) {
                foreach ($rows as $row) {
                    $participants = is_array($row->participants) ? $row->participants : [];
                    // Re-normalize and refill columns
                    $norm = (new class($repo) {
                        public function __construct(private EoglasnaOsijekMonitoringRepository $r) {}
                        public function normalize(array $p): array { $ref = new \ReflectionClass($this->r); $m=$ref->getMethod('normalizeParticipants'); $m->setAccessible(true); return $m->invoke($this->r, $p); }
                        public function fill(EoglasnaOsijekMonitoring $m, array $p): void { $ref = new \ReflectionClass($this->r); $mtd=$ref->getMethod('fillParticipantColumns'); $mtd->setAccessible(true); $mtd->invoke($this->r, $m, $p); }
                    })->normalize($participants);

                    $row->participants = $norm;
                    // Fill parsed columns
                    (new class($repo) {
                        public function __construct(private EoglasnaOsijekMonitoringRepository $r) {}
                        public function fill(EoglasnaOsijekMonitoring $m, array $p): void { $ref = new \ReflectionClass($this->r); $mtd=$ref->getMethod('fillParticipantColumns'); $mtd->setAccessible(true); $mtd->invoke($this->r, $m, $p); }
                    })->fill($row, $norm);

                    $row->save();
                    $count++;
                }
            });

        $this->info("Normalized {$count} rows.");
        return self::SUCCESS;
    }
}

