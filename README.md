# Potuzhno Shukach

## Description

Potuzhno Shukach is a web search app that returns a simple list of results (title + snippet + link), localized for English and Ukrainian.

## Stack

- Frontend: React + Vite + TailwindCSS (EN/UA UI)
- Backend: Python + FastAPI (OpenAI Web Search + vision for pasted images)
- Runtime: Docker Compose (Nginx serves UI and proxies `/api/*` to backend)
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

## Environment

- `OPENAI_API_KEY` (required)
- `OPENAI_MODEL` (optional, default: `gpt-4o-mini`)
- `MAX_RESULTS` (optional, default: `8`)
- `WEB_PORT` (optional, default: `8080`)

## API

- `GET /api/health` → `{ "status": "ok" }`
- `POST /api/search`

Notes:
- `lang` is optional; if omitted, the backend uses the `Accept-Language` header (supported: `en`, `uk`).
- Either `query` or `images` is required.
- `images` is an array of `data:image/*` URLs (the UI attaches them automatically on paste).

Request:

```json
{ "query": "string (optional)", "images": ["data:image/png;base64,..."], "lang": "en|uk", "limit": 8 }
```

Response:

```json
{ "query": "string", "lang": "en|uk", "results": [{ "title": "...", "url": "https://...", "snippet": "...", "source": "..." }], "took_ms": 123 }
```
