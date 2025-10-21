<?php

namespace App\Console\Commands;

use App\Services\EkomService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class EkomDownloadCommand extends Command
{
    protected $signature = 'ekom:download
        {type : predmet-dokumenti|predmet-dostavnica|otpravak-potvrda|otpravak-dokumenti|podnesak-obavijest|podnesak-nalog|podnesak-dokaz}
        {--predmetId= : Predmet ID}
        {--otpravakId= : Otpravak ID}
        {--podnesakId= : Podnesak ID}
        {--dokumentId=* : One or more document IDs}
        {--path= : Full path to save file (defaults to storage/app/ekom/...)}';

    protected $description = 'Download various documents/receipts from e-Komunikacija API';

    public function handle(EkomService $service): int
    {
        $type = $this->argument('type');
        $saveTo = $this->option('path');

        if (!$saveTo) {
            $saveTo = $this->defaultPath($type);
        }

        $params = [
            'predmetId' => $this->option('predmetId') ? (int) $this->option('predmetId') : null,
            'otpravakId' => $this->option('otpravakId') ? (int) $this->option('otpravakId') : null,
            'podnesakId' => $this->option('podnesakId') ? (int) $this->option('podnesakId') : null,
            'dokumentIds' => $this->option('dokumentId') ?: [],
        ];

        try {
            $path = $service->download($type, array_filter($params, fn($v) => $v !== null), $saveTo);
            $this->info("Saved to: {$path}");
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function defaultPath(string $type): string
    {
        $base = storage_path('app/ekom');
        @mkdir($base, 0777, true);

        $ts = now()->format('YmdHis');
        return match ($type) {
            'predmet-dokumenti' => "{$base}/predmet-dokumenti-{$ts}.zip",
            'predmet-dostavnica' => "{$base}/predmet-dostavnica-{$ts}.pdf",
            'otpravak-potvrda' => "{$base}/otpravak-potvrda-{$ts}.pdf",
            'otpravak-dokumenti' => "{$base}/otpravak-dokumenti-{$ts}.zip",
            'podnesak-obavijest' => "{$base}/podnesak-obavijest-{$ts}.pdf",
            'podnesak-nalog' => "{$base}/podnesak-nalog-{$ts}.pdf",
            'podnesak-dokaz' => "{$base}/podnesak-dokaz-{$ts}.pdf",
            default => "{$base}/download-{$ts}.bin",
        };
    }
}
