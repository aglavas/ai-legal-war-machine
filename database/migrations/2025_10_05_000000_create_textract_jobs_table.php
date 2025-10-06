<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('textract_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('drive_file_id')->index();
            $t->string('drive_file_name');
            $t->string('s3_key')->nullable();
            $t->string('job_id')->nullable()->index();
            $t->string('status')->default('queued'); // queued|uploading|started|succeeded|failed
            $t->text('error')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('textract_jobs');
    }
};

