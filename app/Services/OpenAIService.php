<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Throwable;

class OpenAIService
{
    /**
     * @var string $apiKey
     */
    protected string $apiKey;

    /**
     * @var string|\Illuminate\Config\Repository|\Illuminate\Foundation\Application|mixed|object|null $organization
     */
    protected ?string $organization;

    /**
     * @var string|\Illuminate\Config\Repository|\Illuminate\Foundation\Application|mixed|object|null $project
     */
    protected ?string $project;

    /**
     * @var string $baseUrl
     */
    protected string $baseUrl;

    /**
     * @var int $timeout
     */
    protected int $timeout;

    /**
     * @var int $connectTimeout
     */
    protected int $connectTimeout;

    /**
     *
     */
    public function __construct()
    {
        $this->apiKey = (string) config('openai.api_key');
        $this->organization = config('openai.organization');
        $this->project = config('openai.project');
        $this->baseUrl = rtrim((string) config('openai.base_url', 'https://api.openai.com/v1'), '/');
        $this->timeout = (int) config('openai.timeout', 60);
        $this->connectTimeout = (int) config('openai.connect_timeout', 10);
    }

    /**
     * @return PendingRequest
     */
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

    /**
     * @return LoggerInterface
     */
    protected function logChannel()
    {
        return Log::channel('openai');
    }

