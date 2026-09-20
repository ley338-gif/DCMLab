"""RuntimeObject/Presence-Modell (ADR 0120, Phase A) in Isolation -- ohne
Umweg ueber `rules.exec_command`/`trigger_action`, die dieselben Funktionen
nur ueber die beiden Storage-Pfade aufrufen (siehe test_storescu_runtime_
objects.py / test_send_study_runtime_objects.py fuer die Integration)."""

from app.operations import objects as runtime_objects


def test_resolving_from_an_environment_object_creates_a_runtime_object() -> None:
    state: dict = {}
    obj = {
        "filename": "bild-1.dcm",
        "sop_class": "1.2.840.10008.5.1.4.1.1.2",
        "study_uid": "1.2.276.study",
        "series_uid": "1.2.276.series",
        "modality": "CT",
    }

    object_id = runtime_objects.resolve_object_from_environment_object(
        state, obj, origin_host="pacs",
    )

    assert object_id == "obj-001"
    stored = state["objects"][object_id]
    assert stored["filename"] == "bild-1.dcm"
    assert stored["sop_class"] == "1.2.840.10008.5.1.4.1.1.2"
    assert stored["modality"] == "CT"
    assert stored["origin_host"] == "pacs"
    assert stored["route_history"] == []
    # ADR 0120, 8.3: sop_instance_uid ist optional -- environment.objects[]
    # kennt heute kein solches Feld, es wird nichts erfunden.
    assert stored["sop_instance_uid"] is None


def test_resolving_the_same_filename_twice_is_idempotent() -> None:
    """Section 8: dieselbe interne Operation darf ein bereits vorhandenes
    logisches Objekt nicht versehentlich duplizieren."""
    state: dict = {}
    obj = {"filename": "bild-1.dcm", "sop_class": "1.2.840.10008.5.1.4.1.1.2"}

    first = runtime_objects.resolve_object_from_environment_object(state, obj, origin_host="pacs")
    second = runtime_objects.resolve_object_from_environment_object(state, obj, origin_host="pacs")

    assert first == second
    assert len(state["objects"]) == 1


def test_different_filenames_get_different_object_ids() -> None:
    state: dict = {}

    first = runtime_objects.resolve_object_from_environment_object(
        state, {"filename": "bild-1.dcm"}, origin_host="pacs",
    )
    second = runtime_objects.resolve_object_from_environment_object(
        state, {"filename": "bild-2.dcm"}, origin_host="pacs",
    )

    assert first != second
    assert len(state["objects"]) == 2


def test_synthetic_objects_get_a_deterministic_uid_never_derived_from_a_sop_class() -> None:
    state: dict = {}

    object_id = runtime_objects.resolve_synthetic_object(
        state, node_slug="silent-ct", host_name="ct-console", index=1,
        study_uid="1.2.276.study", series_uid="1.2.276.series",
        sop_class="1.2.840.10008.5.1.4.1.1.2",
    )

    stored = state["objects"][object_id]
    assert stored["sop_instance_uid"] != stored["sop_class"]
    assert not stored["sop_instance_uid"].startswith(stored["sop_class"])
    assert stored["sop_instance_uid"].startswith("1.2.276.0.7230010.3.1.2.")
    assert stored["filename"] is None
    assert stored["origin_host"] == "ct-console"


def test_synthetic_sop_instance_uid_is_deterministic_across_calls() -> None:
    first = runtime_objects.synthetic_sop_instance_uid("silent-ct", "ct-console:1")
    second = runtime_objects.synthetic_sop_instance_uid("silent-ct", "ct-console:1")

    assert first == second


def test_synthetic_sop_instance_uid_differs_per_index_and_per_node() -> None:
    a = runtime_objects.synthetic_sop_instance_uid("silent-ct", "ct-console:1")
    b = runtime_objects.synthetic_sop_instance_uid("silent-ct", "ct-console:2")
    c = runtime_objects.synthetic_sop_instance_uid("wrong-door", "ct-console:1")

    assert len({a, b, c}) == 3


def test_resolving_the_same_synthetic_index_twice_is_idempotent() -> None:
    state: dict = {}
    kwargs = {
        "node_slug": "silent-ct", "host_name": "ct-console", "index": 1,
        "study_uid": "1.2.276.study", "series_uid": "1.2.276.series",
    }

    first = runtime_objects.resolve_synthetic_object(state, **kwargs)
    second = runtime_objects.resolve_synthetic_object(state, **kwargs)

    assert first == second
    assert len(state["objects"]) == 1


def test_presence_can_be_added_for_multiple_hosts_without_cloning_the_object() -> None:
    """Multi-Hop, Normalfall (ADR 0120, 8.3): dasselbe object_id liegt an
    mehreren Hosts, kein Klon, kein zweiter Registry-Eintrag."""
    state: dict = {}
    object_id = runtime_objects.resolve_object_from_environment_object(
        state, {"filename": "bild.dcm"}, origin_host="pacs",
    )

    runtime_objects.add_presence(state, object_id, "pacs")
    runtime_objects.add_presence(state, object_id, "dose-scp")

    assert state["stored_objects"]["pacs"] == [object_id]
    assert state["stored_objects"]["dose-scp"] == [object_id]
    assert len(state["objects"]) == 1


def test_adding_presence_twice_for_the_same_host_is_idempotent() -> None:
    state: dict = {}
    object_id = runtime_objects.resolve_object_from_environment_object(
        state, {"filename": "bild.dcm"}, origin_host="pacs",
    )

    runtime_objects.add_presence(state, object_id, "pacs")
    runtime_objects.add_presence(state, object_id, "pacs")

    assert state["stored_objects"]["pacs"] == [object_id]


def test_store_object_registers_presence_at_the_target_host() -> None:
    state: dict = {}
    object_id = runtime_objects.resolve_object_from_environment_object(
        state, {"filename": "bild.dcm"}, origin_host="pacs",
    )

    result = runtime_objects.store_object(state, object_id, "pacs")

    assert result["object_id"] == object_id
    assert state["stored_objects"]["pacs"] == [object_id]


def test_route_history_starts_empty_and_records_are_idempotent() -> None:
    state: dict = {}
    object_id = runtime_objects.resolve_object_from_environment_object(
        state, {"filename": "bild.dcm"}, origin_host="pacs",
    )

    assert runtime_objects.has_route_processed(state, object_id, "pacs", "CT-TO-DOSE") is False

    runtime_objects.record_route_processed(state, object_id, "pacs", "CT-TO-DOSE")
    runtime_objects.record_route_processed(state, object_id, "pacs", "CT-TO-DOSE")

    assert runtime_objects.has_route_processed(state, object_id, "pacs", "CT-TO-DOSE") is True
    assert state["objects"][object_id]["route_history"] == [["pacs", "CT-TO-DOSE"]]


def test_route_history_is_per_object_not_shared_globally() -> None:
    state: dict = {}
    a = runtime_objects.resolve_object_from_environment_object(
        state, {"filename": "a.dcm"}, origin_host="pacs",
    )
    b = runtime_objects.resolve_object_from_environment_object(
        state, {"filename": "b.dcm"}, origin_host="pacs",
    )

    runtime_objects.record_route_processed(state, a, "pacs", "CT-TO-DOSE")

    assert runtime_objects.has_route_processed(state, a, "pacs", "CT-TO-DOSE") is True
    assert runtime_objects.has_route_processed(state, b, "pacs", "CT-TO-DOSE") is False
