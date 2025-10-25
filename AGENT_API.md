# Autonomous Agent API Documentation

This document describes the REST API endpoints for the Autonomous Research Agent system.

## Base URL

```
http://your-domain.com/api/agent
```

## Authentication

Currently, the API endpoints are unauthenticated. In production, you should add authentication middleware.

---

## Endpoints

### 1. Start Research Run

Start a new autonomous research run with a given objective.

**Endpoint:** `POST /api/agent/research/start`

**Request Body:**

```json
{
  "objective": "Research Croatian labor law regarding termination procedures",
  "topics": ["labor law", "termination", "employment"],
  "jurisdiction": "Croatia",
  "max_iterations": 10,
  "threshold": 0.75,
  "token_budget": 50000,
  "cost_budget": 1.00,
  "time_limit_seconds": 600
}
```

**Parameters:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `objective` | string | Yes | The research objective (min 10 chars) |
| `topics` | array | No | Array of topic strings to focus on |
| `jurisdiction` | string | No | Jurisdiction to focus on (default: "Croatia") |
| `max_iterations` | integer | No | Maximum research iterations (1-50, default: 10) |
| `threshold` | float | No | Quality threshold 0-1 (default: 0.75) |
| `token_budget` | number | No | Maximum tokens allowed |
| `cost_budget` | number | No | Maximum cost in USD |
| `time_limit_seconds` | integer | No | Max execution time 10-7200 seconds |

**Response (200 OK):**

```json
{
  "success": true,
  "run": {
    "id": 123,
    "objective": "Research Croatian labor law regarding termination procedures",
    "status": "completed",
    "score": 0.823,
    "iterations": 8,
    "elapsed_seconds": 245,
    "final_output": "# Research Report: Croatian Labor Law...\n\n..."
  }
}
```

**Response (500 Error):**

```json
{
  "success": false,
  "error": "Failed to start research run: insufficient resources"
}
```

---

### 2. List Research Runs

Get a list of all research runs with optional filtering.

**Endpoint:** `GET /api/agent/research`

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `status` | string | Filter by status: running, completed, failed |
| `agent_name` | string | Filter by agent name |
| `limit` | integer | Max results (1-100, default: 20) |

**Example:**

```
GET /api/agent/research?status=completed&limit=10
```

**Response (200 OK):**

```json
{
  "success": true,
  "runs": [
    {
      "id": 123,
      "agent_name": "autonomous_research_agent",
      "objective": "Research Croatian labor law...",
      "status": "completed",
      "score": 0.823,
      "iterations": 8,
      "started_at": "2025-10-24T10:30:00Z",
      "completed_at": "2025-10-24T10:34:05Z",
      "elapsed_seconds": 245
    },
    ...
  ],
  "count": 10
}
```

---

### 3. Get Research Run Details

Get detailed information about a specific research run.

**Endpoint:** `GET /api/agent/research/{id}`

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | The run ID |

**Example:**

```
GET /api/agent/research/123
```

**Response (200 OK):**

```json
{
  "success": true,
  "run": {
    "id": 123,
    "agent_name": "autonomous_research_agent",
    "objective": "Research Croatian labor law regarding termination procedures",
    "topics": ["labor law", "termination", "employment"],
    "status": "completed",
    "score": 0.823,
    "threshold": 0.75,
    "current_iteration": 8,
    "max_iterations": 10,
    "tokens_used": 42350,
    "cost_spent": 0.15,
    "elapsed_seconds": 245,
    "started_at": "2025-10-24T10:30:00Z",
    "completed_at": "2025-10-24T10:34:05Z",
    "final_output": "# Research Report...\n\n...",
    "error": null,
    "iterations": [
      {
        "number": 1,
        "started_at": "2025-10-24T10:30:00Z",
        "plan": {
          "reasoning": "Start with broad vector search...",
          "actions": [...]
        },
        "actions": [
          {
            "tool": "vector_search",
            "params": {...},
            "result": {...},
            "success": true
          }
        ],
        "evaluation": {
          "insights": ["Found relevant law: ZR (NN 93/14)"],
          "insights_count": 1,
          "should_stop": false
        },
        "completed_at": "2025-10-24T10:30:28Z"
      },
      ...
    ]
  }
}
```

