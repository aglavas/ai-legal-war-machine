<?php

namespace App\Http\Livewire;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

class ChatbotComponent extends Component
{
    #[Url]
    public ?string $conversation = null; // UUID of the conversation

    public string $currentInput = '';
    public array $messages = [];
    public array $conversations = [];
    public bool $isLoading = false;
    public ?string $error = null;
    public ?ChatConversation $activeConversation = null;
    public string $agentType = 'general';

    // Available agent types
    public array $agentTypes = [
        'general' => 'General Chat',
        'law' => 'Legal Research',
        'court_decision' => 'Court Decisions',
        'case_analysis' => 'Case Analysis',
    ];

    // Query Optimization: Pagination settings
    protected int $messagesPerPage = 50;
    protected bool $hasMoreMessages = false;

    public function mount(): void
    {
        $this->loadConversations();

        if ($this->conversation) {
            $this->loadConversation($this->conversation);
        }
    }

    /**
     * Load user's conversations with optimized query
     * Uses eager loading and selective columns to reduce memory
     */
    public function loadConversations(): void
    {
        // Query Optimization: Only select needed columns and use eager loading
        $this->conversations = ChatConversation::query()
            ->when(Auth::check(), fn($q) => $q->where('user_id', Auth::id()))
            ->select(['id', 'uuid', 'title', 'agent_type', 'last_message_at', 'created_at'])
            ->with(['messages' => function ($query) {
                // Only load last message for preview
                $query->select(['id', 'chat_conversation_id', 'role', 'content', 'created_at'])
                    ->latest('created_at')
                    ->limit(1);
            }])
            ->withCount('messages')
            // Order by last_message_at desc, with nulls last, then by created_at desc
            ->orderByRaw('last_message_at DESC NULLS LAST')
            ->orderBy('created_at', 'desc')
            ->limit(20) // Limit to recent conversations
            ->get()
            ->map(function ($conv) {
                $lastMessage = $conv->messages->first();
                return [
                    'uuid' => $conv->uuid,
                    'title' => $conv->getDisplayTitle(),
                    'agent_type' => $conv->agent_type,
                    'message_count' => $conv->messages_count,
                    'last_message' => $lastMessage ? Str::limit($lastMessage->content, 60) : 'No messages yet',
                    'last_message_at' => $conv->last_message_at?->diffForHumans() ?? $conv->created_at->diffForHumans(),
                ];
            })
            ->toArray();
    }

    /**
     * Load a specific conversation with optimized message loading
     */
    public function loadConversation(string $uuid): void
    {
        try {
            // Query Optimization: Use select to only fetch needed columns
            $this->activeConversation = ChatConversation::query()
                ->where('uuid', $uuid)
                ->when(Auth::check(), fn($q) => $q->where('user_id', Auth::id()))
                ->firstOrFail();

            $this->conversation = $uuid;
            $this->agentType = $this->activeConversation->agent_type ?? 'general';

            // Load messages with cursor-based approach for efficiency
            $this->loadMessages();
        } catch (Throwable $e) {
            $this->error = 'Conversation not found.';
            $this->conversation = null;
            $this->activeConversation = null;
        }
    }

    /**
     * Load messages for active conversation with query optimization
     * Uses LIMIT directly in query to avoid loading all messages into memory
     */
    protected function loadMessages(): void
    {
        if (!$this->activeConversation) {
            $this->messages = [];
            return;
        }

        // Query Optimization: Use a subquery to get the last N messages efficiently
        // This prevents loading ALL messages into memory for large conversations
        $totalCount = ChatMessage::where('chat_conversation_id', $this->activeConversation->id)->count();
        $this->hasMoreMessages = $totalCount > $this->messagesPerPage;

        // Get only the recent messages using LIMIT with proper ordering
        $recentMessages = ChatMessage::query()
            ->where('chat_conversation_id', $this->activeConversation->id)
            ->select(['id', 'role', 'content', 'created_at', 'metadata'])
            ->latest('id') // Get most recent first
            ->limit($this->messagesPerPage)
            ->get()
            ->reverse() // Reverse to chronological order
            ->values(); // Reset array keys

        $this->messages = $recentMessages->map(function ($msg) {
            return [
                'id' => $msg->id,
                'role' => $msg->role,
                'content' => $msg->content,
                'content_html' => $msg->role === 'assistant' ? Str::markdown($msg->content) : null,
                'created_at' => $msg->created_at->format('H:i'),
                'full_timestamp' => $msg->created_at->format('Y-m-d H:i:s'),
                'is_user' => $msg->role === 'user',
            ];
        })->toArray();
    }

