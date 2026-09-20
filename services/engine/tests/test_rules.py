"""Tabellen-getriebene Tests fuer das Regelwerk (Abschnitt 5.3, 9): jede der
fuenf Ablehnungsstufen, die DCMTK-Defaults, die stille Erfolgsausgabe, die
SERIES-ohne-Study-Abweisung und der Platzhalter-Abfang je ein Testfall.
"""

import copy
from datetime import UTC, datetime, timedelta

import pytest

from app import rules
from app.content import NodeDefinition, load_node
from app.flag import hash_value

NODE = load_node("test-node")


def fresh_state() -> dict:
    return rules.initial_state(NODE)


# -- Die fuenf Ablehnungsstufen, in der vorgeschriebenen Reihenfolge -------


@pytest.mark.parametrize(
    ("calling_ae", "target_ae", "ip", "port", "accepted", "message"),
    [
        pytest.param(
            "WORKSTATION", "TEST-ARCHIV", "10.0.0.99", 104, False, rules.MSG_HOST_UNKNOWN,
            id="1_host_unknown",
        ),
        pytest.param(
            "WORKSTATION", "TEST-ARCHIV", "10.0.0.10", 9999, False, rules.MSG_WRONG_PORT,
            id="2_wrong_port",
        ),
        pytest.param(
            "WORKSTATION", "WRONG_AE", "10.0.0.10", 104, False, rules.MSG_CALLED_AE_REJECTED,
            id="3_called_ae_unknown",
        ),
        pytest.param(
            "UNKNOWN_WS", "TEST-ARCHIV", "10.0.0.10", 104, False, rules.MSG_CALLING_AE_REJECTED,
            id="4_calling_ae_unknown",
        ),
        pytest.param("WORKSTATION", "TEST-ARCHIV", "10.0.0.10", 104, True, "", id="5_accepted"),
    ],
)
def test_association_pipeline_stages(calling_ae, target_ae, ip, port, accepted, message) -> None:
    state = fresh_state()

    result = rules.check_association(NODE, state, calling_ae, target_ae, ip, port)

    assert result.accepted is accepted
    assert result.message == message


def test_stage_1_and_2_do_not_move_any_counter() -> None:
    state = fresh_state()

    rules.check_association(NODE, state, "WORKSTATION", "TEST-ARCHIV", "10.0.0.99", 104)
    rules.check_association(NODE, state, "WORKSTATION", "TEST-ARCHIV", "10.0.0.10", 9999)

    assert state["counters"]["archive"] == {"accepted": 0, "rejected": 0}


def test_stage_3_and_4_increment_the_rejected_counter() -> None:
    state = fresh_state()

    rules.check_association(NODE, state, "WORKSTATION", "WRONG_AE", "10.0.0.10", 104)
    rules.check_association(NODE, state, "UNKNOWN_WS", "TEST-ARCHIV", "10.0.0.10", 104)

    assert state["counters"]["archive"] == {"accepted": 0, "rejected": 2}


def test_stage_5_increments_the_accepted_counter() -> None:
    state = fresh_state()

    rules.check_association(NODE, state, "WORKSTATION", "TEST-ARCHIV", "10.0.0.10", 104)

    assert state["counters"]["archive"] == {"accepted": 1, "rejected": 0}


# -- DCMTK-Defaults ---------------------------------------------------------


def test_dcmtk_defaults_apply_when_aet_and_aec_are_omitted() -> None:
    state = fresh_state()

    # echoscu ohne -aet/-aec: -aet ECHOSCU, -aec ANY-SCP (Abschnitt 5.3) --
    # beides ist hier weder als bekannte Calling AE noch als Called AE gueltig.
    result = rules.exec_command(NODE, state, "workstation", "echoscu 10.0.0.10 104")

    assert result.exit_code == 1
    assert "Called AE Title Not Recognized" in result.stderr


def test_dcmtk_default_aec_any_scp_is_rejected_as_called_ae() -> None:
    parsed = rules._parse_dcmtk_args("echoscu", [])

    assert parsed["aet"] == "ECHOSCU"
    assert parsed["aec"] == "ANY-SCP"


# -- Stille Erfolgsausgabe ---------------------------------------------------


def test_successful_echoscu_is_silent() -> None:
    state = fresh_state()

    result = rules.exec_command(
        NODE, state, "workstation", "echoscu -aet WORKSTATION -aec TEST-ARCHIV 10.0.0.10 104",
    )

    assert result.exit_code == 0
    assert result.stdout == ""
    assert result.stderr == ""


