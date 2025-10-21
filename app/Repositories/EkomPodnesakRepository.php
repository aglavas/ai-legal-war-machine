<?php

namespace App\Repositories;

use App\Models\EkomPodnesak;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class EkomPodnesakRepository
{
    // For paged list mapping
    public function upsertFromPagedApi(array $item): EkomPodnesak
    {
        $remoteId = (int) $item['id'];
        $model = EkomPodnesak::query()->firstOrNew(['remote_id' => $remoteId]);

        $model->status = $item['status'] ?? $model->status;
        $model->sud_remote_id = Arr::get($item, 'sud.id');
        $model->vrsta_podneska_remote_id = Arr::get($item, 'vrstaPodneska.id');
        $model->vrijeme_kreiranja = Arr::get($item, 'vrijemeKreiranja');
        $model->vrijeme_slanja = Arr::get($item, 'vrijemeSlanja');
        $model->vrijeme_zaprimanja = Arr::get($item, 'vrijemeZaprimanja');
        $model->data = $item;
        $model->last_synced_at = Carbon::now();

        $model->save();
        return $model;
    }

    // For full podnesak detail mapping (if needed)
    public function upsertFromDetailApi(array $item): EkomPodnesak
    {
        return $this->upsertFromPagedApi($item);
    }
}
