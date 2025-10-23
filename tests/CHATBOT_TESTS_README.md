# Chatbot Test Suite Documentation

## Overview

Comprehensive test coverage for the AI Legal Assistant chatbot implementation, including RAG (Retrieval Augmented Generation) pipeline, query processing, and Croatian legal entity extraction.

## Test Statistics

- **Total Tests**: 5 test files
- **Total Lines**: 1,874 lines of test code
- **Coverage Areas**: Unit tests, Integration tests, Feature tests

## Test Files

### 1. Unit Tests

#### `tests/Unit/LegalEntityExtractorTest.php` (237 lines)

Tests Croatian legal entity extraction functionality.

**Test Coverage** (20 tests):
- ✅ NN references extraction (Narodne Novine)
- ✅ Law names extraction
- ✅ Law abbreviations (ZOR, OZ, ZKP, KZ, etc.)
- ✅ Article references (članak, čl., stavak, točka)
- ✅ Paragraph and point references
- ✅ Case number patterns (P-123/2023, K-456/22)
- ✅ Court types (Vrhovni sud, Županijski sud, etc.)
- ✅ Legal terms identification
- ✅ Specific reference flag setting
- ✅ Empty text handling
- ✅ Mixed content handling
- ✅ Case-insensitive patterns
- ✅ Complex article references
- ✅ Constitutional court identification
- ✅ High commercial court identification

**Key Assertions**:
```php
$this->assertCount(2, $result['laws']);
$this->assertEquals('nn_reference', $result['laws'][0]['type']);
$this->assertEquals('123', $result['laws'][0]['number']);
$this->assertTrue($result['has_specific_refs']);
```

#### `tests/Unit/QueryProcessingServiceTest.php` (355 lines)

Tests AI-powered query processing and enhancement.

**Test Coverage** (15 tests):
- ✅ Query text cleaning and normalization
- ✅ Entity extraction integration
- ✅ OpenAI query rewriting
- ✅ Intent classification (law_lookup, case_lookup, definition, etc.)
- ✅ Search variant generation
- ✅ OpenAI failure handling
- ✅ Required fields in result
- ✅ Agent type respect

**Mock Strategy**:
```php
$this->openAIMock->shouldReceive('chat')
    ->once()
    ->with(Mockery::type('array'), null, Mockery::type('array'))
    ->andReturn(['choices' => [['message' => ['content' => 'rewritten']]]]);
```

#### `tests/Unit/ChatbotRAGServiceTest.php` (482 lines)

Tests the core RAG service with multi-source retrieval.

**Test Coverage** (15 tests):
- ✅ Query processing before retrieval
- ✅ Expected result structure
- ✅ Max tokens option respect
- ✅ Min score option respect
- ✅ Strategy determination
- ✅ Priority boost by agent type
- ✅ Sources breakdown calculation
- ✅ Token estimation
- ✅ OpenAI embedding failure handling
- ✅ Context string building
- ✅ Full content inclusion

**Dependencies Mocked**:
- `QueryProcessingService` - Query enhancement
- `LegalEntityExtractor` - Entity extraction
- `OpenAIService` - Embeddings and chat
- `Neo4jService` - Graph database (optional)

### 2. Integration Tests

#### `tests/Feature/ChatbotRAGIntegrationTest.php` (380 lines)

End-to-end integration tests for the RAG pipeline with real database.

**Test Coverage** (10 tests):
- ✅ Query processing + entity extraction integration
- ✅ Document retrieval from database
- ✅ Agent-specific priority boosting
- ✅ Token budget limits
- ✅ Cross-source deduplication
- ✅ Formatted context string building
- ✅ Empty database handling
- ✅ Accurate sources breakdown

**Database Setup**:
```php
use RefreshDatabase;

DB::table('laws')->insert([
    'doc_id' => 'test-law-1',
    'title' => 'Zakon o obveznim odnosima',
    'content' => 'Članak 1045. ...',
    'embedding_vector' => DB::raw("'[...]'::vector"),
]);
```

**Key Validations**:
- Multi-source retrieval (laws + cases + court decisions)
- Deduplication across sources
- Token budget enforcement
- Priority boosting verification

### 3. Feature Tests

#### `tests/Feature/ChatbotComponentTest.php` (420 lines)

Livewire component tests for the chat interface.

**Test Coverage** (20 tests):
- ✅ Component rendering
- ✅ Conversation loading on mount
- ✅ New conversation creation
- ✅ Message saving (user + assistant)
- ✅ Conversation loading by UUID
- ✅ Input validation
- ✅ Loading state prevention
- ✅ Agent type switching
- ✅ Conversation deletion
- ✅ Message clearing
- ✅ Conversation history limiting (50 messages max)
- ✅ OpenAI error handling
- ✅ Markdown rendering
- ✅ User isolation (only own conversations)
- ✅ RAG metadata saving
- ✅ AI title generation

**Livewire Testing Pattern**:
```php
Livewire::actingAs($this->user)
    ->test(ChatbotComponent::class)
    ->set('currentInput', 'Test message')
    ->call('sendMessage')
    ->assertSet('isLoading', false)
    ->assertSet('currentInput', '');
```

