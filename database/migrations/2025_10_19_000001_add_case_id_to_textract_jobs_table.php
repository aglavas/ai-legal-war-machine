<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('textract_jobs', function (Blueprint $t) {
            $t->string('case_id')->nullable()->index()->after('drive_file_name');
        });
    }
    public function down(): void {
        Schema::table('textract_jobs', function (Blueprint $t) {
            $t->dropColumn('case_id');
        });
    }
};

