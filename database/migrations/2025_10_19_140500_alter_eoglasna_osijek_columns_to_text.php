<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Postgres-specific: widen potentially long fields to TEXT to avoid 255-char truncation.
        DB::statement('ALTER TABLE eoglasna_osijek_monitoring ALTER COLUMN title TYPE text');
        DB::statement('ALTER TABLE eoglasna_osijek_monitoring ALTER COLUMN public_url TYPE text');
        DB::statement('ALTER TABLE eoglasna_osijek_monitoring ALTER COLUMN notice_documents_download_url TYPE text');
    }

    public function down(): void
    {
        // Best-effort down: convert back to varchar(255) for the two URL fields; title may overflow, so we keep it text to avoid data loss
        DB::statement("ALTER TABLE eoglasna_osijek_monitoring ALTER COLUMN public_url TYPE varchar(255)");
        DB::statement("ALTER TABLE eoglasna_osijek_monitoring ALTER COLUMN notice_documents_download_url TYPE varchar(255)");
        // Note: skipping reverting 'title' to varchar(255) to prevent truncation of existing data.
    }
};

