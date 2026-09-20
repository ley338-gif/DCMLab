"""Generischer Hands-on-Solve-Contract (Phase D.1) -- ausschliesslich
synthetischer State, keine `gefiltert`-Spezialfaelle. `solve.prerequisites_
met()` ist rein lesend: jeder Test, der State vor/nach vergleicht, beweist
das explizit."""

from __future__ import annotations

import copy
from typing import Any

import pytest

from app import solve

CT = "1.2.840.10008.5.1.4.1.1.2"
RDSR = "1.2.840.10008.5.1.4.1.1.88.67"


def _state(**overrides: Any) -> dict[str, Any]:
    base: dict[str, Any] = {
        "objects": {},
        "stored_objects": {},
        "jobs": {},
        "events": [],
    }
    base.update(overrides)

    return base


# ---------------------------------------------------------------------
# Kein solve.requires -> unveraendertes Verhalten (Abschnitt 23)
# ---------------------------------------------------------------------

def test_missing_solve_key_is_always_satisfied() -> None:
    assert solve.prerequisites_met({}, _state()) is True


def test_empty_requires_list_is_satisfied() -> None:
    assert solve.prerequisites_met({"solve": {"requires": []}}, _state()) is True


def test_solve_without_requires_key_is_satisfied() -> None:
    assert solve.prerequisites_met({"solve": {}}, _state()) is True


# ---------------------------------------------------------------------
# object_exists
# ---------------------------------------------------------------------

def test_object_exists_true_when_a_matching_object_is_present() -> None:
    node_raw = {"solve": {"requires": [
        {"type": "object_exists", "as": "rdsr", "where": {"modality": "SR"}},
    ]}}
    state = _state(objects={"obj-001": {"modality": "SR", "sop_class": RDSR}})

    assert solve.prerequisites_met(node_raw, state) is True


def test_object_exists_false_when_no_object_matches() -> None:
    node_raw = {"solve": {"requires": [
        {"type": "object_exists", "as": "rdsr", "where": {"modality": "SR"}},
    ]}}
    state = _state(objects={"obj-001": {"modality": "CT"}})

    assert solve.prerequisites_met(node_raw, state) is False


def test_object_exists_matches_all_where_fields_simultaneously() -> None:
    node_raw = {"solve": {"requires": [
        {"type": "object_exists", "as": "rdsr", "where": {"modality": "SR", "sop_class": RDSR}},
    ]}}
    # Ein Objekt erfuellt nur eines der beiden Felder -- darf nicht reichen.
    state = _state(objects={"obj-001": {"modality": "SR", "sop_class": CT}})

    assert solve.prerequisites_met(node_raw, state) is False


# ---------------------------------------------------------------------
# presence
# ---------------------------------------------------------------------

def test_presence_true_requires_the_bound_object_at_the_given_host() -> None:
    node_raw = {"solve": {"requires": [
        {"type": "object_exists", "as": "rdsr", "where": {"modality": "SR"}},
        {"type": "presence", "object": "rdsr", "host": "pacs", "present": True},
    ]}}
    state = _state(
        objects={"obj-001": {"modality": "SR"}},
        stored_objects={"pacs": ["obj-001"]},
    )

    assert solve.prerequisites_met(node_raw, state) is True


def test_presence_false_when_object_is_not_at_the_given_host() -> None:
    node_raw = {"solve": {"requires": [
        {"type": "object_exists", "as": "rdsr", "where": {"modality": "SR"}},
        {"type": "presence", "object": "rdsr", "host": "dose-scp", "present": True},
    ]}}
    state = _state(
        objects={"obj-001": {"modality": "SR"}},
        stored_objects={"pacs": ["obj-001"]},
    )

    assert solve.prerequisites_met(node_raw, state) is False


