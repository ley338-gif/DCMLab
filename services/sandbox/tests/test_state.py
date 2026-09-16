import fakeredis

from app.state import (
    MAX_EXEC_EVENTS,
    ActiveSandbox,
    QueuedRequest,
    append_exec_event,
    dequeue_next,
    enqueue,
    list_exec_events,
    put_active,
    queue_position,
    remove_active,
)


def test_queue_position_reflects_fifo_order() -> None:
    r = fakeredis.FakeRedis()
    enqueue(r, QueuedRequest("a", "u1", "d", "t1"))
    enqueue(r, QueuedRequest("b", "u2", "d", "t2"))

    assert queue_position(r, "a") == 1
    assert queue_position(r, "b") == 2
    assert queue_position(r, "does-not-exist") is None


def test_dequeue_next_returns_fifo() -> None:
    r = fakeredis.FakeRedis()
    enqueue(r, QueuedRequest("a", "u1", "d", "t1"))
    enqueue(r, QueuedRequest("b", "u2", "d", "t2"))

    first = dequeue_next(r)
    assert first is not None
    assert first.request_id == "a"

    second = dequeue_next(r)
    assert second is not None
    assert second.request_id == "b"

    assert dequeue_next(r) is None


def test_active_sandbox_roundtrips_through_json() -> None:
    sandbox = ActiveSandbox(
        sandbox_id="s1",
        user_id="u1",
        dataset_slug="d",
        network_name="n",
        volume_name="v",
        orthanc_container_id="o1",
        toolbox_container_id="t1",
        started_at="2026-01-01T00:00:00+00:00",
        last_activity_at="2026-01-01T00:00:00+00:00",
        runtime_key="sandbox:user:u1",
    )

    restored = ActiveSandbox.from_json(sandbox.to_json())

    assert restored == sandbox


def test_exec_events_append_and_list_in_order() -> None:
    r = fakeredis.FakeRedis()

    append_exec_event(r, "s1", {"command": "echoscu foo", "exit_code": 0, "timestamp": "t1"})
    append_exec_event(r, "s1", {"command": "dcmdump bar", "exit_code": 1, "timestamp": "t2"})

    events = list_exec_events(r, "s1")

    assert [e["command"] for e in events] == ["echoscu foo", "dcmdump bar"]
    assert list_exec_events(r, "does-not-exist") == []


def test_exec_events_are_capped_at_the_maximum() -> None:
    r = fakeredis.FakeRedis()

    for i in range(MAX_EXEC_EVENTS + 10):
        append_exec_event(r, "s1", {"command": f"cmd-{i}", "exit_code": 0, "timestamp": str(i)})

    events = list_exec_events(r, "s1")

    assert len(events) == MAX_EXEC_EVENTS
    # Die AELTESTEN Eintraege werden verworfen, nicht die neuesten.
    assert events[0]["command"] == "cmd-10"
    assert events[-1]["command"] == f"cmd-{MAX_EXEC_EVENTS + 9}"


def test_remove_active_deletes_its_exec_events() -> None:
    r = fakeredis.FakeRedis()
    sandbox = ActiveSandbox(
        sandbox_id="s1",
        user_id="u1",
        dataset_slug="d",
        network_name="n",
        volume_name="v",
        orthanc_container_id="o1",
        toolbox_container_id="t1",
        started_at="2026-01-01T00:00:00+00:00",
        last_activity_at="2026-01-01T00:00:00+00:00",
        runtime_key="sandbox:user:u1",
    )

    put_active(r, sandbox)
    append_exec_event(r, "s1", {"command": "ls", "exit_code": 0, "timestamp": "t1"})

    remove_active(r, "s1")

    assert list_exec_events(r, "s1") == []