**Response (404 Not Found):**

```json
{
  "success": false,
  "error": "Run not found"
}
```

---

### 4. Get Evaluation Report

Get a detailed evaluation report for a completed research run.

**Endpoint:** `GET /api/agent/research/{id}/evaluation`

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | The run ID |

**Example:**

```
GET /api/agent/research/123/evaluation
```

**Response (200 OK):**

```json
{
  "success": true,
  "run_id": 123,
  "report": "# Evaluation Report: Run #123\n\n## Overview\n\n- **Objective**: Research Croatian labor law...\n- **Status**: completed\n- **Overall Score**: 0.823 / 1.0\n- **Passed**: Yes\n- **Threshold**: 0.75\n\n## Evaluation Criteria\n\n### ✓ Completeness\n\n- **Score**: 0.85\n- **Feedback**: Addresses all aspects of the objective\n\n### ✓ Citations\n\n- **Score**: 0.80\n- **Feedback**: Found 5 citation(s). Good practice.\n\n### ✓ Relevance\n\n- **Score**: 0.82\n- **Feedback**: Content is highly relevant to objective\n\n### ✓ Quality\n\n- **Score**: 0.90\n- **Feedback**: Output quality is good.\n\n### ✓ Evidence\n\n- **Score**: 0.75\n- **Feedback**: Adequate evidence from 8 research action(s).\n\n## Recommendations\n\nThis run met all quality criteria. Well done!\n"
}
```

**Response (400 Bad Request):**

```json
{
  "success": false,
  "error": "Run is not completed yet"
}
```

**Response (404 Not Found):**

```json
{
  "success": false,
  "error": "Run not found"
}
```

---

### 5. Delete Research Run

Delete a research run and its associated data.

**Endpoint:** `DELETE /api/agent/research/{id}`

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | The run ID |

**Example:**

```
DELETE /api/agent/research/123
```

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Run deleted successfully"
}
```

**Response (404 Not Found):**

```json
{
  "success": false,
  "error": "Run not found"
}
```

---

## Web Interface

In addition to the API, there are web interface routes:

- **Dashboard:** `/agent/dashboard` - View all research runs with statistics
- **Run Details:** `/agent/run/{id}` - View detailed information about a specific run

---

## CLI Commands

### Run Scheduled Research

```bash
php artisan agent:research-scheduled \
  --topics="labor law" \
  --topics="contract law" \
  --max-iterations=15 \
  --time-limit=1800 \
  --token-budget=100000 \
  --cost-budget=2.00
