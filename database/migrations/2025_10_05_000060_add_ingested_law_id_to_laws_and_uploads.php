<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $uploadsTable = config('vizra-adk.tables.law_uploads', 'law_uploads');
        $ingestedTable = config('vizra-adk.tables.ingested_laws', 'ingested_laws');

        if (Schema::hasTable($uploadsTable) && !Schema::hasColumn($uploadsTable, 'ingested_law_id')) {
            Schema::table($uploadsTable, function (Blueprint $table) use ($ingestedTable) {
                $table->ulid('ingested_law_id')->nullable()->after('id');
                $table->index('ingested_law_id');
                $table->foreign('ingested_law_id')->references('id')->on($ingestedTable)->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $uploadsTable = config('vizra-adk.tables.law_uploads', 'law_uploads');

        if (Schema::hasTable($uploadsTable) && Schema::hasColumn($uploadsTable, 'ingested_law_id')) {
            Schema::table($uploadsTable, function (Blueprint $table) {
                $table->dropForeign(['ingested_law_id']);
                $table->dropIndex(['ingested_law_id']);
                $table->dropColumn('ingested_law_id');
            });
        }
    }
};
