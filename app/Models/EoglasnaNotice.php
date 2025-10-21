<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EoglasnaNotice extends Model
{
    protected $table = 'eoglasna_notices';

    protected $fillable = [
        'uuid',
        'public_url',
        'notice_documents_download_url',
        'notice_type',
        'title',
        'expiration_date',
        'date_published',
        'notice_source_type',
        'court_code',
        'court_name',
        'court_type',
        'case_number',
        'case_type',
        'institution_name',
        'institution_notice_type',
        'participants',
        'notice_documents',
        'court_notice_details',
        'raw',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'participants' => 'array',
        'notice_documents' => 'array',
        'court_notice_details' => 'array',
        'raw' => 'array',
        'expiration_date' => 'date',
        'date_published' => 'datetime',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];
}
