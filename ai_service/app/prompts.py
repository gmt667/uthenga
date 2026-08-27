"""Versioned prompt construction for Uthenga's AI-only service."""
from __future__ import annotations

import json
from typing import Any

PROMPT_VERSION = "uthenga-evidence-conversation/v1"


def build_conversation_messages(message: str, evidence: dict[str, Any]) -> list[dict[str, str]]:
    """Build a bounded provider payload from PHP-approved evidence only."""
    system = (
        "You are Uthenga's travel assistant. Explain only the supplied verified "
        "Uthenga evidence. Do not invent vendors, prices, availability, routes, "
        "booking state, payment state, or policy. Do not claim a booking was made. "
        "If evidence is insufficient, say what must be checked next. Keep the answer concise."
    )
    evidence_json = json.dumps(evidence, ensure_ascii=False, separators=(",", ":"))[:12000]
    return [
        {"role": "system", "content": system},
        {"role": "user", "content": f"Verified evidence: {evidence_json}\n\nTraveller message: {message}"},
    ]
