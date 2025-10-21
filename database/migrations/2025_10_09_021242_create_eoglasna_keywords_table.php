<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('eoglasna_keywords', function (Blueprint $table) {
            $table->id();
            $table->string('query');
            $table->string('scope')->default('notice'); // 'notice'|'court'|'institution'|'court_legal_bankruptcy'|'court_natural_bankruptcy'
            $table->boolean('deep_scan')->default(false); // if true, allows deep scanning specifically for exact search mode via command
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('last_date_published')->nullable(); // cursor for incremental monitor
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['enabled', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eoglasna_keywords');
    }
};
