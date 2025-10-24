<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('chat_conversation_id')->constrained()->cascadeOnDelete();

            $table->enum('role', ['system', 'user', 'assistant', 'tool'])->default('user');
            $table->text('content');
            $table->json('tool_calls')->nullable(); // For assistant messages with tool calls
            $table->string('tool_call_id')->nullable(); // For tool response messages
            $table->json('metadata')->nullable(); // Token count, model, processing time, etc.

            $table->timestamp('created_at')->useCurrent();

            // Query optimization indexes
            $table->index(['chat_conversation_id', 'created_at']); // Get messages in order for a conversation
            $table->index(['role', 'created_at']); // Filter by role type
            // Composite index for efficient pagination
            $table->index(['chat_conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
