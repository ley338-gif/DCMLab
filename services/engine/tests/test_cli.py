"""Read-only PACS-Operations-CLI (ADR 0120, Phase C) -- `pacs`-Tool-Dispatch,
Objekt-/Routen-/Job-/Event-Darstellung, `route test` als echter Dry Run.
Synthetische `NodeDefinition`-Fixtures (kein echter Content-Node)."""

from __future__ import annotations

import copy
from typing import Any

import pytest

from app import rules
from app.content import NodeDefinition

CT_IMAGE_STORAGE = "1.2.840.10008.5.1.4.1.1.2"
DOSE_SR = "1.2.840.10008.5.1.4.1.1.88.67"


def _node(
    *,
    extra_pacs_routes: list[dict[str, Any]] | None = None,
    extra_objects: list[dict[str, Any]] | None = None,
) -> NodeDefinition:
    """`workstation -> pacs -> {dose-scp, ai-scp}` (Abschnitt 41/61):
    CT-TO-DOSE matcht CT-Objekte, dose-scp akzeptiert nur CT Image Storage
    (SR/RDSR-Objekte matchen entsprechend nicht bzw. scheitern an der
    Zielrestriktion, je nach Testfall)."""

    routes = [{
        "id": "CT-TO-DOSE",
        "destination": {"host": "dose-scp", "service": "dose-store"},
        "match": {"modality": "CT"},
    }]
    routes += extra_pacs_routes or []

    return NodeDefinition(
        slug="cli-test",
        raw={
            "environment": {
                "hosts": [
                    {"name": "workstation", "ip": "10.0.0.50", "role": "shell"},
                    {
                        "name": "pacs", "ip": "10.0.0.10",
                        "dicom": {"calling_ae": "RAD-PACS"},
                        "services": [{"id": "pacs-store", "port": 104, "ae_title": "RAD-ARCHIV"}],
                        "routes": routes,
                    },
                    {
                        "name": "dose-scp", "ip": "10.0.0.30",
                        "services": [{
                            "id": "dose-store", "port": 104, "ae_title": "DOSE-SCP",
                            "accepted_sop_classes": [CT_IMAGE_STORAGE],
                        }],
                    },
                    {
                        "name": "ai-scp", "ip": "10.0.0.40",
                        "services": [{
                            "id": "ai-store", "port": 104, "ae_title": "AI-NODE",
                            "accepted_sop_classes": [CT_IMAGE_STORAGE],
                        }],
                    },
                ],
                "known_calling_aets": ["WORKSTATION", "RAD-PACS"],
                "tools": ["storescu", "pacs"],
                "objects": [
                    {
                        "filename": "ct-bild.dcm", "bytes": 1024,
                        "sop_class": CT_IMAGE_STORAGE, "modality": "CT",
                    },
                    {
                        "filename": "rdsr.dcm", "bytes": 512,
                        "sop_class": DOSE_SR, "modality": "SR",
                    },
                    *(extra_objects or []),
                ],
            },
        },
    )


def _store(node: NodeDefinition, state: dict, filename: str) -> rules.ExecResult:
    return rules.exec_command(
        node, state, "workstation",
        f"storescu -aet WORKSTATION -aec RAD-ARCHIV 10.0.0.10 104 {filename}",
    )


def _pacs(node: NodeDefinition, state: dict, command: str) -> rules.ExecResult:
    return rules.exec_command(node, state, "workstation", f"pacs {command}")


# ---------------------------------------------------------------------
# Tool Dispatch (Abschnitt 42)
# ---------------------------------------------------------------------

def test_pacs_is_available_when_declared_in_environment_tools() -> None:
    node = _node()
    state = rules.initial_state(node)

    result = _pacs(node, state, "objects")

    assert result.exit_code == 0


def test_pacs_is_command_not_found_when_not_declared() -> None:
    node = NodeDefinition(
        slug="no-pacs-test",
        raw={
            "environment": {
                "hosts": [{"name": "workstation", "ip": "10.0.0.50", "role": "shell"}],
                "tools": ["storescu"],
            },
        },
    )
    state = rules.initial_state(node)

    result = rules.exec_command(node, state, "workstation", "pacs objects")

    assert result.exit_code == 127
    assert result.stderr == "pacs: command not found"


