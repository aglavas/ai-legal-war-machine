<?php

namespace App\Repositories;

use App\Models\EoglasnaKeyword;
use Carbon\Carbon;

class EoglasnaKeywordRepository
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function getEnabled(): \Illuminate\Support\Collection
    {
        return EoglasnaKeyword::query()
            ->where('enabled', true)
            ->orderBy('id')
            ->get();
    }

    /**
     * @param EoglasnaKeyword $keyword
     * @return void
     */
    public function touchRun(EoglasnaKeyword $keyword): void
    {
        $keyword->last_run_at = Carbon::now();
        $keyword->save();
    }

    /**
     * @param EoglasnaKeyword $keyword
     * @param Carbon|null $lastDatePublished
     * @return void
     */
    public function updateCursor(EoglasnaKeyword $keyword, ?\Carbon\Carbon $lastDatePublished): void
    {
        if ($lastDatePublished) {
            $keyword->last_date_published = $lastDatePublished;
            $keyword->save();
        }
    }
}
