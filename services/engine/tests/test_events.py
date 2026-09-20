"""Kuratiertes Event-Log (ADR 0120, Phase B) in Isolation."""

from app.operations.events import record_event


def test_record_event_appends_to_state_events() -> None:
    state: dict = {}

    event = record_event(state, "store.completed", object="obj-001", host="pacs")

    assert state["events"] == [event]
    assert event["type"] == "store.completed"
    assert event["object"] == "obj-001"
    assert event["host"] == "pacs"


def test_record_event_assigns_sequential_ids() -> None:
    state: dict = {}

    first = record_event(state, "store.completed", object="obj-001", host="pacs")
    second = record_event(state, "store.completed", object="obj-002", host="pacs")

    assert first["id"] == "evt-001"
    assert second["id"] == "evt-002"


def test_record_event_includes_an_iso_timestamp() -> None:
    state: dict = {}

    event = record_event(state, "store.completed", object="obj-001", host="pacs")

    assert isinstance(event["timestamp"], str)
    assert "T" in event["timestamp"]


def test_record_event_is_the_only_write_path_events_stay_json_serializable() -> None:
    import json

    state: dict = {}
    record_event(
        state, "route.evaluated", object="obj-001", host="pacs", route_id="R1", matched=True,
    )

    roundtripped = json.loads(json.dumps(state))

    assert roundtripped == state
