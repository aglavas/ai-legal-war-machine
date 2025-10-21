<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('eoglasna_osijek_monitoring', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('oib')->nullable();
            $table->string('street')->nullable();
            $table->integer('street_number')->nullable();
            $table->string('city')->nullable();
            $table->integer('zip')->nullable();
            $table->string('public_url')->nullable();
            $table->string('notice_documents_download_url')->nullable();

            $table->string('notice_type')->nullable(); // NOTICE, REPORT_NOTICE, INSTITUTION_NOTICE, COURT_NOTICE
            $table->string('title')->nullable();
            $table->date('expiration_date')->nullable();
            $table->timestamp('date_published')->nullable();
            $table->string('notice_source_type')->nullable(); // COURT, INSTITUTION, NOTARY_PUBLIC

            // Court-specific
            $table->string('court_code')->nullable();
            $table->string('court_name')->nullable();
            $table->string('court_type')->nullable();
            $table->string('case_number')->nullable();
            $table->string('case_type')->nullable(); // NATURAL_PERSON_BANKRUPTCY, LEGAL_PERSON_BANKRUPTCY, OTHER

            // Institution-specific
            $table->string('institution_name')->nullable();
            $table->string('institution_notice_type')->nullable(); // CALL_FOR_ADDRESS_SUBMISSION, OTHER

            $table->json('participants')->nullable();
            $table->json('notice_documents')->nullable();
            $table->json('court_notice_details')->nullable();
            $table->json('raw')->nullable();

            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();

            $table->index(['date_published']);
            $table->index(['notice_source_type']);
            $table->index(['case_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eoglasna_osijek_monitoring');
    }
};
