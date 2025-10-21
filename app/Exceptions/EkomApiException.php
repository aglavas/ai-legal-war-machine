<?php

namespace App\Exceptions;

use Exception;
use Psr\Http\Message\ResponseInterface;

class EkomApiException extends Exception
{
    public function __construct(
        public readonly int $statusCode,
        public readonly ?string $errorId = null,
        public readonly ?string $errorMessage = null,
        public readonly ?array $errorMessages = null,
        public readonly ?string $responseBody = null
    ) {
        $message = "e-Komunikacija API error ({$statusCode})";
        if ($errorId) {
            $message .= " [id: {$errorId}]";
        }
        if ($errorMessage) {
            $message .= " {$errorMessage}";
        }
        parent::__construct($message, $statusCode);
    }

    public static function fromResponse(ResponseInterface $response): self
    {
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $errorId = null;
        $errorMessage = null;
        $errorMessages = null;

        $contentType = $response->getHeaderLine('Content-Type');
        if (str_contains($contentType, 'application/json')) {
            $json = json_decode($body, true);
            if (is_array($json)) {
                // The API uses somewhat different schemas for errors; try to map both.
                $errorId = $json['id'] ?? null;
                $errorMessage = $json['message'] ?? null;
                $errorMessages = $json['messages'] ?? null;
            }
        }

        return new self(
            statusCode: $status,
            errorId: $errorId,
            errorMessage: $errorMessage,
            errorMessages: $errorMessages,
            responseBody: $body
        );
    }
}