def test_bare_pacs_shows_usage_and_exits_one() -> None:
    node = _node()
    state = rules.initial_state(node)

    result = _pacs(node, state, "")

    assert result.exit_code == 1
    assert result.stderr == "usage: pacs <objects|object|routes|route|jobs|job|events> ..."


def test_pacs_help_lists_all_real_entry_points_and_exits_zero() -> None:
    """Betreiber-Review (nach PR #169): `pacs help` soll die tatsaechlich
    vorhandenen Einstiegspunkte zeigen, nicht nur die vier Listing-Befehle."""
    node = _node()
    state = rules.initial_state(node)

    result = _pacs(node, state, "help")

    assert result.exit_code == 0
    for line in [
        "pacs objects", "pacs object show <object-id>",
        "pacs routes", "pacs route show <route-id>",
        "pacs route test <route-id> <object-id>",
        "pacs jobs [--object <object-id>]", "pacs job show <job-id>",
        "pacs events [--object <object-id>]",
    ]:
        assert line in result.stdout


def test_pacs_dashdash_help_is_the_same_as_help() -> None:
    node = _node()
    state = rules.initial_state(node)

    result = _pacs(node, state, "--help")

    assert result.exit_code == 0
    assert "pacs route test <route-id> <object-id>" in result.stdout


def test_pacs_help_with_extra_arguments_is_a_usage_error() -> None:
    node = _node()
    state = rules.initial_state(node)

    result = _pacs(node, state, "help foo")

    assert result.exit_code == 1
    assert result.stderr == "usage: pacs help"


def test_unknown_subcommand_is_a_clear_error() -> None:
    node = _node()
    state = rules.initial_state(node)

    result = _pacs(node, state, "foo")

    assert result.exit_code == 1
    assert result.stderr == 'pacs: unknown subcommand "foo"'


@pytest.mark.parametrize(("command", "usage"), [
    ("objects foo", "usage: pacs objects"),
    ("routes nonsense", "usage: pacs routes"),
    ("jobs x", "usage: pacs jobs [--object <object-id>]"),
    ("events whatever", "usage: pacs events [--object <object-id>]"),
])
def test_plural_listing_commands_reject_extra_arguments(command: str, usage: str) -> None:
    """Betreiber-Review (nach PR #169): ein Tippfehler soll laut scheitern,
    statt so auszusehen, als haette ein Filter/Argument gegriffen. `jobs`/
    `events` akzeptieren seit Phase D.2 zusaetzlich exakt `--object <id>`
    (siehe eigene Tests unten) -- alles andere bleibt ein Usage-Fehler,
    `objects`/`routes` nehmen weiterhin gar keine Argumente entgegen."""
    node = _node()
    state = rules.initial_state(node)

    result = _pacs(node, state, command)

    assert result.exit_code == 1
    assert result.stderr == usage


# ---------------------------------------------------------------------
# pacs objects / pacs object show (Abschnitt 43)
# ---------------------------------------------------------------------

def test_objects_empty_state() -> None:
    node = _node()
    state = rules.initial_state(node)

    result = _pacs(node, state, "objects")

    assert result.exit_code == 0
    assert result.stdout == "No runtime objects.\n"


def test_objects_lists_a_single_object_with_presence() -> None:
    node = _node()
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")

    result = _pacs(node, state, "objects")

    assert result.exit_code == 0
    assert "obj-001" in result.stdout
    assert CT_IMAGE_STORAGE in result.stdout
    assert "CT" in result.stdout
    assert "pacs, dose-scp" in result.stdout


def test_objects_lists_multiple_objects() -> None:
    node = _node()
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")
    _store(node, state, "rdsr.dcm")

    result = _pacs(node, state, "objects")

    assert "obj-001" in result.stdout
    assert "obj-002" in result.stdout


def test_objects_presence_on_a_single_host_only() -> None:
    """Das RDSR matcht die Route nicht -- Presence bleibt auf `pacs`."""
    node = _node()
    state = rules.initial_state(node)
    _store(node, state, "rdsr.dcm")

    result = _pacs(node, state, "objects")

    lines = [line for line in result.stdout.splitlines() if line.startswith("obj-001")]
    assert lines and "pacs" in lines[0] and "dose-scp" not in lines[0]