# -- SERIES-Abfrage ohne StudyInstanceUID -----------------------------------


def test_series_level_query_without_study_instance_uid_is_rejected() -> None:
    state = fresh_state()

    result = rules.exec_command(
        NODE,
        state,
        "workstation",
        "findscu -S -k QueryRetrieveLevel=SERIES -k SeriesDescription "
        "-aet WORKSTATION -aec TEST-ARCHIV 10.0.0.10 104",
    )

    assert result.exit_code == 1
    assert "0xa900" in result.stderr


# -- Platzhalter-Abfang ------------------------------------------------------


def test_unsubstituted_placeholder_in_query_is_caught() -> None:
    state = fresh_state()

    result = rules.exec_command(
        NODE,
        state,
        "workstation",
        "findscu -S -k QueryRetrieveLevel=SERIES -k StudyInstanceUID=UID_AUS_SCHRITT_1 "
        "-aet WORKSTATION -aec TEST-ARCHIV 10.0.0.10 104",
    )

    assert result.exit_code == 1
    assert "VR UI" in result.stderr
    assert "UID_AUS_SCHRITT_1" in result.stderr


# -- storescu von der Workstation scheitert an fehlenden Dateien -----------


def test_storescu_from_workstation_fails_on_missing_files() -> None:
    state = fresh_state()

    result = rules.exec_command(
        NODE, state, "workstation", "storescu -aet WORKSTATION -aec TEST-ARCHIV 10.0.0.10 104",
    )

    assert result.exit_code == 1
    assert "No such file" in result.stderr


# -- Nicht-DICOM-Befehle und unbekannte Befehle -----------------------------


def test_unknown_command_returns_command_not_found_never_a_stacktrace() -> None:
    state = fresh_state()

    result = rules.exec_command(NODE, state, "workstation", "rm -rf /")

    assert result.exit_code == 127
    assert result.stderr == "rm: command not found"


def test_tool_not_declared_in_node_is_also_command_not_found() -> None:
    state = fresh_state()

    result = rules.exec_command(
        NODE, state, "workstation", "movescu -aet WORKSTATION -aec TEST-ARCHIV 10.0.0.10 104",
    )

    assert result.exit_code == 127


def test_no_shell_on_the_modality_console() -> None:
    state = fresh_state()

    result = rules.exec_command(
        NODE, state, "modality", "echoscu -aet WORKSTATION -aec TEST-ARCHIV 10.0.0.10 104",
    )

    assert result.exit_code == 126
    assert "keine Shell" in result.stderr


# -- Bestand springt erst nach erfolgreichem C-STORE ------------------------


def test_series_query_before_store_returns_zero_matches() -> None:
    state = fresh_state()
    # nur ein C-ECHO, noch kein C-STORE
    rules.check_association(NODE, state, "WORKSTATION", "TEST-ARCHIV", "10.0.0.10", 104)

    result = rules.exec_command(
        NODE,
        state,
        "workstation",
        "findscu -S -k QueryRetrieveLevel=STUDY -aet WORKSTATION -aec TEST-ARCHIV 10.0.0.10 104",
    )

    assert "Number of Matches: 0" in result.stdout


def test_send_study_action_updates_bestand_and_query_finds_it_afterwards() -> None:
    state = fresh_state()

    # Eingebauter Fehler: modality.config.remote_ae ist WRONG_AE -> erste
    # Sendeauftraege scheitern, bis die Konfiguration korrigiert wird.
    failed = rules.trigger_action(NODE, state, "modality", "send_study")
    assert state["bestand"]["archive"]["studies"] == 0
    assert failed.events[0]["type"] == "association_rejected"

    rules.set_config(NODE, state, "modality", "remote_ae", "TEST-ARCHIV")
    ok = rules.trigger_action(NODE, state, "modality", "send_study")

    assert state["bestand"]["archive"] == {"studies": 1, "series": 1, "instances": 2}
    assert ok.events[-1]["type"] == "store_completed"

    result = rules.exec_command(
        NODE,
        state,
        "workstation",
        "findscu -S -k QueryRetrieveLevel=STUDY -aet WORKSTATION -aec TEST-ARCHIV 10.0.0.10 104",
    )
    assert "Number of Matches: 1" in result.stdout


