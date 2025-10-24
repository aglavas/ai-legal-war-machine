# MCP Tools - Universal Access Fix

## Problem

The OdlukeAgent was initially configured to use MCP tools via HTTP transport, which caused it to fail when used via:
- Vizra dashboard
- Artisan commands
- Internal API calls

The HTTP transport required the agent to make network requests to itself (`http://localhost/mcp/message`), which:
- Failed due to networking issues
- Was inefficient (HTTP overhead for in-process calls)
- Didn't work if APP_URL was misconfigured
- Failed in certain hosting environments

## Solution

Created a **triple-access architecture** where MCP tools can be accessed via:

1. **Direct/Internal** - In-process calls (dashboard, artisan, internal use)
2. **HTTP MCP Protocol** - Standard MCP over HTTP (external clients)
3. **OpenAI-Compatible API** - For OpenAI Playground and GPT models

## Architecture Overview

```
┌────────────────────────────────────────────────────────────┐
│                     OdlukeTools.php                         │
│         (Core implementation of all 5 tools)                │
└──────────────┬──────────────┬──────────────┬───────────────┘
               │              │              │
      ┌────────┘              │              └─────────┐
      │                       │                        │
      ▼                       ▼                        ▼
┌────────────┐        ┌──────────────┐       ┌────────────────┐
│  Internal  │        │  HTTP MCP    │       │  OpenAI API    │
│  Direct    │        │  Protocol    │       │  Bridge        │
│  Tools     │        │  Endpoint    │       │                │
└─────┬──────┘        └──────┬───────┘       └────────┬───────┘
      │                      │                         │
      ▼                      ▼                         ▼
┌────────────┐        ┌─────────────┐        ┌────────────────┐
│ OdlukeAgent│        │  External   │        │  OpenAI        │
│ (Vizra ADK)│        │  MCP Clients│        │  Playground    │
│ Dashboard  │        │  HTTP       │        │  GPT Models    │
│ Artisan    │        │  Requests   │        │                │
└────────────┘        └─────────────┘        └────────────────┘
```

## Implementation Details

### 1. Internal/Direct Access (NEW)

**Purpose:** In-process tool access without HTTP overhead

**Components:**

```
app/Services/Mcp/InternalMcpClient.php
├── Provides direct method calls to OdlukeTools
├── No HTTP overhead
└── Used by Vizra ADK tool wrappers

app/Tools/
├── OdlukeSearchTool.php         (Vizra ADK wrapper)
├── OdlukeMetaTool.php           (Vizra ADK wrapper)
├── OdlukeDownloadTool.php       (Vizra ADK wrapper)
├── LawArticlesSearchTool.php    (Vizra ADK wrapper)
└── LawArticleByIdTool.php       (Vizra ADK wrapper)

app/Providers/InternalMcpServiceProvider.php
└── Registers InternalMcpClient as singleton

app/Agents/OdlukeAgent.php
└── Uses tools directly (no HTTP, no MCP servers)
```

**How it works:**

```php
// OdlukeAgent configuration
protected array $tools = [
    OdlukeSearchTool::class,    // Direct tool registration
    OdlukeMetaTool::class,      // No HTTP required
    OdlukeDownloadTool::class,
    LawArticlesSearchTool::class,
    LawArticleByIdTool::class,
];

// Tool execution flow
OdlukeAgent → OdlukeSearchTool → InternalMcpClient → OdlukeTools → Result
```

**Benefits:**
- ✅ Works in all contexts (dashboard, artisan, API)
- ✅ No HTTP overhead
- ✅ No networking configuration required
- ✅ Instant execution (in-process)
- ✅ No APP_URL configuration issues

### 2. HTTP MCP Protocol (FIXED)

**Purpose:** Standard MCP protocol over HTTP for external clients

**Components:**

```
app/Http/Controllers/McpHttpController.php
└── Implements JSON-RPC 2.0 MCP protocol

routes/web.php
├── POST /mcp/message  (MCP protocol endpoint)
└── GET  /mcp/info     (Server information)
```

**Usage:**

```bash
# List tools
curl -X POST http://localhost/mcp/message \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": "1",
    "method": "tools/list"
  }'

# Call a tool
curl -X POST http://localhost/mcp/message \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": "2",
    "method": "tools/call",
    "params": {
      "name": "odluke-search",
      "arguments": {"q": "radni odnos", "limit": 5}
    }
  }'
```

**When to use:**
- External MCP clients
- Remote tool access
- Testing via curl/Postman
- Integration with other systems

### 3. OpenAI-Compatible API

**Purpose:** Expose tools as OpenAI functions for GPT models

**Components:**

```
app/Services/McpToOpenAIBridge.php
└── Converts MCP tools to OpenAI function format

app/Http/Controllers/McpOpenAIController.php
└── OpenAI-compatible chat completions endpoint

routes/api.php
├── GET  /api/mcp-openai/tools
├── POST /api/mcp-openai/tools/execute
└── POST /api/mcp-openai/chat/completions  (main endpoint)
```

**Usage:**

```bash
curl -X POST http://localhost/api/mcp-openai/chat/completions \
  -H "Content-Type: application/json" \
  -d '{
    "model": "gpt-4o-mini",
    "messages": [
      {"role": "user", "content": "Find Croatian laws about labor"}
    ]
  }'
```

**When to use:**
- OpenAI Playground integration
- GPT models need to use your tools
- Custom chat interfaces
- AI-powered search

## Usage Examples

### Dashboard/Artisan (Internal - Direct)

```php
use App\Agents\OdlukeAgent;

// Works everywhere now!
$agent = new OdlukeAgent();
$response = $agent->run('Pronađi mi 5 najnovijih odluka o radnim odnosima');

// Artisan command
php artisan vizra:agent odluke_agent "search for labor law decisions"
```