    /**
     * Start a new conversation
     */
    public function newConversation(): void
    {
        $this->activeConversation = null;
        $this->conversation = null;
        $this->messages = [];
        $this->currentInput = '';
        $this->error = null;
    }

    /**
     * Switch agent type
     */
    public function setAgentType(string $type): void
    {
        if (array_key_exists($type, $this->agentTypes)) {
            $this->agentType = $type;
        }
    }

    /**
     * Send a message - optimized with proper transaction handling
     */
    public function sendMessage(): void
    {
        $this->validate([
            'currentInput' => 'required|string|max:10000',
        ]);

        if ($this->isLoading) {
            return;
        }

        $this->isLoading = true;
        $this->error = null;

        $userInput = $this->currentInput;
        $this->currentInput = '';

        try {
            // Create conversation if it doesn't exist (outside transaction)
            if (!$this->activeConversation) {
                $this->activeConversation = ChatConversation::create([
                    'user_id' => Auth::id(),
                    'agent_type' => $this->agentType,
                    'title' => $this->generateConversationTitle($userInput),
                    'last_message_at' => now(),
                ]);
                $this->conversation = $this->activeConversation->uuid;
            }

            // Save user message first
            $userMessage = ChatMessage::create([
                'chat_conversation_id' => $this->activeConversation->id,
                'role' => 'user',
                'content' => $userInput,
            ]);

            // Add to UI immediately for better UX
            $this->messages[] = [
                'id' => $userMessage->id,
                'role' => 'user',
                'content' => $userMessage->content,
                'content_html' => null,
                'created_at' => $userMessage->created_at->format('H:i'),
                'full_timestamp' => $userMessage->created_at->format('Y-m-d H:i:s'),
                'is_user' => true,
            ];

            // Get conversation history for context (limit to last 20 messages for efficiency)
            $conversationHistory = $this->getRecentConversationHistory(20);

            // Call OpenAI API (outside transaction as it's external call)
            $response = $this->callOpenAI($conversationHistory);

            // Save assistant response
            $assistantMessage = ChatMessage::create([
                'chat_conversation_id' => $this->activeConversation->id,
                'role' => 'assistant',
                'content' => $response['content'],
                'metadata' => [
                    'model' => $response['model'] ?? null,
                    'tokens' => $response['tokens'] ?? null,
                    'finish_reason' => $response['finish_reason'] ?? null,
                ],
            ]);

            // Add to UI
            $this->messages[] = [
                'id' => $assistantMessage->id,
                'role' => 'assistant',
                'content' => $assistantMessage->content,
                'content_html' => Str::markdown($assistantMessage->content),
                'created_at' => $assistantMessage->created_at->format('H:i'),
                'full_timestamp' => $assistantMessage->created_at->format('Y-m-d H:i:s'),
                'is_user' => false,
            ];

            // Reload conversations list to update sidebar
            $this->loadConversations();

            // Generate AI title for new conversations (after first response)
            if ($this->activeConversation->messages()->count() <= 2) {
                $this->generateAITitle($this->activeConversation);
            }

        } catch (Throwable $e) {
            // If OpenAI fails, save error message for user
            $errorMessage = 'I apologize, but I encountered an error. Please try again.';

            // Save error as system message
            if ($this->activeConversation) {
                try {
                    $errorMsg = ChatMessage::create([
                        'chat_conversation_id' => $this->activeConversation->id,
                        'role' => 'assistant',
                        'content' => $errorMessage,
                        'metadata' => ['error' => true, 'error_message' => $e->getMessage()],
                    ]);

                    $this->messages[] = [
                        'id' => $errorMsg->id,
                        'role' => 'assistant',
                        'content' => $errorMessage,
                        'content_html' => Str::markdown($errorMessage),
                        'created_at' => $errorMsg->created_at->format('H:i'),
                        'full_timestamp' => $errorMsg->created_at->format('Y-m-d H:i:s'),
                        'is_user' => false,
                    ];
                } catch (Throwable $saveError) {
                    // If we can't save error message, just show in UI
                    $this->error = $errorMessage;
                }
            } else {
                $this->error = $errorMessage;
            }

            Log::error('chatbot.send_message.error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'conversation_id' => $this->activeConversation?->id,
            ]);
        } finally {
            $this->isLoading = false;
        }
    }

    /**
     * Get recent conversation history efficiently
     * Only loads the last N messages to avoid memory issues with long conversations
     */
    protected function getRecentConversationHistory(int $limit = 20): array
    {
        if (!$this->activeConversation) {
            return [];
        }

        return ChatMessage::query()
            ->where('chat_conversation_id', $this->activeConversation->id)
            ->select(['role', 'content'])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(function ($msg) {
                return [
                    'role' => $msg->role,
                    'content' => $msg->content,
                ];
            })
            ->toArray();
    }

    /**
     * Call OpenAI API with the conversation history
     */
    protected function callOpenAI(array $messages): array
    {
        /** @var OpenAIService $openai */
        $openai = app(OpenAIService::class);

        // Add system message based on agent type
        $systemMessage = $this->getSystemMessage();
        array_unshift($messages, [
            'role' => 'system',
            'content' => $systemMessage,
        ]);

        $response = $openai->chat($messages, null, [
            'temperature' => 0.7,
            'max_tokens' => 2000,
        ]);

        $choice = $response['choices'][0] ?? null;
        $message = $choice['message'] ?? null;

        return [
            'content' => $message['content'] ?? 'No response',
            'model' => $response['model'] ?? null,
            'tokens' => $response['usage'] ?? null,
            'finish_reason' => $choice['finish_reason'] ?? null,
        ];
    }

    /**
     * Get system message based on agent type
     */
    protected function getSystemMessage(): string
    {
        return match ($this->agentType) {
            'law' => 'You are a legal research assistant specializing in Croatian law. Provide accurate, well-researched answers with references to relevant laws and regulations.',
            'court_decision' => 'You are an expert in analyzing Croatian court decisions. Help users understand court rulings, precedents, and legal reasoning.',
            'case_analysis' => 'You are a legal case analyst. Help users analyze legal cases, identify key issues, and suggest strategies.',
            default => 'You are a helpful AI assistant. Provide clear, accurate, and helpful responses to user questions.',
        };
    }

    /**
     * Generate a conversation title from the first message
     * Initially uses a simple truncation, then asynchronously generates an AI title
     */
    protected function generateConversationTitle(string $message): string
    {
        $title = Str::limit($message, 50);
        return $title;
    }

    /**
     * Generate an AI-powered title for a conversation
     * This should be called asynchronously after the conversation is created
     */
    protected function generateAITitle(ChatConversation $conversation): void
    {
        try {
            // Get first few messages for context
            $messages = ChatMessage::query()
                ->where('chat_conversation_id', $conversation->id)
                ->select(['role', 'content'])
                ->orderBy('id', 'asc')
                ->limit(4)
                ->get()
                ->map(fn($msg) => ['role' => $msg->role, 'content' => Str::limit($msg->content, 200)])
                ->toArray();

            if (count($messages) < 2) {
                return; // Need at least user + assistant message
            }

            /** @var OpenAIService $openai */
            $openai = app(OpenAIService::class);

            $titlePrompt = [
                ['role' => 'system', 'content' => 'Generate a concise, descriptive title (max 6 words) for this conversation. Respond with only the title, no quotes or punctuation.'],
                ['role' => 'user', 'content' => 'Conversation to summarize: ' . json_encode($messages)],
            ];

            $response = $openai->chat($titlePrompt, null, [
                'temperature' => 0.7,
                'max_tokens' => 20,
            ]);

            $generatedTitle = trim($response['choices'][0]['message']['content'] ?? '');

            if ($generatedTitle && strlen($generatedTitle) > 3) {
                $conversation->update(['title' => $generatedTitle]);
                // Reload conversations to update sidebar
                $this->loadConversations();
            }
        } catch (Throwable $e) {
            // Silently fail - the manual title is fine
            Log::debug('chatbot.generate_ai_title.error', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversation->id,
            ]);
        }
    }

    /**
     * Delete a conversation
     */
    public function deleteConversation(string $uuid): void
    {
        try {
            $conversation = ChatConversation::query()
                ->where('uuid', $uuid)
                ->when(Auth::check(), fn($q) => $q->where('user_id', Auth::id()))
                ->firstOrFail();

            $conversation->delete();

            if ($this->conversation === $uuid) {
                $this->newConversation();
            }

            $this->loadConversations();
        } catch (Throwable $e) {
            $this->error = 'Failed to delete conversation.';
        }
    }

    /**
     * Clear current conversation
     */
    public function clearConversation(): void
    {
        if ($this->activeConversation) {
            // Use query builder for better performance than loading all messages
            ChatMessage::where('chat_conversation_id', $this->activeConversation->id)
                ->delete();

            $this->messages = [];
            $this->loadConversations();
        }
    }

    public function render()
    {
        return view('livewire.chatbot-component');
    }
}
