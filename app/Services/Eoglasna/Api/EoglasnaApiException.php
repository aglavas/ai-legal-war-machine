<?php

namespace App\Services\Eoglasna\Api;

use Exception;

class EoglasnaApiException extends Exception
{
    public function __construct(string $message, public ?int $status = null, public ?array $payload = null)
    {
        parent::__construct($message);
    }
}
