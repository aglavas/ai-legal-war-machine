<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('vizra-adk.tables.laws', 'laws');

        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            } catch (\Exception $e) {
                if (! app()->environment('testing')) {
                    throw $e;
                }
            }
        }

        Schema::create($tableName, function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Logical document/group identifier to group chunks from the same law
            $table->string('doc_id')->index();

            // Rich law metadata
            $table->string('title')->nullable()->index();
            $table->string('law_number')->nullable()->index();
            $table->string('jurisdiction')->nullable()->index();
            $table->string('country')->nullable();
            $table->string('language', 16)->nullable();
            $table->date('promulgation_date')->nullable();
            $table->date('effective_date')->nullable();
            $table->date('repeal_date')->nullable();
            $table->string('version')->nullable();
            $table->string('chapter')->nullable();
            $table->string('section')->nullable();
            $table->json('tags')->nullable();
            $table->string('source_url')->nullable();

            // Content and chunking
            $table->integer('chunk_index')->default(0);
            $table->text('content');
            $table->text('metadata')->nullable();

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

            $table->index(['doc_id', 'chunk_index']);
            $table->index(['embedding_provider', 'embedding_model']);
            $table->unique(['doc_id', 'content_hash']);
        });

        if (DB::connection()->getDriverName() === 'pgsql' && ! app()->environment('testing')) {
            try {
                DB::statement('CREATE INDEX laws_embedding_idx ON ' . $tableName . ' USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
            } catch (\Exception $e) {
                // ignore if pgvector/index not available
            }
        }
    }

    public function down(): void
    {
        $tableName = config('vizra-adk.tables.laws', 'laws');
        Schema::dropIfExists($tableName);
    }
};