# -- Konfiguration: nur editierbare Felder auf editierbaren Hosts -----------


def test_config_can_only_be_changed_on_editable_hosts() -> None:
    state = fresh_state()

    error = rules.set_config(NODE, state, "archive", "ae_title", "HACKED")

    assert error == "Diese Konfiguration ist gesperrt."


def test_config_rejects_unknown_fields() -> None:
    state = fresh_state()

    error = rules.set_config(NODE, state, "modality", "not_a_field", "x")

    assert error is not None


# -- Flag: Hash und Normalisierung -------------------------------------------


def test_flag_check_normalizes_whitespace_and_case() -> None:
    state = fresh_state()

    assert rules.check_flag(NODE, state, "  Test   Series  ") is True
    assert state["solved"] is True


def test_flag_check_rejects_wrong_value() -> None:
    state = fresh_state()

    assert rules.check_flag(NODE, state, "not the flag") is False
    assert state["solved"] is False


# -- Solve Feedback (Phase D.2): evaluate_flag() strukturiertes Ergebnis -----


def _node_with_solve_requires(requires: list[dict]) -> NodeDefinition:
    return NodeDefinition(
        slug="solve-feedback-test",
        raw={
            "flag": {"hash": f"sha256:{hash_value('the-flag', False)}", "case_sensitive": False},
            "solve": {"requires": requires},
        },
    )


def _empty_state() -> dict:
    return {"objects": {}, "stored_objects": {}, "jobs": {}, "events": [], "solved": False}


def test_evaluate_flag_classic_node_without_solve_requires_solves_immediately() -> None:
    """Abschnitt 8/29: Nodes ohne `solve.requires` verhalten sich exakt wie
    vor Phase D.1/D.2 -- ein korrekter Flag loest sofort, kein
    Zwischenzustand."""
    state = fresh_state()

    outcome = rules.evaluate_flag(NODE, state, "Test Series")

    assert outcome.correct is True
    assert outcome.solved is True
    assert outcome.reason is None
    assert state["solved"] is True


def test_evaluate_flag_wrong_value_is_incorrect_and_never_solved() -> None:
    state = fresh_state()

    outcome = rules.evaluate_flag(NODE, state, "not the flag")

    assert outcome.correct is False
    assert outcome.solved is False
    assert outcome.reason is None
    assert state["solved"] is False


def test_evaluate_flag_correct_but_prerequisites_missing_is_incomplete_not_solved() -> None:
    node = _node_with_solve_requires([{"type": "job_exists", "where": {"route_id": "R1"}}])
    state = _empty_state()

    outcome = rules.evaluate_flag(node, state, "the-flag")

    assert outcome.correct is True
    assert outcome.solved is False
    assert outcome.reason == rules.REASON_PREREQUISITES_NOT_MET
    assert state["solved"] is False


def test_evaluate_flag_correct_with_prerequisites_met_solves() -> None:
    node = _node_with_solve_requires([{"type": "job_exists", "where": {"route_id": "R1"}}])
    state = _empty_state()
    state["jobs"] = {"j-001": {"route_id": "R1"}}

    outcome = rules.evaluate_flag(node, state, "the-flag")

    assert outcome.correct is True
    assert outcome.solved is True
    assert outcome.reason is None
    assert state["solved"] is True


def test_evaluate_flag_does_not_leak_which_prerequisite_is_missing() -> None:
    """Abschnitt 6: das Feedback darf nur DASS etwas fehlt zeigen, nie
    WELCHES Requirement -- `reason` ist ein einziger generischer Wert,
    unabhaengig vom konkreten Requirement-Typ/-Feld."""
    node = _node_with_solve_requires([
        {"type": "object_exists", "as": "x", "where": {"modality": "SR"}},
    ])
    state = _empty_state()

    outcome = rules.evaluate_flag(node, state, "the-flag")

    assert outcome.reason == "prerequisites_not_met"
    assert "modality" not in (outcome.reason or "")
    assert "object_exists" not in (outcome.reason or "")


