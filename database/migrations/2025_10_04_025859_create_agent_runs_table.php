<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('agent_runs', function (Blueprint $table) {
            $table->id();
            $table->text('objective');
            $table->json('context');                 // korisnički kontekst (npr. podaci o predmetu)
            $table->json('iterations')->default('[]'); // niz iteracija sa draftovima i feedbackom
            $table->string('status')->default('running'); // running|completed|failed
            $table->unsignedInteger('current_iteration')->default(0);
            $table->float('score')->default(0);
            $table->unsignedInteger('max_iterations')->default(6);
            $table->float('threshold')->default(0.85);
            $table->longText('final_output')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('agent_runs');
    }
};
