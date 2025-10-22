<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * Add metadata column to store extracted document metadata from OCR analysis.
     */
    public function up(): void
    {
        Schema::table('textract_jobs', function (Blueprint $t) {
            // JSON column to store comprehensive document metadata
            $t->json('metadata')->nullable()->after('error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('textract_jobs', function (Blueprint $t) {
            $t->dropColumn('metadata');
        });
    }
};
