from __future__ import annotations

import os

from fastapi import FastAPI, Header, HTTPException
from fastapi.middleware.cors import CORSMiddleware

from .i18n import pick_lang
from .models import SearchRequest, SearchResponse
from .websearch import web_search

app = FastAPI(title="Potuzhno Shukach API", version="0.1.0")

cors_origins = [o.strip() for o in os.getenv("CORS_ORIGINS", "").split(",") if o.strip()]
if not cors_origins:
    cors_origins = [
        "http://localhost:5173",
        "http://localhost:8080",
        "http://127.0.0.1:5173",
        "http://127.0.0.1:8080",
    ]

app.add_middleware(
    CORSMiddleware,
    allow_origins=cors_origins,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


@app.get("/api/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/api/search", response_model=SearchResponse)
def search(payload: SearchRequest, accept_language: str | None = Header(default=None, alias="Accept-Language")) -> SearchResponse:
    lang = pick_lang(payload.lang, accept_language)

    if not os.getenv("OPENAI_API_KEY"):
        detail = "OPENAI_API_KEY is not set" if lang == "en" else "Не задано OPENAI_API_KEY"
        raise HTTPException(status_code=500, detail=detail)

    query = payload.query or ""
    try:
        results, took_ms, answer = web_search(
            query=query, lang=lang, limit=payload.limit, images=payload.images
        )
        return SearchResponse(
            query=query,
            lang=lang,
            results=results,
            took_ms=took_ms,
            answer=answer,
            ai_answer=answer,
        )
    except Exception:
        detail = "Search failed. Please try again." if lang == "en" else "Пошук не вдався. Спробуйте ще раз."
        raise HTTPException(status_code=502, detail=detail)