def test_objects_shows_a_dash_for_missing_optional_metadata() -> None:
    node = _node()
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")  # sop_instance_uid fehlt bei direktem storescu bereits

    result = _pacs(node, state, "object show obj-001")

    assert "SOP Instance UID:   -" in result.stdout
    assert "None" not in result.stdout


def test_object_show_full_detail() -> None:
    node = _node()
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")

    result = _pacs(node, state, "object show obj-001")

    assert result.exit_code == 0
    assert "Object:             obj-001" in result.stdout
    assert f"SOP Class UID:      {CT_IMAGE_STORAGE}" in result.stdout
    assert "Modality:           CT" in result.stdout
    assert "Origin Host:        workstation" in result.stdout
    assert "Presence:           pacs, dose-scp" in result.stdout


def test_object_show_unknown_object_id() -> None:
    node = _node()
    state = rules.initial_state(node)

    result = _pacs(node, state, "object show obj-999")

    assert result.exit_code == 1
    assert result.stderr == 'pacs: unknown object "obj-999"'


def test_a_forwarded_object_shows_the_same_id_at_multiple_hosts() -> None:
    node = _node()
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")

    result = _pacs(node, state, "object show obj-001")

    assert "pacs, dose-scp" in result.stdout


# ---------------------------------------------------------------------
# pacs routes / pacs route show (Abschnitt 44)
# ---------------------------------------------------------------------

def test_routes_empty_state() -> None:
    node = NodeDefinition(
        slug="no-routes-test",
        raw={
            "environment": {
                "hosts": [{"name": "workstation", "ip": "10.0.0.50", "role": "shell"}],
                "tools": ["pacs"],
            },
        },
    )
    state = rules.initial_state(node)

    result = _pacs(node, state, "routes")

    assert result.stdout == "No routes.\n"


def test_routes_lists_a_single_route() -> None:
    node = _node()
    state = rules.initial_state(node)

    result = _pacs(node, state, "routes")

    assert result.exit_code == 0
    assert "CT-TO-DOSE" in result.stdout
    assert "pacs" in result.stdout
    assert "dose-scp" in result.stdout
    assert "yes" in result.stdout


def test_routes_lists_routes_from_multiple_hosts() -> None:
    node = _node()
    node.host("dose-scp")["dicom"] = {"calling_ae": "DOSE-SCP"}
    node.host("dose-scp")["routes"] = [{
        "id": "DOSE-TO-AI",
        "destination": {"host": "ai-scp", "service": "ai-store"},
        "match": {"modality": "CT"},
    }]
    state = rules.initial_state(node)

    result = _pacs(node, state, "routes")

    assert "CT-TO-DOSE" in result.stdout
    assert "DOSE-TO-AI" in result.stdout


def test_routes_shows_disabled_routes_as_no() -> None:
    node = _node()
    node.host("pacs")["routes"][0]["enabled"] = False
    state = rules.initial_state(node)

    result = _pacs(node, state, "routes")

    lines = [line for line in result.stdout.splitlines() if "CT-TO-DOSE" in line]
    assert lines and lines[0].rstrip().endswith("no")


def test_route_show_with_shorthand_match() -> None:
    node = _node()
    state = rules.initial_state(node)

    result = _pacs(node, state, "route show CT-TO-DOSE")

    assert result.exit_code == 0
    assert "Route:       CT-TO-DOSE" in result.stdout
    assert "Source:      pacs" in result.stdout
    assert "Destination: dose-scp / dose-store" in result.stdout
    assert "Enabled:     yes" in result.stdout
    assert "Match:\n  modality equals CT" in result.stdout


def test_route_show_with_all_and_multiple_conditions() -> None:
    node = _node(extra_pacs_routes=[{
        "id": "MULTI",
        "destination": {"host": "ai-scp", "service": "ai-store"},
        "match": {"all": [
            {"field": "modality", "op": "equals", "value": "CT"},
            {"field": "sop_class", "op": "not_equals", "value": DOSE_SR},
        ]},
    }])
    state = rules.initial_state(node)

    result = _pacs(node, state, "route show MULTI")

    assert "Match ALL:" in result.stdout
    assert "modality equals CT" in result.stdout
    assert f"sop_class not_equals {DOSE_SR}" in result.stdout


