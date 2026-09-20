"""ADR 0120, Phase A: `storescu` (direkter Objekt-Pfad) normalisiert auf
`store_object()` -- RuntimeObject/Presence entstehen zusaetzlich zum
unveraenderten `bestand`-Zaehler, ohne dessen bestehendes Verhalten (Feature
1-5 aus P10) zu regressieren."""

from app import rules
from app.content import NodeDefinition

CT_IMAGE_STORAGE = "1.2.840.10008.5.1.4.1.1.2"
SECONDARY_CAPTURE = "1.2.840.10008.5.1.4.1.1.7"

NODE = NodeDefinition(
    slug="storescu-runtime-objects-test",
    raw={
        "environment": {
            "hosts": [
                {"name": "workstation", "ip": "10.0.0.50", "role": "shell"},
                {
                    "name": "archive", "ip": "10.0.0.10",
                    "services": [{
                        "port": 104, "ae_title": "TEST-ARCHIV",
                        "accepted_sop_classes": [CT_IMAGE_STORAGE],
                    }],
                },
            ],
            "known_calling_aets": ["WORKSTATION"],
            "tools": ["storescu"],
            "objects": [
                {
                    "filename": "ct-bild.dcm", "bytes": 524_288,
                    "sop_class": CT_IMAGE_STORAGE, "modality": "CT",
                },
                {"filename": "screenshot.dcm", "bytes": 262_144, "sop_class": SECONDARY_CAPTURE},
            ],
        },
    },
)


def _store(state: dict, filename: str) -> rules.ExecResult:
    return rules.exec_command(
        NODE, state, "workstation",
        f"storescu -aet WORKSTATION -aec TEST-ARCHIV 10.0.0.10 104 {filename}",
    )


def test_a_successful_store_registers_a_runtime_object_and_presence() -> None:
    state = rules.initial_state(NODE)

    result = _store(state, "ct-bild.dcm")

    assert result.exit_code == 0
    assert len(state["objects"]) == 1
    object_id = next(iter(state["objects"]))
    stored = state["objects"][object_id]
    assert stored["filename"] == "ct-bild.dcm"
    assert stored["sop_class"] == CT_IMAGE_STORAGE
    assert stored["modality"] == "CT"
    assert state["stored_objects"]["archive"] == [object_id]


def test_origin_host_is_the_sending_host_not_the_target_archive() -> None:
    """ADR 0120, Phase-A-Review: `origin_host` ist der simulierte Host, von
    dem ein Objekt urspruenglich gesendet wurde -- bei direktem `storescu`
    von einer Shell ist das die Shell (`workstation`), nicht das Ziel-PACS
    (`archive`). Herkunft (`origin_host`) und aktueller Speicherort
    (Presence) sind unabhaengige Fragen: dasselbe Objekt hat EINEN Ursprung,
    aber kann an mehreren Hosts liegen."""
    state = rules.initial_state(NODE)

    _store(state, "ct-bild.dcm")

    object_id = next(iter(state["objects"]))
    assert state["objects"][object_id]["origin_host"] == "workstation"
    assert state["stored_objects"]["archive"] == [object_id]
    assert "workstation" not in state.get("stored_objects", {})


def test_a_rejected_store_registers_no_runtime_object() -> None:
    """Feature 5 (P10): screenshot.dcm wird wegen der SOP Class abgelehnt --
    kein RuntimeObject fuer ein Objekt, das nie ankam."""
    state = rules.initial_state(NODE)

    result = _store(state, "screenshot.dcm")

    assert result.exit_code == 1
    assert state.get("objects", {}) == {}
    assert state.get("stored_objects", {}) == {}


def test_resending_the_same_file_reuses_the_object_id_but_bestand_counts_each_store() -> None:
    """Section 8: Idempotenz gilt fuer die RuntimeObject-Identitaet, nicht
    fuer den unveraenderten bestand-Zaehler (der zaehlt weiterhin jeden
    erfolgreichen C-STORE, siehe test_teiltransfer_is_solvable_from_the_
    real_content in test_real_content.py)."""
    state = rules.initial_state(NODE)

    _store(state, "ct-bild.dcm")
    _store(state, "ct-bild.dcm")

    assert len(state["objects"]) == 1
    assert state["bestand"]["archive"]["instances"] == 2
    object_id = next(iter(state["objects"]))
    assert state["stored_objects"]["archive"] == [object_id]


def test_a_mixed_folder_registers_only_the_accepted_object() -> None:
    state = rules.initial_state(NODE)

    _store(state, "ct-bild.dcm")
    _store(state, "screenshot.dcm")

    assert len(state["objects"]) == 1
    stored = next(iter(state["objects"].values()))
    assert stored["filename"] == "ct-bild.dcm"
