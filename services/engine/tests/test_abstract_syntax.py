"""Feature 4 (P10): Abstract-Syntax-(SOP-Class-)Ablehnung beim Senden.
Konstruiert die Node direkt, um die Regel isoliert vom Content zu pruefen --
Host/Port/AE-Title und Transfer Syntax stimmen hier immer, nur der
vorgeschlagene SOP Class UID entscheidet ueber Erfolg oder Ablehnung.
"""

from app import rules
from app.content import NodeDefinition

CT_IMAGE_STORAGE = "1.2.840.10008.5.1.4.1.1.2"
ENHANCED_CT_IMAGE_STORAGE = "1.2.840.10008.5.1.4.1.1.2.1"
IMPLICIT_VR_LE = "1.2.840.10008.1.2"

NODE = NodeDefinition(
    slug="as-test",
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
                            "accepted_sop_classes": [CT_IMAGE_STORAGE],
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
                        "transfer_syntax": IMPLICIT_VR_LE,
                        "sop_class": ENHANCED_CT_IMAGE_STORAGE,
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


def test_unsupported_sop_class_is_rejected_without_touching_the_bestand() -> None:
    state = fresh_state()

    result = rules.trigger_action(NODE, state, "modality", "send_study")

    assert result.error is None
    assert any("No Acceptable Presentation Contexts" in line for line in result.log)
    assert any("abstract-syntax-not-supported" in line for line in result.log)
    assert result.events[-1]["type"] == "presentation_context_rejected"
    assert result.events[-1]["reason"] == "abstract_syntax_not_supported"
    assert state["bestand"]["archive"] == {"studies": 0, "series": 0, "instances": 0}


def test_matching_sop_class_succeeds() -> None:
    state = fresh_state()
    rules.set_config(NODE, state, "modality", "sop_class", CT_IMAGE_STORAGE)

    result = rules.trigger_action(NODE, state, "modality", "send_study")

    assert result.events[-1]["type"] == "store_completed"
    assert state["bestand"]["archive"]["studies"] == 1


def test_abstract_syntax_check_takes_priority_over_transfer_syntax() -> None:
    """Ein Presentation Context traegt Abstract- und Transfer-Syntax
    zusammen -- schlaegt schon der Abstract Syntax fehl, ist die Transfer
    Syntax irrelevant. Hier waere die Transfer Syntax sogar korrekt."""
    state = fresh_state()
    rules.set_config(NODE, state, "modality", "transfer_syntax", "1.2.840.10008.1.2.4.91")

    result = rules.trigger_action(NODE, state, "modality", "send_study")

    assert result.events[-1]["reason"] == "abstract_syntax_not_supported"


def test_host_port_ae_mismatches_still_take_priority_over_abstract_syntax() -> None:
    state = fresh_state()
    rules.set_config(NODE, state, "modality", "remote_port", 9999)

    result = rules.trigger_action(NODE, state, "modality", "send_study")

    assert result.events[-1]["type"] == "association_rejected"
    assert result.events[-1]["reason"] == "wrong_port"


def test_nodes_without_accepted_sop_classes_keep_the_pre_p10_9_behaviour() -> None:
    node = NodeDefinition(
        slug="no-as-check",
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
