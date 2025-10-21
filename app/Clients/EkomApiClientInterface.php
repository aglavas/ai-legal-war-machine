<?php

namespace App\Clients\Ekom;

interface EkomApiClientInterface
{
    // Predmeti (Cases)
    public function listPredmeti(array $query): array;
    public function getPredmetById(int $id): array;
    public function getPredmetByParams(array $query): array;
    public function getOtpravciPredmeta(int $predmetId): array;
    public function downloadDostavnicaOtpravkaPredmeta(int $predmetId, int $otpravakId, string $saveToPath): string;
    public function downloadDokumentiPredmeta(int $predmetId, array $dokumentIds, string $saveToPath): string;

    // Do Not Disturb
    public function turnOnDoNotDisturbPredmet(int $predmetId): bool;
    public function turnOffDoNotDisturbPredmet(int $predmetId): bool;
    public function turnOnGeneralDoNotDisturb(): array;
    public function turnOffGeneralDoNotDisturb(): array;
    public function turnOffDoNotDisturbForAllPredmet(): void;

    // Podnesci (Submissions)
    public function listPodnesci(array $query): array;
    public function getPodnesak(int $id): array;
    public function createPodnesak(array $payload, array $filePaths): array;
    public function createPrilogPodneska(int $podnesakId, array $payload, string $filePath): int;
    public function posaljiPodnesakNaSud(int $podnesakId, array $payload): void;
    public function downloadObavijestOPrimitkuPodneska(int $id, string $saveToPath): string;
    public function downloadNalogZaPlacanjePristojbePodneska(int $id, string $saveToPath): string;
    public function downloadDokazUplateOslobodjenjaPristojbePodneska(int $id, string $saveToPath): string;

    // Otpravci (Dispatches)
    public function listOtpravci(array $query): array;
    public function getOtpravakById(int $id): array;
    public function potvrdiPrimitakOtpravka(int $id): void;
    public function downloadPotvrdaPrimitkaOtpravka(int $id, string $saveToPath): string;
    public function downloadDokumentiOtpravka(int $id, array $dokumentIds, string $saveToPath): string;

    // Šifrarnici (Reference)
    public function getSudovi(): array;
}
