"""Feature 9 (P10): weitere reale `dcmdump`-Felder fuer die Hierarchie
(PatientID, StudyInstanceUID, SeriesInstanceUID) -- fuer Nodes, in denen
ein Ordner mit mehreren Objekten per Zaehlschleife je Ebene ausgewertet
werden soll (Lektion 1.2).
"""

from app import rules
from app.content import NodeDefinition

NODE = NodeDefinition(
    slug="hierarchy-node",
    raw={
        "environment": {
            "hosts": [{"name": "workstation", "ip": "10.0.0.50", "role": "shell"}],
            "tools": ["dcmdump"],
            "objects": [
                {
                    "filename": "bild-01.dcm", "bytes": 1024,
                    "patient_id": "4711",
                    "study_uid": "1.2.276.0.7230010.3.1.4.1",
                    "series_uid": "1.2.276.0.7230010.3.1.3.1",
                },
            ],
        },
    },
)


def fresh_state() -> dict:
    return rules.initial_state(NODE)


def test_dcmdump_shows_patient_study_series_in_ascending_tag_order() -> None:
    state = fresh_state()

    result = rules.exec_command(NODE, state, "workstation", "dcmdump bild-01.dcm")

    assert result.stdout == (
        "I: (0010,0020) LO [4711]  # xx, 1 PatientID\n"
        "I: (0020,000D) UI [1.2.276.0.7230010.3.1.4.1]  # xx, 1 StudyInstanceUID\n"
        "I: (0020,000E) UI [1.2.276.0.7230010.3.1.3.1]  # xx, 1 SeriesInstanceUID\n"
    )
