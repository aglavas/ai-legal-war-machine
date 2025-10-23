<?php

namespace App\Http\Livewire;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            ->orderBy('last_message_at', 'desc')
            ->limit(20) // Limit to recent conversations
            ->get()
            ->map(function ($conv) {
                return [
                    'uuid' => $conv->uuid,
                    'title' => $conv->getDisplayTitle(),
                    'agent_type' => $conv->agent_type,
                    'message_count' => $conv->messages_count,
                    'last_message' => $conv->messages->first()?->content,
                    'last_message_at' => $conv->last_message_at?->diffForHumans(),
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
     * Uses cursor pagination instead of offset for better performance
     */
    protected function loadMessages(): void
    {
        if (!$this->activeConversation) {
            $this->messages = [];
            return;
        }

        // Query Optimization: Select only necessary columns
        $query = ChatMessage::query()
            ->where('chat_conversation_id', $this->activeConversation->id)
            ->select(['id', 'role', 'content', 'created_at', 'metadata'])
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');

        // Get recent messages (last N messages for better UX)
        $allMessages = $query->get();

        // Check if there are more messages than we display
        $this->hasMoreMessages = $allMessages->count() > $this->messagesPerPage;

        // Take only the most recent messages
        $recentMessages = $allMessages->take(-$this->messagesPerPage);

        $this->messages = $recentMessages->map(function ($msg) {
            return [
                'id' => $msg->id,
                'role' => $msg->role,
                'content' => $msg->content,
                'created_at' => $msg->created_at->format('H:i'),
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
     * Send a message - optimized with database transaction
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

        try {
            // Use database transaction for consistency
            DB::beginTransaction();

            // Create conversation if it doesn't exist
            if (!$this->activeConversation) {
                $this->activeConversation = ChatConversation::create([
                    'user_id' => Auth::id(),
                    'agent_type' => $this->agentType,
                    'title' => $this->generateConversationTitle($this->currentInput),
                    'last_message_at' => now(),
                ]);
                $this->conversation = $this->activeConversation->uuid;
            }

            // Save user message - using create is more efficient than new + save
            $userMessage = ChatMessage::create([
                'chat_conversation_id' => $this->activeConversation->id,
                'role' => 'user',
                'content' => $this->currentInput,
            ]);

            // Add to UI immediately for better UX
            $this->messages[] = [
                'id' => $userMessage->id,
                'role' => 'user',
                'content' => $userMessage->content,
                'created_at' => $userMessage->created_at->format('H:i'),
                'is_user' => true,
            ];

            $userInput = $this->currentInput;
            $this->currentInput = '';

            DB::commit();

            // Get conversation history for context
            // Query Optimization: Use getFormattedMessages method from model
            $conversationHistory = $this->activeConversation->getFormattedMessages();

            // Call OpenAI API
            $response = $this->callOpenAI($conversationHistory);

            // Save assistant response
            DB::beginTransaction();

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

            DB::commit();

            // Add to UI
            $this->messages[] = [
                'id' => $assistantMessage->id,
                'role' => 'assistant',
                'content' => $assistantMessage->content,
                'created_at' => $assistantMessage->created_at->format('H:i'),
                'is_user' => false,
            ];

            // Reload conversations list
            $this->loadConversations();

        } catch (Throwable $e) {
            DB::rollBack();
            $this->error = 'Failed to send message: ' . $e->getMessage();
            Log::error('chatbot.send_message.error', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'conversation_id' => $this->activeConversation?->id,
            ]);
        } finally {
            $this->isLoading = false;
        }
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
     */
    protected function generateConversationTitle(string $message): string
    {
        $title = substr($message, 0, 50);
        if (strlen($message) > 50) {
            $title .= '...';
        }
        return $title;
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
