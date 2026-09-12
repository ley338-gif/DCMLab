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
    return sandbox


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


def dequeue_next(r: RedisLike) -> QueuedRequest | None:
    raw = r.lpop(QUEUE_KEY)
    return QueuedRequest.from_json(_decode(raw)) if raw is not None else None
