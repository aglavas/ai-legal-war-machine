<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EkomOtpravak extends Model
{
    protected $table = 'ekom_otpravci';

    protected $fillable = [
        'remote_id',
        'status',
        'predmet_remote_id',
        'vrijeme_slanja_sa_suda',
        'vrijeme_potvrde_primitka',
        'primljen_zbog_isteka_roka',
        'data',
        'last_synced_at',
    ];

    protected $casts = [
        'data' => 'array',
        'primljen_zbog_isteka_roka' => 'boolean',
        'last_synced_at' => 'datetime',
    ];
}
