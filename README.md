<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

# MCP Tools

## Textract searchable-PDF pipeline
This project includes a pipeline that:
- fetches PDFs from a Google Drive folder (via Service Account),
- uploads them to S3,
- runs AWS Textract OCR asynchronously,
- reconstructs a “searchable” PDF by overlaying an invisible text layer (FPDI + TCPDF),
- stores the raw Textract JSON and the final searchable PDF back to S3.

### 1) Install PHP packages
Already included in composer.json; if needed, ensure dependencies are installed:

```
composer install
```

### 2) Configure .env
Add the following keys (see `.env.example`):

```
# Google Drive (Service Account)
GOOGLE_APPLICATION_CREDENTIALS=/absolute/path/to/service-account.json
GOOGLE_DRIVE_FOLDER_ID=YOUR_FOLDER_ID
GOOGLE_IMPERSONATE_USER=

# AWS / S3 / Textract
AWS_DEFAULT_REGION=eu-central-1
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_BUCKET=your-s3-bucket
AWS_URL=
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=false

# S3 prefixes for pipeline artifacts
S3_INPUT_PREFIX=textract/input
S3_OUTPUT_PREFIX=textract/output
S3_JSON_PREFIX=textract/json

# Queue
QUEUE_CONNECTION=database
```

Share the Drive folder with the Service Account email and enable the Drive API in Google Cloud.
Ensure the S3 bucket exists and the IAM user/role has permissions:
- s3:GetObject, s3:PutObject, s3:ListBucket
- textract:StartDocumentTextDetection, textract:GetDocumentTextDetection

### 3) Migrate database

```
php artisan migrate --force
```

This creates the `textract_jobs` table for job tracking.

### 4) Run the pipeline
Start a queue worker and enqueue jobs for a Drive folder:

```
php artisan queue:work --queue=textract
php artisan textract:process-drive-folder YOUR_FOLDER_ID --limit=3
```

Artifacts will appear on S3:
- `${AWS_BUCKET}/${S3_JSON_PREFIX}/{drive_file_id}.json`
- `${AWS_BUCKET}/${S3_OUTPUT_PREFIX}/{drive_file_id}-searchable.pdf`

### Notes
- FPDI/TCPDF overlays OCR LINE blocks as invisible text; adjust opacity in `PdfReconstructor` if you want to debug placement.
- For large PDFs, consider SNS/SQS instead of polling Textract.
- The pipeline defaults to LINE blocks; WORD-level placement is possible with minor changes.

## Odluke Agent (ChatGPT + MCP)

This app includes an autonomous agent `odluke_agent` powered by OpenAI (ChatGPT) via Vizra ADK. It uses MCP tools to search and download decisions from odluke.sudovi.hr.

### Configure

Set the following in your `.env`:

- `APP_URL` (e.g., `http://localhost:8000`)
- `OPENAI_API_KEY` (required)
- optional: `MCP_ODLUKE_URL` (defaults to `${APP_URL}/mcp/message`)

The MCP HTTP server is exposed at `/mcp/message` and is auto-registered by `php-mcp/laravel`.

### Quick start

1) Start the Laravel server and queues as usual.

2) Call the agent via Vizra ADK API:

```
curl -sS -X POST "$APP_URL/api/vizra-adk/interact" \
  -H 'Content-Type: application/json' \
  -d '{
    "agent_name": "odluke_agent",
    "input": "Pronađi najnovije presude o ugovoru o radu i preuzmi PDF jedne relevantne presude"
  }'
```

3) Optional: test MCP directly

- Initialize and list tools
```
curl -sS -X POST "$APP_URL/mcp/message" -H 'Content-Type: application/json' -d '{
  "jsonrpc":"2.0","id":1,
  "method":"initialize",
  "params":{
    "protocolVersion":"2024-11-05",
    "capabilities":{"tools":{},"resources":{},"prompts":{}},
    "clientInfo":{"name":"local","version":"dev"}
  }
}'

curl -sS -X POST "$APP_URL/mcp/message" -H 'Content-Type: application/json' -d '{
  "jsonrpc":"2.0","id":2,
  "method":"tools/list"
}'
```

- Call `odluke-search`
```
curl -sS -X POST "$APP_URL/mcp/message" -H 'Content-Type: application/json' -d '{
  "jsonrpc":"2.0","id":3,
  "method":"tools/call",
  "params":{ "name":"odluke-search", "arguments":{ "q":"ugovor o radu", "limit":25, "page":1 }}
}'
```

### What the agent does

- Uses `odluke-search` to find IDs by topic/filters.
- Uses `odluke-meta` to score and shortlist results.
- Uses `odluke-download` to fetch PDF/HTML (set `save=true` when appropriate).
- Produces a concise Croatian or user-language summary and listed downloads.

### Notes

- Saved files default to `storage/app/odluke`. Configure via `config/odluke.php` if present.
- Ensure `APP_URL` is correct for MCP HTTP calls.
- The agent uses model `gpt-4.1-mini` by default; change in `App/Agents/OdlukeAgent.php` if needed.