```

**Options:**

| Option | Description |
|--------|-------------|
| `--topics` | Specific topics to research (can be specified multiple times) |
| `--max-iterations` | Maximum research iterations (default: 10) |
| `--time-limit` | Time limit in seconds (default: 600) |
| `--token-budget` | Token budget |
| `--cost-budget` | Cost budget in USD |

If no topics are specified, the command will automatically determine active topics from recent successful runs.

---

## Agent Toolbox

The autonomous agent has access to the following tools:

### 1. vector_search

Semantic search across laws, cases, and court decisions.

**Parameters:**
- `query` (string): Search query
- `types` (array): Types to search - "laws", "cases", "decisions"
- `limit` (int): Max results per type
- `jurisdiction` (string): Filter by jurisdiction
- `min_similarity` (float): Minimum similarity score

### 2. law_lookup

Find specific laws by law number.

**Parameters:**
- `law_number` (string): Law number (e.g., "NN 94/14")
- `jurisdiction` (string): Optional jurisdiction filter

### 3. decision_lookup

Search court decisions by criteria.

**Parameters:**
- `case_number` (string): Case number
- `court` (string): Court name
- `jurisdiction` (string): Jurisdiction
- `from_date` (string): Start date (Y-m-d)
- `to_date` (string): End date (Y-m-d)
- `decision_type` (string): Type of decision
- `limit` (int): Max results

### 4. graph_query

Execute Cypher queries on the Neo4j knowledge graph.

**Parameters:**
- `cypher` (string): Cypher query
- `parameters` (object): Query parameters

### 5. web_fetch

Fetch content from external URLs.

**Parameters:**
- `url` (string): URL to fetch
- `timeout` (int): Request timeout
- `headers` (object): Additional headers
- `method` (string): HTTP method

### 6. note_save

Save insights to agent vector memory.

**Parameters:**
- `agent_name` (string): Agent name
- `content` (string): Content to save
- `namespace` (string): Memory namespace
- `metadata` (object): Additional metadata
- `source` (string): Source reference

---

## Evaluation Criteria

Research runs are evaluated on 5 criteria:

1. **Completeness (25%)** - Does the output fully address the objective?
2. **Citations (25%)** - Are proper legal citations included?
3. **Relevance (20%)** - Is the content focused and on-topic?
4. **Quality (15%)** - Structure, length, formatting
5. **Evidence (15%)** - Sufficient research actions performed?

Each criterion is scored 0-1, with a weighted average determining the overall score.

### Citation Patterns Recognized

- `NN 94/14` - Narodne Novine references
- `2024/123` - Case numbers
- `čl. 15` - Article references
- `st. 2` - Paragraph references

---

## Configuration

Agent behavior can be customized via `config/agent.php` or environment variables:

```env
# Default limits
AGENT_MAX_ITERATIONS=10
AGENT_TIME_LIMIT=600
AGENT_THRESHOLD=0.75
AGENT_TOKEN_BUDGET=
AGENT_COST_BUDGET=

# Scheduling
AGENT_SCHEDULE_CRON=weekly
AGENT_SCHEDULE_DAY=0
AGENT_SCHEDULE_TIME=02:00

# Performance
AGENT_ASYNC_EXECUTION=false
AGENT_CACHE_ENABLED=true
AGENT_CACHE_TTL=3600

# Safety
AGENT_MAX_COST=5.00
AGENT_MAX_TIME=3600
AGENT_MAX_CONCURRENT=5
AGENT_REQUIRE_APPROVAL=false

# Logging
AGENT_LOG_LEVEL=info
AGENT_LOG_ITERATIONS=true
AGENT_LOG_ACTIONS=true
```

---

## Error Handling

All endpoints return consistent error responses:

```json
{
  "success": false,
  "error": "Error message describing what went wrong"
}
```

Common HTTP status codes:
- `200` - Success
- `400` - Bad Request (validation error)
- `404` - Not Found
- `500` - Internal Server Error

---

## Rate Limiting

Currently, there are no rate limits. In production, consider adding:
- Per-IP rate limiting
- Per-user rate limiting
- Concurrent run limits (configured via `AGENT_MAX_CONCURRENT`)

---

## Best Practices

1. **Set reasonable budgets** - Use token_budget and cost_budget to prevent runaway costs
2. **Use time limits** - Always set time_limit_seconds to prevent hanging runs
3. **Monitor runs** - Check the dashboard regularly for failed runs
4. **Review evaluations** - Use evaluation reports to improve research quality
5. **Cleanup old runs** - Periodically delete old runs to save database space

---

## Example: Complete Workflow

```bash
# 1. Start a research run
curl -X POST http://localhost/api/agent/research/start \
  -H "Content-Type: application/json" \
  -d '{
    "objective": "Analyze Croatian data protection laws and GDPR compliance",
    "topics": ["data protection", "GDPR", "privacy"],
    "max_iterations": 10,
    "time_limit_seconds": 600,
    "cost_budget": 0.50
  }'

# Response: {"success": true, "run": {"id": 456, ...}}

# 2. Check run status
curl http://localhost/api/agent/research/456

# 3. Get evaluation report (once completed)
curl http://localhost/api/agent/research/456/evaluation

# 4. View in browser
# Visit: http://localhost/agent/run/456

# 5. Delete when done
curl -X DELETE http://localhost/api/agent/research/456
```

---

## Support

For issues or questions:
- Check the logs: `storage/logs/laravel.log`
- Review the configuration: `config/agent.php`
- Consult the main README: `README.md`
