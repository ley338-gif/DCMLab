from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


def test_health_ok() -> None:
    response = client.get("/health")
    assert response.status_code == 200
    assert response.json() == {"status": "ok"}


def test_sandboxes_requires_internal_key() -> None:
    response = client.post("/v1/sandboxes", json={"user_id": "u1", "dataset_slug": "test-set"})
    assert response.status_code == 401
