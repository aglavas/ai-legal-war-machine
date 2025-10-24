# MCP-OpenAI Integration Guide

## Overview

This integration exposes your MCP (Model Context Protocol) tools as OpenAI-compatible function calling endpoints, allowing GPT models from OpenAI Playground to discover and use your custom tools.

## Architecture

```
OpenAI Playground/API
    ↓ (HTTP POST with function calling)
Your Laravel App (/api/mcp-openai/chat/completions)
    ↓ (forwards to OpenAI with tools)
OpenAI API
    ↓ (returns tool calls)
Your Laravel App (executes MCP tools)
    ↓ (returns results to OpenAI)
OpenAI API
    ↓ (final response with tool results)
User
```

## Available Endpoints

### 1. **GET /api/mcp-openai/info**
Get information about the MCP-OpenAI bridge and available tools.

```bash
curl https://your-domain.com/api/mcp-openai/info
```

**Response:**
```json
{
  "name": "MCP-OpenAI Bridge",
  "version": "1.0.0",
  "description": "Exposes MCP tools as OpenAI-compatible function calling endpoints",
  "available_tools": [
    "odluke_search",
    "odluke_meta",
    "odluke_download",
    "law_articles_search",
    "law_article_by_id"
  ],
  "tools_count": 5
}
```

### 2. **GET /api/mcp-openai/tools**
List all available MCP tools in OpenAI function format.

```bash
curl https://your-domain.com/api/mcp-openai/tools
```

**Response:**
```json
{
  "object": "list",
  "data": [
    {
      "id": "tool_odlukesearch",
      "type": "function",
      "function": {
        "name": "odluke_search",
        "description": "Search Odluke (judicial decisions)...",
        "parameters": {
          "type": "object",
          "properties": {
            "q": { "type": "string", "description": "..." },
            "limit": { "type": "integer", "minimum": 1, "maximum": 500 }
          }
        }
      }
    }
  ]
}
```

### 3. **POST /api/mcp-openai/tools/execute**
Execute a specific MCP tool directly.

```bash
curl -X POST https://your-domain.com/api/mcp-openai/tools/execute \
  -H "Content-Type: application/json" \
  -d '{
    "tool_name": "odluke_search",
    "arguments": {
      "q": "ugovor o radu",
      "limit": 5
    }
  }'
```

**Response:**
```json
{
  "tool_name": "odluke_search",
  "result": "{...JSON results from tool...}"
}
```

### 4. **POST /api/mcp-openai/chat/completions** ⭐ Main Endpoint
OpenAI-compatible chat completions endpoint that automatically includes MCP tools.

This endpoint:
- Accepts standard OpenAI chat completion requests
- Automatically includes all MCP tools as available functions
- Forwards requests to OpenAI API
- When OpenAI calls a tool, executes it automatically
- Returns the final response with tool results integrated

```bash
curl -X POST https://your-domain.com/api/mcp-openai/chat/completions \
  -H "Content-Type: application/json" \
  -d '{
    "model": "gpt-4o-mini",
    "messages": [
      {
        "role": "user",
        "content": "Pretraži odluke o ugovoru o radu i pronađi mi 3 najnovije"
      }
    ]
  }'
```

## Using with OpenAI Playground

### Option 1: Direct API Usage (Recommended)

OpenAI Playground doesn't natively support external function calling endpoints, but you can use your own proxy endpoint:

1. **Configure your app URL** in `.env`:
   ```env
   APP_URL=https://your-domain.com
   OPENAI_API_KEY=sk-...your-key...
   ```

2. **Use the chat completions endpoint** from your application code or via API clients like Postman:
   ```javascript
   // Example using fetch in your frontend
   const response = await fetch('https://your-domain.com/api/mcp-openai/chat/completions', {
     method: 'POST',
     headers: { 'Content-Type': 'application/json' },
     body: JSON.stringify({
       model: 'gpt-4o-mini',
       messages: [
         { role: 'user', content: 'Search for laws about labor contracts' }
       ]
     })
   });

   const data = await response.json();
   console.log(data.choices[0].message.content);
   ```

3. **GPT will automatically use your tools** when relevant to the conversation.

### Option 2: Manual Tool Integration

If you want to manually test tool calling in OpenAI Playground:

1. **Go to OpenAI Playground**: https://platform.openai.com/playground

2. **Enable Function Calling**: In the playground, enable "Functions" mode

3. **Add Function Definitions**: Copy function definitions from:
   ```bash
   curl https://your-domain.com/api/mcp-openai/tools
   ```

4. **Chat with GPT**: When GPT decides to use a function, you'll see the function call

5. **Execute the Tool**: Copy the function call and execute it via your API:
   ```bash
   curl -X POST https://your-domain.com/api/mcp-openai/tools/execute \
     -H "Content-Type: application/json" \
     -d '{
       "tool_name": "odluke_search",
       "arguments": { ... }
     }'
   ```

6. **Return Results to Playground**: Copy the results back to the playground as a "function" role message

### Option 3: Custom Frontend Integration

Create a custom chat interface that uses your endpoint:

