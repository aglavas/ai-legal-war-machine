<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Casts\JsonUnescaped;

class EoglasnaOsijekMonitoring extends Model
{
    protected $table = 'eoglasna_osijek_monitoring';

    protected $fillable = [
        'uuid',
        'name',
        'last_name',
        'oib',
        'street',
        'street_number',
        'city',
        'zip',
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
        'participants' => JsonUnescaped::class,
        'notice_documents' => 'array',
        'court_notice_details' => 'array',
        'raw' => 'array',
        'date_published' => 'datetime',
        'expiration_date' => 'date',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        // Numeric casts for parsed participant columns
        'street_number' => 'integer',
        'zip' => 'integer',
    ];
}
