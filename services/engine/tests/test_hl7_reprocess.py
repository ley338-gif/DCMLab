"""Abschnitt 6l: HL7-v2-Nachrichteninspektion (`mllpq`) und Reprocessing
(`mllpsend`) -- dieselbe Text-in/Text-out-Simulation gegen node.yml-Fixtures
wie jedes DICOM-Werkzeug, kein echter MLLP-Socket.
"""

from app import rules
from app.content import NodeDefinition

RAW_MESSAGE = (
    "MSH|^~\\&|RIS|KH|IFE|KH|20260910081500||ORM^O01|MSG4711|P|2.3\n"
    "PID|1||4711^^^KH^MR||MUSTER^ERIKA||19750314|F\n"
    "ORC|NW|ORD93821\n"
    "OBR|1||ORD93821||CTTHX2^CT Thorax mit KM^L"
)
REJECT_ACK = (
    "MSA|AE|MSG4711|Unknown procedure code CTTHX2\nERR|||OBR^4^1|103^Table value not found"
)
ACCEPT_ACK = "MSA|AA|MSG4711"

NODE = NodeDefinition(
    slug="hl7-order-gap-test",
    raw={
        "environment": {
            "hosts": [
                {
                    "name": "workstation", "ip": "10.0.0.50", "role": "shell",
                    "config_editable": True, "config": {"procedure_codes": ""},
                },
                {
                    "name": "broker", "ip": "10.0.0.10",
                    "services": [{"port": 104, "ae_title": "BROKER", "accepts": []}],
                    "worklist": [],
                },
            ],
            "known_calling_aets": ["WORKSTATION"],
            "tools": ["mllpq", "mllpsend", "findscu"],
            "messages": [
                {
                    "id": "MSG4711",
                    "raw": RAW_MESSAGE,
                    "ack": REJECT_ACK,
                    "reprocess": {
                        "requires_config_field": "procedure_codes",
                        "requires_value": "CTTHX2",
                        "ack": ACCEPT_ACK,
                        "worklist_entry": {
                            "host": "broker",
                            "patient_id": "4711",
                            "patient_name": "MUSTER^ERIKA",
                            "accession_number": "ORD93821",
                            "scheduled_station_ae_title": "CT5-RAUM3",
                            "scheduled_procedure_step_start_date": "20260910",
                            "modality": "CT",
                        },
                    },
                },
            ],
        },
    },
)

# hl7-ack-trap-Fixture: eine Nachricht ganz ohne reprocess-Block --
# read-only-by-design, mllpsend muss darauf sauber fehlschlagen.
NODE_READONLY = NodeDefinition(
    slug="hl7-ack-trap-test",
    raw={
        "environment": {
            "hosts": [{"name": "workstation", "ip": "10.0.0.50", "role": "shell"}],
            "tools": ["mllpq", "mllpsend"],
            "messages": [
                {
                    "id": "MSG8821",
                    "raw": "MSH|...|ADT^A08|MSG8821|P|2.5",
                    "ack": "MSA|AE|MSG8821|Patient identifier domain unknown",
                },
            ],
        },
    },
)


def fresh_state() -> dict:
    return rules.initial_state(NODE)


def test_mllpq_lists_declared_messages_with_ack_code() -> None:
    state = fresh_state()

    result = rules.exec_command(NODE, state, "workstation", "mllpq")

    assert result.stdout == "MSG4711  AE"


def test_mllpq_show_dumps_raw_message_and_current_ack() -> None:
    state = fresh_state()

    result = rules.exec_command(NODE, state, "workstation", "mllpq MSG4711")

    assert RAW_MESSAGE in result.stdout
    assert REJECT_ACK in result.stdout


def test_mllpq_unknown_id_errors() -> None:
    state = fresh_state()

    result = rules.exec_command(NODE, state, "workstation", "mllpq NOPE")

    assert result.exit_code == 1
    assert "unknown message" in result.stderr


def test_mllpsend_before_config_fix_repeats_the_rejection() -> None:
    state = fresh_state()

    result = rules.exec_command(NODE, state, "workstation", "mllpsend MSG4711")

    assert result.exit_code == 1
    assert REJECT_ACK.splitlines()[0] in result.stdout
    assert state["hl7_messages"]["MSG4711"]["resolved"] is False


def test_mllpsend_after_config_fix_succeeds_and_updates_ack() -> None:
    state = fresh_state()
    error = rules.set_config(NODE, state, "workstation", "procedure_codes", "CTTHX2")
    assert error is None

    result = rules.exec_command(NODE, state, "workstation", "mllpsend MSG4711")

    assert result.exit_code == 0
    assert ACCEPT_ACK in result.stdout
    assert state["hl7_messages"]["MSG4711"]["resolved"] is True

    follow_up = rules.exec_command(NODE, state, "workstation", "mllpq MSG4711")
    assert ACCEPT_ACK in follow_up.stdout
    assert REJECT_ACK not in follow_up.stdout


def test_mllpsend_creates_dynamic_worklist_entry_visible_to_findscu_dash_w() -> None:
    state = fresh_state()
    rules.set_config(NODE, state, "workstation", "procedure_codes", "CTTHX2")
    rules.exec_command(NODE, state, "workstation", "mllpsend MSG4711")

    result = rules.exec_command(
        NODE, state, "workstation",
        "findscu -W -k PatientID=4711 -aet WORKSTATION -aec BROKER 10.0.0.10 104",
    )

    assert "I: Number of Matches: 1" in result.stdout
    assert "MUSTER^ERIKA" in result.stdout


def test_mllpsend_is_idempotent_once_resolved() -> None:
    state = fresh_state()
    rules.set_config(NODE, state, "workstation", "procedure_codes", "CTTHX2")
    rules.exec_command(NODE, state, "workstation", "mllpsend MSG4711")

    result = rules.exec_command(NODE, state, "workstation", "mllpsend MSG4711")

    assert result.exit_code == 0
    assert ACCEPT_ACK in result.stdout


def test_mllpsend_on_message_without_reprocess_block_errors() -> None:
    state = rules.initial_state(NODE_READONLY)

    result = rules.exec_command(NODE_READONLY, state, "workstation", "mllpsend MSG8821")

    assert result.exit_code == 1
    assert "keine Reprocessing-Regel" in result.stderr
