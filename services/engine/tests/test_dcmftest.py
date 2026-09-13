"""Feature 8 (P10): `dcmftest` prueft, ob eine Datei ueberhaupt gueltiges
DICOM-Format ist -- die reale erste Frage vor jedem `dcmdump` (Lektion 1.1).
"""

from app import rules
from app.content import NodeDefinition

NODE = NodeDefinition(
    slug="dcmftest-node",
    raw={
        "environment": {
            "hosts": [{"name": "workstation", "ip": "10.0.0.50", "role": "shell"}],
            "tools": ["dcmftest", "dcmdump"],
            "objects": [
                {
                    "filename": "datei-ohne-namen", "bytes": 524288,
                    "modality": "US", "study_description": "Abdomen komplett",
                },
            ],
        },
    },
)


def fresh_state() -> dict:
    return rules.initial_state(NODE)


def test_dcmftest_reports_yes_for_a_known_object() -> None:
    state = fresh_state()

    result = rules.exec_command(NODE, state, "workstation", "dcmftest datei-ohne-namen")

    assert result.stdout == "yes: datei-ohne-namen"
    assert result.exit_code == 0


def test_dcmftest_reports_no_for_an_unknown_file() -> None:
    state = fresh_state()

    result = rules.exec_command(NODE, state, "workstation", "dcmftest nicht-vorhanden")

    assert result.stdout == "no: nicht-vorhanden"
    assert result.exit_code == 1


def test_dcmdump_shows_modality_and_study_description() -> None:
    state = fresh_state()

    result = rules.exec_command(NODE, state, "workstation", "dcmdump datei-ohne-namen")

    assert "(0008,0060) CS [US]  # xx, 1 Modality" in result.stdout
    assert "(0008,1030) LO [Abdomen komplett]  # xx, 1 StudyDescription" in result.stdout


def test_dcmftest_is_not_gated_behind_node_tools_declaration() -> None:
    """dcmftest ist wie dcmdump ein reiner Lesebefehl, kein Netzwerk-Tool --
    dieselbe Gate-Ausnahme wie bei echoscu/storescu/findscu gilt hier nicht."""
    node = NodeDefinition(
        slug="dcmftest-no-tools-declared",
        raw={
            "environment": {
                "hosts": [{"name": "workstation", "ip": "10.0.0.50", "role": "shell"}],
                "objects": [{"filename": "x", "bytes": 1, "modality": "CT"}],
            },
        },
    )
    state = rules.initial_state(node)

    result = rules.exec_command(node, state, "workstation", "dcmftest x")

    assert result.stdout == "yes: x"
