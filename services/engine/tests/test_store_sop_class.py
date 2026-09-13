"""Feature 5 (P10): Abstract-Syntax-Ablehnung pro Objekt beim direkten
storescu von der Shell -- dieselbe Ablehnung wie Feature 4
(trigger_action), aber pro einzelner Datei statt pro Sendeauftrag einer
Modalitaets-Simulation. Ein gemischter Ordner kann so teilweise ankommen.
"""

from app import rules
from app.content import NodeDefinition

CT_IMAGE_STORAGE = "1.2.840.10008.5.1.4.1.1.2"
SECONDARY_CAPTURE = "1.2.840.10008.5.1.4.1.1.7"

NODE = NodeDefinition(
    slug="sop-class-test",
    raw={
        "environment": {
            "hosts": [
                {"name": "workstation", "ip": "10.0.0.50", "role": "shell"},
                {
                    "name": "archive",
                    "ip": "10.0.0.10",
                    "services": [
                        {
                            "port": 104, "ae_title": "TEST-ARCHIV",
                            "accepts": ["ct-image-storage"],
                            "accepted_sop_classes": [CT_IMAGE_STORAGE],
                        },
                    ],
                },
            ],
            "known_calling_aets": ["WORKSTATION"],
            "tools": ["storescu"],
            "objects": [
                {"filename": "ct-bild.dcm", "bytes": 524_288, "sop_class": CT_IMAGE_STORAGE},
                {"filename": "screenshot.dcm", "bytes": 262_144, "sop_class": SECONDARY_CAPTURE},
            ],
        },
    },
)


def fresh_state() -> dict:
    return rules.initial_state(NODE)


def _store(state: dict, filename: str) -> rules.ExecResult:
    return rules.exec_command(
        NODE, state, "workstation",
        f"storescu -aet WORKSTATION -aec TEST-ARCHIV 10.0.0.10 104 {filename}",
    )


def test_an_accepted_sop_class_is_stored() -> None:
    state = fresh_state()

    result = _store(state, "ct-bild.dcm")

    assert result.exit_code == 0
    assert state["bestand"]["archive"] == {"studies": 1, "series": 1, "instances": 1}


def test_an_unsupported_sop_class_is_refused_and_not_counted() -> None:
    state = fresh_state()

    result = _store(state, "screenshot.dcm")

    assert result.exit_code == 1
    assert "abstract-syntax-not-supported" in result.stderr
    assert state["bestand"]["archive"] == {"studies": 0, "series": 0, "instances": 0}


def test_a_mixed_folder_arrives_only_partially() -> None:
    """Genau das Szenario aus Lektion 4.3: ein Ordner mit gemischten SOP
    Classes kommt nicht ganz oder gar nicht an, sondern teilweise."""
    state = fresh_state()

    _store(state, "ct-bild.dcm")
    _store(state, "screenshot.dcm")

    assert state["bestand"]["archive"] == {"studies": 1, "series": 1, "instances": 1}


def test_dcmdump_shows_the_real_sop_class_uid_of_an_object() -> None:
    state = fresh_state()

    result = rules.exec_command(NODE, state, "workstation", "dcmdump screenshot.dcm")

    assert result.exit_code == 0
    assert "(0008,0016)" in result.stdout
    assert SECONDARY_CAPTURE in result.stdout
    assert "SOPClassUID" in result.stdout


def test_dcmdump_on_an_object_without_a_declared_sop_class_keeps_the_pre_p10_10_behaviour() -> None:
    node = NodeDefinition(
        slug="dcmdump-no-sop-class",
        raw={
            "environment": {
                "hosts": [{"name": "workstation", "ip": "10.0.0.50", "role": "shell"}],
                "tools": ["storescu"],
                "objects": [{"filename": "klein.dcm", "bytes": 1024}],
            },
        },
    )
    state = rules.initial_state(node)

    result = rules.exec_command(node, state, "workstation", "dcmdump klein.dcm")

    assert result.exit_code == 1
    assert "keine lokale Datei in dieser Simulation" in result.stderr


def test_nodes_without_accepted_sop_classes_keep_the_pre_p10_10_behaviour() -> None:
    node = NodeDefinition(
        slug="no-sop-class-check",
        raw={
            "environment": {
                "hosts": [
                    {"name": "workstation", "ip": "10.0.0.50", "role": "shell"},
                    {
                        "name": "archive", "ip": "10.0.0.10",
                        "services": [{"port": 104, "ae_title": "ARCHIV", "accepts": []}],
                    },
                ],
                "known_calling_aets": ["WORKSTATION"],
                "tools": ["storescu"],
                "objects": [{"filename": "x.dcm", "bytes": 1024}],
            },
        },
    )
    state = rules.initial_state(node)

    result = rules.exec_command(
        node, state, "workstation",
        "storescu -aet WORKSTATION -aec ARCHIV 10.0.0.10 104 x.dcm",
    )

    assert result.exit_code == 0
    assert state["bestand"]["archive"] == {"studies": 1, "series": 1, "instances": 1}
