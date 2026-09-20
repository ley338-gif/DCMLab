"""ADR 0120, Phase A: `send_study` (Sendeauftrag einer Modalitaets-
Simulation) normalisiert auf `store_object()` -- `datasets.yml` kennt keine
einzelnen Objekte, nur einen `file_count`, deshalb werden RuntimeObjects hier
deterministisch synthetisiert statt aus `environment.objects[]` aufgeloest."""

from app import rules
from app.content import NodeDefinition

NODE = NodeDefinition(
    slug="send-study-runtime-objects-test",
    raw={
        "environment": {
            "dataset": "test-dataset",
            "hosts": [
                {
                    "name": "ct-1", "ip": "10.0.0.50", "role": "modality-simulator",
                    "config_editable": True,
                    "config": {
                        "local_ae": "CT_1", "remote_ae": "TEST-ARCHIV",
                        "remote_host": "10.0.0.10", "remote_port": 104,
                        "sop_class": "1.2.840.10008.5.1.4.1.1.2",
                        "transfer_syntax": "1.2.840.10008.1.2",
                    },
                },
                {
                    "name": "archive", "ip": "10.0.0.10",
                    "services": [{"port": 104, "ae_title": "TEST-ARCHIV"}],
                },
            ],
            "known_calling_aets": ["CT_1"],
        },
    },
)


def test_send_study_synthesizes_one_runtime_object_per_file_and_registers_presence() -> None:
    state = rules.initial_state(NODE)

    result = rules.trigger_action(NODE, state, "ct-1", "send_study")

    assert result.events[-1]["type"] == "store_completed"
    assert len(state["objects"]) == 2  # test-dataset: file_count 2
    assert sorted(state["stored_objects"]["archive"]) == sorted(state["objects"].keys())


def test_send_study_objects_share_study_and_series_but_have_distinct_instance_uids() -> None:
    state = rules.initial_state(NODE)

    rules.trigger_action(NODE, state, "ct-1", "send_study")

    objects = list(state["objects"].values())
    study_uids = {o["study_uid"] for o in objects}
    series_uids = {o["series_uid"] for o in objects}
    sop_instance_uids = {o["sop_instance_uid"] for o in objects}

    assert len(study_uids) == 1
    assert len(series_uids) == 1
    assert len(sop_instance_uids) == len(objects)


def test_send_study_objects_carry_metadata_from_the_sender_config_and_dataset() -> None:
    state = rules.initial_state(NODE)

    rules.trigger_action(NODE, state, "ct-1", "send_study")

    for obj in state["objects"].values():
        assert obj["sop_class"] == "1.2.840.10008.5.1.4.1.1.2"
        assert obj["transfer_syntax"] == "1.2.840.10008.1.2"
        assert obj["study_description"] == "Test Study"
        assert obj["series_description"] == "Test Series"
        assert obj["origin_host"] == "ct-1"
        # Section 9: keine Modality erfunden, die die vorhandene Sender-
        # Konfiguration dieses Hosts nicht hergibt.
        assert obj["modality"] is None


def test_a_rejected_send_study_registers_no_runtime_objects() -> None:
    node = NodeDefinition(
        slug="send-study-rejected-test",
        raw={
            "environment": {
                "dataset": "test-dataset",
                "hosts": [
                    {
                        "name": "ct-1", "ip": "10.0.0.50", "role": "modality-simulator",
                        "config_editable": True,
                        "config": {
                            "local_ae": "CT_1", "remote_ae": "WRONG-AE",
                            "remote_host": "10.0.0.10", "remote_port": 104,
                        },
                    },
                    {
                        "name": "archive", "ip": "10.0.0.10",
                        "services": [{"port": 104, "ae_title": "TEST-ARCHIV"}],
                    },
                ],
                "known_calling_aets": ["CT_1"],
            },
        },
    )
    state = rules.initial_state(node)

    result = rules.trigger_action(node, state, "ct-1", "send_study")

    assert result.events[-1]["type"] == "association_rejected"
    assert state.get("objects", {}) == {}
    assert state.get("stored_objects", {}) == {}


def test_calling_send_study_twice_successfully_does_not_duplicate_objects() -> None:
    state = rules.initial_state(NODE)

    rules.trigger_action(NODE, state, "ct-1", "send_study")
    rules.trigger_action(NODE, state, "ct-1", "send_study")

    assert len(state["objects"]) == 2
    assert sorted(state["stored_objects"]["archive"]) == sorted(state["objects"].keys())
