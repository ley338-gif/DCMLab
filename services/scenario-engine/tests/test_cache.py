"""POST /internal/cache/clear (docs/offene-fragen.md, "Cache-Invalidierung
bei Engine/Sandbox nach einer Veroeffentlichung"). Dieser Dienst haelt
keinen `lru_cache` (siehe app/content.py) -- der Endpunkt existiert nur
fuer eine einheitliche Schnittstelle ueber alle drei internen Dienste."""

from fastapi.testclient import TestClient

from app.main import app

HEADERS = {"X-DCMLAB-KEY": "test-key"}

client = TestClient(app)


def test_cache_clear_requires_internal_key() -> None:
    response = client.post("/internal/cache/clear")
    assert response.status_code == 401


def test_cache_clear_succeeds() -> None:
    response = client.post("/internal/cache/clear", headers=HEADERS)
    assert response.status_code == 200
    assert response.json() == {"status": "cleared"}