    /**
     * @param string $method
     * @param string $url
     * @param array $payload
     * @return array|mixed
     * @throws Throwable
     */
    protected function request(string $method, string $url, array $payload = [])
    {
        $reqId = (string) Str::uuid();
        $start = microtime(true);
        $this->logChannel()->info('openai.request', [
            'event' => 'openai.request',
            'request_id' => $reqId,
            'method' => strtoupper($method),
            'url' => ltrim($url, '/'),
            'payload' => $payload,
        ]);

        try {
            $pendingRequest = $this->client();

            if (!count($payload)) {
                $resp = $pendingRequest->send($method, ltrim($url, '/'));
            } else {
                $resp = $pendingRequest->send($method, ltrim($url, '/'), [
                    'json' => $payload,
                ]);
            }

            $duration = (int) round((microtime(true) - $start) * 1000);

            $status = $resp->status();
            $body = (string) $resp->body();
            $json = null;
            try {
                $json = $resp->json();
            } catch (Throwable $e) {
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
        } catch (Throwable $e) {
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

    /**
     * @param string $url
     * @param array $headers
     * @return array|mixed
     * @throws Throwable
     */
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
            } catch (Throwable $e) {
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
        } catch (Throwable $e) {
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

    /**
     * @param array $options
     * @return array
     * @throws Throwable
     */
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

    /**
     * @param array $messages
     * @param string|null $model
     * @param array $options
     * @return array
     * @throws Throwable
     */
    public function chat(array $messages, ?string $model = null, array $options = []): array
    {
        $payload = array_merge($options, [
            'model' => $model ?? config('openai.models.chat'),
            'messages' => $messages,
        ]);
        return $this->request('POST', '/chat/completions', $payload);
    }

    /**
     * @param string|array $input
     * @param string|null $model
     * @param array $options
     * @return array
     * @throws Throwable
     */
    public function embeddings(string|array $input, ?string $model = null, array $options = []): array
    {
        $payload = array_merge($options, [
            'model' => $model ?? config('openai.models.embeddings'),
            'input' => $input,
        ]);
        return $this->request('POST', '/embeddings', $payload);
    }

    /**
     * Wrapper for the Responses API list endpoint with sensible defaults and proper headers.
     * This replaces the previous raw curl/Guzzle snippet.
     *
     * Supported $query keys include: created_after, created_before, limit, order, input_item_limit, output_item_limit.
     * Use $include for repeated include[] values (e.g. message.input_image.image_url).
     */
    public function getResponses(array $query = [], array $include = []): array
    {
        $query = array_filter([
            'created_after' => $query['created_after'] ?? null,
            'created_before' => $query['created_before'] ?? null,
            'limit' => $query['limit'] ?? null,
            'order' => $query['order'] ?? null,
            'input_item_limit' => $query['input_item_limit'] ?? 1,
            'output_item_limit' => $query['output_item_limit'] ?? 1,
        ], fn($v) => $v !== null);

        $include = $include ?: [
            'message.input_text',
            'output_text',
            // add more includes as needed for richer previews
        ];

        return $this->responsesList($query, $include);
    }

    /**
     * @param string $prompt
     * @param array $options
     * @return array
     * @throws Throwable
     */
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

    /**
     * @param string $filePath
     * @param array $options
     * @return array|mixed
     * @throws ConnectionException
     * @throws RequestException
     * @throws Throwable
     */
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
        } catch (Throwable $e) {
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

    /**
     * @param string $text
     * @param array $options
     * @return string
     * @throws ConnectionException
     * @throws RequestException
     */
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

    /**
     * @param string $path
     * @param string $purpose
     * @return array
     * @throws ConnectionException
     * @throws RequestException
     * @throws Throwable
     */
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
        } catch (Throwable $e) {
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

    /**
     * @return array
     * @throws Throwable
     */
    public function fileList(): array
    {
        return $this->request('GET', '/files');
    }

    /**
     * @param string $fileId
     * @return array
     * @throws Throwable
     */
    public function fileRetrieve(string $fileId): array
    {
        return $this->request('GET', "/files/{$fileId}");
    }

    /**
     * @param string $fileId
     * @return array
     * @throws Throwable
     */
    public function fileDelete(string $fileId): array
    {
        return $this->request('DELETE', "/files/{$fileId}");
    }

    /**
     * @param array $data
     * @return array
     * @throws Throwable
     */
    public function assistantsCreate(array $data): array
    {
        $data['model'] = $data['model'] ?? config('openai.models.chat');
        return $this->request('POST', '/assistants', $data);
    }

    /**
     * @param string $assistantId
     * @return array
     * @throws Throwable
     */
    public function assistantsRetrieve(string $assistantId): array
    {
        return $this->request('GET', "/assistants/{$assistantId}");
    }

    /**
     * @param array $query
     * @return array
     * @throws ConnectionException
     * @throws RequestException
     */
    public function assistantsList(array $query = []): array
    {
        return $this->client()->get('/assistants', $query)->throw()->json();
    }

    /**
     * @param string $assistantId
     * @return array
     * @throws Throwable
     */
    public function assistantsDelete(string $assistantId): array
    {
        return $this->request('DELETE', "/assistants/{$assistantId}");
    }

    /**
     * @param array $data
     * @return array
     * @throws Throwable
     */
    public function vectorStoreCreate(array $data): array
    {
        return $this->request('POST', '/vector_stores', $data);
    }

    /**
     * @param string $storeId
     * @return array
     * @throws Throwable
     */
    public function vectorStoreRetrieve(string $storeId): array
    {
        return $this->request('GET', "/vector_stores/{$storeId}");
    }

    /**
     * @param array $query
     * @return array
     * @throws ConnectionException
     * @throws RequestException
     */
    public function vectorStoreList(array $query = []): array
    {
        return $this->client()->get('/vector_stores', $query)->throw()->json();
    }

    /**
     * @param string $storeId
     * @return array
     * @throws Throwable
     */
    public function vectorStoreDelete(string $storeId): array
    {
        return $this->request('DELETE', "/vector_stores/{$storeId}");
    }

    /**
     * @param string $storeId
     * @param string $fileId
     * @return array
     * @throws Throwable
     */
    public function vectorStoreAddFile(string $storeId, string $fileId): array
    {
        return $this->request('POST', "/vector_stores/{$storeId}/files", ['file_id' => $fileId]);
    }

    /**
     * @param string $storeId
     * @param array $query
     * @return array
     * @throws ConnectionException
     * @throws RequestException
     */
    public function vectorStoreListFiles(string $storeId, array $query = []): array
    {
        return $this->client()->get("/vector_stores/{$storeId}/files", $query)->throw()->json();
    }

    /**
     * @param string $storeId
     * @param string $fileId
     * @return array
     * @throws Throwable
     */
    public function vectorStoreDeleteFile(string $storeId, string $fileId): array
    {
        return $this->request('DELETE', "/vector_stores/{$storeId}/files/{$fileId}");
    }

    /**
     * @param string $storeId
     * @param string $fileId
     * @param array $data
     * @return array
     * @throws Throwable
     */
    public function vectorStoreFileMetadataUpdate(string $storeId, string $fileId, array $data): array
    {
        $metadata = $data['metadata'] ?? [];

        if (!empty($metadata) && (!empty($data['attributes']))) {
            $metadata['attributes'] = $data['attributes'];
        }

        return $this->request('POST', "/vector_stores/{$storeId}/files/{$fileId}", $metadata);
    }

    /**
     * @param string $storeId
     * @param string $fileId
     * @return array
     * @throws Throwable
     */
    public function vectorStoreGetFile(string $storeId, string $fileId): array
    {
        return $this->request('GET', "/vector_stores/{$storeId}/files/{$fileId}");
    }

    /**
     * @param array $params
     * @return array
     */
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
