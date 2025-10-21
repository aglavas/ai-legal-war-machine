<?php

namespace App\Repositories;

use App\Models\EoglasnaOsijekMonitoring;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class EoglasnaOsijekMonitoringRepository
{
    /**
     * Upsert a monitoring record from e-Oglasna API payload.
     */
    public function upsertFromApiPayload(array $payload): EoglasnaOsijekMonitoring
    {
        $uuid = Arr::get($payload, 'uuid');
        $model = EoglasnaOsijekMonitoring::firstOrNew(['uuid' => $uuid]);

        // Basic notice fields
        $model->public_url = Arr::get($payload, 'publicUrl');
        $model->notice_documents_download_url = Arr::get($payload, 'noticeDocumentsDownloadUrl');
        $model->notice_type = Arr::get($payload, 'noticeType');
        $model->title = Arr::get($payload, 'title');

        $expirationDate = Arr::get($payload, 'expirationDate');
        $model->expiration_date = $expirationDate ? Carbon::parse($expirationDate)->toDateString() : null;

        $datePublished = Arr::get($payload, 'datePublished');
        $model->date_published = $datePublished ? Carbon::parse($datePublished) : null;

        $model->notice_source_type = Arr::get($payload, 'noticeSourceType');

        // Participants / Documents (normalized)
        $participants = Arr::get($payload, 'participants', []);
        if (!is_null($participants)) {
            $participants = $this->normalizeParticipants($participants);
        } else {
            $participants = [];
        }

        $model->participants = $participants;

        $model->notice_documents = Arr::get($payload, 'noticeDocuments', []);

        // Court fields (if present)
        $court = Arr::get($payload, 'court', []);
        $model->court_code = Arr::get($court, 'code');
        $model->court_name = Arr::get($court, 'name');
        $model->court_type = Arr::get($court, 'courtType');
        $model->case_number = Arr::get($payload, 'caseNumber');
        $model->case_type = Arr::get($payload, 'caseType');

        // Institution fields (if present)
        $institution = Arr::get($payload, 'institution', []);
        $model->institution_name = Arr::get($institution, 'name');
        $model->institution_notice_type = Arr::get($payload, 'institutionNoticeType');

        // Court notice details
        $model->court_notice_details = Arr::get($payload, 'courtNoticeDetails');

        $model->raw = $payload;

        $now = Carbon::now();
        if (!$model->exists || !$model->first_seen_at) {
            $model->first_seen_at = $now;
        }
        $model->last_seen_at = $now;

        // Extract dedicated columns from first relevant participant
        $this->fillParticipantColumns($model, $participants);

        $model->save();

        return $model;
    }

    /**
     * Normalize participant array: decode unicode escapes, trim, and normalize to NFC.
     * Also ensures slashes are clean ("/" instead of " \/ ").
     */
    protected function normalizeParticipants(array $participants): array
    {
        return array_map(function ($p) {
            if (!is_array($p)) return $p;
            $out = [];
            foreach ($p as $k => $v) {
                if (is_string($v)) {
                    $nv = $this->decodeUnicodeEscapes($v);
                    // collapse escaped separators
                    $nv = str_replace(' \/ ', ' / ', $nv);
                    $nv = str_replace('\/', '/', $nv);
                    $nv = trim($nv);
                    // Normalize if intl extension is available
                    if (class_exists('Normalizer')) {
                        /** @var \Normalizer $norm */
                        $nv = \Normalizer::normalize($nv, \Normalizer::FORM_C) ?: $nv;
                    }
                    $out[$k] = $nv;
                } else {
                    $out[$k] = $v;
                }
            }
            return $out;
        }, $participants);
    }

    /**
     * Decode literal \uXXXX sequences in a string to UTF-8 characters.
     */
    protected function decodeUnicodeEscapes(string $s): string
    {
        // Fast path: if no \uXXXX sequences, return as-is
        if (strpos($s, '\\u') === false) return $s;
        return preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($m) {
            $code = $m[1];
            $bin = pack('H*', $code);
            return mb_convert_encoding($bin, 'UTF-8', 'UCS-2BE');
        }, $s) ?? $s;
    }

    /**
     * Fill model's dedicated participant columns based on first relevant participant.
     * NATURAL_PERSON: name -> name, surname -> last_name. LEGAL_PERSON: name goes to name, last_name empty.
     * In both cases parse address for street, number, zip, city and set oib if provided.
     */
    protected function fillParticipantColumns(EoglasnaOsijekMonitoring $model, array $participants): void
    {
        if (empty($participants)) return;

        // Prefer NATURAL_PERSON, else LEGAL_PERSON, else first
        $chosen = collect($participants)
            ->first(fn($p) => is_array($p) && ($p['participantType'] ?? null) === 'NATURAL_PERSON')
            ?? collect($participants)->first(fn($p) => is_array($p) && ($p['participantType'] ?? null) === 'LEGAL_PERSON')
            ?? (is_array($participants[0] ?? null) ? $participants[0] : null);

        if (!is_array($chosen)) return;

        $ptype = $chosen['participantType'] ?? null;
        $name = (string)($chosen['name'] ?? '');
        $surname = (string)($chosen['surname'] ?? '');
        $fullName = (string)($chosen['fullName'] ?? '');
        $companyName = (string)($chosen['companyName'] ?? '');

        if ($ptype === 'LEGAL_PERSON') {
            // Company name goes into first name column, last name stays null
            $model->name = $name !== '' ? $name : ($companyName !== '' ? $companyName : ($fullName !== '' ? $fullName : null));
            $model->last_name = null;
        } else {
            // NATURAL_PERSON or default
            $model->name = $name !== '' ? $name : null;
            $model->last_name = $surname !== '' ? $surname : null;
            // If missing split from fullName as a fallback
            if ((!$model->name || !$model->last_name) && $fullName !== '') {
                [$fn, $ln] = $this->splitFullName($fullName);
                $model->name = $model->name ?: $fn;
                $model->last_name = $model->last_name ?: $ln;
            }
        }

        $oib = (string)($chosen['oib'] ?? '');
        $model->oib = $oib !== '' ? preg_replace('/[^0-9]/', '', $oib) : null;

        // Address parsing
        $addr = (string)($chosen['address'] ?? '');
        if ($addr !== '') {
            $parsed = $this->parseAddress($addr);
            $model->street = $parsed['street'] ?? null;
            $model->street_number = $parsed['street_number_int'] ?? null;
            $model->city = $parsed['city'] ?? null;
            $model->zip = $parsed['zip_int'] ?? null;
        }
    }

    /**
     * Split a full name into [first, last] by the last space.
     */
    protected function splitFullName(string $fullName): array
    {
        $fullName = trim(preg_replace('/\s+/', ' ', $fullName) ?: '');
        if ($fullName === '') return ['', ''];
        $pos = strrpos($fullName, ' ');
        if ($pos === false) return [$fullName, ''];
        return [substr($fullName, 0, $pos), substr($fullName, $pos + 1)];
    }

    /**
     * Parse addresses like "Ul. Radoslava Lopašića 6, 10000 Zagreb, Hrvatska"
     * Returns: street, street_number, street_number_int, zip, zip_int, city, country
     */
    protected function parseAddress(string $address): array
    {
        $res = [
            'street' => null,
            'street_number' => null,
            'street_number_int' => null,
            'zip' => null,
            'zip_int' => null,
            'city' => null,
            'country' => null,
        ];

        // Normalize whitespace and commas
        $addr = trim(preg_replace('/\s+/', ' ', $address) ?: '');
        $parts = array_map('trim', array_filter(explode(',', $addr)));

        // First part: street + number (if present)
        if (!empty($parts[0])) {
            $p0 = $parts[0];
            // Try to split trailing number from street name
            if (preg_match('/^(.*?)(?:\s+(\d+[A-Za-z\-\/]*))$/u', $p0, $m)) {
                $res['street'] = trim($m[1]);
                $res['street_number'] = $m[2];
            } else {
                $res['street'] = $p0;
            }
        }

        // Second part: might be ZIP and city like "10000 Zagreb"
        if (!empty($parts[1])) {
            $p1 = $parts[1];
            if (preg_match('/^(\d{4,5})\s+(.+)$/u', $p1, $m)) {
                $res['zip'] = $m[1];
                $res['city'] = trim($m[2]);
            } else {
                // If no zip, maybe it's just city
                $res['city'] = $p1;
            }
        }

        // Third part: country (optional)
        if (!empty($parts[2])) {
            $res['country'] = $parts[2];
        }

        // Integers
        if (!empty($res['street_number'])) {
            if (preg_match('/^(\d+)/', $res['street_number'], $mm)) {
                $res['street_number_int'] = (int) $mm[1];
            }
        }
        if (!empty($res['zip'])) {
            if (preg_match('/^(\d{4,5})$/', $res['zip'], $mm)) {
                $res['zip_int'] = (int) $mm[1];
            }
        }

        return $res;
    }
}
