<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ChatConversation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'agent_type',
        'metadata',
        'last_message_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_message_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($conversation) {
            if (empty($conversation->uuid)) {
                $conversation->uuid = (string) Str::uuid();
            }
        });
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');
    }

    // Query Scopes for Optimization

    /**
     * Scope to get recent conversations with message count
     */
    public function scopeWithMessageCount($query)
    {
        return $query->withCount('messages');
    }

    /**
     * Scope to get conversations with their latest message
     */
    public function scopeWithLatestMessage($query)
    {
        return $query->with(['messages' => function ($query) {
            $query->latest('created_at')->limit(1);
        }]);
    }

    /**
     * Scope for active conversations (ordered by last activity)
     */
    public function scopeActive($query)
    {
        return $query->orderBy('last_message_at', 'desc');
    }

    /**
     * Scope to filter by agent type
     */
    public function scopeByAgent($query, string $agentType)
    {
        return $query->where('agent_type', $agentType);
    }

    /**
     * Scope for user's conversations with optimized loading
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId)
            ->with(['messages' => function ($query) {
                // Only load the last message for list views
                $query->latest('created_at')->limit(1);
            }])
            ->withCount('messages')
            ->active();
    }

    // Helper Methods

    /**
     * Get conversation history formatted for OpenAI
     */
    public function getFormattedMessages(): array
    {
        // Use select to only fetch needed columns, reducing memory usage
        return $this->messages()
            ->select(['id', 'role', 'content', 'created_at'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($message) {
                return [
                    'role' => $message->role,
                    'content' => $message->content,
                ];
            })
            ->toArray();
    }

    /**
     * Update the last message timestamp
     */
    public function touchLastMessage(): void
    {
        // Use update instead of save to avoid loading the whole model
        $this->update(['last_message_at' => now()]);
    }

    /**
     * Get or generate conversation title
     */
    public function getDisplayTitle(): string
    {
        if ($this->title) {
            return $this->title;
        }

        return 'Conversation ' . $this->created_at->format('M d, Y H:i');
    }
}