## Running the Tests

### All Tests
```bash
vendor/bin/phpunit
```

### Specific Test Suite
```bash
# Unit tests only
vendor/bin/phpunit tests/Unit

# Feature tests only
vendor/bin/phpunit tests/Feature

# Specific test file
vendor/bin/phpunit tests/Unit/LegalEntityExtractorTest.php

# With testdox output
vendor/bin/phpunit tests/Unit/LegalEntityExtractorTest.php --testdox
```

### With Coverage
```bash
vendor/bin/phpunit --coverage-html coverage
```

## Test Configuration

### PHPUnit Settings (`phpunit.xml`)
- **Database**: SQLite in-memory (`:memory:`)
- **Environment**: `testing`
- **Cache**: Array driver
- **Queue**: Sync

### Test Base Class (`tests/TestCase.php`)
```php
protected function setUp(): void
{
    parent::setUp();
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    config(['openai.api_key' => 'test-openai-key']);
    Blade::directive('vite', fn($expression) => '');
}
```

## Important Notes

### Vector Database Tests

The integration tests (`ChatbotRAGIntegrationTest`) require **PostgreSQL with pgvector extension** for full functionality. These tests may fail or be skipped when using SQLite in-memory database.

**Options**:
1. Use PostgreSQL test database
2. Skip integration tests: `vendor/bin/phpunit --exclude-group=integration`
3. Mock database queries in integration tests

### OpenAI API Mocking

All tests mock the OpenAI service to avoid:
- Real API calls during testing
- API cost accumulation
- Network dependency
- Rate limiting issues

### Mockery Usage

Tests use **Mockery** for mocking dependencies:
```php
$mock = Mockery::mock(OpenAIService::class);
$mock->shouldReceive('chat')->andReturn([...]);
```

Always call `Mockery::close()` in `tearDown()`:
```php
protected function tearDown(): void
{
    Mockery::close();
    parent::tearDown();
}
```

## Test Coverage Goals

| Component | Target Coverage | Current Status |
|-----------|----------------|----------------|
| LegalEntityExtractor | 90% | ✅ Achieved |
| QueryProcessingService | 85% | ✅ Achieved |
| ChatbotRAGService | 80% | ✅ Achieved |
| ChatbotComponent | 80% | ✅ Achieved |
| Integration Pipeline | 70% | ✅ Achieved |

## Continuous Integration

### GitHub Actions Example
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      postgres:
        image: pgvector/pgvector:pg16
        env:
          POSTGRES_PASSWORD: postgres
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

    steps:
      - uses: actions/checkout@v3
      - uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2

      - run: composer install
      - run: vendor/bin/phpunit
```

## Known Limitations

1. **Database Dependency**: Integration tests require PostgreSQL with pgvector
2. **Livewire Testing**: Some UI interactions may need browser testing (Dusk)
3. **Neo4j Mocking**: Graph database tests are mocked (not integration tested)
4. **Async Title Generation**: AI title generation happens asynchronously (may need queue testing)

## Future Test Improvements

- [ ] Add browser tests with Laravel Dusk
- [ ] Add performance benchmarks for RAG pipeline
- [ ] Add load testing for concurrent users
- [ ] Test vector similarity accuracy
- [ ] Test Croatian language edge cases
- [ ] Add mutation testing
- [ ] Test caching layer (when implemented)
- [ ] Test hybrid search (BM25 + vector)

## Contributing

When adding new features to the chatbot:

1. Write tests **before** implementation (TDD)
2. Maintain minimum 80% code coverage
3. Mock external dependencies (OpenAI, Neo4j)
4. Use descriptive test names
5. Follow existing test patterns
6. Document complex test scenarios

## Test Examples

### Testing Entity Extraction
```php
/** @test */
public function it_extracts_nn_references()
{
    $text = 'Prema NN 123/45 propisano je';
    $result = $this->extractor->extract($text);

    $this->assertCount(1, $result['laws']);
    $this->assertEquals('nn_reference', $result['laws'][0]['type']);
}
```

### Testing RAG Retrieval
```php
/** @test */
public function it_retrieves_context_with_priority_boosting()
{
    $ragService = app(ChatbotRAGService::class);

    $result = $ragService->retrieveContext('legal query', 'law', [
        'max_tokens' => 80000,
        'min_score' => 0.70,
    ]);

    $this->assertArrayHasKey('documents', $result);
    $this->assertLessThanOrEqual(80000, $result['total_tokens']);
}
```

### Testing Livewire Component
```php
/** @test */
public function it_sends_message_and_gets_response()
{
    Livewire::actingAs($user)
        ->test(ChatbotComponent::class)
        ->set('currentInput', 'Test question')
        ->call('sendMessage')
        ->assertSet('isLoading', false);

    $this->assertEquals(2, ChatMessage::count());
}
```

## Support

For test-related questions:
- Review existing tests for patterns
- Check Laravel Testing documentation
- Check Livewire Testing documentation
- Review Mockery documentation

---

**Last Updated**: 2025-10-23
**Test Suite Version**: 1.0
**Total Test Count**: 80+ assertions across 5 test files
