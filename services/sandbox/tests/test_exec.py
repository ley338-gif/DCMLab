"""POST /v1/sandboxes/{id}/exec (CMS-8b, Betreiber-Review): eine wartende
Anfrage darf nicht wie eine weggeraeumte behandelt werden -- dieselbe
Unterscheidung wie bei GET .../events (test_events.py), hier fuer den
zweiten betroffenen Endpunkt."""

from __future__ import annotations

import fakeredis
import pytest
from fastapi.testclient import TestClient

from app import state
from app.config import settings
from app.main import app

HEADERS = {"X-DCMLAB-KEY": settings.internal_key}

client = TestClient(app)


@pytest.fixture(autouse=True)
def _fake_redis_and_docker(monkeypatch: pytest.MonkeyPatch) -> fakeredis.FakeRedis:
    r = fakeredis.FakeRedis()
    monkeypatch.setattr("app.main.get_redis", lambda: r)
    monkeypatch.setattr("app.main.get_docker", lambda: None)
    return r


def test_exec_returns_404_for_an_unknown_sandbox() -> None:
    response = client.post(
        "/v1/sandboxes/does-not-exist/exec", headers=HEADERS, json={"command": "ls"},
    )

    assert response.status_code == 404


def test_exec_rejects_a_command_over_the_length_limit() -> None:
    """Betreiber-Review: der Command wird seit den Exec-Facts dauerhaft in
    Redis gespeichert (bis zu 200 mal je Sitzung) -- ein unbegrenzt langer
    String waere eine echte Speicherbegrenzungsluecke."""

    response = client.post(
        "/v1/sandboxes/does-not-exist/exec",
        headers=HEADERS,
        json={"command": "x" * 4097},
    )

    assert response.status_code == 422


def test_exec_returns_409_for_a_queued_sandbox(
    _fake_redis_and_docker: fakeredis.FakeRedis,
) -> None:
    state.enqueue(
        _fake_redis_and_docker,
        state.QueuedRequest(
            request_id="req-1",
            user_id="u1",
            dataset_slug="d",
            queued_at=state.now_iso(),
            template_slug="dicom-basic-tools",
            runtime_key="sandbox:user:u1",
        ),
    )

    response = client.post(
        "/v1/sandboxes/req-1/exec", headers=HEADERS, json={"command": "ls"},
    )

    assert response.status_code == 409
    assert response.json()["detail"] == "sandbox_not_ready"
    # Der verfrueher Aufruf darf die wartende Anfrage nicht aus der
    # Warteschlange werfen.
    assert state.queue_position(_fake_redis_and_docker, "req-1") == 1
