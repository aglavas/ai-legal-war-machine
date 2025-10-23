<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('title')->nullable();
            $table->string('agent_type')->nullable(); // odluke, general, law, etc.
            $table->json('metadata')->nullable(); // flexible storage for context, settings

            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Query optimization indexes
            $table->index(['user_id', 'last_message_at']); // List user conversations sorted by activity
            $table->index(['agent_type', 'created_at']); // Filter by agent type
            $table->index(['created_at']); // Chronological queries
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
