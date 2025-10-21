<?php

namespace App\Clients\Ekom;

use App\Exceptions\EkomApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class EkomApiClient implements EkomApiClientInterface
{
    private Client $client;
    private string $token;
    private int $retries;
    private int $retryDelayMs;

    public function __construct(
        string $baseUrl,
        string $token,
        int $timeout = 30,
        int $retries = 2,
        int $retryDelayMs = 300,
        string $userAgent = 'Laravel-Ekom-Client/1.0'
    ) {
        $this->client = new Client([
            'base_uri' => rtrim($baseUrl, '/') . '/',
            'timeout' => $timeout,
            'http_errors' => false, // We'll throw our own exceptions
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => $userAgent,
            ],
        ]);
        $this->token = $token ?? '';
        $this->retries = max(0, $retries);
        $this->retryDelayMs = max(0, $retryDelayMs);
    }

    // --------------- Public API methods (specialized) ---------------

    // Predmeti
    public function listPredmeti(array $query): array
    {
        return $this->get('api/veliki-korisnici/predmeti', $query);
    }

    public function getPredmetById(int $id): array
    {
        return $this->get("api/veliki-korisnici/predmeti/{$id}");
    }

    public function getPredmetByParams(array $query): array
    {
        // Note trailing slash in spec
        return $this->get('api/veliki-korisnici/predmeti/', $query);
    }

    public function getOtpravciPredmeta(int $predmetId): array
    {
        return $this->get("api/veliki-korisnici/predmeti/{$predmetId}/otpravci");
    }

    public function downloadDostavnicaOtpravkaPredmeta(int $predmetId, int $otpravakId, string $saveToPath): string
    {
        return $this->downloadToPath(
            "api/veliki-korisnici/predmeti/{$predmetId}/otpravci/{$otpravakId}/dostavnica/sadrzaj",
            [],
            $saveToPath
        );
    }

    public function downloadDokumentiPredmeta(int $predmetId, array $dokumentIds, string $saveToPath): string
    {
        $query = [];
        if (!empty($dokumentIds)) {
            $query['dokumentId'] = array_values($dokumentIds);
        }
        return $this->downloadToPath(
            "api/veliki-korisnici/predmeti/{$predmetId}/dokumenti/sadrzaj",
            $query,
            $saveToPath
        );
    }

    // DND
    public function turnOnDoNotDisturbPredmet(int $predmetId): bool
    {
        $res = $this->post("api/veliki-korisnici/predmeti/{$predmetId}/do-not-disturb/turn-on");
        // Successful response is boolean JSON
        return (bool) $res;
    }

    public function turnOffDoNotDisturbPredmet(int $predmetId): bool
    {
        $res = $this->post("api/veliki-korisnici/predmeti/{$predmetId}/do-not-disturb/turn-off");
        return (bool) $res;
    }

    public function turnOnGeneralDoNotDisturb(): array
    {
        return $this->post("api/veliki-korisnici/predmeti/do-not-disturb/turn-on") ?? [];
    }

    public function turnOffGeneralDoNotDisturb(): array
    {
        return $this->post("api/veliki-korisnici/predmeti/do-not-disturb/turn-off") ?? [];
    }

    public function turnOffDoNotDisturbForAllPredmet(): void
    {
        $this->post("api/veliki-korisnici/predmeti/do-not-disturb/all", expectJson: false);
    }

    // Podnesci
    public function listPodnesci(array $query): array
    {
        return $this->get('api/veliki-korisnici/podnesci', $query);
    }

    public function getPodnesak(int $id): array
    {
        return $this->get("api/veliki-korisnici/podnesci/{$id}");
    }

    public function createPodnesak(array $payload, array $filePaths): array
    {
        // multipart: 'podnesak' (application/json) + 'files' (array of binaries)
        $multipart = [
            [
                'name' => 'podnesak',
                'contents' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'headers' => ['Content-Type' => 'application/json'],
            ],
        ];

        foreach ($filePaths as $path) {
            $multipart[] = [
                'name' => 'files',
                'contents' => fopen($path, 'rb'),
                'filename' => basename($path),
            ];
        }

        return $this->postMultipart('api/veliki-korisnici/podnesci', $multipart);
    }

    public function createPrilogPodneska(int $podnesakId, array $payload, string $filePath): int
    {
        $multipart = [
            [
                'name' => 'prilog',
                'contents' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'headers' => ['Content-Type' => 'application/json'],
            ],
            [
                'name' => 'file',
                'contents' => fopen($filePath, 'rb'),
                'filename' => basename($filePath),
            ],
        ];

        $response = $this->postMultipart("api/veliki-korisnici/podnesci/{$podnesakId}/prilozi", $multipart);
        // API returns integer ID in JSON
        return (int) $response;
    }

    public function posaljiPodnesakNaSud(int $podnesakId, array $payload): void
    {
        $this->post("api/veliki-korisnici/podnesci/{$podnesakId}/posalji-na-sud", $payload, expectJson: false, expectNoContent: true);
    }

    public function downloadObavijestOPrimitkuPodneska(int $id, string $saveToPath): string
    {
        return $this->downloadToPath("api/veliki-korisnici/podnesci/{$id}/obavijest-o-primitku", [], $saveToPath);
    }

    public function downloadNalogZaPlacanjePristojbePodneska(int $id, string $saveToPath): string
    {
        return $this->downloadToPath("api/veliki-korisnici/podnesci/{$id}/pristojba/nalog-za-placanje", [], $saveToPath);
    }

    public function downloadDokazUplateOslobodjenjaPristojbePodneska(int $id, string $saveToPath): string
    {
        return $this->downloadToPath("api/veliki-korisnici/podnesci/{$id}/pristojba/dokaz-uplate-oslobodjenja", [], $saveToPath);
    }

    // Otpravci
    public function listOtpravci(array $query): array
    {
        return $this->get('api/veliki-korisnici/otpravci', $query);
    }

    public function getOtpravakById(int $id): array
    {
        return $this->get("api/veliki-korisnici/otpravci/{$id}");
    }

    public function potvrdiPrimitakOtpravka(int $id): void
    {
        $this->post("api/veliki-korisnici/otpravci/{$id}/potvrdi-primitak", expectJson: false, expectNoContent: true);
    }

    public function downloadPotvrdaPrimitkaOtpravka(int $id, string $saveToPath): string
    {
        return $this->downloadToPath("api/veliki-korisnici/otpravci/{$id}/potvrda-primitka", [], $saveToPath);
    }

    public function downloadDokumentiOtpravka(int $id, array $dokumentIds, string $saveToPath): string
    {
        $query = [];
        if (!empty($dokumentIds)) {
            $query['dokumentId'] = array_values($dokumentIds);
        }
        return $this->downloadToPath("api/veliki-korisnici/otpravci/{$id}/dokumenti/sadrzaj", $query, $saveToPath);
    }

    // Šifrarnici
    public function getSudovi(): array
    {
        return $this->get("api/veliki-korisnici/sifrarnici/sudovi");
    }

    // --------------- Low-level helpers ---------------

    private function get(string $uri, array $query = []): array
    {
        $options = [
            'query' => $query,
            'headers' => $this->authHeaders(),
        ];
        $response = $this->sendWithRetry('GET', $uri, $options);
        return $this->jsonOrThrow($response);
    }

    private function post(string $uri, ?array $json = null, bool $expectJson = true, bool $expectNoContent = false)
    {
        $options = [
            'headers' => $this->authHeaders(),
        ];

        if (!is_null($json)) {
            $options['json'] = $json;
        }

        $response = $this->sendWithRetry('POST', $uri, $options);

        if ($expectNoContent) {
            if ($response->getStatusCode() !== 204) {
                throw EkomApiException::fromResponse($response);
            }
            return null;
        }

        if ($expectJson) {
            return $this->jsonOrThrow($response);
        }

        // No JSON expected, ensure 2xx
        $this->throwIfNot2xx($response);
        // Some endpoints return boolean JSON; if not expecting JSON, we just return null.
        return null;
    }

    private function postMultipart(string $uri, array $multipart): array
    {
        $options = [
            'headers' => $this->authHeaders(),
            'multipart' => $multipart,
        ];
        $response = $this->sendWithRetry('POST', $uri, $options);
        return $this->jsonOrThrow($response);
    }

    private function downloadToPath(string $uri, array $query, string $saveToPath): string
    {
        $dir = dirname($saveToPath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new \RuntimeException("Unable to create directory: {$dir}");
            }
        }

        $options = [
            'headers' => $this->authHeaders(['Accept' => '*/*']),
            'query' => $query,
            'sink' => $saveToPath,
        ];

        $response = $this->sendWithRetry('GET', $uri, $options);
        $this->throwIfNot2xx($response);

        return $saveToPath;
    }

    private function authHeaders(array $extra = []): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->token,
        ];
        return array_merge($headers, $extra);
    }

    private function jsonOrThrow(ResponseInterface $response): array|bool|int|string|null
    {
        $this->throwIfNot2xx($response);
        $body = (string) $response->getBody();
        if ($body === '') {
            return null;
        }

        $ct = $response->getHeaderLine('Content-Type');
        if (!str_contains($ct, 'application/json')) {
            // Some endpoints return boolean JSON but may omit header; attempt decode anyway.
        }

        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Non-JSON: return raw string
        return $body;
    }

    private function throwIfNot2xx(ResponseInterface $response): void
    {
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw EkomApiException::fromResponse($response);
        }
    }

    private function sendWithRetry(string $method, string $uri, array $options): ResponseInterface
    {
        $attempts = 0;
        start:
        try {
            $attempts++;
            return $this->client->request($method, $uri, $options);
        } catch (GuzzleException $e) {
            // Retry only on network level errors
            if ($attempts <= $this->retries) {
                usleep($this->retryDelayMs * 1000);
                goto start;
            }
            throw new EkomApiException(
                statusCode: 0,
                errorId: null,
                errorMessage: $e->getMessage(),
                errorMessages: null,
                responseBody: null
            );
        } finally {
            // Also handle retry for HTTP 429/5xx after receiving response
            // But this path is in caller; below logic duplicates in get/post etc.
        }
    }
}
