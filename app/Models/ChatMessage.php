<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ChatMessage extends Model
{
    // Disable updated_at since we only need created_at for messages
    const UPDATED_AT = null;

    protected $fillable = [
        'uuid',
        'chat_conversation_id',
        'role',
        'content',
        'tool_calls',
        'tool_call_id',
        'metadata',
    ];

    protected $casts = [
        'tool_calls' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($message) {
            if (empty($message->uuid)) {
                $message->uuid = (string) Str::uuid();
            }
        });

        // Update conversation's last_message_at timestamp
        static::created(function ($message) {
            $message->conversation->touchLastMessage();
        });
    }

    // Relationships

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    // Query Scopes for Optimization

    /**
     * Scope to get messages for a conversation in chronological order
     */
    public function scopeForConversation($query, int $conversationId)
    {
        return $query->where('chat_conversation_id', $conversationId)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');
    }

    /**
     * Scope to filter by role (user, assistant, system)
     */
    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope to get recent messages (optimized with limit)
     */
    public function scopeRecent($query, int $limit = 50)
    {
        return $query->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit);
    }

    /**
     * Scope to get messages with minimal data (for list views)
     */
    public function scopeMinimal($query)
    {
        return $query->select(['id', 'chat_conversation_id', 'role', 'content', 'created_at']);
    }

    /**
     * Scope for cursor-based pagination (more efficient than offset)
     */
    public function scopeCursorPaginate($query, int $conversationId, ?int $lastMessageId = null, int $limit = 50)
    {
        $query = $query->where('chat_conversation_id', $conversationId);

        if ($lastMessageId) {
            $query->where('id', '>', $lastMessageId);
        }

        return $query->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->limit($limit);
    }

    // Helper Methods

    /**
     * Check if message is from user
     */
    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    /**
     * Check if message is from assistant
     */
    public function isAssistant(): bool
    {
        return $this->role === 'assistant';
    }

    /**
     * Get token count from metadata
     */
    public function getTokenCount(): ?int
    {
        return $this->metadata['token_count'] ?? null;
    }

    /**
     * Format message for display
     */
    public function getFormattedContent(): string
    {
        // Could add markdown parsing or other formatting here
        return $this->content;
    }
}
