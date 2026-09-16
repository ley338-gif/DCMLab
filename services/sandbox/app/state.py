"""Redis-gestuetzter Zustand: aktive Sitzungen, Warteschlange, Tageskontingent
(Abschnitt 6). Reine Funktionen ueber einen injizierten Redis-Client, damit
Tests einen In-Memory-Fake statt eines echten Valkey brauchen.
"""

from __future__ import annotations

import json
from dataclasses import asdict, dataclass
from datetime import UTC, datetime
from typing import Any, cast

import redis

# `redis.Redis` statt eines eigenen Protocol: fakeredis.FakeRedis erbt real
# davon (siehe Tests), strukturelles Typing ueber redis-py's eigene,
# Async/Sync-generische Stubs hinweg war unzuverlaessig.
RedisLike = redis.Redis


ACTIVE_KEY = "sandbox:active"
QUEUE_KEY = "sandbox:queue"
USER_ACTIVE_PREFIX = "sandbox:user_active:"
QUOTA_PREFIX = "sandbox:quota:"
EVENTS_PREFIX = "sandbox:events:"

# Deckel gegen unbegrenztes Redis-Wachstum bei langen Sitzungen (CMS-8b,
# Betreiber-Review) -- fuer den C-ECHO-Nachweis (ein einzelner Befehl)
# voellig ausreichend, gross genug fuer eine realistische Autoren-/
# Debug-Sitzung.
MAX_EXEC_EVENTS = 200


def _decode(value: Any) -> str:
    return value.decode("utf-8") if isinstance(value, bytes) else value


@dataclass
class ActiveSandbox:
    sandbox_id: str
    user_id: str
    dataset_slug: str
    network_name: str
    volume_name: str
    orthanc_container_id: str
    toolbox_container_id: str
    started_at: str
    last_activity_at: str
    # CMS-8b, Betreiber-Review: opaker Eigentuemer-/Idempotenz-Schluessel
    # vom Aufrufer (z. B. "sandbox:user:42" oder "lab-attempt:123") --
    # entscheidet, ob eine bestehende Sitzung fuer denselben Nutzer
    # wiederverwendet werden darf, oder ob es ein Konflikt ist (siehe
    # orchestrator.create_sandbox()). Default "" fuer Abwaertskompatibilitaet
    # mit vor CMS-8b geschriebenem Redis-Zustand.
    runtime_key: str = ""

    def to_json(self) -> str:
        return json.dumps(asdict(self))

    @staticmethod
    def from_json(raw: str) -> ActiveSandbox:
        return ActiveSandbox(**json.loads(raw))


@dataclass
class QueuedRequest:
    request_id: str
    user_id: str
    dataset_slug: str
    queued_at: str
    template_slug: str = ""
    runtime_key: str = ""

    def to_json(self) -> str:
        return json.dumps(asdict(self))

    @staticmethod
    def from_json(raw: str) -> QueuedRequest:
        return QueuedRequest(**json.loads(raw))


def now_iso() -> str:
    return datetime.now(UTC).isoformat()


def today() -> str:
    return datetime.now(UTC).date().isoformat()


def active_count(r: RedisLike) -> int:
    return cast(int, r.hlen(ACTIVE_KEY))


def user_active_sandbox_id(r: RedisLike, user_id: str) -> str | None:
    value = r.get(USER_ACTIVE_PREFIX + user_id)
    return _decode(value) if value is not None else None


def get_active(r: RedisLike, sandbox_id: str) -> ActiveSandbox | None:
    raw = r.hget(ACTIVE_KEY, sandbox_id)
    return ActiveSandbox.from_json(_decode(raw)) if raw is not None else None


def list_active(r: RedisLike) -> list[ActiveSandbox]:
    entries = cast("dict[Any, Any]", r.hgetall(ACTIVE_KEY))
    return [ActiveSandbox.from_json(_decode(v)) for v in entries.values()]


def put_active(r: RedisLike, sandbox: ActiveSandbox) -> None:
    r.hset(ACTIVE_KEY, sandbox.sandbox_id, sandbox.to_json())
    r.set(USER_ACTIVE_PREFIX + sandbox.user_id, sandbox.sandbox_id)


def touch_activity(r: RedisLike, sandbox_id: str) -> None:
    sandbox = get_active(r, sandbox_id)
    if sandbox is None:
        return
    sandbox.last_activity_at = now_iso()
    r.hset(ACTIVE_KEY, sandbox_id, sandbox.to_json())


