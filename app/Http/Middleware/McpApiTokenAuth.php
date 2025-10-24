<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MCP API Token Authentication Middleware
 *
 * Validates API tokens for MCP endpoints.
 * Expects Authorization: Bearer <token> header.
 */
class McpApiTokenAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get token from environment
        $validToken = config('mcp.api_token');

        // If no token is configured, allow access (for backward compatibility)
        // In production, this should be enforced
        if (empty($validToken)) {
            return $next($request);
        }

        // Extract token from Authorization header
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Missing or invalid Authorization header. Expected: Bearer <token>',
            ], 401);
        }

        $token = substr($authHeader, 7); // Remove 'Bearer ' prefix

        // Validate token
        if ($token !== $validToken) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid API token',
            ], 401);
        }

        return $next($request);
    }
}
