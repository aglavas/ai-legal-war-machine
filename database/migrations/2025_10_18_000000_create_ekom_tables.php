<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ekom_predmeti', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('remote_id')->unique();
            $table->string('oznaka')->nullable();
            $table->string('status', 32)->nullable()->index();
            $table->unsignedBigInteger('sud_remote_id')->nullable()->index();
            $table->boolean('do_not_disturb')->default(false);
            $table->json('data')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ekom_podnesci', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('remote_id')->unique();
            $table->string('status', 32)->nullable()->index();
            $table->unsignedBigInteger('sud_remote_id')->nullable()->index();
            $table->unsignedBigInteger('vrsta_podneska_remote_id')->nullable()->index();
            $table->string('vrijeme_kreiranja')->nullable();
            $table->string('vrijeme_slanja')->nullable();
            $table->string('vrijeme_zaprimanja')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ekom_otpravci', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('remote_id')->unique();
            $table->string('status', 32)->nullable()->index();
            $table->unsignedBigInteger('predmet_remote_id')->nullable()->index();
            $table->string('vrijeme_slanja_sa_suda')->nullable();
            $table->string('vrijeme_potvrde_primitka')->nullable();
            $table->boolean('primljen_zbog_isteka_roka')->default(false);
            $table->json('data')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ekom_otpravci');
        Schema::dropIfExists('ekom_podnesci');
        Schema::dropIfExists('ekom_predmeti');
    }
};
