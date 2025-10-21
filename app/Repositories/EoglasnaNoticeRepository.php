<?php

namespace App\Repositories;

use App\Models\EoglasnaNotice;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class EoglasnaNoticeRepository
{
    /**
     * @param array $payload
     * @return EoglasnaNotice
     */
    public function upsertFromApiPayload(array $payload): EoglasnaNotice
    {
        $uuid = Arr::get($payload, 'uuid');
        $notice = EoglasnaNotice::firstOrNew(['uuid' => $uuid]);

        $notice->public_url = Arr::get($payload, 'publicUrl');
        $notice->notice_documents_download_url = Arr::get($payload, 'noticeDocumentsDownloadUrl');
        $notice->notice_type = Arr::get($payload, 'noticeType');
        $notice->title = Arr::get($payload, 'title');

        $expirationDate = Arr::get($payload, 'expirationDate');
        $notice->expiration_date = $expirationDate ? Carbon::parse($expirationDate)->toDateString() : null;

        $datePublished = Arr::get($payload, 'datePublished');
        $notice->date_published = $datePublished ? Carbon::parse($datePublished) : null;

        $notice->notice_source_type = Arr::get($payload, 'noticeSourceType');

        // Participants / Documents
        $notice->participants = Arr::get($payload, 'participants', []);
        $notice->notice_documents = Arr::get($payload, 'noticeDocuments', []);

        // Court fields (if present)
        $court = Arr::get($payload, 'court', []);
        $notice->court_code = Arr::get($court, 'code');
        $notice->court_name = Arr::get($court, 'name');
        $notice->court_type = Arr::get($court, 'courtType');
        $notice->case_number = Arr::get($payload, 'caseNumber');
        $notice->case_type = Arr::get($payload, 'caseType');

        // Institution fields (if present)
        $institution = Arr::get($payload, 'institution', []);
        $notice->institution_name = Arr::get($institution, 'name');
        $notice->institution_notice_type = Arr::get($payload, 'institutionNoticeType');

        // Court notice details
        $notice->court_notice_details = Arr::get($payload, 'courtNoticeDetails');

        $notice->raw = $payload;

        $now = Carbon::now();
        if (!$notice->exists || !$notice->first_seen_at) {
            $notice->first_seen_at = $now;
        }
        $notice->last_seen_at = $now;

        $notice->save();

        return $notice;
    }
}
