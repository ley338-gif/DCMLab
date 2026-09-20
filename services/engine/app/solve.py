"""Generische Hands-on-Solve-Prerequisites (Phase D.1 -- kein PACS-
Sonderfall). Ein Node kann optional in `node.yml` unter `solve.requires`
eine kleine, whitelisted Liste von Bedingungen ueber den bereits
vorhandenen Session-State deklarieren (`state["objects"]`,
`state["stored_objects"]`, `state["jobs"]`, `state["events"]`) -- Beweis,
dass der praktische Vorgang tatsaechlich stattgefunden hat, nicht nur, dass
der Lernende den richtigen Wert kennt.

Wichtige Trennung (siehe `rules.evaluate_flag()`, dem einzigen Aufrufer,
Phase D.2):

    Runtime-State  = Beweis, dass der Incident tatsaechlich passiert ist
    Flag/Antwort   = Beweis, dass der Lernende die Ursache erkannt hat
    beides zusammen -> solved

`prerequisites_met()` allein loest nie einen Node -- sie ist eine reine,
seiteneffektfreie Auswertungsfunktion (kein Event, keine State-Mutation,
kein `last_progress_at`-Touch, kein Kommando-/CLI-Tracking). Fehlt der
`solve`-Schluessel komplett (der Normalfall fuer jeden heute bestehenden
Node), gilt die Bedingung automatisch als erfuellt -- exakt das Verhalten vor
Phase D.1. Ein VORHANDENER, aber strukturell ungueltiger `solve`-Block
(falscher Typ, unbekanntes Top-Level-Feld, fehlendes/leeres `requires`, ein
`requires`-Eintrag, der kein Objekt ist) ist dagegen fail-closed: nie
erfuellt, nie automatisch geloest, nie ein Crash -- ContentValidator
verhindert das bei regulaer autorisiertem Content ohnehin, diese Pruefung
greift nur, wenn invalider Content trotzdem direkt geladen wird.
"""

from __future__ import annotations

from typing import Any

OBJECT_WHERE_FIELDS = (
    "filename", "sop_instance_uid", "study_uid", "series_uid", "sop_class",
    "modality", "transfer_syntax", "study_description", "series_description",
    "origin_host",
)
JOB_WHERE_FIELDS = ("route_id", "status", "object")
EVENT_WHERE_FIELDS = (
    "type", "route_id", "object", "job_id", "host", "matched", "field",
    "operator", "expected", "actual", "reason",
)


class RequirementError(ValueError):
    """Strukturfehler in `solve.requires` (ContentValidator verhindert das
    normalerweise -- Runtime bleibt defensiv, falls invalider Content direkt
    geladen wird, siehe `prerequisites_met()`)."""


def prerequisites_met(node_raw: dict[str, Any], state: dict[str, Any]) -> bool:
    """Wertet `solve.requires` (falls vorhanden) rein lesend gegen `state`
    aus. Kein `solve`-Schluessel -> True (bestehende Nodes unveraendert).

    Fail-closed statt fail-open: ein VORHANDENER, aber strukturell ungueltiger
    `solve`-Block (falscher Typ, fehlendes/leeres `requires`, Tippfehler wie
    `require` statt `requires`) gilt als NICHT erfuellt, nie als automatisch
    erfuellt -- ein Autor, der glaubt ein Gate definiert zu haben, darf nie
    stillschweigend das alte Flag-only-Verhalten zurueckbekommen. Nur ein
    komplett fehlender `solve`-Schluessel ist der Backward-Compatibility-Fall."""

    solve_config = node_raw.get("solve")

    if solve_config is None:
        return True

    if not isinstance(solve_config, dict) or set(solve_config) != {"requires"}:
        return False

    requirements = solve_config.get("requires")

    if not isinstance(requirements, list) or not requirements:
        return False

    if not all(isinstance(condition, dict) for condition in requirements):
        return False

    bindings: dict[str, str | None] = {}

    try:
        return all(_evaluate(condition, state, bindings) for condition in requirements)
    except (RequirementError, KeyError, TypeError):
        return False