def test_presence_absent_is_satisfied_when_object_is_indeed_absent() -> None:
    node_raw = {"solve": {"requires": [
        {"type": "object_exists", "as": "rdsr", "where": {"modality": "SR"}},
        {"type": "presence", "object": "rdsr", "host": "dose-scp", "present": False},
    ]}}
    state = _state(
        objects={"obj-001": {"modality": "SR"}},
        stored_objects={"pacs": ["obj-001"]},
    )

    assert solve.prerequisites_met(node_raw, state) is True


def test_presence_with_an_unbound_alias_is_false_not_a_crash() -> None:
    node_raw = {"solve": {"requires": [
        {"type": "presence", "object": "never-bound", "host": "pacs", "present": True},
    ]}}

    assert solve.prerequisites_met(node_raw, _state()) is False


# ---------------------------------------------------------------------
# job_exists / job_not_exists
# ---------------------------------------------------------------------

def test_job_exists_true_when_a_matching_job_is_present() -> None:
    node_raw = {"solve": {"requires": [
        {"type": "job_exists", "where": {"route_id": "R1", "status": "sent"}},
    ]}}
    state = _state(jobs={"j-001": {"route_id": "R1", "status": "sent"}})

    assert solve.prerequisites_met(node_raw, state) is True


def test_job_exists_false_when_no_job_matches() -> None:
    node_raw = {"solve": {"requires": [
        {"type": "job_exists", "where": {"route_id": "R1", "status": "sent"}},
    ]}}
    state = _state(jobs={"j-001": {"route_id": "R1", "status": "failed"}})

    assert solve.prerequisites_met(node_raw, state) is False


def test_job_not_exists_true_when_nothing_matches() -> None:
    node_raw = {"solve": {"requires": [
        {"type": "job_not_exists", "where": {"route_id": "R1"}},
    ]}}

    assert solve.prerequisites_met(node_raw, _state()) is True


def test_job_not_exists_false_when_a_matching_job_exists() -> None:
    node_raw = {"solve": {"requires": [
        {"type": "job_not_exists", "where": {"route_id": "R1"}},
    ]}}
    state = _state(jobs={"j-001": {"route_id": "R1", "status": "sent"}})

    assert solve.prerequisites_met(node_raw, state) is False


def test_job_condition_correlates_via_bound_object_alias() -> None:
    node_raw = {"solve": {"requires": [
        {"type": "object_exists", "as": "rdsr", "where": {"modality": "SR"}},
        {"type": "job_not_exists", "where": {"object": "rdsr"}},
    ]}}
    state = _state(
        objects={"obj-001": {"modality": "SR"}},
        jobs={"j-001": {"object": "obj-002"}},  # Job existiert, aber fuer ein ANDERES Objekt
    )

    assert solve.prerequisites_met(node_raw, state) is True


# ---------------------------------------------------------------------
# event_exists
# ---------------------------------------------------------------------

def test_event_exists_true_when_a_matching_event_is_present() -> None:
    node_raw = {"solve": {"requires": [
        {"type": "event_exists", "where": {"type": "route.evaluated", "matched": False}},
    ]}}
    state = _state(events=[{"type": "route.evaluated", "matched": False, "field": "modality"}])

    assert solve.prerequisites_met(node_raw, state) is True


def test_event_exists_false_when_no_event_matches() -> None:
    node_raw = {"solve": {"requires": [
        {"type": "event_exists", "where": {"type": "route.evaluated", "matched": False}},
    ]}}
    state = _state(events=[{"type": "route.evaluated", "matched": True}])

    assert solve.prerequisites_met(node_raw, state) is False


def test_event_condition_correlates_via_bound_object_alias() -> None:
    node_raw = {"solve": {"requires": [
        {"type": "object_exists", "as": "rdsr", "where": {"modality": "SR"}},
        {
            "type": "event_exists",
            "where": {"type": "route.evaluated", "object": "rdsr", "matched": False},
        },
    ]}}
    state = _state(
        objects={"obj-001": {"modality": "SR"}, "obj-002": {"modality": "CT"}},
        events=[
            {"type": "route.evaluated", "object": "obj-002", "matched": False},  # falsches Objekt
        ],
    )

    assert solve.prerequisites_met(node_raw, state) is False


