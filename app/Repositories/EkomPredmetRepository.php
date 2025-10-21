<?php

namespace App\Repositories;

use App\Models\EkomPredmet;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class EkomPredmetRepository
{
    public function upsertFromApi(array $predmet): EkomPredmet
    {
        $remoteId = (int) $predmet['id'];
        $model = EkomPredmet::query()->firstOrNew(['remote_id' => $remoteId]);

        $model->oznaka = $predmet['oznaka'] ?? $model->oznaka;
        $model->status = $predmet['status'] ?? $model->status;
        $model->sud_remote_id = Arr::get($predmet, 'sud.id');
        $model->do_not_disturb = (bool) ($predmet['doNotDisturb'] ?? false);
        $model->data = $predmet;
        $model->last_synced_at = Carbon::now();
        $model->save();

        return $model;
    }
}