def test_route_show_with_any() -> None:
    node = _node(extra_pacs_routes=[{
        "id": "ANY-ROUTE",
        "destination": {"host": "ai-scp", "service": "ai-store"},
        "match": {"any": [
            {"field": "modality", "op": "equals", "value": "CT"},
            {"field": "modality", "op": "equals", "value": "MR"},
        ]},
    }])
    state = rules.initial_state(node)

    result = _pacs(node, state, "route show ANY-ROUTE")

    assert "Match ANY:" in result.stdout


def test_route_show_renders_in_operator() -> None:
    node = _node(extra_pacs_routes=[{
        "id": "IN-ROUTE",
        "destination": {"host": "ai-scp", "service": "ai-store"},
        "match": {"all": [{"field": "modality", "op": "in", "values": ["CT", "MR"]}]},
    }])
    state = rules.initial_state(node)

    result = _pacs(node, state, "route show IN-ROUTE")

    assert "modality in [CT, MR]" in result.stdout


def test_route_show_renders_exists_operator() -> None:
    node = _node(extra_pacs_routes=[{
        "id": "EXISTS-ROUTE",
        "destination": {"host": "ai-scp", "service": "ai-store"},
        "match": {"all": [{"field": "modality", "op": "exists"}]},
    }])
    state = rules.initial_state(node)

    result = _pacs(node, state, "route show EXISTS-ROUTE")

    assert "modality exists" in result.stdout


def test_route_show_unknown_route() -> None:
    node = _node()
    state = rules.initial_state(node)

    result = _pacs(node, state, "route show NOPE")

    assert result.exit_code == 1
    assert result.stderr == 'pacs: unknown route "NOPE"'


def test_route_show_reports_ambiguous_route_id_defensively() -> None:
    """ContentValidator erzwingt normalerweise Eindeutigkeit -- Runtime
    bleibt defensiv, falls invalider Content direkt geladen wird."""
    node = _node()
    node.host("dose-scp")["dicom"] = {"calling_ae": "DOSE-SCP"}
    node.host("dose-scp")["routes"] = [{
        "id": "CT-TO-DOSE",  # dieselbe ID wie am pacs-Host
        "destination": {"host": "ai-scp", "service": "ai-store"},
        "match": {"modality": "CT"},
    }]
    state = rules.initial_state(node)

    result = _pacs(node, state, "route show CT-TO-DOSE")

    assert result.exit_code == 1
    assert result.stderr == 'pacs: route id is ambiguous: "CT-TO-DOSE"'


# ---------------------------------------------------------------------
# pacs route test (Abschnitt 45/49)
# ---------------------------------------------------------------------

def test_route_test_match() -> None:
    node = _node()
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")

    result = _pacs(node, state, "route test CT-TO-DOSE obj-001")

    assert result.exit_code == 0
    assert result.stdout == "matched: true\n"


def test_route_test_no_match_shows_structured_diagnostics() -> None:
    """Abschnitt 49: der zentrale RDSR-Lernfall -- Modality SR gegen eine
    auf CT filternde Route."""
    node = _node()
    state = rules.initial_state(node)
    _store(node, state, "rdsr.dcm")

    result = _pacs(node, state, "route test CT-TO-DOSE obj-001")

    assert result.exit_code == 0
    assert result.stdout == (
        "matched: false\n"
        "field: modality\n"
        "operator: equals\n"
        "expected: CT\n"
        "actual: SR\n"
    )


def test_route_test_missing_field_shows_a_dash() -> None:
    node = _node(extra_pacs_routes=[{
        "id": "NEEDS-SERIES-DESC",
        "destination": {"host": "ai-scp", "service": "ai-store"},
        "match": {"series_description": "Thorax"},
    }])
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")  # series_description bleibt None

    result = _pacs(node, state, "route test NEEDS-SERIES-DESC obj-001")

    assert "actual: -" in result.stdout


def test_route_test_unknown_object_exits_one() -> None:
    node = _node()
    state = rules.initial_state(node)

    result = _pacs(node, state, "route test CT-TO-DOSE obj-999")

    assert result.exit_code == 1
    assert result.stderr == 'pacs: unknown object "obj-999"'


def test_route_test_unknown_route_exits_one() -> None:
    node = _node()
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")

    result = _pacs(node, state, "route test NOPE obj-001")

    assert result.exit_code == 1
    assert result.stderr == 'pacs: unknown route "NOPE"'


# ---------------------------------------------------------------------
# pacs route test -- Dry-Run-Garantie (Abschnitt 14/46)
# ---------------------------------------------------------------------

