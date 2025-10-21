<?php

namespace App\Services;

use App\Repositories\EoglasnaNoticeRepository;
use App\Repositories\EoglasnaKeywordRepository;
use App\Repositories\EoglasnaKeywordMatchRepository;
use App\Services\Eoglasna\Api\EoglasnaHttpClient;
use App\Repositories\EoglasnaOsijekMonitoringRepository;
use App\Models\EoglasnaKeyword;
use App\Models\EoglasnaCourt;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class EoglasnaService
{
    /**
     * @param EoglasnaHttpClient $client
     * @param EoglasnaNoticeRepository $noticeRepo
     * @param EoglasnaKeywordRepository $keywordRepo
     * @param EoglasnaKeywordMatchRepository $matchRepo
     * @param EoglasnaOsijekMonitoringRepository $osijekRepo
     */
    public function __construct(
        protected EoglasnaHttpClient $client,
        protected EoglasnaNoticeRepository $noticeRepo,
        protected EoglasnaKeywordRepository $keywordRepo,
        protected EoglasnaKeywordMatchRepository $matchRepo,
        protected EoglasnaOsijekMonitoringRepository $osijekRepo,
    ) {}

    /**
     * @return void
     */
    public function monitorKeywords(): void
    {
        $keywords = $this->keywordRepo->getEnabled();

        foreach ($keywords as $keyword) {
            $this->monitorSingleKeyword($keyword);
        }
    }

    /**
     * @param EoglasnaKeyword $keyword
     * @return void
     */
    protected function monitorSingleKeyword(EoglasnaKeyword $keyword): void
    {
        $query = trim($keyword->query);
        if ($query === '') {
            return;
        }

        $this->keywordRepo->touchRun($keyword);

        $page = 0;
        $newestDatePublished = $keyword->last_date_published; // track latest in this run
        $hasMore = true;

        while ($hasMore) {
            $filter = ['text' => $query];
            $response = $this->callNoticeEndpointForScope($keyword->scope, $filter, $page);
            $content = Arr::get($response, 'content', []);
            if (empty($content)) {
                break;
            }

            foreach ($content as $payload) {
                // Stop if older or equal than last cursor
                $datePublished = Arr::get($payload, 'datePublished');
                $datePublishedTs = $datePublished ? Carbon::parse($datePublished) : null;

                if ($keyword->last_date_published && $datePublishedTs && $datePublishedTs->lessThanOrEqualTo($keyword->last_date_published)) {
                    // reached already-processed zone; end this keyword scanning
                    $hasMore = false;
                    break;
                }

                $notice = $this->noticeRepo->upsertFromApiPayload($payload);

                // Record match metadata
                $matchedFields = $this->detectMatchFields($payload, $query);
                $this->matchRepo->recordMatch($keyword->id, $notice->uuid, $matchedFields);

                if (!$newestDatePublished || ($datePublishedTs && $datePublishedTs->greaterThan($newestDatePublished))) {
                    $newestDatePublished = $datePublishedTs;
                }
            }

            // Next page if still has more
            if ($hasMore) {
                $page++;
                $current = (int) Arr::get($response, 'number', $page - 1);
                $totalPages = (int) Arr::get($response, 'totalPages', $page);
                if ($page >= $totalPages) {
                    $hasMore = false;
                }
            }
        }

        // Update cursor
        if ($newestDatePublished && (!$keyword->last_date_published || $newestDatePublished->greaterThan($keyword->last_date_published))) {
            $this->keywordRepo->updateCursor($keyword, $newestDatePublished);
        }
    }

    /**
     * @param string $scope
     * @param array $filter
     * @param int $page
     * @return array
     */
    protected function callNoticeEndpointForScope(string $scope, array $filter, int $page): array
    {
        $sort = config('eoglasna.default_sort', 'datePublished,desc');

        return match ($scope) {
            'court' => $this->client->getCourtNotices($filter, $page, $sort),
            'institution' => $this->client->getInstitutionNotices($filter, $page, $sort),
            'court_legal_bankruptcy' => $this->client->getCourtNoticesLegalPersonBankruptcy($filter, $page, $sort),
            'court_natural_bankruptcy' => $this->client->getCourtNoticesNaturalPersonBankruptcy($filter, $page, $sort),
            default => $this->client->getNotices($filter, $page, $sort), // 'notice' (all)
        };
    }

    /**
     * @param array $payload
     * @param string $query
     * @return array
     */
    protected function detectMatchFields(array $payload, string $query): array
    {
        $q = mb_strtolower($query);
        $fields = [];

        $title = (string) Arr::get($payload, 'title');
        if ($title && mb_stripos($title, $q) !== false) {
            $fields[] = 'title';
        }

        $caseNumber = (string) Arr::get($payload, 'caseNumber');
        if ($caseNumber && mb_stripos($caseNumber, $q) !== false) {
            $fields[] = 'caseNumber';
        }

        $participants = Arr::get($payload, 'participants', []);
        foreach ($participants as $p) {
            $titles = (string) Arr::get($p, 'titles');
            if ($titles && mb_stripos($titles, $q) !== false) {
                $fields[] = 'participants.titles';
                break;
            }
        }

        if (empty($fields)) {
            $fields[] = 'api.text';
        }

        return ['fields' => array_values(array_unique($fields))];
    }

    /**
     * @param string $term
     * @param string $scope
     * @return int
     */
    public function deepScanExact(string $term, string $scope = 'notice'): int
    {
        $term = trim($term);
        if ($term === '') {
            return 0;
        }

        $page = 0;
        $countSaved = 0;
        $maxPages = (int) config('eoglasna.deep_scan_max_pages', 500);
        $sort = config('eoglasna.default_sort', 'datePublished,desc');

        while (true) {
            $resp = $this->callNoticeEndpointForScope($scope, ['text' => $term], $page);
            $content = Arr::get($resp, 'content', []);
            if (empty($content)) {
                break;
            }

            foreach ($content as $payload) {
                // Local exact phrase match (case-insensitive) across key fields
                if ($this->isExactMatch($payload, $term)) {
                    $this->noticeRepo->upsertFromApiPayload($payload);
                    $countSaved++;
                }
            }

            $current = (int) Arr::get($resp, 'number', $page);
            $totalPages = (int) Arr::get($resp, 'totalPages', $page + 1);
            $page++;

            if ($page >= $totalPages) {
                break;
            }
            if ($page >= $maxPages) {
                Log::warning("deepScanExact reached configured page cap", ['scope' => $scope, 'term' => $term, 'page' => $page]);
                break;
            }
        }

        return $countSaved;
    }

    /**
     * @param array $payload
     * @param string $term
     * @return bool
     */
    protected function isExactMatch(array $payload, string $term): bool
    {
        $needle = mb_strtolower($term);

        $fields = [];
        $fields[] = (string) Arr::get($payload, 'title');
        $fields[] = (string) Arr::get($payload, 'caseNumber');

        $participants = Arr::get($payload, 'participants', []);
        foreach ($participants as $p) {
            $fields[] = (string) Arr::get($p, 'titles');
        }

        foreach ($fields as $f) {
            if ($f && (mb_stripos(mb_strtolower($f), $needle) !== false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return int
     */
    public function syncCourts(): int
    {
        $list = $this->client->getCourts();
        $count = 0;
        foreach ($list as $court) {
            $code = Arr::get($court, 'code');
            $name = Arr::get($court, 'name');
            $type = Arr::get($court, 'courtType');

            if (!$code) continue;

            \App\Models\EoglasnaCourt::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'court_type' => $type]
            );
            $count++;
        }
        return $count;
    }

    /**
     * Fetch and upsert ALL court notices for Općinski sud u Osijeku into eoglasna_osijek_monitoring.
     * Always scans all pages (no cursor), respecting API throttling.
     *
     * @return int Number of records upserted in this run
     */
    public function monitorOsijekCourtAll(): int
    {
        // Try to locate court code locally first
        $court = EoglasnaCourt::query()
            ->where('name', 'like', '%Općinski%')
            ->where('name', 'like', '%Osijek%')
            ->first();

        if (!$court) {
            // Fallback: sync courts from API, then search again
            try {
                $this->syncCourts();
            } catch (\Throwable $e) {
                Log::warning('Failed to sync courts for Osijek monitoring', ['error' => $e->getMessage()]);
            }
            $court = EoglasnaCourt::query()
                ->where('name', 'like', '%Općinski%')
                ->where('name', 'like', '%Osijek%')
                ->first();
        }

        if (!$court) {
            throw new \RuntimeException('Osijek municipal court not found in court registry.');
        }

        $code = $court->code;
        $page = 0;
        $totalUpserted = 0;
        $sort = config('eoglasna.default_sort', 'datePublished,desc');

        while (true) {
            $resp = $this->client->getCourtNotices(['courtCode' => [$code]], $page, $sort);
            $content = Arr::get($resp, 'content', []);
            if (empty($content)) {
                break;
            }

            foreach ($content as $payload) {
                $this->osijekRepo->upsertFromApiPayload($payload);
                $totalUpserted++;
            }

            $current = (int) Arr::get($resp, 'number', $page);
            $totalPages = (int) Arr::get($resp, 'totalPages', $page + 1);
            $page++;
            if ($page >= $totalPages) {
                break;
            }
        }

        return $totalUpserted;
    }
}