### External MCP Client (HTTP Protocol)

```php
// From another service
$response = Http::post('https://your-app.com/mcp/message', [
    'jsonrpc' => '2.0',
    'id' => 'req-123',
    'method' => 'tools/call',
    'params' => [
        'name' => 'odluke-search',
        'arguments' => ['q' => 'ugovor', 'limit' => 10],
    ],
]);
```

### OpenAI Playground (OpenAI API)

```javascript
// Your custom frontend
const response = await fetch('/api/mcp-openai/chat/completions', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    messages: [
      { role: 'user', content: 'Search for laws about employment contracts' }
    ]
  })
});
```

## Configuration

### Environment Variables

```env
# No longer required for internal use!
# APP_URL only needed for external HTTP access

# Optional: Disable HTTP transport if only using internal
MCP_ODLUKE_ENABLED=false

# Optional: Custom URL for HTTP transport
MCP_ODLUKE_URL=https://your-domain.com/mcp/message
```

### Vizra ADK Config

```php
// config/vizra-adk.php

// HTTP transport (optional, for external clients only)
'mcp_servers' => [
    'odluke' => [
        'transport' => 'http',
        'url' => env('MCP_ODLUKE_URL', rtrim(env('APP_URL'), '/') . '/mcp/message'),
        'enabled' => env('MCP_ODLUKE_ENABLED', false), // Disabled by default
    ],
],
```

### Service Providers

```php
// bootstrap/providers.php
return [
    App\Providers\McpOdlukeServiceProvider::class,  // php-mcp/server registration
    App\Providers\InternalMcpServiceProvider::class, // Internal direct access
    // ...
];
```

## Available Tools

All 5 tools accessible via all 3 methods:

1. **odluke-search** / **odluke_search**
   - Search judicial decisions from odluke.sudovi.hr
   - Parameters: q, params, limit, page, base_url

2. **odluke-meta** / **odluke_meta**
   - Fetch metadata for decision IDs
   - Parameters: id, ids, base_url

3. **odluke-download** / **odluke_download**
   - Download decision PDF/HTML
   - Parameters: id, format, save, base_url

4. **law-articles-search** / **law_articles_search**
   - Search Croatian laws and articles
   - Parameters: query, law_number, title, limit

5. **law-article-by-id** / **law_article_by_id**
   - Get specific law article by ID
   - Parameters: id

## Testing

### Test Internal (Direct)

```bash
php artisan tinker

# Test InternalMcpClient directly
$client = app(App\Services\Mcp\InternalMcpClient::class);
$result = $client->callTool('odluke-search', ['q' => 'test', 'limit' => 5]);
print_r($result);

# Test via OdlukeAgent
$agent = new App\Agents\OdlukeAgent();
$response = $agent->run('Pronađi mi odluke o radnim sporovima');
echo $response;
```

### Test HTTP MCP Protocol

```bash
# Get info
curl http://localhost/mcp/info

# List tools
curl -X POST http://localhost/mcp/message \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":"1","method":"tools/list"}'

# Call tool
curl -X POST http://localhost/mcp/message \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": "2",
    "method": "tools/call",
    "params": {
      "name": "law-articles-search",
      "arguments": {"query": "zakon o radu", "limit": 3}
    }
  }'
```

### Test OpenAI API

```bash
curl -X POST http://localhost/api/mcp-openai/chat/completions \
  -H "Content-Type: application/json" \
  -d '{
    "messages": [{"role": "user", "content": "Find laws about employment"}]
  }'
```

## Logging

All tool calls are logged with context:

```bash
# Internal calls
tail -f storage/logs/laravel.log | grep "Internal MCP"

# HTTP calls
tail -f storage/logs/laravel.log | grep "MCP HTTP"

# OpenAI bridge calls
tail -f storage/logs/laravel.log | grep "MCP-OpenAI"
```

## Files Changed

### Created:
```
+ app/Services/Mcp/InternalMcpClient.php         (Direct tool access)
+ app/Providers/InternalMcpServiceProvider.php   (Service registration)
+ app/Tools/OdlukeSearchTool.php                 (Vizra ADK wrapper)
+ app/Tools/OdlukeMetaTool.php                   (Vizra ADK wrapper)
+ app/Tools/OdlukeDownloadTool.php               (Vizra ADK wrapper)
+ app/Tools/LawArticlesSearchTool.php            (Vizra ADK wrapper)
+ app/Tools/LawArticleByIdTool.php               (Vizra ADK wrapper)
+ app/Http/Controllers/McpHttpController.php     (HTTP MCP protocol)
+ app/Http/Controllers/McpOpenAIController.php   (OpenAI bridge)
+ app/Services/McpToOpenAIBridge.php             (OpenAI conversion)
```

### Modified:
```
~ app/Agents/OdlukeAgent.php        (Uses direct tools instead of HTTP)
~ app/Mcp/OdlukeTools.php           (Added law article tools)
~ bootstrap/providers.php           (Registered InternalMcpServiceProvider)
~ routes/web.php                    (Added /mcp routes)
~ routes/api.php                    (Added /api/mcp-openai routes)
```

## Summary

**The OdlukeAgent now works in ALL contexts:**

✅ Vizra Dashboard - Direct tool access
✅ Artisan Commands - Direct tool access
✅ Internal API calls - Direct tool access
✅ External HTTP - MCP protocol endpoint
✅ OpenAI Playground - OpenAI-compatible API

**No HTTP required for internal use.**
**No configuration hassles.**
**Works everywhere, instantly.**