def remove_active(r: RedisLike, sandbox_id: str) -> ActiveSandbox | None:
    sandbox = get_active(r, sandbox_id)
    if sandbox is None:
        return None
    r.hdel(ACTIVE_KEY, sandbox_id)
    r.delete(USER_ACTIVE_PREFIX + sandbox.user_id)
    # Kein verwaister Redis-Key nach Sitzungsende (CMS-8b, Betreiber-Review).
    r.delete(EVENTS_PREFIX + sandbox_id)
    return sandbox


def append_exec_event(r: RedisLike, sandbox_id: str, event: dict[str, object]) -> None:
    """Exec facts (CMS-8b): haengt einen ausgefuehrten Befehl an die
    Redis-Liste dieser Sitzung an, gedeckelt auf die letzten
    `MAX_EXEC_EVENTS` Eintraege (LTRIM), damit eine lange Sitzung nicht
    unbegrenzt waechst."""

    key = EVENTS_PREFIX + sandbox_id
    r.rpush(key, json.dumps(event))
    r.ltrim(key, -MAX_EXEC_EVENTS, -1)


def list_exec_events(r: RedisLike, sandbox_id: str) -> list[dict[str, object]]:
    raw_entries = cast("list[Any]", r.lrange(EVENTS_PREFIX + sandbox_id, 0, -1))
    return [json.loads(_decode(v)) for v in raw_entries]


def quota_used_seconds(r: RedisLike, user_id: str, day: str) -> int:
    value = r.get(f"{QUOTA_PREFIX}{user_id}:{day}")
    return int(_decode(value)) if value is not None else 0


def add_quota_usage(r: RedisLike, user_id: str, day: str, seconds: int) -> None:
    if seconds > 0:
        r.incrby(f"{QUOTA_PREFIX}{user_id}:{day}", seconds)


def enqueue(r: RedisLike, request: QueuedRequest) -> None:
    r.rpush(QUEUE_KEY, request.to_json())


def queue_position(r: RedisLike, request_id: str) -> int | None:
    raw_entries = cast("list[Any]", r.lrange(QUEUE_KEY, 0, -1))
    entries = [QueuedRequest.from_json(_decode(v)) for v in raw_entries]
    for index, entry in enumerate(entries):
        if entry.request_id == request_id:
            return index + 1
    return None


def queued_request_for_user(r: RedisLike, user_id: str) -> QueuedRequest | None:
    """CMS-8b, Betreiber-Review (viertes Review): die `runtime_key`-
    Idempotenz in `orchestrator.create_sandbox()` griff bisher nur fuer
    AKTIVE Runtimes -- ein bereits wartender Nutzer hat noch keinen
    `user_active`-Eintrag und konnte so einen zweiten (oder
    widerspruechlichen) Request in dieselbe Warteschlange stellen. Liefert
    die aelteste wartende Anfrage dieses Nutzers, falls vorhanden."""

    raw_entries = cast("list[Any]", r.lrange(QUEUE_KEY, 0, -1))
    for raw in raw_entries:
        entry = QueuedRequest.from_json(_decode(raw))
        if entry.user_id == user_id:
            return entry
    return None


def remove_queued(r: RedisLike, request_id: str) -> QueuedRequest | None:
    """Gegenstueck zu `remove_active()` fuer eine noch wartende Anfrage
    (CMS-8b, Betreiber-Review, viertes Review) -- ohne das konnte
    `delete_sandbox()` einen queued Request gar nicht entfernen: er blieb
    in der Warteschlange stehen und wurde spaeter trotzdem promoted, obwohl
    die zugehoerige SandboxSession laengst 'destroyed' war (Zombie-Runtime,
    CMS-8d-relevant)."""

    raw_entries = cast("list[Any]", r.lrange(QUEUE_KEY, 0, -1))
    for raw in raw_entries:
        decoded = _decode(raw)
        entry = QueuedRequest.from_json(decoded)
        if entry.request_id == request_id:
            r.lrem(QUEUE_KEY, 1, decoded)
            return entry
    return None


def dequeue_next(r: RedisLike) -> QueuedRequest | None:
    raw = r.lpop(QUEUE_KEY)
    return QueuedRequest.from_json(_decode(raw)) if raw is not None else None
