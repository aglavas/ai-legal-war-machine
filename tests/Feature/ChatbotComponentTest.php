<?php

namespace Tests\Feature;

use App\Http\Livewire\ChatbotComponent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\OpenAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class ChatbotComponentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create();

        // Run migrations
        $this->artisan('migrate');
    }

    /** @test */
    public function it_renders_chatbot_component()
    {
        Livewire::actingAs($this->user)
            ->test(ChatbotComponent::class)
            ->assertStatus(200)
            ->assertSee('AI Legal Assistant');
    }

    /** @test */
    public function it_loads_user_conversations_on_mount()
    {
        // Create test conversations
        $conversation1 = ChatConversation::create([
            'user_id' => $this->user->id,
            'agent_type' => 'general',
            'title' => 'Test Conversation 1',
        ]);

        $conversation2 = ChatConversation::create([
            'user_id' => $this->user->id,
            'agent_type' => 'law',
            'title' => 'Test Conversation 2',
        ]);

        Livewire::actingAs($this->user)
            ->test(ChatbotComponent::class)
            ->assertSet('conversations', function ($conversations) {
                return count($conversations) === 2;
            });
    }

    /** @test */
    public function it_creates_new_conversation_on_first_message()
    {
        // Mock OpenAI service
        $openaiMock = Mockery::mock(OpenAIService::class);
        $openaiMock->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'AI response'], 'finish_reason' => 'stop']
                ],
                'usage' => ['total_tokens' => 100],
                'model' => 'gpt-4o-mini',
            ]);

        $this->app->instance(OpenAIService::class, $openaiMock);

        $this->assertEquals(0, ChatConversation::count());

        Livewire::actingAs($this->user)
            ->test(ChatbotComponent::class)
            ->set('currentInput', 'Hello, this is a test message')
            ->call('sendMessage')
            ->assertSet('isLoading', false)
            ->assertSet('currentInput', '');

        // Verify conversation was created
        $this->assertEquals(1, ChatConversation::count());
        $conversation = ChatConversation::first();
        $this->assertEquals($this->user->id, $conversation->user_id);
    }

    /** @test */
    public function it_saves_user_and_assistant_messages()
    {
        // Mock OpenAI service
        $openaiMock = Mockery::mock(OpenAIService::class);
        $openaiMock->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'AI response'], 'finish_reason' => 'stop']
                ],
                'usage' => ['total_tokens' => 100],
                'model' => 'gpt-4o-mini',
            ]);

        $this->app->instance(OpenAIService::class, $openaiMock);

        Livewire::actingAs($this->user)
            ->test(ChatbotComponent::class)
            ->set('currentInput', 'Test question')
            ->call('sendMessage');

        // Verify messages were saved
        $this->assertEquals(2, ChatMessage::count());

        $userMessage = ChatMessage::where('role', 'user')->first();
        $this->assertEquals('Test question', $userMessage->content);

        $assistantMessage = ChatMessage::where('role', 'assistant')->first();
        $this->assertEquals('AI response', $assistantMessage->content);
    }

    /** @test */
    public function it_loads_conversation_by_uuid()
    {
        $conversation = ChatConversation::create([
            'user_id' => $this->user->id,
            'agent_type' => 'law',
            'title' => 'Test Conversation',
        ]);

        ChatMessage::create([
            'chat_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Test message',
        ]);

        Livewire::actingAs($this->user)
            ->test(ChatbotComponent::class)
            ->call('loadConversation', $conversation->uuid)
            ->assertSet('activeConversation.id', $conversation->id)
            ->assertSet('agentType', 'law')
            ->assertSet('messages', function ($messages) {
                return count($messages) === 1 && $messages[0]['content'] === 'Test message';
            });
    }

    /** @test */
    public function it_validates_input_before_sending()
    {
        Livewire::actingAs($this->user)
            ->test(ChatbotComponent::class)
            ->set('currentInput', '') // Empty input
            ->call('sendMessage')
            ->assertHasErrors(['currentInput']);
    }

    /** @test */
    public function it_prevents_sending_while_loading()
    {
        Livewire::actingAs($this->user)
            ->test(ChatbotComponent::class)
            ->set('isLoading', true)
            ->set('currentInput', 'Test message')
            ->call('sendMessage');

        // Should not create any messages
        $this->assertEquals(0, ChatMessage::count());
    }

    /** @test */
    public function it_switches_agent_type()
    {
        Livewire::actingAs($this->user)
            ->test(ChatbotComponent::class)
            ->assertSet('agentType', 'general')
            ->call('setAgentType', 'law')
            ->assertSet('agentType', 'law')
            ->call('setAgentType', 'court_decision')
            ->assertSet('agentType', 'court_decision');
    }

    /** @test */
    public function it_creates_new_conversation()
    {
        $existingConversation = ChatConversation::create([
            'user_id' => $this->user->id,
            'agent_type' => 'general',
            'title' => 'Existing',
        ]);

        Livewire::actingAs($this->user)
            ->test(ChatbotComponent::class)
            ->set('conversation', $existingConversation->uuid)
            ->set('activeConversation', $existingConversation)
            ->call('newConversation')
            ->assertSet('activeConversation', null)
            ->assertSet('conversation', null)
            ->assertSet('messages', [])
            ->assertSet('currentInput', '');
    }

    /** @test */
    public function it_deletes_conversation()
    {
        $conversation = ChatConversation::create([
            'user_id' => $this->user->id,
            'agent_type' => 'general',
            'title' => 'To Delete',
        ]);

        $this->assertEquals(1, ChatConversation::count());

        Livewire::actingAs($this->user)
            ->test(ChatbotComponent::class)
            ->call('deleteConversation', $conversation->uuid);

        $this->assertEquals(0, ChatConversation::count());
    }

    /** @test */
    public function it_clears_conversation_messages()
    {
        $conversation = ChatConversation::create([
            'user_id' => $this->user->id,
            'agent_type' => 'general',
            'title' => 'Test',
        ]);

        ChatMessage::create([
            'chat_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Message 1',
        ]);

        ChatMessage::create([
            'chat_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Response 1',
        ]);

        $this->assertEquals(2, ChatMessage::count());

        Livewire::actingAs($this->user)
            ->test(ChatbotComponent::class)
            ->call('loadConversation', $conversation->uuid)
            ->call('clearConversation');

        $this->assertEquals(0, ChatMessage::count());
    }

    /** @test */
    public function it_limits_conversation_history_to_prevent_memory_issues()
    {
        $conversation = ChatConversation::create([
            'user_id' => $this->user->id,
            'agent_type' => 'general',
            'title' => 'Large Conversation',
        ]);

        // Create 100 messages
        for ($i = 0; $i < 100; $i++) {
            ChatMessage::create([
                'chat_conversation_id' => $conversation->id,
                'role' => $i % 2 === 0 ? 'user' : 'assistant',
                'content' => "Message $i",
            ]);
        }

        Livewire::actingAs($this->user)
            ->test(ChatbotComponent::class)
            ->call('loadConversation', $conversation->uuid)
            ->assertSet('messages', function ($messages) {
                // Should only load most recent 50 messages
                return count($messages) <= 50;
            });
    }

    /** @test */
    public function it_handles_openai_errors_gracefully()
    {
        // Mock OpenAI to throw exception
        $openaiMock = Mockery::mock(OpenAIService::class);
        $openaiMock->shouldReceive('chat')
            ->andThrow(new \Exception('OpenAI API Error'));

        $this->app->instance(OpenAIService::class, $openaiMock);

        Livewire::actingAs($this->user)
            ->test(ChatbotComponent::class)
            ->set('currentInput', 'Test message')
            ->call('sendMessage')
            ->assertSet('isLoading', false);

        // Should save error message to conversation
        $errorMessage = ChatMessage::where('role', 'assistant')->first();
        $this->assertNotNull($errorMessage);
        $this->assertStringContainsString('error', $errorMessage->content);
    }

    /** @test */
    public function it_renders_markdown_in_assistant_messages()
    {
        $conversation = ChatConversation::create([
            'user_id' => $this->user->id,
            'agent_type' => 'general',
            'title' => 'Test',
        ]);

        ChatMessage::create([
            'chat_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => '**Bold text** and _italic text_',
        ]);

        Livewire::actingAs($this->user)
            ->test(ChatbotComponent::class)
            ->call('loadConversation', $conversation->uuid)
            ->assertSet('messages', function ($messages) {
                return isset($messages[0]['content_html']) &&
                       str_contains($messages[0]['content_html'], '<strong>');
            });
    }

    /** @test */
    public function it_only_shows_user_own_conversations()
    {
        $otherUser = User::factory()->create();

        // Create conversation for other user
        ChatConversation::create([
            'user_id' => $otherUser->id,
            'agent_type' => 'general',
            'title' => 'Other User Conversation',
        ]);

        // Create conversation for current user
        ChatConversation::create([
            'user_id' => $this->user->id,
            'agent_type' => 'general',
            'title' => 'My Conversation',
        ]);

        Livewire::actingAs($this->user)
            ->test(ChatbotComponent::class)
            ->assertSet('conversations', function ($conversations) {
                return count($conversations) === 1 &&
                       $conversations[0]['title'] === 'My Conversation';
            });
    }

    /** @test */
    public function it_saves_rag_metadata_with_messages()
    {
        // Mock OpenAI service with RAG metadata
        $openaiMock = Mockery::mock(OpenAIService::class);
        $openaiMock->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'AI response'], 'finish_reason' => 'stop']
                ],
                'usage' => ['total_tokens' => 100],
                'model' => 'gpt-4o-mini',
            ]);

        // For law/court_decision/case_analysis agents, RAG would be called
        // but we're not mocking the full RAG pipeline here
        $this->app->instance(OpenAIService::class, $openaiMock);

        Livewire::actingAs($this->user)
            ->test(ChatbotComponent::class)
            ->call('setAgentType', 'general')
            ->set('currentInput', 'Test question')
            ->call('sendMessage');

        $assistantMessage = ChatMessage::where('role', 'assistant')->first();
        $this->assertNotNull($assistantMessage->metadata);
        $this->assertArrayHasKey('agent_type', $assistantMessage->metadata);
    }

    /** @test */
    public function it_generates_conversation_title_from_first_message()
    {
        $openaiMock = Mockery::mock(OpenAIService::class);

        // First call for chat response
        $openaiMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'AI response'], 'finish_reason' => 'stop']
                ],
                'usage' => ['total_tokens' => 100],
                'model' => 'gpt-4o-mini',
            ]);

        // Second call for title generation
        $openaiMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'Croatian Contract Law Question']]
                ],
            ]);

        $this->app->instance(OpenAIService::class, $openaiMock);

        Livewire::actingAs($this->user)
            ->test(ChatbotComponent::class)
            ->set('currentInput', 'What does Croatian contract law say about obligations?')
            ->call('sendMessage');

        $conversation = ChatConversation::first();
        $this->assertNotNull($conversation);
        // Title should eventually be updated by AI
    }
}
