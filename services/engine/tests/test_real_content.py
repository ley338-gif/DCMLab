"""Prueft die echten, ausgelieferten Nodes (nicht die Testfixture) gegen das
Regelwerk -- speziell fuer P6 (Wrong Door): "ausschliesslich aus dem Schema
erzeugt, ohne eine Zeile Sonderlogik". Wenn dieser Test nur mit
Node-spezifischem Engine-Code gruen wird, ist das ein Schema-Mangel
(Abschnitt 10, P6-DoD).
"""

from pathlib import Path

import pytest

from app import content, rules

REAL_CONTENT = Path(__file__).parent.parent.parent.parent / "content"


@pytest.fixture(autouse=True)
def _use_real_content(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.setattr(content.settings, "content_path", str(REAL_CONTENT))
    content._datasets_cached.cache_clear()


def test_silent_ct_is_solvable_from_the_real_content() -> None:
    node = content.load_node("silent-ct")
    state = rules.initial_state(node)

    rejected = rules.check_association(node, state, "CT_RAUM3", "PACS_ARCHIV", "10.20.0.10", 104)
    assert rejected.message == rules.MSG_CALLED_AE_REJECTED

    ok = rules.set_config(node, state, "ct-console", "remote_ae", "PACS-ARCHIV")
    assert ok is None

    result = rules.trigger_action(node, state, "ct-console", "send_study")
    assert result.events[-1]["type"] == "store_completed"
    assert state["bestand"]["archive"] == {"studies": 1, "series": 1, "instances": 3}

    assert rules.check_flag(node, state, "Thorax 5.0 B31f") is True


def test_wrong_door_is_solvable_from_the_real_content_with_zero_special_casing() -> None:
    """Dasselbe Regelwerk, derselbe Code -- nur die YAML-Werte unterscheiden
    sich von silent-ct (Calling- statt Called-Seite)."""

    node = content.load_node("wrong-door")
    state = rules.initial_state(node)

    # Der eingebaute Fehler liegt diesmal auf der Calling-Seite.
    rejected = rules.check_association(node, state, "MR-2", "PACS-ARCHIV", "10.20.0.10", 104)
    assert rejected.message == rules.MSG_CALLING_AE_REJECTED

    ok = rules.set_config(node, state, "mr-console", "local_ae", "MR_2")
    assert ok is None

    result = rules.trigger_action(node, state, "mr-console", "send_study")
    assert result.events[-1]["type"] == "store_completed"
    assert state["bestand"]["archive"] == {"studies": 1, "series": 1, "instances": 1}

    assert rules.check_flag(node, state, "Thorax 1.0 B70f") is True


def test_wrong_door_and_silent_ct_have_different_flags() -> None:
    silent_ct = content.load_node("silent-ct")
    wrong_door = content.load_node("wrong-door")

    assert silent_ct.flag_hash != wrong_door.flag_hash
