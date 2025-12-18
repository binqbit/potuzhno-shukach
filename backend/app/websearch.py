from __future__ import annotations

import json
import os
import time
from typing import Any
from urllib.parse import urlparse

from openai import OpenAI

from .models import SearchResult
from .prompts import SYSTEM_PROMPT, build_user_prompt

_client: OpenAI | None = None


def _get_client() -> OpenAI:
    global _client
    if _client is None:
        _client = OpenAI(api_key=os.getenv("OPENAI_API_KEY"))
    return _client


def _get_int_env(name: str, default: int) -> int:
    raw = os.getenv(name)
    if raw is None or raw == "":
        return default
    try:
        return int(raw)
    except ValueError:
        return default


def _extract_json(text: str) -> dict[str, Any]:
    raw = text.strip()
    try:
        return json.loads(raw)
    except json.JSONDecodeError:
        start = raw.find("{")
        end = raw.rfind("}")
        if start == -1 or end == -1 or end <= start:
            raise
        return json.loads(raw[start : end + 1])


def _domain_from_url(url: str) -> str | None:
    try:
        parsed = urlparse(url)
        return parsed.netloc or None
    except ValueError:
        return None


def web_search(*, query: str, lang: str, limit: int, images: list[str] | None = None) -> tuple[list[SearchResult], int]:
    model = os.getenv("OPENAI_MODEL", "gpt-4o-mini")
    max_results = _get_int_env("MAX_RESULTS", 8)
    limit = max(1, min(limit, 10, max_results))
    images = images or []

    client = _get_client()

    user_content: list[dict[str, Any]] = [
        {"type": "input_text", "text": build_user_prompt(query=query, language=lang, limit=limit, images_count=len(images))}
    ]
    for image_url in images:
        user_content.append({"type": "input_image", "image_url": image_url})

    started = time.perf_counter()
    response = client.responses.create(
        model=model,
        input=[
            {"role": "system", "content": SYSTEM_PROMPT},
            {"role": "user", "content": user_content},
        ],
        tools=[{"type": "web_search_preview"}],
        temperature=0.2,
    )
    took_ms = int((time.perf_counter() - started) * 1000)

    if not response.output_text:
        raise ValueError("Empty model output")

    data = _extract_json(response.output_text)
    items = data.get("results", [])
    if not isinstance(items, list):
        items = []

    results: list[SearchResult] = []
    seen_urls: set[str] = set()

    for item in items:
        if len(results) >= limit:
            break
        if not isinstance(item, dict):
            continue

        title = str(item.get("title") or "").strip()
        url = str(item.get("url") or "").strip()
        snippet = str(item.get("snippet") or "").strip()
        source = item.get("source")
        source = str(source).strip() if source is not None else None

        if not title or not url or not snippet:
            continue
        if not (url.startswith("https://") or url.startswith("http://")):
            continue

        normalized_url = url
        if normalized_url in seen_urls:
            continue
        seen_urls.add(normalized_url)

        if not source:
            source = _domain_from_url(url)

        results.append(
            SearchResult(
                title=title[:200],
                url=url[:2048],
                snippet=snippet[:600],
                source=(source[:120] if source else None),
            )
        )

    return results, took_ms
