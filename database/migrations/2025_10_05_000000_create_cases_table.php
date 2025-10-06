<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('vizra-adk.tables.cases', 'cases');

        Schema::create($tableName, function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('case_number')->nullable()->index();
            $table->string('title')->nullable();
            $table->string('client_name')->nullable();
            $table->string('opponent_name')->nullable();
            $table->string('court')->nullable()->index();
            $table->string('jurisdiction')->nullable()->index();
            $table->string('judge')->nullable();
            $table->date('filing_date')->nullable();
            $table->string('status')->nullable()->index();
            $table->json('tags')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['case_number', 'court']);
        });
    }

    public function down(): void
    {
        $tableName = config('vizra-adk.tables.cases', 'cases');
        Schema::dropIfExists($tableName);
    }
};

