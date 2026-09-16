"""GET /v1/sandboxes/{id}/events (CMS-8b): Observation-API. Seedet den
Redis-Zustand direkt (statt ueber POST /v1/sandboxes einen echten Container
zu bauen) und fakt nur `docker_ops.collect_orthanc_facts()`, damit dieser
Test ohne Docker-Daemon laeuft -- dieselbe Faking-Strategie wie
test_orchestrator.py."""

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
    monkeypatch.setattr("app.orchestrator.docker_ops.collect_orthanc_facts", lambda *_a, **_kw: [])
    return r


def _seed_active_sandbox(r: fakeredis.FakeRedis, sandbox_id: str) -> None:
    state.put_active(
        r,
        state.ActiveSandbox(
            sandbox_id=sandbox_id,
            user_id="u1",
            dataset_slug="d",
            network_name="n",
            volume_name="v",
            orthanc_container_id="o1",
            toolbox_container_id="t1",
            started_at=state.now_iso(),
            last_activity_at=state.now_iso(),
            runtime_key="sandbox:user:u1",
        ),
    )


def _seed_queued_request(r: fakeredis.FakeRedis, request_id: str) -> None:
    state.enqueue(
        r,
        state.QueuedRequest(
            request_id=request_id,
            user_id="u1",
            dataset_slug="d",
            queued_at=state.now_iso(),
            template_slug="dicom-basic-tools",
            runtime_key="sandbox:user:u1",
        ),
    )


def test_events_requires_internal_key() -> None:
    response = client.get("/v1/sandboxes/sb-1/events")
    assert response.status_code == 401


def test_events_returns_404_for_an_unknown_sandbox() -> None:
    response = client.get("/v1/sandboxes/does-not-exist/events", headers=HEADERS)
    assert response.status_code == 404


def test_events_returns_409_for_a_queued_sandbox(
    _fake_redis_and_docker: fakeredis.FakeRedis,
) -> None:
    """Betreiber-Review: eine wartende Anfrage ist nicht 'gone' -- sie muss
    von einer echten 404 unterscheidbar sein, sonst wuerde Laravel sie
    faelschlich als 'reaped' reconcilen."""

    _seed_queued_request(_fake_redis_and_docker, "req-1")

    response = client.get("/v1/sandboxes/req-1/events", headers=HEADERS)

    assert response.status_code == 409
    assert response.json()["detail"] == "sandbox_not_ready"


def test_events_combines_exec_and_orthanc_facts(
    _fake_redis_and_docker: fakeredis.FakeRedis,
) -> None:
    _seed_active_sandbox(_fake_redis_and_docker, "sb-1")
    state.append_exec_event(
        _fake_redis_and_docker, "sb-1",
        {"command": "echoscu foo", "exit_code": 0, "timestamp": "t1", "stdout_preview": ""},
    )

    response = client.get("/v1/sandboxes/sb-1/events", headers=HEADERS)

    assert response.status_code == 200
    body = response.json()
    assert body["exec"] == [
        {"command": "echoscu foo", "exit_code": 0, "timestamp": "t1", "stdout_preview": ""},
    ]
    assert body["orthanc"] == {"new_instances": []}


def test_create_sandbox_requires_template_slug_and_runtime_key() -> None:
    response = client.post(
        "/v1/sandboxes",
        headers=HEADERS,
        json={"user_id": "u1", "dataset_slug": "d"},
    )

    assert response.status_code == 422


def test_create_sandbox_rejects_an_unknown_template_slug(
    _fake_redis_and_docker: fakeredis.FakeRedis,
) -> None:
    # Ein realer dataset_slug (aus content/datasets.yml), damit der Aufruf
    # tatsaechlich bis zur Template-Aufloesung kommt, statt vorher schon an
    # einem unbekannten Datensatz zu scheitern.
    response = client.post(
        "/v1/sandboxes",
        headers=HEADERS,
        json={
            "user_id": "u1", "dataset_slug": "ct-thorax-60",
            "template_slug": "does-not-exist", "runtime_key": "sandbox:user:u1",
        },
    )

    assert response.status_code == 400


def test_create_sandbox_returns_409_on_a_conflicting_runtime_key(
    _fake_redis_and_docker: fakeredis.FakeRedis,
) -> None:
    state.put_active(
        _fake_redis_and_docker,
        state.ActiveSandbox(
            sandbox_id="sb-existing",
            user_id="u1",
            dataset_slug="d",
            network_name="n",
            volume_name="v",
            orthanc_container_id="o1",
            toolbox_container_id="t1",
            started_at=state.now_iso(),
            last_activity_at=state.now_iso(),
            runtime_key="sandbox:user:u1",
        ),
    )

    response = client.post(
        "/v1/sandboxes",
        headers=HEADERS,
        json={
            "user_id": "u1", "dataset_slug": "d",
            "template_slug": "dicom-basic-tools", "runtime_key": "lab-attempt:123",
        },
    )

    assert response.status_code == 409
    assert response.json()["detail"] == "active_runtime_exists"
