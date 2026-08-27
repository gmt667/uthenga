import os

os.environ["UTHENGA_AI_SERVICE_TOKEN"] = "test-token"

from fastapi.testclient import TestClient
import app.main as main
from app.providers import ProviderUnavailable


client = TestClient(main.app)


def test_health_declares_no_database_access():
    response = client.get("/health")
    assert response.status_code == 200
    assert response.json()["database_access"] is False
    assert "booking" in response.json()["forbidden_actions"]


def test_conversation_requires_internal_authentication():
    response = client.post("/v1/conversation", json={"conversation_id": "one", "message": "Help"})
    assert response.status_code == 401


def test_forbidden_tool_is_rejected():
    response = client.post("/v1/conversation", headers={"Authorization": "Bearer test-token"}, json={"conversation_id": "one", "message": "Book this", "requested_tools": [{"name": "booking"}]})
    assert response.status_code == 403


def test_minimises_context_and_uses_safe_fallback():
    response = client.post("/v1/conversation", headers={"Authorization": "Bearer test-token"}, json={"conversation_id": "one", "message": "Explain", "approved_context": {"destination": "Mzuzu", "email": "private@example.test", "latitude": -13.9}, "requested_tools": [{"name": "recommendations"}]})
    assert response.status_code == 200
    payload = response.json()
    assert payload["fallback"] is True
    assert payload["used_tools"] == []
    assert payload["tool_status"] == "not_configured"
    assert "Mzuzu" in payload["message"]


def test_provider_failure_degrades_to_evidence_fallback(monkeypatch):
    monkeypatch.setattr(main, "PROVIDER", "groq")
    monkeypatch.setattr(main, "complete", lambda *_: (_ for _ in ()).throw(ProviderUnavailable("timeout")))
    response = client.post("/v1/conversation", headers={"Authorization": "Bearer test-token"}, json={"conversation_id": "one", "message": "Explain", "approved_context": {"destination": "Lilongwe"}})
    assert response.status_code == 200
    assert response.json()["provider"] == "fallback"
    assert response.json()["fallback"] is True
