from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


def test_health_ok() -> None:
    response = client.get("/health")
    assert response.status_code == 200
    assert response.json() == {"status": "ok"}


def test_sessions_requires_internal_key() -> None:
    response = client.post("/v1/sessions", json={"node_slug": "test-scenario-node"})
    assert response.status_code == 401
