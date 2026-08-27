"""Provider adapters kept separate from conversation orchestration."""
from __future__ import annotations

import os
from typing import Any

import httpx


class ProviderUnavailable(Exception):
    """A provider is disabled, unavailable, or produced an unusable response."""


def complete(provider: str, messages: list[dict[str, str]]) -> str:
    provider = provider.lower()
    if provider != "groq":
        raise ProviderUnavailable("No enabled provider adapter.")
    key = os.getenv("GROQ_API_KEY", "")
    if not key:
        raise ProviderUnavailable("Groq is not configured.")
    payload: dict[str, Any] = {
        "model": os.getenv("UTHENGA_AI_MODEL", "openai/gpt-oss-20b"),
        "messages": messages,
        "temperature": 0,
        "max_tokens": int(os.getenv("UTHENGA_AI_MAX_TOKENS", "500")),
    }
    try:
        with httpx.Client(timeout=float(os.getenv("UTHENGA_AI_TIMEOUT_SECONDS", "12"))) as client:
            response = client.post("https://api.groq.com/openai/v1/chat/completions", headers={"Authorization": f"Bearer {key}", "Content-Type": "application/json"}, json=payload)
            response.raise_for_status()
            data = response.json()
    except httpx.HTTPError as error:
        raise ProviderUnavailable("Groq request failed.") from error
    try:
        text = str(data["choices"][0]["message"]["content"]).strip()
    except (KeyError, IndexError, TypeError) as error:
        raise ProviderUnavailable("Groq returned an invalid response.") from error
    if not text:
        raise ProviderUnavailable("Groq returned an empty response.")
    return text
