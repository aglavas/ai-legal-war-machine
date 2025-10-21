<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('eoglasna_keyword_matches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('keyword_id');
            $table->uuid('notice_uuid');
            $table->timestamp('matched_at')->nullable();
            $table->json('matched_fields')->nullable(); // metadata: where it matched (title, participants, etc.)
            $table->timestamps();

            $table->foreign('keyword_id')->references('id')->on('eoglasna_keywords')->onDelete('cascade');
            $table->index(['keyword_id', 'notice_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eoglasna_keyword_matches');
    }
};
