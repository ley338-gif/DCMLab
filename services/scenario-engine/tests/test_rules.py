from app import rules
from app.content import load_node


def node():
    return load_node("test-scenario-node")


def test_initial_state_starts_at_scenario_start() -> None:
    n = node()
    state = rules.initial_state(n)

    assert state["current_step_id"] == "frage"
    assert state["solved"] is False
    assert state["log"] == []


def test_public_state_exposes_current_step() -> None:
    n = node()
    state = rules.initial_state(n)
    public = rules.public_state(n, state)

    assert public["scenario"]["step_id"] == "frage"
    assert public["scenario"]["prompt"] == "Testfrage."
    assert {o["id"] for o in public["scenario"]["options"]} == {"falsch", "richtig"}
    assert public["scenario"]["terminal"] is False
    assert public["points"] == 10


def test_wrong_path_reaches_terminal_wrong_outcome() -> None:
    n = node()
    state = rules.initial_state(n)

    result = rules.trigger_action(n, state, "player", "falsch")

    assert result.error is None
    assert state["current_step_id"] == "ende_falsch"
    public = rules.public_state(n, state)
    assert public["scenario"]["terminal"] is True
    assert public["scenario"]["outcome"] == "wrong"
    assert public["scenario"]["log"] == [
        {"prompt": "Testfrage.", "chosen_label": "Falsche Antwort."},
    ]


def test_correct_path_unlocks_flag() -> None:
    n = node()
    state = rules.initial_state(n)

    rules.trigger_action(n, state, "player", "richtig")

    assert state["current_step_id"] == "ende_richtig"
    assert rules.check_flag(n, state, "test-flag-klartext") is True
    assert state["solved"] is True
    assert rules.check_flag(n, state, "irgendwas falsches") is False


def test_unknown_option_is_rejected() -> None:
    n = node()
    state = rules.initial_state(n)

    result = rules.trigger_action(n, state, "player", "nicht-vorhanden")

    assert result.error is not None
    assert state["current_step_id"] == "frage"


def test_terminal_step_blocks_further_actions_until_restart() -> None:
    n = node()
    state = rules.initial_state(n)
    rules.trigger_action(n, state, "player", "falsch")

    blocked = rules.trigger_action(n, state, "player", "richtig")
    assert blocked.error is not None

    restarted = rules.trigger_action(n, state, "player", "restart")
    assert restarted.error is None
    assert state["current_step_id"] == "frage"
    assert state["log"] == []


def test_use_hint_costs_points() -> None:
    n = node()
    state = rules.initial_state(n)

    error = rules.use_hint(n, state, "h1")

    assert error is None
    assert rules.points(n, state) == 9


def test_use_unknown_hint_is_rejected() -> None:
    n = node()
    state = rules.initial_state(n)

    assert rules.use_hint(n, state, "nicht-vorhanden") is not None


def test_exec_and_config_are_graceful_no_ops() -> None:
    n = node()
    state = rules.initial_state(n)

    exec_result = rules.exec_command(n, state, "player", "ls")
    assert exec_result.exit_code != 0
    assert exec_result.stdout == ""

    config_error = rules.set_config(n, state, "player", "field", "value")
    assert config_error is not None


def test_view_write_up_before_solve_zeroes_points() -> None:
    n = node()
    state = rules.initial_state(n)

    rules.view_write_up(state)

    assert rules.points(n, state) == 0
    assert state["write_up_seen"] is True
