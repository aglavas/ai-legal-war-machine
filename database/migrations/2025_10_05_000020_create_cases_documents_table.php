<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $casesTable = config('vizra-adk.tables.cases', 'cases');
        $uploadsTable = config('vizra-adk.tables.cases_documents_uploads', 'cases_documents_uploads');
        $tableName = config('vizra-adk.tables.cases_documents', 'cases_documents');

        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            } catch (\Exception $e) {
                if (! app()->environment('testing')) {
                    throw $e;
                }
            }
        }

        Schema::create($tableName, function (Blueprint $table) use ($casesTable, $uploadsTable) {
            $table->ulid('id')->primary();
            $table->ulid('case_id');
            $table->string('doc_id')->index();
            $table->ulid('upload_id')->nullable();

            // Optional descriptive metadata
            $table->string('title')->nullable();
            $table->string('category')->nullable();
            $table->string('author')->nullable();
            $table->string('language', 16)->nullable();
            $table->json('tags')->nullable();

            // Content and chunking
            $table->integer('chunk_index')->default(0);
            $table->text('content');
            $table->text('metadata')->nullable();

            // Source info
            $table->string('source')->nullable();
            $table->string('source_id')->nullable();

            // Embedding metadata
            $table->string('embedding_provider');
            $table->string('embedding_model');
            $table->integer('embedding_dimensions');

            if (DB::connection()->getDriverName() === 'pgsql') {
                $table->addColumn('vector', 'embedding', ['dimensions' => 1536]);
            } else {
                $table->json('embedding_vector');
            }

            $table->float('embedding_norm')->nullable();
            $table->string('content_hash', 64)->index();
            $table->integer('token_count')->nullable();
            $table->timestamps();

            $table->index(['case_id']);
            $table->index(['case_id', 'doc_id', 'chunk_index']);
            $table->index(['embedding_provider', 'embedding_model']);
            $table->unique(['case_id', 'content_hash']);

            $table->foreign('case_id')->references('id')->on($casesTable)->cascadeOnDelete();
            $table->foreign('upload_id')->references('id')->on($uploadsTable)->nullOnDelete();
        });

        if (DB::connection()->getDriverName() === 'pgsql' && ! app()->environment('testing')) {
            try {
                DB::statement('CREATE INDEX cases_documents_embedding_idx ON ' . $tableName . ' USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
            } catch (\Exception $e) {
                // ignore when vector index cannot be created
            }
        }
    }

    public function down(): void
    {
        $tableName = config('vizra-adk.tables.cases_documents', 'cases_documents');
        Schema::dropIfExists($tableName);
    }
};

