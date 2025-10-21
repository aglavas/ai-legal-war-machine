<?php
namespace App\Services\Eoglasna\Api;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Carbon\Carbon;

class EoglasnaHttpClient
{
    protected string $baseUrl;
    protected int $timeout;
    protected int $connectTimeout;
    protected int $minDelayMs;
    protected int $retryJitterMs;
    protected ?Carbon $lastCallAt = null;
    protected int $requestsThisHour = 0;
    protected ?Carbon $hourWindowStart = null;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('eoglasna.base_url'), '/');
        $this->timeout = (int) config('eoglasna.timeout', 15);
        $this->connectTimeout = (int) config('eoglasna.connect_timeout', 10);
        $this->minDelayMs = (int) config('eoglasna.min_delay_ms', 1100);
        $this->retryJitterMs = (int) config('eoglasna.retry_backoff_jitter_ms', 100);
    }

    // Public endpoints methods

    public function getNotices(array $filter = [], int $page = 0, ?string $sort = null): array
    {
        $params = array_merge($filter, [
            'page' => $page,
            'sort' => $sort ?: config('eoglasna.default_sort', 'datePublished,desc'),
        ]);

        return $this->requestJson('GET', '/api/v1/notice', $params);
    }

    public function getNoticeByUuid(string $uuid): array
    {
        return $this->requestJson('GET', "/api/v1/notice/{$uuid}");
    }

    public function getCourtNotices(array $filter = [], int $page = 0, ?string $sort = null): array
    {
        $params = array_merge($filter, [
            'page' => $page,
            'sort' => $sort ?: config('eoglasna.default_sort', 'datePublished,desc'),
        ]);

        return $this->requestJson('GET', '/api/v1/court-notice', $params);
    }

    public function getCourtNoticeByUuid(string $uuid): array
    {
        return $this->requestJson('GET', "/api/v1/court-notice/{$uuid}");
    }

    public function getCourtNoticesNaturalPersonBankruptcy(array $filter = [], int $page = 0, ?string $sort = null): array
    {
        $params = array_merge($filter, [
            'page' => $page,
            'sort' => $sort ?: config('eoglasna.default_sort', 'datePublished,desc'),
        ]);

        return $this->requestJson('GET', '/api/v1/court-notice/natural-person-bankruptcy', $params);
    }

    public function getCourtNoticesLegalPersonBankruptcy(array $filter = [], int $page = 0, ?string $sort = null): array
    {
        $params = array_merge($filter, [
            'page' => $page,
            'sort' => $sort ?: config('eoglasna.default_sort', 'datePublished,desc'),
        ]);

        return $this->requestJson('GET', '/api/v1/court-notice/legal-person-bankruptcy', $params);
    }

    public function getInstitutionNotices(array $filter = [], int $page = 0, ?string $sort = null): array
    {
        $params = array_merge($filter, [
            'page' => $page,
            'sort' => $sort ?: config('eoglasna.default_sort', 'datePublished,desc'),
        ]);

        return $this->requestJson('GET', '/api/v1/institution-notice', $params);
    }

    public function getInstitutionNoticeByUuid(string $uuid): array
    {
        return $this->requestJson('GET', "/api/v1/institution-notice/{$uuid}");
    }

    public function getCourts(): array
    {
        return $this->requestJson('GET', '/api/v1/court/all');
    }

    public function getExportList(): array
    {
        return $this->requestJson('GET', '/api/v1/export/list');
    }

    // Core request

    protected function requestJson(string $method, string $path, array $query = []): array
    {
        $this->throttle();

        // Build query with repeated params for arrays
        $url = $this->baseUrl . $path;
        $queryString = $this->buildQueryString($query);
        if ($queryString) {
            $url .= (str_contains($url, '?') ? '&' : '?') . $queryString;
        }

        $attempts = 0;
        $maxAttempts = 5;

        while (true) {
            $attempts++;

            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->send($method, $url);

            $this->markCall();

            if ($response->successful()) {
                // Convert JSON to array
                return $response->json();
            }

            if ($response->status() === 429) {
                $retryAfterMs = (int) $response->header('X-Rate-Limit-Retry-After-Milliseconds', 0);
                $retryAfterMs += random_int(0, $this->retryJitterMs);
                if ($retryAfterMs <= 0) {
                    $retryAfterMs = 1500;
                }

                if ($attempts < $maxAttempts) {
                    usleep($retryAfterMs * 1000);
                    continue;
                }

                throw new EoglasnaApiException(
                    'Too many requests (rate limited)',
                    429,
                    $response->json()
                );
            }

            if ($response->serverError()) {
                if ($attempts < $maxAttempts) {
                    // Linear backoff + jitter
                    $sleepMs = 500 * $attempts + random_int(0, $this->retryJitterMs);
                    usleep($sleepMs * 1000);
                    continue;
                }

                throw new EoglasnaApiException(
                    'Server error from e-Oglasna API',
                    $response->status(),
                    $response->json()
                );
            }

            if ($response->clientError()) {
                // 400/404 -> throw with details
                throw new EoglasnaApiException(
                    'Client error from e-Oglasna API',
                    $response->status(),
                    $response->json()
                );
            }

            // Unknown state
            throw new EoglasnaApiException(
                'Unexpected response from e-Oglasna API',
                $response->status(),
                $response->json()
            );
        }
    }

    protected function buildQueryString(array $query): string
    {
        $parts = [];

        foreach ($query as $key => $value) {
            if (is_array($value)) {
                // Emit repeated parameters: key=val1&key=val2
                foreach ($value as $v) {
                    if ($v === null || $v === '') continue;
                    $parts[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $v);
                }
            } elseif ($value !== null && $value !== '') {
                $parts[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
            }
        }

        return implode('&', $parts);
    }

    protected function throttle(): void
    {
        // Hour window guard
        $now = Carbon::now();
        if (!$this->hourWindowStart || $now->diffInHours($this->hourWindowStart) >= 1) {
            $this->hourWindowStart = $now;
            $this->requestsThisHour = 0;
        }

        $maxPerHour = (int) config('eoglasna.max_requests_per_hour', 950);
        if ($this->requestsThisHour >= $maxPerHour) {
            // Soft stop: sleep until hour window resets
            $sleepSec = 3600 - $now->diffInSeconds($this->hourWindowStart);
            $sleepSec = max($sleepSec, 1);
            sleep($sleepSec);
            $this->hourWindowStart = Carbon::now();
            $this->requestsThisHour = 0;
        }

        // Min inter-request delay
        if ($this->lastCallAt) {
            $elapsedMs = $now->diffInMilliseconds($this->lastCallAt);
            $toWait = $this->minDelayMs - $elapsedMs;
            if ($toWait > 0) {
                usleep($toWait * 1000);
            }
        }
    }

    protected function markCall(): void
    {
        $this->lastCallAt = Carbon::now();
        $this->requestsThisHour++;
    }
}
