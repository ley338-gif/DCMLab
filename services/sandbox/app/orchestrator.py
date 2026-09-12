"""Geschaeftslogik der Spielwiese (Abschnitt 6): Sitzung anlegen/wiederverwenden,
Kontingent und Warteschlange pruefen, Befehle durchreichen, aufraeumen. Nimmt
Redis- und Docker-Client als Parameter entgegen (kein globaler Zustand), damit
Tests echte Fakes injizieren koennen, ohne einen Docker-Daemon zu brauchen.
"""

from __future__ import annotations

import uuid
from dataclasses import dataclass
from datetime import UTC, datetime

import docker

from app import datasets_yaml, docker_ops, state
from app.config import settings
from app.state import ActiveSandbox, QueuedRequest, RedisLike


class QuotaExceededError(Exception):
    pass


class SandboxNotFoundError(Exception):
    pass


@dataclass
class SandboxView:
    status: str  # "running" | "queued"
    sandbox_id: str | None = None
    queue_position: int | None = None


def create_sandbox(
    r: RedisLike, docker_client: docker.DockerClient, *, user_id: str, dataset_slug: str,
) -> SandboxView:
    existing_id = state.user_active_sandbox_id(r, user_id)
    if existing_id is not None and state.get_active(r, existing_id) is not None:
        return SandboxView(status="running", sandbox_id=existing_id)

    used = state.quota_used_seconds(r, user_id, state.today())
    if used >= settings.daily_quota_minutes * 60:
        raise QuotaExceededError(user_id)

    if state.active_count(r) >= settings.max_concurrent_sandboxes:
        request = QueuedRequest(
            request_id=str(uuid.uuid4()),
            user_id=user_id,
            dataset_slug=dataset_slug,
            queued_at=state.now_iso(),
        )
        state.enqueue(r, request)

        return SandboxView(status="queued", sandbox_id=request.request_id, queue_position=1)

    sandbox_id = _start_sandbox(
        r,
        docker_client,
        user_id=user_id,
        dataset_slug=dataset_slug,
        sandbox_id=docker_ops.new_sandbox_id(),
    )

    return SandboxView(status="running", sandbox_id=sandbox_id)


def _start_sandbox(
    r: RedisLike,
    docker_client: docker.DockerClient,
    *,
    user_id: str,
    dataset_slug: str,
    sandbox_id: str,
) -> str:
    # Beim Befoerdern aus der Warteschlange (_promote_next) ist `sandbox_id`
    # bewusst die schon an den Client ausgegebene request_id -- sonst haette
    # der wartende Browser keine Moeglichkeit mehr, seine eigene, inzwischen
    # laufende Sitzung wiederzufinden.
    dataset_params = datasets_yaml.load_dataset_params(dataset_slug)
    session = docker_ops.build_session(
        docker_client,
        sandbox_id=sandbox_id,
        dataset_slug=dataset_slug,
        dataset_params=dataset_params,
    )

    now = state.now_iso()
    state.put_active(
        r,
        ActiveSandbox(
            sandbox_id=sandbox_id,
            user_id=user_id,
            dataset_slug=dataset_slug,
            network_name=session.network_name,
            volume_name=session.volume_name,
            orthanc_container_id=session.orthanc.id,
            toolbox_container_id=session.toolbox.id,
            started_at=now,
            last_activity_at=now,
        ),
    )

    return sandbox_id


def get_sandbox(r: RedisLike, sandbox_id: str) -> SandboxView:
    sandbox = state.get_active(r, sandbox_id)
    if sandbox is not None:
        return SandboxView(status="running", sandbox_id=sandbox_id)

    position = state.queue_position(r, sandbox_id)
    if position is not None:
        return SandboxView(status="queued", sandbox_id=sandbox_id, queue_position=position)

    raise SandboxNotFoundError(sandbox_id)


def exec_command(
    r: RedisLike, docker_client: docker.DockerClient, *, sandbox_id: str, command: str,
) -> dict[str, object]:
    sandbox = state.get_active(r, sandbox_id)
    if sandbox is None:
        raise SandboxNotFoundError(sandbox_id)

    result = docker_ops.exec_command(docker_client, sandbox.toolbox_container_id, command)
    state.touch_activity(r, sandbox_id)

    return result


def delete_sandbox(r: RedisLike, docker_client: docker.DockerClient, *, sandbox_id: str) -> None:
    sandbox = state.remove_active(r, sandbox_id)
    if sandbox is None:
        return

    docker_ops.teardown_session(
        docker_client, network_name=sandbox.network_name, volume_name=sandbox.volume_name,
    )

    elapsed = int(
        (datetime.now(UTC) - datetime.fromisoformat(sandbox.started_at)).total_seconds(),
    )
    state.add_quota_usage(r, sandbox.user_id, state.today(), elapsed)

    _promote_next(r, docker_client)


def _promote_next(r: RedisLike, docker_client: docker.DockerClient) -> None:
    if state.active_count(r) >= settings.max_concurrent_sandboxes:
        return

    request = state.dequeue_next(r)
    if request is None:
        return

    _start_sandbox(
        r,
        docker_client,
        user_id=request.user_id,
        dataset_slug=request.dataset_slug,
        sandbox_id=request.request_id,
    )


def run_cleanup_once(r: RedisLike, docker_client: docker.DockerClient) -> list[str]:
    """Raeumt Sitzungen ohne Aktivitaet seit `idle_timeout_minutes` weg
    (Abschnitt 6). Gibt die entfernten Sandbox-IDs zurueck."""

    now = datetime.now(UTC)
    removed: list[str] = []

    for sandbox in state.list_active(r):
        last_activity = datetime.fromisoformat(sandbox.last_activity_at)
        idle_minutes = (now - last_activity).total_seconds() / 60

        if idle_minutes >= settings.idle_timeout_minutes:
            delete_sandbox(r, docker_client, sandbox_id=sandbox.sandbox_id)
            removed.append(sandbox.sandbox_id)

    return removed
