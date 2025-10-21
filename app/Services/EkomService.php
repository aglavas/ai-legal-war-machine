<?php

namespace App\Services;

use App\Clients\Ekom\EkomApiClientInterface;
use App\Repositories\EkomPredmetRepository;
use App\Repositories\EkomPodnesakRepository;
use App\Repositories\EkomOtpravakRepository;

class EkomService
{
    public function __construct(
        private readonly EkomApiClientInterface $client,
        private readonly EkomPredmetRepository $predmetRepo,
        private readonly EkomPodnesakRepository $podnesakRepo,
        private readonly EkomOtpravakRepository $otpravakRepo,
    ) {}

    // ---------- Sync operations ----------

    public function syncPredmeti(array $filters = [], int $maxPages = 1, int $pageSize = null): int
    {
        $page = 0;
        $saved = 0;
        $size = $pageSize ?? (int) config('ekom.default_page_size', 50);

        do {
            $query = array_merge($filters, ['page' => $page, 'size' => $size]);
            $res = $this->client->listPredmeti($query);

            $content = $res['content'] ?? [];
            foreach ($content as $item) {
                $this->predmetRepo->upsertFromApi($item);
                $saved++;
            }

            $last = (bool) ($res['last'] ?? true);
            $page++;
        } while (!$last && $page <= $maxPages);

        return $saved;
    }

    public function syncPodnesci(array $filters = [], int $maxPages = 1, int $pageSize = null): int
    {
        $page = 0;
        $saved = 0;
        $size = $pageSize ?? (int) config('ekom.default_page_size', 50);

        do {
            $query = array_merge($filters, ['page' => $page, 'size' => $size]);
            $res = $this->client->listPodnesci($query);

            $content = $res['content'] ?? [];
            foreach ($content as $item) {
                $this->podnesakRepo->upsertFromPagedApi($item);
                $saved++;
            }

            $last = (bool) ($res['last'] ?? true);
            $page++;
        } while (!$last && $page <= $maxPages);

        return $saved;
    }

    public function syncOtpravci(array $filters = [], int $maxPages = 1, int $pageSize = null): int
    {
        $page = 0;
        $saved = 0;
        $size = $pageSize ?? (int) config('ekom.default_page_size', 50);

        do {
            $query = array_merge($filters, ['page' => $page, 'size' => $size]);
            $res = $this->client->listOtpravci($query);

            $content = $res['content'] ?? [];
            foreach ($content as $item) {
                $this->otpravakRepo->upsertFromPagedApi($item);
                $saved++;
            }

            $last = (bool) ($res['last'] ?? true);
            $page++;
        } while (!$last && $page <= $maxPages);

        return $saved;
    }

    // ---------- Shortcuts to client methods for commands/controllers ----------

    public function turnOnDndPredmet(int $predmetId): bool
    {
        return $this->client->turnOnDoNotDisturbPredmet($predmetId);
    }

    public function turnOffDndPredmet(int $predmetId): bool
    {
        return $this->client->turnOffDoNotDisturbPredmet($predmetId);
    }

    public function turnOnGeneralDnd(): array
    {
        return $this->client->turnOnGeneralDoNotDisturb();
    }

    public function turnOffGeneralDnd(): array
    {
        return $this->client->turnOffGeneralDoNotDisturb();
    }

    public function dndAllOff(): void
    {
        $this->client->turnOffDoNotDisturbForAllPredmet();
    }

    public function potvrdiPrimitakOtpravka(int $id): void
    {
        $this->client->potvrdiPrimitakOtpravka($id);
    }

    public function download(string $type, array $params, string $saveToPath): string
    {
        switch ($type) {
            case 'predmet-dokumenti':
                return $this->client->downloadDokumentiPredmeta(
                    $params['predmetId'],
                    $params['dokumentIds'] ?? [],
                    $saveToPath
                );
            case 'predmet-dostavnica':
                return $this->client->downloadDostavnicaOtpravkaPredmeta(
                    $params['predmetId'],
                    $params['otpravakId'],
                    $saveToPath
                );
            case 'otpravak-potvrda':
                return $this->client->downloadPotvrdaPrimitkaOtpravka($params['otpravakId'], $saveToPath);
            case 'otpravak-dokumenti':
                return $this->client->downloadDokumentiOtpravka(
                    $params['otpravakId'],
                    $params['dokumentIds'] ?? [],
                    $saveToPath
                );
            case 'podnesak-obavijest':
                return $this->client->downloadObavijestOPrimitkuPodneska($params['podnesakId'], $saveToPath);
            case 'podnesak-nalog':
                return $this->client->downloadNalogZaPlacanjePristojbePodneska($params['podnesakId'], $saveToPath);
            case 'podnesak-dokaz':
                return $this->client->downloadDokazUplateOslobodjenjaPristojbePodneska($params['podnesakId'], $saveToPath);
        }
        throw new \InvalidArgumentException("Unsupported download type: {$type}");
    }

    public function createPodnesak(array $payload, array $filePaths): array
    {
        return $this->client->createPodnesak($payload, $filePaths);
    }
}
