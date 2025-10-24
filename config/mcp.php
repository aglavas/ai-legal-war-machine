<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MCP API Authentication Token
    |--------------------------------------------------------------------------
    |
    | This token is used to authenticate requests to MCP endpoints.
    | Set MCP_API_TOKEN in your .env file to enable authentication.
    |
    | If not set, authentication is disabled (for backward compatibility).
    | In production, this MUST be set to a secure random token.
    |
    */

    'api_token' => env('MCP_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for MCP endpoints.
    | Format: max_attempts:decay_minutes
    |
    */

    'rate_limit' => env('MCP_RATE_LIMIT', '60:1'),

];
