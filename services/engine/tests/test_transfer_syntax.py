"""Feature 3 (P10): Presentation-Context-/Transfer-Syntax-Aushandlung beim
Senden. Konstruiert die Node direkt, um die Regel isoliert vom Content zu
pruefen -- Host/Port/AE-Title stimmen hier immer, nur die Transfer Syntax
entscheidet ueber Erfolg oder Ablehnung.
"""

from app import rules
from app.content import NodeDefinition

IMPLICIT_VR_LE = "1.2.840.10008.1.2"
JPEG_2000 = "1.2.840.10008.1.2.4.91"

NODE = NodeDefinition(
    slug="ts-test",
    raw={
        "environment": {
            "hosts": [
                {
                    "name": "archive",
                    "ip": "10.0.0.10",
                    "services": [
                        {
                            "port": 104, "ae_title": "TEST-ARCHIV",
                            "accepts": ["ct-image-storage"],
                            "accepted_transfer_syntaxes": [IMPLICIT_VR_LE],
                        },
                    ],
                },
                {
                    "name": "modality",
                    "ip": "10.0.0.40",
                    "role": "modality-simulator",
                    "config_editable": True,
                    "config": {
                        "local_ae": "MOD-1",
                        "remote_ae": "TEST-ARCHIV",
                        "remote_host": "10.0.0.10",
                        "remote_port": 104,
                        "transfer_syntax": JPEG_2000,
                    },
                },
            ],
            "known_calling_aets": ["MOD-1"],
            "dataset": None,
        },
    },
)


def fresh_state() -> dict:
    return rules.initial_state(NODE)


def test_mismatched_transfer_syntax_is_rejected_without_touching_the_bestand() -> None:
    state = fresh_state()

    result = rules.trigger_action(NODE, state, "modality", "send_study")

    assert result.error is None
    assert any("No Acceptable Presentation Contexts" in line for line in result.log)
    assert result.events[-1]["type"] == "presentation_context_rejected"
    assert state["bestand"]["archive"] == {"studies": 0, "series": 0, "instances": 0}


def test_matching_transfer_syntax_succeeds() -> None:
    state = fresh_state()
    rules.set_config(NODE, state, "modality", "transfer_syntax", IMPLICIT_VR_LE)

    result = rules.trigger_action(NODE, state, "modality", "send_study")

    assert result.events[-1]["type"] == "store_completed"
    assert state["bestand"]["archive"]["studies"] == 1


def test_host_port_ae_mismatches_still_take_priority_over_transfer_syntax() -> None:
    """Die Reihenfolge aus Abschnitt 5.3 bleibt unveraendert: Association
    zuerst, Presentation Context erst danach."""
    state = fresh_state()
    rules.set_config(NODE, state, "modality", "remote_port", 9999)

    result = rules.trigger_action(NODE, state, "modality", "send_study")

    assert result.events[-1]["type"] == "association_rejected"
    assert result.events[-1]["reason"] == "wrong_port"


def test_nodes_without_accepted_transfer_syntaxes_keep_the_pre_p10_behaviour() -> None:
    node = NodeDefinition(
        slug="no-ts-check",
        raw={
            "environment": {
                "hosts": [
                    {
                        "name": "archive", "ip": "10.0.0.10",
                        "services": [{"port": 104, "ae_title": "ARCHIV", "accepts": []}],
                    },
                    {
                        "name": "modality", "ip": "10.0.0.40", "role": "modality-simulator",
                        "config_editable": True,
                        "config": {
                            "local_ae": "MOD", "remote_ae": "ARCHIV",
                            "remote_host": "10.0.0.10", "remote_port": 104,
                        },
                    },
                ],
                "known_calling_aets": ["MOD"],
            },
        },
    )
    state = rules.initial_state(node)

    result = rules.trigger_action(node, state, "modality", "send_study")

    assert result.events[-1]["type"] == "store_completed"