def _evaluate(
    condition: dict[str, Any], state: dict[str, Any], bindings: dict[str, str | None],
) -> bool:
    condition_type = condition.get("type")

    if condition_type == "object_exists":
        return _object_exists(condition, state, bindings)
    if condition_type == "presence":
        return _presence(condition, state, bindings)
    if condition_type == "job_exists":
        return _job_matches(condition, state, bindings, expect_match=True)
    if condition_type == "job_not_exists":
        return _job_matches(condition, state, bindings, expect_match=False)
    if condition_type == "event_exists":
        return _event_exists(condition, state, bindings)

    # Unerreichbar, sofern der ContentValidator (Abschnitt 20/PR-Beschreibung)
    # vorgeschaltet ist -- Runtime bleibt trotzdem defensiv statt zu raten.
    raise RequirementError(f"Unbekannter Requirement-Typ '{condition_type}'.")


def _resolve_alias(ref: Any, bindings: dict[str, str | None]) -> str | None:
    """Loest eine `object`-Referenz (ein zuvor per `object_exists.as`
    gebundener Alias) auf das dabei gefundene `object_id` auf -- `None`,
    wenn der Alias nie gebunden wurde oder das gesuchte Objekt fehlte
    (Abschnitt 8: Fakten muessen sich auf DASSELBE Objekt beziehen, nie auf
    zufaellig irgendein Objekt, das irgendeine der Bedingungen erfuellt)."""

    if not isinstance(ref, str):
        raise RequirementError("object-Referenz muss ein Alias-Name (str) sein.")

    return bindings.get(ref)


def _check_where_fields(where: dict[str, Any], allowed: tuple[str, ...], type_name: str) -> None:
    """Semantik-Paritaet mit dem PHP-Validator (`ContentValidator::
    checkWhereFields()`): dieselbe Feld-Whitelist, kein zweites,
    abweichendes Vokabular. Der Validator verhindert das bei regulaer
    autorisiertem Content bereits; diese Pruefung greift nur, wenn Content
    ihn umgeht."""

    for field in where:
        if field not in allowed:
            raise RequirementError(f"{type_name}: unbekanntes where-Feld '{field}'.")


def _object_exists(
    condition: dict[str, Any], state: dict[str, Any], bindings: dict[str, str | None],
) -> bool:
    """Bindet `alias` nur, wenn `where` GENAU EIN RuntimeObject identifiziert.

    0 Treffer -> nicht erfuellt. >1 Treffer -> ebenfalls nicht erfuellt
    (mehrdeutig) statt willkuerlich das erste Dict-Item zu nehmen -- sonst
    haengt das Solve-Ergebnis von der zufaelligen Insertion-Order in
    `state["objects"]` ab, nicht vom fachlichen Zustand."""

    where: dict[str, Any] = condition.get("where") or {}
    _check_where_fields(where, OBJECT_WHERE_FIELDS, "object_exists")
    alias: str = condition["as"]
    objects: dict[str, dict[str, Any]] = state.get("objects", {})

    matches = [
        object_id for object_id, obj in objects.items()
        if all(obj.get(field) == value for field, value in where.items())
    ]

    if len(matches) != 1:
        bindings[alias] = None
        return False

    bindings[alias] = matches[0]

    return True


def _presence(
    condition: dict[str, Any], state: dict[str, Any], bindings: dict[str, str | None],
) -> bool:
    object_id = _resolve_alias(condition.get("object"), bindings)

    if object_id is None:
        return False

    host = condition["host"]
    present = object_id in state.get("stored_objects", {}).get(host, [])

    return present is condition["present"]


def _job_matches(
    condition: dict[str, Any],
    state: dict[str, Any],
    bindings: dict[str, str | None],
    *,
    expect_match: bool,
) -> bool:
    where = dict(condition.get("where") or {})
    _check_where_fields(where, JOB_WHERE_FIELDS, "job_exists" if expect_match else "job_not_exists")

    if "object" in where:
        object_id = _resolve_alias(where["object"], bindings)

        if object_id is None:
            return False

        where["object"] = object_id

    jobs: dict[str, dict[str, Any]] = state.get("jobs", {})
    found = any(
        all(job.get(field) == value for field, value in where.items())
        for job in jobs.values()
    )

    return found is expect_match


def _event_exists(
    condition: dict[str, Any], state: dict[str, Any], bindings: dict[str, str | None],
) -> bool:
    where = dict(condition.get("where") or {})
    _check_where_fields(where, EVENT_WHERE_FIELDS, "event_exists")

    if "object" in where:
        object_id = _resolve_alias(where["object"], bindings)

        if object_id is None:
            return False

        where["object"] = object_id

    events: list[dict[str, Any]] = state.get("events", [])

    return any(
        all(event.get(field) == value for field, value in where.items())
        for event in events
    )