def test_route_test_is_a_true_dry_run_state_is_byte_for_byte_identical() -> None:
    node = _node()
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")
    _store(node, state, "rdsr.dcm")

    before = copy.deepcopy(state)

    _pacs(node, state, "route test CT-TO-DOSE obj-001")
    _pacs(node, state, "route test CT-TO-DOSE obj-002")

    assert state == before


def test_route_test_creates_no_job_and_no_event_and_does_not_touch_route_history() -> None:
    node = _node()
    state = rules.initial_state(node)
    _store(node, state, "rdsr.dcm")

    jobs_before = copy.deepcopy(state.get("jobs", {}))
    events_before = copy.deepcopy(state.get("events", []))
    route_history_before = copy.deepcopy(state["objects"]["obj-001"]["route_history"])

    _pacs(node, state, "route test CT-TO-DOSE obj-001")

    assert state.get("jobs", {}) == jobs_before
    assert state.get("events", []) == events_before
    assert state["objects"]["obj-001"]["route_history"] == route_history_before


def test_route_test_does_not_touch_last_progress_at() -> None:
    node = _node()
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")

    last_progress_before = state["last_progress_at"]

    _pacs(node, state, "route test CT-TO-DOSE obj-001")

    assert state["last_progress_at"] == last_progress_before


@pytest.mark.parametrize("command", [
    "objects", "object show obj-001", "routes", "route show CT-TO-DOSE",
    "route test CT-TO-DOSE obj-001", "jobs", "events",
])
def test_all_read_only_commands_never_mutate_state(command: str) -> None:
    node = _node()
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")

    before = copy.deepcopy(state)
    _pacs(node, state, command)

    assert state == before


# ---------------------------------------------------------------------
# pacs jobs / pacs job show (Abschnitt 47)
# ---------------------------------------------------------------------

def test_jobs_empty_state() -> None:
    node = _node()
    state = rules.initial_state(node)

    result = _pacs(node, state, "jobs")

    assert result.stdout == "No routing jobs.\n"


def test_jobs_lists_a_sent_job() -> None:
    node = _node()
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")

    result = _pacs(node, state, "jobs")

    assert "j-001" in result.stdout
    assert "CT-TO-DOSE" in result.stdout
    assert "obj-001" in result.stdout
    assert "pacs" in result.stdout
    assert "dose-scp" in result.stdout
    assert "sent" in result.stdout


def test_jobs_lists_a_failed_job_with_reason() -> None:
    node = _node()
    node.host("dose-scp")["services"][0]["accepted_sop_classes"] = [DOSE_SR]
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")

    result = _pacs(node, state, "jobs")

    assert "failed" in result.stdout
    assert "abstract_syntax_not_supported" in result.stdout


def test_jobs_shows_deterministic_creation_order() -> None:
    node = _node(extra_pacs_routes=[{
        "id": "CT-TO-AI",
        "destination": {"host": "ai-scp", "service": "ai-store"},
        "match": {"modality": "CT"},
    }])
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")

    result = _pacs(node, state, "jobs")
    lines = [line for line in result.stdout.splitlines() if line.startswith("j-")]
    assert [line.split()[0] for line in lines] == ["j-001", "j-002"]


def test_job_show_detail() -> None:
    node = _node()
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")

    result = _pacs(node, state, "job show j-001")

    assert result.exit_code == 0
    assert "Job:           j-001" in result.stdout
    assert "Route:         CT-TO-DOSE" in result.stdout
    assert "Object:        obj-001" in result.stdout
    assert "Source:        pacs" in result.stdout
    assert "Destination:   dose-scp / dose-store" in result.stdout
    assert "Routing Depth: 1" in result.stdout
    assert "Attempt:       1" in result.stdout
    assert "Status:        sent" in result.stdout
    assert "Reason:        -" in result.stdout
    assert "Created:" in result.stdout


def test_job_show_unknown_job() -> None:
    node = _node()
    state = rules.initial_state(node)

    result = _pacs(node, state, "job show j-999")

    assert result.exit_code == 1
    assert result.stderr == 'pacs: unknown job "j-999"'


# ---------------------------------------------------------------------
# pacs jobs --object (Phase D.2, Betreiber-Playtest von "gefiltert")
# ---------------------------------------------------------------------

