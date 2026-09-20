"""Kuratiertes, persistiertes Event-Log (ADR 0120, Phase B). Kein Event
Sourcing, keine vollstaendige Trace-Infrastruktur -- eine einfache
Append-Only-Liste JSON-kompatibler Dicts unter `state["events"]`, die nur
lernrelevante Ereignisse festhaelt (Route-Auswertung, Job-Lebenszyklus,
Objekt-Speicherung), nie jedes interne `ExecResult`/`ActionResult`-Ereignis.

`record_event()` ist der EINZIGE Schreibpfad zu `state["events"]` -- kein
verteiltes `state["events"].append(...)` in mehreren Dateien.
"""

from __future__ import annotations

from datetime import UTC, datetime
from typing import Any


def now_iso() -> str:
    return datetime.now(UTC).isoformat()


def record_event(state: dict[str, Any], event_type: str, **fields: Any) -> dict[str, Any]:
    """Haengt ein Event an `state["events"]` an. `fields` sind die
    ereignisspezifischen Zusatzangaben (z. B. `object`, `host`, `route_id`,
    `job_id`, `matched`, `reason`) -- bewusst klein gehalten, nur was fuer
    die Diagnose eines Falls gebraucht wird."""

    events: list[dict[str, Any]] = state.setdefault("events", [])
    event: dict[str, Any] = {
        "id": f"evt-{len(events) + 1:03d}",
        "type": event_type,
        "timestamp": now_iso(),
        **fields,
    }
    events.append(event)

    return event
