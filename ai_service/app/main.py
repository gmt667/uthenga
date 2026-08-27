"""FastAPI boundary for Uthenga's AI-only capabilities.

This service is intentionally unable to open a MySQL connection, create a
booking, or initiate payment.  A trusted PHP backend may call it with a
short-lived service token and approved, minimised context.  In production PHP
will expose a separate allowlisted tool gateway; the browser never calls this
service with marketplace authority.
"""
from __future__ import annotations

import os
from typing import Any, Literal

import httpx
from fastapi import FastAPI, Header, HTTPException
from pydantic import BaseModel, Field

from .prompts import build_conversation_messages
from .providers import ProviderUnavailable, complete

APP_TOKEN = os.getenv("UTHENGA_AI_SERVICE_TOKEN", "")
PROVIDER = os.getenv("UTHENGA_AI_PROVIDER", "disabled").lower()
PHP_TOOL_URL = os.getenv("UTHENGA_PHP_TOOL_URL", "")
PHP_TOOL_TOKEN = os.getenv("UTHENGA_PHP_TOOL_TOKEN", "")
TOOL_TIMEOUT_SECONDS = float(os.getenv("UTHENGA_AI_TIMEOUT_SECONDS", "12"))

ALLOWED_TOOLS = frozenset({"travel_context", "recommendations", "availability", "trip_plan", "location_context"})
FORBIDDEN_ACTIONS = frozenset({"booking", "payment", "refund", "inventory_mutation", "sql"})


class ToolCall(BaseModel):
    name: str
    arguments: dict[str, Any] = Field(default_factory=dict)


class ConversationRequest(BaseModel):
    conversation_id: str = Field(min_length=1, max_length=100)
    message: str = Field(min_length=1, max_length=4000)
    approved_context: dict[str, Any] = Field(default_factory=dict)
    requested_tools: list[ToolCall] = Field(default_factory=list, max_length=5)


class ConversationResponse(BaseModel):
    message: str
    suggested_actions: list[str] = Field(default_factory=list)
    provider: str
    used_tools: list[str] = Field(default_factory=list)
    tool_status: Literal["not_requested", "not_configured", "executed"] = "not_requested"
    fallback: bool = False


def require_internal_token(authorization: str | None) -> None:
    if not APP_TOKEN or authorization != f"Bearer {APP_TOKEN}":
        raise HTTPException(status_code=401, detail="Internal AI authentication required.")


def authorize_tools(calls: list[ToolCall]) -> list[ToolCall]:
    for call in calls:
        normalised = call.name.strip().lower()
        if normalised in FORBIDDEN_ACTIONS or normalised not in ALLOWED_TOOLS:
            raise HTTPException(status_code=403, detail="The requested AI tool is not authorised.")
        call.name = normalised
    return calls


def minimise_context(context: dict[str, Any]) -> dict[str, Any]:
    """Retain only evidence supplied through PHP's deterministic boundary.

    This strips obvious secrets and direct identifiers before a provider adapter
    ever receives the context.  It is intentionally conservative and should be
    supplemented by a versioned PHP context contract before provider enablement.
    """
    forbidden_keys = {"password", "token", "secret", "email", "phone", "precise_coordinates", "latitude", "longitude"}
    return {key: value for key, value in context.items() if key.lower() not in forbidden_keys}


def fallback_message(context: dict[str, Any]) -> str:
    destination = context.get("destination")
    if destination:
        return f"I have your verified travel context for {destination}. I can explain the available options once the AI provider is enabled."
    return "I have received the verified travel context. I can explain deterministic Uthenga results once the AI provider is enabled."


def complete_with_provider(message: str, context: dict[str, Any]) -> str | None:
    if PROVIDER == "disabled":
        return None
    try:
        return complete(PROVIDER, build_conversation_messages(message, context))
    except ProviderUnavailable:
        return None


def invoke_php_tools(calls: list[ToolCall], capability: str | None) -> tuple[list[str], Literal["not_requested", "not_configured", "executed"]]:
    """Call only PHP's approved internal tool gateway.

    `capability` is a short-lived, PHP-issued scope token. The AI service can
    forward it but is never able to create one. This prevents the service from
    choosing an arbitrary customer or escaping its approved tool set.
    """
    if not calls:
        return [], "not_requested"
    if not PHP_TOOL_URL or not PHP_TOOL_TOKEN or not capability:
        return [], "not_configured"
    used: list[str] = []
    try:
        with httpx.Client(timeout=TOOL_TIMEOUT_SECONDS) as client:
            for call in calls:
                response = client.post(
                    PHP_TOOL_URL,
                    headers={"Authorization": f"Bearer {PHP_TOOL_TOKEN}"},
                    json={"capability": capability, "tool": call.name, "arguments": call.arguments},
                )
                response.raise_for_status()
                payload = response.json()
                if not payload.get("success"):
                    raise ValueError("PHP tool gateway rejected the request.")
                used.append(call.name)
    except (httpx.HTTPError, ValueError) as error:
        raise HTTPException(status_code=503, detail="Approved Uthenga tools are temporarily unavailable.") from error
    return used, "executed"


app = FastAPI(title="Uthenga AI Service", version="0.1.0", docs_url=None, redoc_url=None)


@app.get("/health")
def health() -> dict[str, Any]:
    return {"status": "ok", "service": "uthenga-ai", "provider": PROVIDER, "database_access": False, "forbidden_actions": sorted(FORBIDDEN_ACTIONS)}


@app.post("/v1/conversation", response_model=ConversationResponse)
def conversation(request: ConversationRequest, authorization: str | None = Header(default=None)) -> ConversationResponse:
    require_internal_token(authorization)
    calls = authorize_tools(request.requested_tools)
    context = minimise_context(request.approved_context)
    capability = request.approved_context.get("php_capability")
    if not isinstance(capability, str):
        capability = None
    used_tools, tool_status = invoke_php_tools(calls, capability)

    # Provider adapters will be plugged in here only after a tested PHP tool
    # gateway is available.  Returning an explicit fallback is safer than
    # silently allowing a model to answer without Uthenga evidence.
    ai_message = complete_with_provider(request.message, context)
    return ConversationResponse(
        message=ai_message or fallback_message(context),
        suggested_actions=["view_recommendations", "refine_trip"],
        provider=PROVIDER if ai_message else "fallback",
        used_tools=used_tools,
        tool_status=tool_status,
        fallback=ai_message is None,
    )