def _node_with_two_ct_objects() -> NodeDefinition:
    return _node(extra_objects=[{
        "filename": "ct-bild-2.dcm", "bytes": 1024,
        "sop_class": CT_IMAGE_STORAGE, "modality": "CT",
    }])


def test_jobs_object_filter_shows_only_the_matching_object() -> None:
    node = _node_with_two_ct_objects()
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")  # obj-001 -> j-001
    _store(node, state, "ct-bild-2.dcm")  # obj-002 -> j-002

    result = _pacs(node, state, "jobs --object obj-001")

    assert result.exit_code == 0
    assert "j-001" in result.stdout
    assert "j-002" not in result.stdout
    assert "obj-002" not in result.stdout


def test_jobs_object_filter_unknown_object_is_an_error() -> None:
    node = _node()
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")

    result = _pacs(node, state, "jobs --object obj-999")

    assert result.exit_code == 1
    assert result.stderr == 'pacs: unknown object "obj-999"'


def test_jobs_object_filter_on_a_known_object_with_no_jobs_succeeds() -> None:
    """Abschnitt 19/37: das RDSR-Analogon -- ein bekanntes Objekt ohne Job
    ist ein valides Diagnoseergebnis, kein Fehler wie ein Tippfehler in der
    Objekt-ID."""
    node = _node()
    state = rules.initial_state(node)
    _store(node, state, "rdsr.dcm")  # obj-001, matcht CT-TO-DOSE nicht -> kein Job

    result = _pacs(node, state, "jobs --object obj-001")

    assert result.exit_code == 0
    assert result.stdout == "No routing jobs for object obj-001.\n"


def test_jobs_usage_error_for_malformed_object_syntax() -> None:
    node = _node()
    state = rules.initial_state(node)

    for command in ["jobs obj-001", "jobs --object=obj-001", "jobs --object obj-001 --route X"]:
        result = _pacs(node, state, command)
        assert result.exit_code == 1
        assert result.stderr == "usage: pacs jobs [--object <object-id>]"


def test_jobs_object_filter_never_mutates_state() -> None:
    node = _node_with_two_ct_objects()
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")
    _store(node, state, "ct-bild-2.dcm")

    before = copy.deepcopy(state)
    _pacs(node, state, "jobs --object obj-001")
    _pacs(node, state, "jobs --object obj-999")  # auch der Fehlerpfad bleibt rein lesend

    assert state == before


# ---------------------------------------------------------------------
# pacs events --object (Phase D.2, Betreiber-Playtest von "gefiltert")
# ---------------------------------------------------------------------

def test_events_object_filter_shows_only_the_matching_object() -> None:
    node = _node()
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")  # obj-001: store.completed, route.evaluated(true), job.*
    _store(node, state, "rdsr.dcm")  # obj-002: store.completed, route.evaluated(false)

    result = _pacs(node, state, "events --object obj-002")

    assert result.exit_code == 0
    assert "obj-002" in result.stdout
    assert "obj-001" not in result.stdout
    assert "matched=false" in result.stdout
    assert "job.created" not in result.stdout
    assert "job.sent" not in result.stdout


def test_events_object_filter_unknown_object_is_an_error() -> None:
    node = _node()
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")

    result = _pacs(node, state, "events --object obj-999")

    assert result.exit_code == 1
    assert result.stderr == 'pacs: unknown object "obj-999"'


def test_events_object_filter_known_object_no_events_is_unreachable_but_defensive() -> None:
    """Jedes bekannte Objekt hat mindestens ein `store.completed`-Event --
    dieser Fall ist nach heutiger Engine-Logik nicht erreichbar, die
    leere Ausgabe bleibt trotzdem ein definiertes, nicht abstuerzendes
    Ergebnis, falls sich das je aendert."""
    node = _node()
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")
    state["events"] = []  # synthetisch: ein bekanntes Objekt ganz ohne Events

    result = _pacs(node, state, "events --object obj-001")

    assert result.exit_code == 0
    assert result.stdout == "No PACS events for object obj-001.\n"


def test_events_usage_error_for_malformed_object_syntax() -> None:
    node = _node()
    state = rules.initial_state(node)

    malformed_commands = [
        "events obj-001", "events --object=obj-001", "events --object obj-001 --route X",
    ]
    for command in malformed_commands:
        result = _pacs(node, state, command)
        assert result.exit_code == 1
        assert result.stderr == "usage: pacs events [--object <object-id>]"


