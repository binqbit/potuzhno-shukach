from __future__ import annotations

from typing import Literal

SupportedLang = Literal["en", "uk"]


def normalize_lang(value: str | None) -> SupportedLang | None:
    if not value:
        return None
    v = value.strip().lower()
    if v in {"uk", "uk-ua", "ua", "ua-ua"}:
        return "uk"
    if v in {"en", "en-us", "en-gb"}:
        return "en"
    primary = v.split("-", 1)[0]
    if primary == "ua":
        return "uk"
    if primary in {"en", "uk"}:
        return primary  # type: ignore[return-value]
    return None


def _parse_accept_language(header_value: str) -> list[tuple[str, float]]:
    out: list[tuple[str, float]] = []
    for raw in header_value.split(","):
        part = raw.strip()
        if not part:
            continue
        bits = [b.strip() for b in part.split(";") if b.strip()]
        tag = bits[0].lower()
        q = 1.0
        for b in bits[1:]:
            if b.lower().startswith("q="):
                try:
                    q = float(b.split("=", 1)[1])
                except ValueError:
                    q = 1.0
        out.append((tag, q))
    out.sort(key=lambda x: x[1], reverse=True)
    return out


def pick_lang(explicit_lang: str | None, accept_language: str | None) -> SupportedLang:
    explicit = normalize_lang(explicit_lang)
    if explicit:
        return explicit

    if accept_language:
        for tag, _q in _parse_accept_language(accept_language):
            normalized = normalize_lang(tag)
            if normalized:
                return normalized

    return "uk"
