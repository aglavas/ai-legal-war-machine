<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('vizra-adk.tables.ingested_laws', 'ingested_laws');

        Schema::create($tableName, function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('doc_id')->unique();

            // Basic identifiers
            $table->string('title')->nullable();
            $table->string('law_number')->nullable()->index();
            $table->string('jurisdiction')->nullable()->index();
            $table->string('country')->nullable();
            $table->string('language', 16)->nullable();

            // Provenance
            $table->string('source_url')->nullable()->index();

            // Aliases and keywords
            $table->json('aliases')->nullable();
            $table->json('keywords')->nullable();
            $table->string('keywords_text')->nullable()->index();

            // Free-form metadata
            $table->json('metadata')->nullable();

            // Timestamps
            $table->timestamp('ingested_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $tableName = config('vizra-adk.tables.ingested_laws', 'ingested_laws');
        Schema::dropIfExists($tableName);
    }
};

