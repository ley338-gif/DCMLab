import fakeredis

from app.state import ActiveSandbox, QueuedRequest, dequeue_next, enqueue, queue_position


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
    )

    restored = ActiveSandbox.from_json(sandbox.to_json())

    assert restored == sandbox
