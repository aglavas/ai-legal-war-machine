<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('vizra-adk.tables.law_uploads', 'law_uploads');

        Schema::create($tableName, function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('doc_id')->index();

            // File storage metadata
            $table->string('disk')->default('local');
            $table->string('local_path');
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('sha256', 64)->nullable()->index();
            $table->string('source_url')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->string('status')->default('stored');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['doc_id', 'sha256']);
        });
    }

    public function down(): void
    {
        $tableName = config('vizra-adk.tables.law_uploads', 'law_uploads');
        Schema::dropIfExists($tableName);
    }
};