# ---------------------------------------------------------------------
# Objekt-Korrelation (Abschnitt 35): der zentrale Negativtest
# ---------------------------------------------------------------------

def test_facts_from_different_objects_must_not_combine() -> None:
    """Object A erfuellt die SOP-Class-Bedingung, Object B hat das
    no-match-Event -- eine Condition, die beides am selben gebundenen
    Objekt verlangt, darf NICHT erfuellt sein, nur weil irgendein Objekt
    irgendeine der Teilbedingungen erfuellt."""
    node_raw = {"solve": {"requires": [
        {"type": "object_exists", "as": "rdsr", "where": {"sop_class": RDSR}},
        {
            "type": "event_exists",
            "where": {"type": "route.evaluated", "object": "rdsr", "matched": False},
        },
    ]}}
    state = _state(
        objects={
            # erfuellt sop_class, aber kein Event dafuer
            "obj-A": {"sop_class": RDSR, "modality": "SR"},
            # hat das no-match-Event, aber falsche sop_class
            "obj-B": {"sop_class": CT, "modality": "CT"},
        },
        events=[{"type": "route.evaluated", "object": "obj-B", "matched": False}],
    )

    assert solve.prerequisites_met(node_raw, state) is False


def test_facts_correlate_correctly_when_the_same_object_satisfies_both() -> None:
    node_raw = {"solve": {"requires": [
        {"type": "object_exists", "as": "rdsr", "where": {"sop_class": RDSR}},
        {
            "type": "event_exists",
            "where": {"type": "route.evaluated", "object": "rdsr", "matched": False},
        },
    ]}}
    state = _state(
        objects={"obj-A": {"sop_class": RDSR, "modality": "SR"}},
        events=[{"type": "route.evaluated", "object": "obj-A", "matched": False}],
    )

    assert solve.prerequisites_met(node_raw, state) is True


# ---------------------------------------------------------------------
# Unbekannte Typen/Felder bleiben defensiv (kein Crash)
# ---------------------------------------------------------------------

def test_unknown_condition_type_is_false_not_a_crash() -> None:
    node_raw = {"solve": {"requires": [{"type": "totally_unknown_type"}]}}

    assert solve.prerequisites_met(node_raw, _state()) is False


def test_unknown_where_field_is_false_not_a_crash() -> None:
    node_raw = {"solve": {"requires": [
        {"type": "object_exists", "as": "x", "where": {"manufacturer": "Acme"}},
    ]}}

    assert solve.prerequisites_met(node_raw, _state()) is False


# ---------------------------------------------------------------------
# Reine Auswertung (Abschnitt 52): niemals eine State-Mutation
# ---------------------------------------------------------------------

@pytest.mark.parametrize("requirements", [
    [],
    [{"type": "object_exists", "as": "rdsr", "where": {"modality": "SR"}}],
    [{"type": "presence", "object": "rdsr", "host": "pacs", "present": True}],
    [{"type": "job_exists", "where": {"route_id": "R1"}}],
    [{"type": "job_not_exists", "where": {"route_id": "R1"}}],
    [{"type": "event_exists", "where": {"type": "route.evaluated"}}],
    [{"type": "unknown_type"}],
])
def test_prerequisites_met_never_mutates_state(requirements: list[dict[str, Any]]) -> None:
    node_raw = {"solve": {"requires": requirements}}
    state = _state(
        objects={"obj-001": {"modality": "SR"}},
        stored_objects={"pacs": ["obj-001"]},
        jobs={"j-001": {"route_id": "R1", "status": "sent"}},
        events=[{"type": "route.evaluated", "matched": False}],
    )
    before = copy.deepcopy(state)

    solve.prerequisites_met(node_raw, state)

    assert state == before
