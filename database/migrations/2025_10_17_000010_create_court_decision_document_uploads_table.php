<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $courtDecisionsTable = config('vizra-adk.tables.court_decisions', 'court_decisions');
        $tableName = config('vizra-adk.tables.court_decision_document_uploads', 'court_decision_document_uploads');

        Schema::create($tableName, function (Blueprint $table) use ($courtDecisionsTable) {
            $table->ulid('id')->primary();
            $table->ulid('decision_id');
            $table->string('doc_id')->index();

            // File storage metadata
            $table->string('disk')->default('local');
            $table->string('local_path');
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('sha256', 64)->nullable()->index();
            $table->string('source_url')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->string('status')->default('stored');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['decision_id', 'doc_id']);
            $table->foreign('decision_id')->references('id')->on($courtDecisionsTable)->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $tableName = config('vizra-adk.tables.court_decision_document_uploads', 'court_decision_document_uploads');
        Schema::dropIfExists($tableName);
    }
};

