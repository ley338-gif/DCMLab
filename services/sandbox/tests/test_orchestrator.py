"""Testet die Geschaeftslogik (Quote, Warteschlange, Aufraeumen) komplett
ohne Docker-Daemon -- docker_ops wird gefaked, nur state.py laeuft echt
(gegen fakeredis)."""

from __future__ import annotations

from dataclasses import dataclass
from datetime import UTC, datetime, timedelta
from typing import Any

import fakeredis
import pytest

from app import orchestrator, state
from app.config import settings


@dataclass
class FakeContainer:
    id: str


@dataclass
class FakeSession:
    network_name: str
    volume_name: str
    orthanc: FakeContainer
    toolbox: FakeContainer


@pytest.fixture(autouse=True)
def _small_limits(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.setattr(settings, "max_concurrent_sandboxes", 2)
    monkeypatch.setattr(settings, "daily_quota_minutes", 60)


@pytest.fixture
def redis_client() -> fakeredis.FakeRedis:
    return fakeredis.FakeRedis()


@pytest.fixture(autouse=True)
def _fake_docker_ops(monkeypatch: pytest.MonkeyPatch) -> list[str]:
    built: list[str] = []

    def fake_build_session(
        _client: Any, *, sandbox_id: str, dataset_slug: str, dataset_params: dict[str, Any],
    ) -> FakeSession:
        built.append(sandbox_id)
        return FakeSession(
            network_name=f"net-{sandbox_id}",
            volume_name=f"vol-{sandbox_id}",
            orthanc=FakeContainer(f"orthanc-{sandbox_id}"),
            toolbox=FakeContainer(f"toolbox-{sandbox_id}"),
        )

    def fake_exec_command(_client: Any, toolbox_container_id: str, command: str) -> dict[str, Any]:
        return {"exit_code": 0, "stdout": f"ran: {command}", "stderr": ""}

    def fake_teardown_session(_client: Any, *, network_name: str, volume_name: str) -> None:
        pass

    monkeypatch.setattr("app.orchestrator.docker_ops.build_session", fake_build_session)
    monkeypatch.setattr("app.orchestrator.docker_ops.exec_command", fake_exec_command)
    monkeypatch.setattr("app.orchestrator.docker_ops.teardown_session", fake_teardown_session)
    fake_params = {
        "patient": "X", "patient_id": "1", "study": "S", "series": ["A"], "file_count": 1,
    }
    monkeypatch.setattr(
        "app.orchestrator.datasets_yaml.load_dataset_params",
        lambda slug: fake_params,
    )

    return built


def test_create_sandbox_starts_immediately_under_capacity(
    redis_client: fakeredis.FakeRedis,
) -> None:
    view = orchestrator.create_sandbox(
        redis_client, None, user_id="u1", dataset_slug="ct-thorax-1-slice",
    )

    assert view.status == "running"
    assert view.sandbox_id is not None
    assert state.active_count(redis_client) == 1


def test_create_sandbox_is_idempotent_per_user(
    redis_client: fakeredis.FakeRedis, _fake_docker_ops: list[str],
) -> None:
    first = orchestrator.create_sandbox(redis_client, None, user_id="u1", dataset_slug="d")
    second = orchestrator.create_sandbox(redis_client, None, user_id="u1", dataset_slug="d")

    assert first.sandbox_id == second.sandbox_id
    assert len(_fake_docker_ops) == 1


def test_create_sandbox_queues_when_at_capacity(redis_client: fakeredis.FakeRedis) -> None:
    orchestrator.create_sandbox(redis_client, None, user_id="u1", dataset_slug="d")
    orchestrator.create_sandbox(redis_client, None, user_id="u2", dataset_slug="d")

    queued = orchestrator.create_sandbox(redis_client, None, user_id="u3", dataset_slug="d")

    assert queued.status == "queued"
    assert queued.queue_position == 1
    assert state.active_count(redis_client) == 2


def test_deleting_a_sandbox_promotes_the_next_queued_request(
    redis_client: fakeredis.FakeRedis, _fake_docker_ops: list[str],
) -> None:
    a = orchestrator.create_sandbox(redis_client, None, user_id="u1", dataset_slug="d")
    orchestrator.create_sandbox(redis_client, None, user_id="u2", dataset_slug="d")
    queued = orchestrator.create_sandbox(redis_client, None, user_id="u3", dataset_slug="d")
    assert a.sandbox_id is not None
    assert queued.sandbox_id is not None

    orchestrator.delete_sandbox(redis_client, None, sandbox_id=a.sandbox_id)

    assert state.active_count(redis_client) == 2
    # u3 hat jetzt eine echte, laufende Sandbox -- unter derselben ID, die
    # der Browser schon als "queued" kannte, sonst waere sie fuer ihn
    # verschollen.
    promoted = orchestrator.get_sandbox(redis_client, queued.sandbox_id)
    assert promoted.status == "running"
    assert promoted.sandbox_id == queued.sandbox_id
    assert state.user_active_sandbox_id(redis_client, "u3") == queued.sandbox_id


def test_quota_exceeded_raises(redis_client: fakeredis.FakeRedis) -> None:
    state.add_quota_usage(redis_client, "u1", state.today(), settings.daily_quota_minutes * 60)

    with pytest.raises(orchestrator.QuotaExceededError):
        orchestrator.create_sandbox(redis_client, None, user_id="u1", dataset_slug="d")


def test_exec_command_touches_activity(redis_client: fakeredis.FakeRedis) -> None:
    view = orchestrator.create_sandbox(redis_client, None, user_id="u1", dataset_slug="d")
    assert view.sandbox_id is not None
    sandbox = state.get_active(redis_client, view.sandbox_id)
    assert sandbox is not None
    original_activity = sandbox.last_activity_at

    result = orchestrator.exec_command(redis_client, None, sandbox_id=view.sandbox_id, command="ls")

    assert result["exit_code"] == 0
    updated = state.get_active(redis_client, view.sandbox_id)
    assert updated is not None
    assert updated.last_activity_at >= original_activity


def test_exec_command_on_unknown_sandbox_raises(redis_client: fakeredis.FakeRedis) -> None:
    with pytest.raises(orchestrator.SandboxNotFoundError):
        orchestrator.exec_command(redis_client, None, sandbox_id="does-not-exist", command="ls")


def test_cleanup_removes_idle_sandboxes_and_records_quota(
    redis_client: fakeredis.FakeRedis,
) -> None:
    view = orchestrator.create_sandbox(redis_client, None, user_id="u1", dataset_slug="d")
    assert view.sandbox_id is not None
    sandbox = state.get_active(redis_client, view.sandbox_id)
    assert sandbox is not None

    stale_minutes = settings.idle_timeout_minutes + 1
    stale_time = (datetime.now(UTC) - timedelta(minutes=stale_minutes)).isoformat()
    sandbox.last_activity_at = stale_time
    sandbox.started_at = stale_time
    state.put_active(redis_client, sandbox)

    removed = orchestrator.run_cleanup_once(redis_client, None)

    assert removed == [view.sandbox_id]
    assert state.active_count(redis_client) == 0
    used = state.quota_used_seconds(redis_client, "u1", state.today())
    assert used >= settings.idle_timeout_minutes * 60


def test_cleanup_leaves_active_sandboxes_alone(redis_client: fakeredis.FakeRedis) -> None:
    orchestrator.create_sandbox(redis_client, None, user_id="u1", dataset_slug="d")

    removed = orchestrator.run_cleanup_once(redis_client, None)

    assert removed == []
    assert state.active_count(redis_client) == 1