def test_evaluate_flag_incorrect_value_never_evaluates_prerequisites(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """Abschnitt 9: ein Hash-Mismatch ist immer `incorrect`, unabhaengig vom
    Runtime-State -- `solve.prerequisites_met()` darf dafuer gar nicht erst
    aufgerufen werden."""
    from app import solve

    def _must_not_be_called(*_args: object, **_kwargs: object) -> bool:
        raise AssertionError("prerequisites_met() must not run for an incorrect flag")

    monkeypatch.setattr(solve, "prerequisites_met", _must_not_be_called)
    node = _node_with_solve_requires([{"type": "job_exists", "where": {"route_id": "R1"}}])
    state = _empty_state()

    outcome = rules.evaluate_flag(node, state, "wrong-value")

    assert outcome.correct is False
    assert outcome.solved is False


def test_evaluate_flag_incomplete_result_never_mutates_state() -> None:
    node = _node_with_solve_requires([{"type": "job_exists", "where": {"route_id": "R1"}}])
    state = _empty_state()
    before = copy.deepcopy(state)

    rules.evaluate_flag(node, state, "the-flag")

    assert state == before


def test_check_flag_wrapper_stays_backward_compatible() -> None:
    """Abschnitt 5: `check_flag()` bleibt fuer bestehende Aufrufstellen ein
    reiner Bool-Wrapper -- `True` nur bei einem tatsaechlichen Solve, exakt
    wie vor Phase D.2."""
    node = _node_with_solve_requires([{"type": "job_exists", "where": {"route_id": "R1"}}])
    incomplete_state = _empty_state()
    complete_state = _empty_state()
    complete_state["jobs"] = {"j-001": {"route_id": "R1"}}

    assert rules.check_flag(node, incomplete_state, "the-flag") is False
    assert rules.check_flag(node, complete_state, "the-flag") is True


# -- Hints und Punkte ---------------------------------------------------------


def test_points_reduce_by_hint_cost_and_floor_at_zero() -> None:
    state = fresh_state()
    assert rules.points(NODE, state) == 10

    rules.use_hint(NODE, state, "h1")
    assert rules.points(NODE, state) == 9

    rules.use_hint(NODE, state, "h2")
    assert rules.points(NODE, state) == 7


def test_using_the_same_hint_twice_does_not_double_charge() -> None:
    state = fresh_state()

    rules.use_hint(NODE, state, "h1")
    rules.use_hint(NODE, state, "h1")

    assert rules.points(NODE, state) == 9


def test_viewing_the_write_up_zeroes_points_but_keeps_progress() -> None:
    state = fresh_state()
    rules.check_association(NODE, state, "WORKSTATION", "TEST-ARCHIV", "10.0.0.10", 104)

    rules.view_write_up(state)

    assert rules.points(NODE, state) == 0
    assert state["counters"]["archive"]["accepted"] == 1


def test_viewing_the_write_up_after_solving_does_not_zero_points() -> None:
    # Abschnitt 5.3 sagt ausdruecklich "vorab" -- nach dem Loesen darf man
    # sich das Write-up straflos ansehen.
    state = fresh_state()
    rules.check_flag(NODE, state, "Test Series")

    rules.view_write_up(state)

    assert rules.points(NODE, state) == 10
    assert state["solved"] is True


def test_unknown_hint_id_is_rejected() -> None:
    state = fresh_state()

    assert rules.use_hint(NODE, state, "h99") is not None


# -- stuck_timeout ------------------------------------------------------------


def test_not_stuck_right_after_session_start() -> None:
    state = fresh_state()

    assert rules.is_stuck(NODE, state) is False


def test_stuck_after_timeout_without_progress() -> None:
    state = fresh_state()

    future = datetime.now(UTC) + timedelta(minutes=NODE.stuck_timeout_minutes + 1)

    assert rules.is_stuck(NODE, state, now=future) is True


def test_never_stuck_once_solved() -> None:
    state = fresh_state()
    rules.check_flag(NODE, state, "Test Series")

    future = datetime.now(UTC) + timedelta(days=1)

    assert rules.is_stuck(NODE, state, now=future) is False


# -- cat/ls ---------------------------------------------------------------


def test_ls_lists_declared_files() -> None:
    state = fresh_state()

    result = rules.exec_command(NODE, state, "workstation", "ls")

    assert result.stdout == "plan.txt"


def test_cat_reads_an_existing_asset() -> None:
    state = fresh_state()

    result = rules.exec_command(NODE, state, "workstation", "cat plan.txt")

    assert "TEST-ARCHIV" in result.stdout


def test_cat_unknown_file_fails_like_a_real_shell() -> None:
    state = fresh_state()

    result = rules.exec_command(NODE, state, "workstation", "cat does-not-exist.txt")

    assert result.exit_code == 1
    assert "No such file" in result.stderr
