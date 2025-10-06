<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $lawsTable = config('vizra-adk.tables.laws', 'laws');
        $ingestedTable = config('vizra-adk.tables.ingested_laws', 'ingested_laws');

        if (Schema::hasTable($lawsTable) && !Schema::hasColumn($lawsTable, 'ingested_law_id')) {
            Schema::table($lawsTable, function (Blueprint $table) use ($ingestedTable) {
                $table->ulid('ingested_law_id')->nullable()->after('id');
                $table->index('ingested_law_id');
                $table->foreign('ingested_law_id')->references('id')->on($ingestedTable)->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $lawsTable = config('vizra-adk.tables.laws', 'laws');

        if (Schema::hasTable($lawsTable) && Schema::hasColumn($lawsTable, 'ingested_law_id')) {
            Schema::table($lawsTable, function (Blueprint $table) {
                $table->dropForeign(['ingested_law_id']);
                $table->dropIndex(['ingested_law_id']);
                $table->dropColumn('ingested_law_id');
            });
        }
    }
};