```html
<!DOCTYPE html>
<html>
<head>
    <title>MCP Chat</title>
</head>
<body>
    <div id="chat"></div>
    <input id="input" type="text" placeholder="Ask me anything...">
    <button onclick="sendMessage()">Send</button>

    <script>
    const messages = [];

    async function sendMessage() {
        const input = document.getElementById('input');
        const message = input.value;
        input.value = '';

        messages.push({ role: 'user', content: message });

        const response = await fetch('/api/mcp-openai/chat/completions', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                model: 'gpt-4o-mini',
                messages: messages
            })
        });

        const data = await response.json();
        const reply = data.choices[0].message;
        messages.push(reply);

        document.getElementById('chat').innerHTML +=
            `<div><strong>You:</strong> ${message}</div>
             <div><strong>AI:</strong> ${reply.content}</div>`;
    }
    </script>
</body>
</html>
```

## Available MCP Tools

### 1. **odluke_search**
Search judicial decisions from odluke.sudovi.hr

**Parameters:**
- `q` (string, optional): Free text search query
- `params` (string, optional): Additional filter parameters
- `limit` (integer, optional): Max results (1-500, default 100)
- `page` (integer, optional): Page number (default 1)

**Example:**
```json
{
  "tool_name": "odluke_search",
  "arguments": {
    "q": "radni odnos",
    "limit": 10
  }
}
```

### 2. **odluke_meta**
Fetch metadata for decision IDs

**Parameters:**
- `id` (string, optional): Single decision ID (GUID)
- `ids` (array, optional): Array of decision IDs

**Example:**
```json
{
  "tool_name": "odluke_meta",
  "arguments": {
    "ids": ["guid1", "guid2"]
  }
}
```

### 3. **odluke_download**
Download decision PDF/HTML

**Parameters:**
- `id` (string, required): Decision ID (GUID)
- `format` (string, optional): Format (pdf|html|both, default: pdf)
- `save` (boolean, optional): Save locally (default: false)

**Example:**
```json
{
  "tool_name": "odluke_download",
  "arguments": {
    "id": "decision-guid-123",
    "format": "pdf",
    "save": true
  }
}
```

### 4. **law_articles_search**
Search ingested Croatian laws and articles

**Parameters:**
- `query` (string, optional): Free text search across laws
- `law_number` (string, optional): Filter by law number (e.g., "NN 123/20")
- `title` (string, optional): Filter by law title
- `limit` (integer, optional): Number of laws (1-100, default 10)

**Example:**
```json
{
  "tool_name": "law_articles_search",
  "arguments": {
    "query": "radni odnos",
    "limit": 5
  }
}
```

### 5. **law_article_by_id**
Get a single law article by ID

**Parameters:**
- `id` (string, required): Law article ID (ULID)

**Example:**
```json
{
  "tool_name": "law_article_by_id",
  "arguments": {
    "id": "01HQW9X1..."
  }
}
```

## Authentication (Optional)

To add authentication to your MCP-OpenAI endpoints, you can use Laravel's built-in middleware.

### Add Bearer Token Authentication

1. **Update routes/api.php**:
```php
Route::prefix('mcp-openai')->middleware('auth:sanctum')->group(function () {
    // ... existing routes
});
```

2. **Or create custom middleware**:
```bash
php artisan make:middleware McpApiAuth
```

```php
// app/Http/Middleware/McpApiAuth.php
public function handle(Request $request, Closure $next)
{
    $token = $request->bearerToken();

    if ($token !== config('services.mcp.api_key')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    return $next($request);
}
```

3. **Add to config/services.php**:
```php
'mcp' => [
    'api_key' => env('MCP_API_KEY', 'your-secret-key'),
],
```

## Testing

### Test Info Endpoint
```bash
curl https://your-domain.com/api/mcp-openai/info
```

### Test Tool Execution
```bash
curl -X POST https://your-domain.com/api/mcp-openai/tools/execute \
  -H "Content-Type: application/json" \
  -d '{
    "tool_name": "law_articles_search",
    "arguments": { "query": "zakon o radu", "limit": 3 }
  }'
```

### Test Chat Completions
```bash
curl -X POST https://your-domain.com/api/mcp-openai/chat/completions \
  -H "Content-Type: application/json" \
  -d '{
    "model": "gpt-4o-mini",
    "messages": [
      {
        "role": "user",
        "content": "Pronađi mi zakone o radnim odnosima"
      }
    ]
  }'
```

## Troubleshooting

### Common Issues

1. **"Tool execution failed"**
   - Check logs: `tail -f storage/logs/laravel.log`
   - Verify your OpenAI API key is configured
   - Ensure database has ingested laws (for law tools)

2. **"Class McpToOpenAIBridge not found"**
   - Run: `composer dump-autoload`

3. **Tools not being called**
   - Check that OpenAI has credits
   - Verify tool descriptions are clear
   - Make sure the user's question is relevant to the tools

4. **CORS errors** (if calling from frontend)
   - Configure CORS in `config/cors.php`
   - Add your domain to allowed origins

## Logging

All tool executions are logged with context:

```php
// View logs
tail -f storage/logs/laravel.log | grep "MCP-OpenAI"
```

## Next Steps

- **Add more tools**: Extend `OdlukeTools.php` with new MCP tools
- **Add authentication**: Protect endpoints with API keys
- **Create frontend**: Build a custom chat interface
- **Monitor usage**: Track tool calls and usage patterns
- **Rate limiting**: Add rate limits to prevent abuse

## Support

For issues or questions:
- Check logs: `storage/logs/laravel.log`
- Review OpenAI API docs: https://platform.openai.com/docs/guides/function-calling
- MCP Protocol: https://modelcontextprotocol.io