def test_events_object_filter_never_mutates_state() -> None:
    node = _node()
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")
    _store(node, state, "rdsr.dcm")

    before = copy.deepcopy(state)
    _pacs(node, state, "events --object obj-001")
    _pacs(node, state, "events --object obj-999")

    assert state == before


# ---------------------------------------------------------------------
# pacs events (Abschnitt 48/50)
# ---------------------------------------------------------------------

def test_events_empty_state() -> None:
    node = _node()
    state = rules.initial_state(node)

    result = _pacs(node, state, "events")

    assert result.stdout == "No PACS events.\n"


def test_events_reflects_real_automatic_routing_state_not_synthetic() -> None:
    """Abschnitt 50: kein state["events"] = [...] von Hand -- echter Store,
    echtes automatisches Routing, CLI liest den tatsaechlichen Phase-B-State."""
    node = _node()
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")

    result = _pacs(node, state, "events")

    types_in_state = [e["type"] for e in state["events"]]
    assert types_in_state == [
        "store.completed", "route.evaluated", "job.created", "job.sent", "store.completed",
    ]
    for event_type in types_in_state:
        assert event_type in result.stdout


def test_events_order_matches_append_order_not_resorted() -> None:
    node = _node()
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")

    result = _pacs(node, state, "events")

    ids_in_output = [line.split()[0] for line in result.stdout.splitlines()[1:]]
    assert ids_in_output == [e["id"] for e in state["events"]]


def test_events_no_match_shows_diagnostics_in_details() -> None:
    node = _node()
    state = rules.initial_state(node)
    _store(node, state, "rdsr.dcm")

    result = _pacs(node, state, "events")

    assert "matched=false" in result.stdout
    assert "modality" in result.stdout
    assert "expected CT" in result.stdout
    assert "actual SR" in result.stdout


def test_events_job_failed_shows_reason() -> None:
    node = _node()
    node.host("dose-scp")["services"][0]["accepted_sop_classes"] = [DOSE_SR]
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")

    result = _pacs(node, state, "events")

    assert "job.failed" in result.stdout
    assert "reason=abstract_syntax_not_supported" in result.stdout


def test_events_job_created_shows_source_and_destination() -> None:
    node = _node()
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")

    result = _pacs(node, state, "events")

    assert "pacs -> dose-scp" in result.stdout


# ---------------------------------------------------------------------
# Fan-out (Abschnitt 64)
# ---------------------------------------------------------------------

def test_fan_out_shows_two_jobs_same_object_same_depth_different_destinations() -> None:
    node = _node(extra_pacs_routes=[{
        "id": "CT-TO-AI",
        "destination": {"host": "ai-scp", "service": "ai-store"},
        "match": {"modality": "CT"},
    }])
    state = rules.initial_state(node)
    _store(node, state, "ct-bild.dcm")

    jobs_result = _pacs(node, state, "jobs")
    objects_result = _pacs(node, state, "objects")

    assert jobs_result.stdout.count("obj-001") == 2
    for job in state["jobs"].values():
        assert job["routing_depth"] == 1
        assert job["object"] == "obj-001"
    destinations = {job["destination"]["host"] for job in state["jobs"].values()}
    assert destinations == {"dose-scp", "ai-scp"}
    assert "pacs, dose-scp, ai-scp" in objects_result.stdout


# ---------------------------------------------------------------------
# Regression: bestehende Nodes ohne `pacs` bleiben unveraendert (Abschnitt 60)
# ---------------------------------------------------------------------

def test_help_does_not_mention_pacs_when_the_tool_is_not_declared() -> None:
    node = NodeDefinition(
        slug="no-pacs-help-test",
        raw={
            "environment": {
                "hosts": [{"name": "workstation", "ip": "10.0.0.50", "role": "shell"}],
                "tools": ["storescu"],
            },
        },
    )
    state = rules.initial_state(node)

    result = rules.exec_command(node, state, "workstation", "help")

    assert "pacs" not in result.stdout


def test_help_mentions_pacs_when_the_tool_is_declared() -> None:
    node = _node()
    state = rules.initial_state(node)

    result = rules.exec_command(node, state, "workstation", "help")

    assert "pacs" in result.stdout
