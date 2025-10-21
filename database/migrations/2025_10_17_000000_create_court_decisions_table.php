<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('vizra-adk.tables.court_decisions', 'court_decisions');

        Schema::create($tableName, function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('case_number')->nullable()->index();
            $table->string('title')->nullable();
            $table->string('court')->nullable()->index();
            $table->string('jurisdiction')->nullable()->index();
            $table->string('judge')->nullable();
            $table->date('decision_date')->nullable();
            $table->date('publication_date')->nullable();
            $table->string('decision_type')->nullable()->index(); // vrsta_odluke
            $table->string('register')->nullable(); // upisnik
            $table->string('finality')->nullable(); // pravomocnost
            $table->string('ecli')->nullable()->index(); // European Case Law Identifier
            $table->json('tags')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['case_number', 'court']);
            $table->index(['decision_date']);
            $table->index(['publication_date']);
        });
    }

    public function down(): void
    {
        $tableName = config('vizra-adk.tables.court_decisions', 'court_decisions');
        Schema::dropIfExists($tableName);
    }
};
