<?php

namespace App\Services\AI\LLM;

/**
 * Enhanced ChatMessage with support for grounded responses and citations
 */
class ChatMessage
{
    public function __construct(
        public string $role,   // system|user|assistant
        public string $content,
        public ?array $citations = null,  // Array of source citations
        public ?array $metadata = null,   // Additional metadata
        public ?float $confidence = null, // Confidence score [0-1]
        public ?string $name = null       // Optional name for function/tool messages
    ) {}

    /**
     * Create a grounded assistant message with citations
     */
    public static function grounded(
        string $content,
        array $citations,
        float $confidence,
        array $metadata = []
    ): self {
        return new self(
            role: 'assistant',
            content: $content,
            citations: $citations,
            metadata: $metadata,
            confidence: $confidence
        );
    }

    /**
     * Create a system message
     */
    public static function system(string $content, array $metadata = []): self
    {
        return new self(
            role: 'system',
            content: $content,
            metadata: $metadata
        );
    }

    /**
     * Create a user message
     */
    public static function user(string $content, array $metadata = []): self
    {
        return new self(
            role: 'user',
            content: $content,
            metadata: $metadata
        );
    }

    /**
     * Create an assistant message
     */
    public static function assistant(string $content, array $metadata = []): self
    {
        return new self(
            role: 'assistant',
            content: $content,
            metadata: $metadata
        );
    }

    /**
     * Convert to OpenAI API format
     */
    public function toOpenAIFormat(): array
    {
        $message = [
            'role' => $this->role,
            'content' => $this->content,
        ];

        if ($this->name !== null) {
            $message['name'] = $this->name;
        }

        return $message;
    }

    /**
     * Convert to array with all data
     */
    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'content' => $this->content,
            'citations' => $this->citations,
            'metadata' => $this->metadata,
            'confidence' => $this->confidence,
            'name' => $this->name,
        ];
    }

    /**
     * Format citations as markdown
     */
    public function formatCitations(): string
    {
        if (empty($this->citations)) {
            return '';
        }

        $formatted = "\n\n## Pravni izvori\n\n";
        foreach ($this->citations as $idx => $citation) {
            $num = $idx + 1;
            $source = $citation['source'] ?? 'Nepoznat izvor';
            $title = $citation['title'] ?? '';
            $confidence = $citation['confidence'] ?? null;

            $formatted .= "[{$num}] {$source}";
            if ($title) {
                $formatted .= " - {$title}";
            }
            if ($confidence !== null) {
                $formatted .= " (pouzdanost: " . round($confidence * 100) . "%)";
            }
            $formatted .= "\n";
        }

        return $formatted;
    }

    /**
     * Build JSON schema for structured responses
     */
    public static function buildResponseSchema(array $schema): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => $schema['name'] ?? 'response',
                'strict' => $schema['strict'] ?? true,
                'schema' => $schema['schema'] ?? [
                    'type' => 'object',
                    'properties' => [
                        'answer' => [
                            'type' => 'string',
                            'description' => 'The main answer to the query'
                        ],
                        'citations' => [
                            'type' => 'array',
                            'description' => 'Citations supporting the answer',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'source' => ['type' => 'string'],
                                    'quote' => ['type' => 'string'],
                                    'confidence' => ['type' => 'number'],
                                ],
                                'required' => ['source', 'quote'],
                            ],
                        ],
                        'confidence' => [
                            'type' => 'number',
                            'description' => 'Overall confidence in the answer (0-1)',
                            'minimum' => 0,
                            'maximum' => 1,
                        ],
                    ],
                    'required' => ['answer', 'citations', 'confidence'],
                ],
            ],
        ];
    }
}
