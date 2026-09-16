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


class SandboxNotReadyError(Exception):
    """CMS-8b, Betreiber-Review: die Sandbox existiert noch -- sie wartet in
    der Warteschlange und ist noch nicht aktiv. `exec()`/`get_events()`
    duerfen das nicht mit einer wirklich weggeraeumten Sitzung verwechseln
    (`SandboxNotFoundError`), sonst reconciled Laravel eine bloss wartende
    Sitzung faelschlich als 'reaped', obwohl sie spaeter noch unter
    derselben ID starten kann."""


class ActiveRuntimeConflictError(Exception):
    """CMS-8b, Betreiber-Review: ein anderer Zweck (anderer `runtime_key`)
    haelt bereits die einzige erlaubte Sitzung dieses Nutzers -- verhindert,
    dass z. B. eine offene Lesson-Spielwiese blind einem neu gestarteten
    Lab-Attempt zugeordnet wird."""


@dataclass
class SandboxView:
    status: str  # "running" | "queued"
    sandbox_id: str | None = None
    queue_position: int | None = None


def create_sandbox(
    r: RedisLike,
    docker_client: docker.DockerClient,
    *,
    user_id: str,
    dataset_slug: str,
    template_slug: str,
    runtime_key: str,
) -> SandboxView:
    existing_id = state.user_active_sandbox_id(r, user_id)
    if existing_id is not None:
        existing = state.get_active(r, existing_id)
        if existing is not None:
            # CMS-8b, Betreiber-Review: nur bei echtem Owner-Match
            # wiederverwenden -- vorher wurde JEDE aktive Sitzung des
            # Nutzers blind zurueckgegeben, unabhaengig davon, wofuer sie
            # gestartet wurde (Gefahr: ein Lab-Attempt bekaeme faelschlich
            # eine offene Lesson-Spielwiese zugeordnet).
            if existing.runtime_key == runtime_key:
                return SandboxView(status="running", sandbox_id=existing_id)

            raise ActiveRuntimeConflictError(user_id)

    # CMS-8b, Betreiber-Review (viertes Review): dieselbe Owner-Match-Pruefung
    # wie oben, aber fuer einen Nutzer, der noch GAR keinen `user_active`-
    # Eintrag hat, weil sein voriger Request noch in der Warteschlange
    # wartet -- sonst koennte er (oder ein widerspruechlicher zweiter
    # Aufruf) einen weiteren Queue-Eintrag anlegen, obwohl die "eine Runtime
    # pro User/runtime_key"-Garantie das gerade verhindern soll.
    queued = state.queued_request_for_user(r, user_id)
    if queued is not None:
        if queued.runtime_key == runtime_key:
            return SandboxView(
                status="queued",
                sandbox_id=queued.request_id,
                queue_position=state.queue_position(r, queued.request_id),
            )

        raise ActiveRuntimeConflictError(user_id)

    used = state.quota_used_seconds(r, user_id, state.today())
    if used >= settings.daily_quota_minutes * 60:
        raise QuotaExceededError(user_id)

    if state.active_count(r) >= settings.max_concurrent_sandboxes:
        request = QueuedRequest(
            request_id=str(uuid.uuid4()),
            user_id=user_id,
            dataset_slug=dataset_slug,
            queued_at=state.now_iso(),
            template_slug=template_slug,
            runtime_key=runtime_key,
        )
        state.enqueue(r, request)

        return SandboxView(status="queued", sandbox_id=request.request_id, queue_position=1)

    sandbox_id = _start_sandbox(
        r,
        docker_client,
        user_id=user_id,
        dataset_slug=dataset_slug,
        template_slug=template_slug,
        runtime_key=runtime_key,
        sandbox_id=docker_ops.new_sandbox_id(),
    )

    return SandboxView(status="running", sandbox_id=sandbox_id)


def _start_sandbox(
    r: RedisLike,
    docker_client: docker.DockerClient,
    *,
    user_id: str,
    dataset_slug: str,
    template_slug: str,
    runtime_key: str,
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
        template_slug=template_slug,
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
            runtime_key=runtime_key,
        ),
    )

    return sandbox_id


def _require_active(r: RedisLike, sandbox_id: str) -> ActiveSandbox:
    """Gemeinsame Vorbedingung fuer `exec_command()`/`get_events()`
    (Betreiber-Review): eine `sandbox_id`, die noch in der Warteschlange
    steht, ist NICHT dasselbe wie eine unbekannte/weggeraeumte -- nur der
    zweite Fall darf als 404 (und damit als Gone/Reaped-Reconciliation
    Laravel-seitig) behandelt werden."""

    sandbox = state.get_active(r, sandbox_id)
    if sandbox is not None:
        return sandbox

    if state.queue_position(r, sandbox_id) is not None:
        raise SandboxNotReadyError(sandbox_id)

    raise SandboxNotFoundError(sandbox_id)


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
    sandbox = _require_active(r, sandbox_id)

    result = docker_ops.exec_command(docker_client, sandbox.toolbox_container_id, command)
    state.touch_activity(r, sandbox_id)

    # Exec facts (CMS-8b): kein stdout/stderr-Volltext (Redis-Wachstum,
    # fuer den C-ECHO-Nachweis unnoetig), nur eine gekuerzte Vorschau fuer
    # spaetere Autoren-/Debug-Ansichten.
    stdout_preview = str(result.get("stdout", ""))[:500]
    state.append_exec_event(
        r,
        sandbox_id,
        {
            "command": command,
            "exit_code": result["exit_code"],
            "timestamp": state.now_iso(),
            "stdout_preview": stdout_preview,
        },
    )

    return result


def get_events(
    r: RedisLike, docker_client: docker.DockerClient, *, sandbox_id: str,
) -> dict[str, object]:
    """Observation-API (CMS-8b): Exec facts (tatsaechlich ausgefuehrte
    Befehle) aus Redis, Orthanc facts (neue Instanzen) on demand ueber die
    Toolbox erfragt -- niemals kontinuierlich mitgeschnitten."""

    sandbox = _require_active(r, sandbox_id)

    exec_events = state.list_exec_events(r, sandbox_id)
    new_instances = docker_ops.collect_orthanc_facts(docker_client, sandbox.toolbox_container_id)

    return {
        "exec": exec_events,
        "orthanc": {"new_instances": new_instances},
    }


def delete_sandbox(r: RedisLike, docker_client: docker.DockerClient, *, sandbox_id: str) -> None:
    sandbox = state.remove_active(r, sandbox_id)
    if sandbox is not None:
        docker_ops.teardown_session(
            docker_client, network_name=sandbox.network_name, volume_name=sandbox.volume_name,
        )

        elapsed = int(
            (datetime.now(UTC) - datetime.fromisoformat(sandbox.started_at)).total_seconds(),
        )
        state.add_quota_usage(r, sandbox.user_id, state.today(), elapsed)

        _promote_next(r, docker_client)
        return

    # CMS-8b, Betreiber-Review (viertes Review): eine noch wartende Anfrage
    # hat keine Container zum Abbauen, muss aber trotzdem aus der
    # Warteschlange verschwinden -- sonst wuerde `_promote_next()` sie
    # spaeter noch starten, obwohl die zugehoerige SandboxSession
    # Laravel-seitig laengst 'destroyed' ist (Zombie-Runtime, CMS-8d-relevant).
    state.remove_queued(r, sandbox_id)


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
        template_slug=request.template_slug,
        runtime_key=request.runtime_key,
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
