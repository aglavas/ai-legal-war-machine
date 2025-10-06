<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    protected string $apiKey;
    protected ?string $organization;
    protected ?string $project;
    protected string $baseUrl;
    protected int $timeout;
    protected int $connectTimeout;

    public function __construct()
    {
        $this->apiKey = (string) config('openai.api_key');
        $this->organization = config('openai.organization');
        $this->project = config('openai.project');
        $this->baseUrl = rtrim((string) config('openai.base_url', 'https://api.openai.com/v1'), '/');
        $this->timeout = (int) config('openai.timeout', 60);
        $this->connectTimeout = (int) config('openai.connect_timeout', 10);
    }

    protected function client()
    {
        if (!$this->apiKey) {
            throw new \RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];
        if ($this->organization) {
            $headers['OpenAI-Organization'] = $this->organization;
        }
        if ($this->project) {
            $headers['OpenAI-Project'] = $this->project;
        }

        return Http::withHeaders($headers)
            ->baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout);
    }

    protected function logChannel()
    {
        return Log::channel('openai');
    }

    // General request wrapper
    protected function request(string $method, string $url, array $payload = [])
    {
        $reqId = (string) Str::uuid();
        $start = microtime(true);

        // Log request start (avoid logging binary)
        $this->logChannel()->info('openai.request', [
            'event' => 'openai.request',
            'request_id' => $reqId,
            'method' => strtoupper($method),
            'url' => ltrim($url, '/'),
            'payload' => $payload,
        ]);

        try {
            $resp = $this->client()->send($method, ltrim($url, '/'), [
                'json' => $payload,
            ]);
            $duration = (int) round((microtime(true) - $start) * 1000);

            $status = $resp->status();
            $body = (string) $resp->body();
            $json = null;
            try {
                $json = $resp->json();
            } catch (\Throwable $e) {
                $json = null;
            }

            $this->logChannel()->info('openai.response', [
                'event' => 'openai.response',
                'request_id' => $reqId,
                'status' => $status,
                'duration_ms' => $duration,
                'response' => $json ?? [
                    'text' => Str::limit($body, 2000),
                ],
            ]);

            $resp->throw();
            return $json ?? [];
        } catch (\Throwable $e) {
            $duration = (int) round((microtime(true) - $start) * 1000);
            $code = method_exists($e, 'getCode') ? $e->getCode() : 0;
            $this->logChannel()->error('openai.error', [
                'event' => 'openai.error',
                'request_id' => $reqId,
                'duration_ms' => $duration,
                'error' => [
                    'message' => $e->getMessage(),
                    'code' => $code,
                    'class' => get_class($e),
                ],
            ]);
            throw $e;
        }
    }

    // Lightweight GET wrapper with additional headers and logging
    protected function getWithHeaders(string $url, array $headers = [])
    {
        $reqId = (string) Str::uuid();
        $start = microtime(true);

        $this->logChannel()->info('openai.request', [
            'event' => 'openai.request',
            'request_id' => $reqId,
            'method' => 'GET',
            'url' => ltrim($url, '/'),
            'headers' => array_keys($headers),
        ]);

        try {
            $resp = $this->client()->withHeaders($headers)->get(ltrim($url, '/'));
            $duration = (int) round((microtime(true) - $start) * 1000);

            $status = $resp->status();
            $body = (string) $resp->body();
            $json = null;
            try {
                $json = $resp->json();
            } catch (\Throwable $e) {
                $json = null;
            }

            $this->logChannel()->info('openai.response', [
                'event' => 'openai.response',
                'request_id' => $reqId,
                'status' => $status,
                'duration_ms' => $duration,
                'response' => $json ?? [
                    'text' => Str::limit($body, 2000),
                ],
            ]);

            $resp->throw();
            return $json ?? [];
        } catch (\Throwable $e) {
            $duration = (int) round((microtime(true) - $start) * 1000);
            $this->logChannel()->error('openai.error', [
                'event' => 'openai.error',
                'request_id' => $reqId,
                'duration_ms' => $duration,
                'error' => [
                    'message' => $e->getMessage(),
                    'code' => method_exists($e, 'getCode') ? $e->getCode() : 0,
                    'class' => get_class($e),
                ],
            ]);
            throw $e;
        }
    }

    // Responses API
    public function responses(array $options): array
    {
        $options['model'] = $options['model'] ?? config('openai.models.responses');
        return $this->request('POST', '/responses', $options);
    }

    /**
     * Fetch list of responses (logs) with optional include[] params and limits, using Responses API (beta).
     * Example usage mirrors curl with include[]=..., input_item_limit, output_item_limit.
     */
    public function responsesList(array $query = [], array $include = []): array
    {
        // Build query string manually to support repeated include[] keys
        $parts = [];
        foreach ($include as $inc) {
            $parts[] = 'include[]=' . rawurlencode($inc);
        }
        foreach ($query as $k => $v) {
            if ($v === null) continue;
            $parts[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
        }
        $qs = $parts ? ('?' . implode('&', $parts)) : '';

        $headers = [
            'OpenAI-Beta' => 'responses=v1',
            'Accept' => '*/*',
        ];

        return $this->getWithHeaders('/responses' . $qs, $headers);
    }

    /**
     * Retrieve a single response with optional include[] fields (beta Responses API)
     */
    public function responseRetrieve(string $responseId, array $include = [], array $query = []): array
    {
        $parts = [];
        foreach ($include as $inc) {
            $parts[] = 'include[]=' . rawurlencode($inc);
        }
        foreach ($query as $k => $v) {
            if ($v === null) continue;
            $parts[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
        }
        $qs = $parts ? ('?' . implode('&', $parts)) : '';

        $headers = [
            'OpenAI-Beta' => 'responses=v1',
            'Accept' => '*/*',
        ];

        return $this->getWithHeaders("/responses/{$responseId}" . $qs, $headers);
    }

    /**
     * Fetch input_items for a response, with include[] filters (beta Responses API)
     */
    public function responseInputItems(string $responseId, array $include = [], array $query = []): array
    {
        $parts = [];
        foreach ($include as $inc) {
            $parts[] = 'include[]=' . rawurlencode($inc);
        }
        foreach ($query as $k => $v) {
            if ($v === null) continue;
            $parts[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
        }
        $qs = $parts ? ('?' . implode('&', $parts)) : '';

        $headers = [
            'OpenAI-Beta' => 'responses=v1',
            'Accept' => '*/*',
        ];

        return $this->getWithHeaders("/responses/{$responseId}/input_items" . $qs, $headers);
    }

    // Chat Completions (legacy-style)
    public function chat(array $messages, ?string $model = null, array $options = []): array
    {
        $payload = array_merge($options, [
            'model' => $model ?? config('openai.models.chat'),
            'messages' => $messages,
        ]);
        return $this->request('POST', '/chat/completions', $payload);
    }

    // Embeddings
    public function embeddings(string|array $input, ?string $model = null, array $options = []): array
    {
        $payload = array_merge($options, [
            'model' => $model ?? config('openai.models.embeddings'),
            'input' => $input,
        ]);
        return $this->request('POST', '/embeddings', $payload);
    }

    // Images generation
    public function imageGenerate(string $prompt, array $options = []): array
    {
        $payload = array_merge([
            'model' => config('openai.models.image'),
            'prompt' => $prompt,
            'n' => $options['n'] ?? 1,
            'size' => $options['size'] ?? '1024x1024',
            'response_format' => $options['response_format'] ?? 'b64_json',
        ], Arr::except($options, ['n', 'size', 'response_format', 'prompt', 'model']));
        return $this->request('POST', '/images/generations', $payload);
    }

    // Audio Transcription (Speech-to-Text)
    public function transcribe(string $filePath, array $options = [])
    {
        $model = $options['model'] ?? config('openai.models.stt');
        $params = Arr::except($options, ['model', 'file']);

        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ];
        if ($this->organization) {
            $headers['OpenAI-Organization'] = $this->organization;
        }
        if ($this->project) {
            $headers['OpenAI-Project'] = $this->project;
        }

        $reqId = (string) Str::uuid();
        $start = microtime(true);
        $this->logChannel()->info('openai.request', [
            'event' => 'openai.request',
            'request_id' => $reqId,
            'method' => 'POST',
            'url' => 'audio/transcriptions',
            'payload' => Arr::except($params, ['file']),
        ]);

        try {
            $resp = Http::withHeaders($headers)
                ->baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->asMultipart()
                ->post('/audio/transcriptions', array_merge([
                    ['name' => 'model', 'contents' => $model],
                    ['name' => 'file', 'contents' => fopen($filePath, 'r'), 'filename' => basename($filePath)],
                ], $this->toMultipart($params)));

            $duration = (int) round((microtime(true) - $start) * 1000);
            $this->logChannel()->info('openai.response', [
                'event' => 'openai.response',
                'request_id' => $reqId,
                'status' => $resp->status(),
                'duration_ms' => $duration,
                'response' => $resp->json(),
            ]);

            $resp->throw();
            return $resp->json();
        } catch (\Throwable $e) {
            $duration = (int) round((microtime(true) - $start) * 1000);
            $this->logChannel()->error('openai.error', [
                'event' => 'openai.error',
                'request_id' => $reqId,
                'duration_ms' => $duration,
                'error' => [
                    'message' => $e->getMessage(),
                    'code' => method_exists($e, 'getCode') ? $e->getCode() : 0,
                    'class' => get_class($e),
                ],
            ]);
            throw $e;
        }
    }

    // Text-to-Speech
    public function tts(string $text, array $options = []): string
    {
        $model = $options['model'] ?? config('openai.models.tts');
        $voice = $options['voice'] ?? 'alloy';
        $format = $options['format'] ?? 'mp3';

        $payload = [
            'model' => $model,
            'input' => $text,
            'voice' => $voice,
            'format' => $format,
        ];

        $reqId = (string) Str::uuid();
        $start = microtime(true);
        $this->logChannel()->info('openai.request', [
            'event' => 'openai.request',
            'request_id' => $reqId,
            'method' => 'POST',
            'url' => 'audio/speech',
            'payload' => Arr::except($payload, []),
        ]);

        $resp = $this->client()->asJson()->post('/audio/speech', $payload);
        $duration = (int) round((microtime(true) - $start) * 1000);

        if ($resp->failed()) {
            $this->logChannel()->error('openai.error', [
                'event' => 'openai.error',
                'request_id' => $reqId,
                'duration_ms' => $duration,
                'error' => [
                    'message' => $resp->body(),
                    'code' => $resp->status(),
                    'class' => 'HttpClientException',
                ],
            ]);
            $resp->throw();
        }

        $this->logChannel()->info('openai.response', [
            'event' => 'openai.response',
            'request_id' => $reqId,
            'status' => $resp->status(),
            'duration_ms' => $duration,
            'response' => [
                'binary_length' => strlen((string) $resp->body()),
                'content_type' => $resp->header('Content-Type'),
            ],
        ]);

        return (string) $resp->body(); // binary audio
    }

    // Files API
    public function fileUpload(string $path, string $purpose = 'assistants'): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ];
        if ($this->organization) {
            $headers['OpenAI-Organization'] = $this->organization;
        }
        if ($this->project) {
            $headers['OpenAI-Project'] = $this->project;
        }

        $reqId = (string) Str::uuid();
        $start = microtime(true);
        $this->logChannel()->info('openai.request', [
            'event' => 'openai.request',
            'request_id' => $reqId,
            'method' => 'POST',
            'url' => 'files',
            'payload' => [
                'purpose' => $purpose,
                'file' => basename($path),
            ],
        ]);

        try {
            $resp = Http::withHeaders($headers)
                ->baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->asMultipart()
                ->post('/files', [
                    ['name' => 'purpose', 'contents' => $purpose],
                    ['name' => 'file', 'contents' => fopen($path, 'r'), 'filename' => basename($path)],
                ]);

            $duration = (int) round((microtime(true) - $start) * 1000);
            $this->logChannel()->info('openai.response', [
                'event' => 'openai.response',
                'request_id' => $reqId,
                'status' => $resp->status(),
                'duration_ms' => $duration,
                'response' => $resp->json(),
            ]);

            $resp->throw();
            return $resp->json();
        } catch (\Throwable $e) {
            $duration = (int) round((microtime(true) - $start) * 1000);
            $this->logChannel()->error('openai.error', [
                'event' => 'openai.error',
                'request_id' => $reqId,
                'duration_ms' => $duration,
                'error' => [
                    'message' => $e->getMessage(),
                    'code' => method_exists($e, 'getCode') ? $e->getCode() : 0,
                    'class' => get_class($e),
                ],
            ]);
            throw $e;
        }
    }

    public function fileList(): array
    {
        return $this->request('GET', '/files');
    }

    public function fileRetrieve(string $fileId): array
    {
        return $this->request('GET', "/files/{$fileId}");
    }

    public function fileDelete(string $fileId): array
    {
        return $this->request('DELETE', "/files/{$fileId}");
    }

    // Assistants
    public function assistantsCreate(array $data): array
    {
        $data['model'] = $data['model'] ?? config('openai.models.chat');
        return $this->request('POST', '/assistants', $data);
    }

    public function assistantsRetrieve(string $assistantId): array
    {
        return $this->request('GET', "/assistants/{$assistantId}");
    }

    public function assistantsList(array $query = []): array
    {
        return $this->client()->get('/assistants', $query)->throw()->json();
    }

    public function assistantsDelete(string $assistantId): array
    {
        return $this->request('DELETE', "/assistants/{$assistantId}");
    }

    // Vector Stores
    public function vectorStoreCreate(array $data): array
    {
        return $this->request('POST', '/vector_stores', $data);
    }

    public function vectorStoreRetrieve(string $storeId): array
    {
        return $this->request('GET', "/vector_stores/{$storeId}");
    }

    public function vectorStoreList(array $query = []): array
    {
        return $this->client()->get('/vector_stores', $query)->throw()->json();
    }

    public function vectorStoreDelete(string $storeId): array
    {
        return $this->request('DELETE', "/vector_stores/{$storeId}");
    }

    public function vectorStoreAddFile(string $storeId, string $fileId): array
    {
        return $this->request('POST', "/vector_stores/{$storeId}/files", ['file_id' => $fileId]);
    }

    public function vectorStoreListFiles(string $storeId, array $query = []): array
    {
        return $this->client()->get("/vector_stores/{$storeId}/files", $query)->throw()->json();
    }

    public function vectorStoreDeleteFile(string $storeId, string $fileId): array
    {
        return $this->request('DELETE', "/vector_stores/{$storeId}/files/{$fileId}");
    }

    // Utility to convert array params to multipart entries
    protected function toMultipart(array $params): array
    {
        $parts = [];
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                $parts[] = ['name' => $k, 'contents' => json_encode($v)];
            } else {
                $parts[] = ['name' => $k, 'contents' => (string) $v];
            }
        }
        return $parts;
    }
}
