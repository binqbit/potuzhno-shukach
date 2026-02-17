# Potuzhno Shukach

## Description

Potuzhno Shukach is a web search app that returns a simple list of results (title + snippet + link), localized for English and Ukrainian.

## Stack

- Frontend: React + Vite + TailwindCSS (EN/UA UI)
- Backend (default): Python + FastAPI (OpenAI Web Search + vision for pasted images)
- Backend (optional): PHP (same API)
- Runtime (local testing): Docker Compose (Nginx serves UI and proxies `/api/*` to backend)
- Nginx config: `config/nginx.conf`

## Quick start (Docker)

1) Create `.env` from the template:

```bash
cp .env.example .env
```

2) Set `OPENAI_API_KEY` in `.env`.

3) Run:

```bash
docker compose up --build
```

Open `http://localhost:${WEB_PORT:-8080}`.

## PHP backend (optional)

Run the same app but with the PHP backend instead of Python:

```bash
docker compose -f docker-compose.yml -f docker-compose.php.yml up --build
```

## Deploy to shared hosting (PHP + static, single folder)

Docker Compose is intended for local testing. For a typical shared hosting (Apache + PHP), build a single upload folder:

```powershell
./scripts/export-hosting.ps1
```

Upload everything from `hosting/` into your site root (`public_html`, `www`, etc), or upload `hosting/hosting.zip` (or `hosting/hosting.tar.gz` as fallback) and extract on the server.

Create `hosting/.env` (or set hosting environment variables):

```bash
OPENAI_API_KEY=...
# OPENAI_MODEL=gpt-4o-mini
# MAX_RESULTS=8
```

Check:
- `GET /api/health` → `{ "status": "ok" }`

Notes:
- A purely static hosting (no PHP/backend) is not supported because it would expose `OPENAI_API_KEY`.
- If your hosting returns 500 because of `.htaccess` `Options`, remove the `Options ...` lines.

## Environment

- `OPENAI_API_KEY` (required)
- `OPENAI_MODEL` (optional, default: `gpt-4o-mini`)
- `MAX_RESULTS` (optional, default: `8`)
- `WEB_PORT` (optional, default: `8080`, Docker only)

## API

- `GET /api/health` → `{ "status": "ok" }`
- `POST /api/search`

Notes:
- `lang` is optional; if omitted, the backend uses the `Accept-Language` header (supported: `en`, `uk`; default: `uk`).
- Either `query` or `images` is required.
- `images` is an array of `data:image/*` URLs (the UI attaches them automatically on paste).

Request:

```json
{ "query": "string (optional)", "images": ["data:image/png;base64,..."], "lang": "en|uk", "limit": 8 }
```

Response:

```json
{ "query": "string", "lang": "en|uk", "results": [{ "title": "...", "url": "https://...", "snippet": "...", "source": "..." }], "answer": "short direct answer", "took_ms": 123 }
```
