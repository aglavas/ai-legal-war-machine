# OdlukeAgent Fix - Missing HTTP Endpoint

## Problem Identified

The `OdlukeAgent` (located in `app/Agents/OdlukeAgent.php`) was **non-functional** due to a missing HTTP endpoint.

### Root Cause

The agent was configured to use MCP tools via HTTP transport:

```php
// In config/vizra-adk.php
'odluke' => [
    'transport' => 'http',
    'url' => env('MCP_ODLUKE_URL', rtrim(env('APP_URL'), '/') . '/mcp/message'),
    'enabled' => true,
],
```

However, the `/mcp/message` endpoint **did not exist**, causing the agent to fail when attempting to call any tools.

## Solution Implemented

Created a complete MCP HTTP endpoint that:

1. **Implements MCP Protocol over HTTP** (`app/Http/Controllers/McpHttpController.php`)
   - JSON-RPC 2.0 compliant
   - Handles MCP protocol methods: `tools/list`, `tools/call`, `initialize`
   - Proper error handling and logging

2. **Exposes All MCP Tools** via HTTP
   - odluke-search
   - odluke-meta
   - odluke-download
   - law-articles-search (new)
   - law-article-by-id (new)

3. **Routes Added** (`routes/web.php`)
   ```php
   POST /mcp/message  // MCP protocol endpoint
   GET  /mcp/info     // Server information
   ```

## How It Works

```
OdlukeAgent (Vizra ADK)
    ↓ HTTP POST
/mcp/message endpoint
    ↓ JSON-RPC 2.0
McpHttpController
    ↓ Method dispatch
OdlukeTools::search/meta/download/etc.
    ↓ Result
Return MCP protocol response
```

## Files Changed

### Created:
- `app/Http/Controllers/McpHttpController.php` (334 lines)
  - Complete MCP protocol implementation
  - Tool listing and execution
  - JSON-RPC 2.0 format

### Modified:
- `routes/web.php` (+6 lines)
  - Added /mcp/message and /mcp/info routes

## Testing

### Test the endpoint:
```bash
# Get server info
curl http://your-domain.com/mcp/info

# List available tools
curl -X POST http://your-domain.com/mcp/message \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": "1",
    "method": "tools/list"
  }'

# Call a tool
curl -X POST http://your-domain.com/mcp/message \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": "2",
    "method": "tools/call",
    "params": {
      "name": "odluke-search",
      "arguments": {
        "q": "ugovor o radu",
        "limit": 5
      }
    }
  }'
```

### Test with OdlukeAgent:
```php
use App\Agents\OdlukeAgent;

$agent = new OdlukeAgent();
$response = $agent->run('Pronađi mi 5 najnovijih odluka o radnim odnosima');
```

## Configuration

The agent is configured in `config/vizra-adk.php`:

```php
'mcp_servers' => [
    'odluke' => [
        'transport' => 'http',
        'url' => env('MCP_ODLUKE_URL', rtrim(env('APP_URL', 'http://localhost'), '/') . '/mcp/message'),
        'enabled' => env('MCP_ODLUKE_ENABLED', true),
        'timeout' => env('MCP_ODLUKE_TIMEOUT', 45),
    ],
],
```

Make sure `APP_URL` is correctly set in your `.env`:
```env
APP_URL=https://your-domain.com
```

## Impact

✅ **OdlukeAgent is now fully functional**

The agent can now:
- Search judicial decisions from odluke.sudovi.hr
- Fetch metadata for decisions
- Download decision documents
- Search ingested Croatian laws
- Retrieve specific law articles

All through the HTTP MCP endpoint.

## Related Components

- **McpOdlukeServiceProvider** - Registers tools with php-mcp/server
- **OdlukeTools** - Contains tool implementations
- **McpHttpController** - HTTP transport layer for MCP protocol
- **OdlukeAgent** - Autonomous agent using these tools

## Logging

All MCP HTTP requests are logged:

```bash
tail -f storage/logs/laravel.log | grep "MCP HTTP"
```

Log entries include:
- Incoming requests (method, id)
- Tool calls (name, arguments)
- Responses (success/error)
- Failures (with stack traces)

## Security Notes

The `/mcp/message` endpoint is currently **public** (no authentication).

To add authentication:

1. **Option 1: Add middleware**
   ```php
   Route::post('/mcp/message', [McpHttpController::class, 'message'])
       ->middleware('auth:sanctum');
   ```

2. **Option 2: Custom API key**
   ```php
   // In McpHttpController::message()
   $apiKey = $request->header('X-MCP-API-Key');
   if ($apiKey !== config('services.mcp.api_key')) {
       return response()->json(['error' => 'Unauthorized'], 401);
   }
   ```

3. **Option 3: IP whitelist**
   ```php
   // Only allow localhost for internal agent use
   if (!in_array($request->ip(), ['127.0.0.1', '::1'])) {
       return response()->json(['error' => 'Forbidden'], 403);
   }
   ```

## Summary

This fix restores full functionality to the `OdlukeAgent` by providing the missing HTTP transport layer for MCP tools. The agent can now successfully execute all 5 available MCP tools via the standardized MCP protocol over HTTP.

