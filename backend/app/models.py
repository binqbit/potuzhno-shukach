from __future__ import annotations

from typing import Literal

from pydantic import BaseModel, Field, field_validator, model_validator

SupportedLang = Literal["en", "uk"]


class SearchRequest(BaseModel):
    query: str | None = Field(default=None, max_length=400)
    images: list[str] = Field(default_factory=list, max_length=6)
    lang: SupportedLang | None = None
    limit: int = Field(default=8, ge=1, le=10)

    @field_validator("query", mode="before")
    @classmethod
    def _normalize_query(cls, value: object) -> object:
        if value is None:
            return None
        if isinstance(value, str):
            stripped = value.strip()
            return stripped or None
        return value

    @field_validator("images", mode="before")
    @classmethod
    def _normalize_images(cls, value: object) -> object:
        if value is None:
            return []
        return value

    @field_validator("images")
    @classmethod
    def _validate_images(cls, images: list[str]) -> list[str]:
        normalized: list[str] = []
        for image in images:
            s = image.strip()
            if not s.startswith("data:image/"):
                raise ValueError("images must be data:image/* URLs")
            normalized.append(s)
        return normalized

    @model_validator(mode="after")
    def _require_query_or_images(self) -> "SearchRequest":
        if not self.query and not self.images:
            raise ValueError("query or images is required")
        return self


class SearchResult(BaseModel):
    title: str = Field(min_length=1, max_length=200)
    url: str = Field(min_length=1, max_length=2048)
    snippet: str = Field(min_length=1, max_length=600)
    source: str | None = Field(default=None, max_length=120)


class SearchResponse(BaseModel):
    query: str
    lang: SupportedLang
    results: list[SearchResult]
    took_ms: int
    answer: str | None = Field(default=None)
    ai_answer: str | None = Field(default=None)
