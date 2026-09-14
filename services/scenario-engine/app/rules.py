"""Entscheidungsbaum-Auswertung fuer Szenario-Nodes (Abschnitt 13, PoC
"Datenschutz im Klinikbetrieb"). Kein Shell, keine Tools -- eine Session
steht immer auf genau einem `scenario.steps`-Schritt, jede Aktion waehlt
eine der dort deklarierten Optionen.

`is_stuck`, `points`, `use_hint`, `view_write_up`, `check_flag`, `now_iso`
sind wortwoertlich aus services/engine/app/rules.py uebernommen -- dort
bereits 0% DICOM-spezifisch. Bewusste kleine Duplikation statt eines
geteilten internen Pakets zwischen zwei unabhaengig gebauten Docker-Images
(siehe app/flag.py).
"""

from __future__ import annotations

from dataclasses import dataclass, field
from datetime import UTC, datetime
from typing import Any

from app.content import NodeDefinition


def now_iso() -> str:
    return datetime.now(UTC).isoformat()


def initial_state(node: NodeDefinition) -> dict[str, Any]:
    return {
        "current_step_id": node.scenario_start,
        "log": [],
        "hints_used": [],
        "write_up_seen": False,
        "write_up_seen_before_solve": False,
        "solved": False,
        "created_at": now_iso(),
        "last_progress_at": now_iso(),
    }


def _touch_progress(state: dict[str, Any]) -> None:
    state["last_progress_at"] = now_iso()


def is_stuck(node: NodeDefinition, state: dict[str, Any], now: datetime | None = None) -> bool:
    if state.get("solved"):
        return False

    now = now or datetime.now(UTC)
    last_progress = datetime.fromisoformat(state["last_progress_at"])
    elapsed_minutes = (now - last_progress).total_seconds() / 60

    return elapsed_minutes >= node.stuck_timeout_minutes


def points(node: NodeDefinition, state: dict[str, Any]) -> int:
    if state.get("write_up_seen_before_solve"):
        return 0

    spent = sum(node.hint_cost(hint_id) or 0 for hint_id in state.get("hints_used", []))

    return max(node.points - spent, 0)


def public_state(node: NodeDefinition, state: dict[str, Any]) -> dict[str, Any]:
    step = node.step(state["current_step_id"]) or {}

    return {
        "node_slug": node.slug,
        # Dieselben generischen Schluessel wie beim DICOM-Engine
        # (hints_used/write_up_seen/solved/points/stuck/created_at) --
        # NodeController/Show.vue lesen sie ungeprueft fuer beide Engines.
        "scenario": {
            "step_id": state["current_step_id"],
            "prompt": step.get("prompt", ""),
            "options": [
                {"id": o["id"], "label": o["label"]} for o in step.get("options", [])
            ],
            "terminal": bool(step.get("terminal", False)),
            "outcome": step.get("outcome"),
            "log": state.get("log", []),
        },
        "hints_used": state.get("hints_used", []),
        "write_up_seen": state.get("write_up_seen", False),
        "solved": state.get("solved", False),
        "points": points(node, state),
        "stuck": is_stuck(node, state),
        "created_at": state.get("created_at"),
    }


@dataclass
class ExecResult:
    stdout: str = ""
    stderr: str = ""
    exit_code: int = 0
    events: list[dict[str, Any]] = field(default_factory=list)


def exec_command(
    node: NodeDefinition, state: dict[str, Any], host_name: str, command: str,
) -> ExecResult:
    return ExecResult(stderr="Auf dieser Node gibt es keine Shell.", exit_code=126)


def set_config(
    node: NodeDefinition, state: dict[str, Any], host_name: str, field_name: str, value: str,
) -> str | None:
    return "Diese Node hat keine Konfiguration."


@dataclass
class ActionResult:
    log: list[str] = field(default_factory=list)
    events: list[dict[str, Any]] = field(default_factory=list)
    error: str | None = None


def trigger_action(
    node: NodeDefinition, state: dict[str, Any], host_name: str, action: str,
) -> ActionResult:
    """`host_name` ist ein ignoriertes Sentinel ("player") -- beibehalten,
    weil EngineClientContract::triggerAction(sessionId, host, action) das
    Feld erwartet, hier aber kein Host-Konzept existiert."""

    step = node.step(state["current_step_id"])

    if step is None:
        return ActionResult(error="Unbekannter Schritt.")

    if step.get("terminal"):
        if action == "restart":
            state["current_step_id"] = node.scenario_start
            state["log"] = []
            _touch_progress(state)

            return ActionResult(log=["Neu gestartet."])

        return ActionResult(error="Dieser Pfad ist bereits abgeschlossen.")

    option = next((o for o in step.get("options", []) if o["id"] == action), None)

    if option is None:
        return ActionResult(error=f'Unbekannte Option "{action}".')

    state.setdefault("log", []).append(
        {"prompt": step.get("prompt", ""), "chosen_label": option["label"]},
    )
    state["current_step_id"] = option["next"]
    _touch_progress(state)

    return ActionResult(log=[option["label"]])


def use_hint(node: NodeDefinition, state: dict[str, Any], hint_id: str) -> str | None:
    """Gibt eine Fehlermeldung zurueck, oder None bei Erfolg (idempotent)."""

    if node.hint_cost(hint_id) is None:
        return f'Unbekannter Hint "{hint_id}".'

    if hint_id not in state.setdefault("hints_used", []):
        state["hints_used"].append(hint_id)

    return None


def view_write_up(state: dict[str, Any]) -> None:
    if not state.get("solved"):
        state["write_up_seen_before_solve"] = True

    state["write_up_seen"] = True


def check_flag(node: NodeDefinition, state: dict[str, Any], value: str) -> bool:
    from app.flag import hash_value

    correct = hash_value(value, node.flag_case_sensitive) == node.flag_hash

    if correct:
        state["solved"] = True

    return correct
