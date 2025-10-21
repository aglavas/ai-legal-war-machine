<?php

namespace App\GraphQL\AutoDiscovery\Exceptions;

use Throwable;

class GraphQLQueryException extends \RuntimeException
{
    public array $errors;

    public function __construct(string $message, array $errors = [], int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }
}
