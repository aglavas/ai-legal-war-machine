<?php

namespace App\Repositories;

use App\Models\EkomOtpravak;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class EkomOtpravakRepository
{
    public function upsertFromPagedApi(array $item): EkomOtpravak
    {
        $remoteId = (int) $item['id'];
        $model = EkomOtpravak::query()->firstOrNew(['remote_id' => $remoteId]);

        $model->status = $item['status'] ?? $model->status;
        $model->predmet_remote_id = Arr::get($item, 'predmet.id');
        $model->vrijeme_slanja_sa_suda = Arr::get($item, 'vrijemeSlanjaSaSuda');
        $model->vrijeme_potvrde_primitka = Arr::get($item, 'vrijemePotvrdePrimitka');
        $model->primljen_zbog_isteka_roka = (bool) ($item['primljenZbogIstekaRoka'] ?? false);
        $model->data = $item;
        $model->last_synced_at = Carbon::now();

        $model->save();
        return $model;
    }
}
