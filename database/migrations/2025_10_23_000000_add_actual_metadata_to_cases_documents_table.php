<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add 'actual' column to store comprehensive legal metadata extracted via LegalMetadataExtractor.
     * This provides the same rich metadata as TextractJob, including citations, courts, parties,
     * document classification, and content analysis.
     */
    public function up(): void
    {
        $tableName = config('vizra-adk.tables.cases_documents', 'cases_documents');

        Schema::table($tableName, function (Blueprint $table) {
            // JSON column for comprehensive legal metadata (citations, courts, parties, etc.)
            $table->json('actual')->nullable()->after('metadata');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('vizra-adk.tables.cases_documents', 'cases_documents');

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('actual');
        });
    }
};
