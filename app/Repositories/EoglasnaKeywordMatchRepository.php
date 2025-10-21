<?php

namespace App\Repositories;

use App\Models\EoglasnaKeywordMatch;
use Carbon\Carbon;

class EoglasnaKeywordMatchRepository
{
    /**
     * @param int $keywordId
     * @param string $noticeUuid
     * @param array $metadata
     * @return EoglasnaKeywordMatch
     */
    public function recordMatch(int $keywordId, string $noticeUuid, array $metadata = []): EoglasnaKeywordMatch
    {
        // Avoid duplicate matches: we can firstOrCreate by (keyword_id, notice_uuid)
        $match = EoglasnaKeywordMatch::firstOrNew([
            'keyword_id' => $keywordId,
            'notice_uuid' => $noticeUuid,
        ]);

        if (!$match->exists) {
            $match->matched_at = Carbon::now();
        }
        $match->matched_fields = $metadata ?: $match->matched_fields;
        $match->save();

        return $match;
    }
}
