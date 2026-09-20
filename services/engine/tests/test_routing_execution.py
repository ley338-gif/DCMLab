"""Automatische Routenausfuehrung (ADR 0120, Phase B): `jobs.store_and_route()`
als der eine Einstiegspunkt fuer Presence, Event-Log, Job-Erzeugung und
-Ausfuehrung. Synthetische `NodeDefinition`-Fixtures (keine echten
`content/nodes/`) -- Prototype-Nodes kommen erst in einer spaeteren Phase."""

from __future__ import annotations

from typing import Any

from app import rules
from app.content import NodeDefinition
from app.operations import jobs, routing
from app.operations import objects as runtime_objects

CT_IMAGE_STORAGE = "1.2.840.10008.5.1.4.1.1.2"
DOSE_SR = "1.2.840.10008.5.1.4.1.1.88.67"


def _node(
    *,
    dose_accepted_sop_classes: list[str] | None = None,
    dose_accepted_transfer_syntaxes: list[str] | None = None,
    match: dict[str, Any] | None = None,
    extra_pacs_routes: list[dict[str, Any]] | None = None,
    extra_hosts: list[dict[str, Any]] | None = None,
    known_calling_aets: list[str] | None = None,
) -> NodeDefinition:
    """Ein-Route-Fixture `workstation -> pacs -> dose-scp` (ADR 0120,
    Beispiel 9.1) fuer direktes `storescu`. `match` und die Dose-Restriktionen
    sind parametrisiert, damit Match/No-Match/Negotiation-Failure-Tests
    dieselbe Grundtopologie wiederverwenden koennen."""

    dose_service: dict[str, Any] = {"id": "dose-store", "port": 104, "ae_title": "DOSE-SCP"}

    if dose_accepted_sop_classes is not None:
        dose_service["accepted_sop_classes"] = dose_accepted_sop_classes

    if dose_accepted_transfer_syntaxes is not None:
        dose_service["accepted_transfer_syntaxes"] = dose_accepted_transfer_syntaxes

    routes = [{
        "id": "CT-TO-DOSE",
        "destination": {"host": "dose-scp", "service": "dose-store"},
        "match": match if match is not None else {"modality": "CT"},
    }]
    routes += extra_pacs_routes or []

    hosts = [
        {"name": "workstation", "ip": "10.0.0.50", "role": "shell"},
        {
            "name": "pacs", "ip": "10.0.0.10",
            "dicom": {"calling_ae": "RAD-PACS"},
            "services": [{"id": "pacs-store", "port": 104, "ae_title": "RAD-ARCHIV"}],
            "routes": routes,
        },
        {"name": "dose-scp", "ip": "10.0.0.30", "services": [dose_service]},
        *(extra_hosts or []),
    ]

    return NodeDefinition(
        slug="routing-execution-test",
        raw={
            "environment": {
                "hosts": hosts,
                "known_calling_aets": known_calling_aets or ["WORKSTATION", "RAD-PACS", "AI-NODE"],
                "tools": ["storescu"],
                "objects": [
                    {
                        "filename": "ct-bild.dcm", "bytes": 1024,
                        "sop_class": CT_IMAGE_STORAGE, "modality": "CT",
                    },
                ],
            },
        },
    )


def _store(node: NodeDefinition, state: dict) -> rules.ExecResult:
    return rules.exec_command(
        node, state, "workstation",
        "storescu -aet WORKSTATION -aec RAD-ARCHIV 10.0.0.10 104 ct-bild.dcm",
    )


# ---------------------------------------------------------------------
# Match -> genau ein Job, sent, Presence an beiden Hosts
# ---------------------------------------------------------------------

def test_a_matching_route_creates_exactly_one_job_and_forwards_the_object() -> None:
    node = _node(dose_accepted_sop_classes=[CT_IMAGE_STORAGE])
    state = rules.initial_state(node)

    result = _store(node, state)

    assert result.exit_code == 0
    assert len(state["objects"]) == 1
    object_id = next(iter(state["objects"]))

    assert len(state["jobs"]) == 1
    job = next(iter(state["jobs"].values()))
    assert job["status"] == "sent"
    assert job["route_id"] == "CT-TO-DOSE"
    assert job["object"] == object_id
    assert job["source"] == "pacs"
    assert job["routing_depth"] == 1
    assert job["attempt"] == 1
    assert job["reason"] is None
    assert job["destination"]["host"] == "dose-scp"

    assert state["stored_objects"]["pacs"] == [object_id]
    assert state["stored_objects"]["dose-scp"] == [object_id]
    assert len(state["objects"]) == 1  # kein Klon


def test_forwarding_keeps_the_same_object_id_and_sop_instance_uid() -> None:
    node = _node(dose_accepted_sop_classes=[CT_IMAGE_STORAGE])
    state = rules.initial_state(node)

    _store(node, state)

    object_id = next(iter(state["objects"]))
    obj = state["objects"][object_id]
    assert obj["sop_instance_uid"] is None  # environment.objects[] deklariert keine
    assert obj["filename"] == "ct-bild.dcm"


def test_route_history_is_marked_after_a_successful_job() -> None:
    node = _node(dose_accepted_sop_classes=[CT_IMAGE_STORAGE])
    state = rules.initial_state(node)

    _store(node, state)

    object_id = next(iter(state["objects"]))
    assert state["objects"][object_id]["route_history"] == [["pacs", "CT-TO-DOSE"]]


def test_successful_flow_event_order() -> None:
    node = _node(dose_accepted_sop_classes=[CT_IMAGE_STORAGE])
    state = rules.initial_state(node)

    _store(node, state)

    types = [e["type"] for e in state["events"]]
    assert types == [
        "store.completed", "route.evaluated", "job.created", "job.sent", "store.completed",
    ]
    assert state["events"][1]["matched"] is True


# ---------------------------------------------------------------------
# No match -> kein Job, nur route.evaluated
# ---------------------------------------------------------------------

def test_a_non_matching_route_creates_no_job() -> None:
    node = _node(match={"modality": "MR"})
    state = rules.initial_state(node)

    _store(node, state)

    assert state.get("jobs", {}) == {}
    object_id = next(iter(state["objects"]))
    assert "dose-scp" not in state.get("stored_objects", {})
    assert state["stored_objects"]["pacs"] == [object_id]


def test_no_match_event_order_is_store_then_route_evaluated_only() -> None:
    node = _node(match={"modality": "MR"})
    state = rules.initial_state(node)

    _store(node, state)

    types = [e["type"] for e in state["events"]]
    assert types == ["store.completed", "route.evaluated"]
    assert state["events"][1]["matched"] is False


# ---------------------------------------------------------------------
# Failed destination negotiation -> Job existiert, failed, keine Presence
# ---------------------------------------------------------------------

def test_an_unsupported_sop_class_at_the_destination_fails_the_job_without_presence() -> None:
    node = _node(dose_accepted_sop_classes=[DOSE_SR])  # Dose akzeptiert kein CT Image Storage
    state = rules.initial_state(node)

    _store(node, state)

    assert len(state["jobs"]) == 1
    job = next(iter(state["jobs"].values()))
    assert job["status"] == "failed"
    assert job["reason"] == "abstract_syntax_not_supported"
    assert "dose-scp" not in state.get("stored_objects", {})

    object_id = next(iter(state["objects"]))
    assert state["stored_objects"]["pacs"] == [object_id]  # Source-Presence bleibt


def test_failed_job_event_order() -> None:
    node = _node(dose_accepted_sop_classes=[DOSE_SR])
    state = rules.initial_state(node)

    _store(node, state)

    types = [e["type"] for e in state["events"]]
    assert types == ["store.completed", "route.evaluated", "job.created", "job.failed"]


def test_an_unsupported_transfer_syntax_at_the_destination_fails_the_job() -> None:
    node = _node(
        dose_accepted_sop_classes=[CT_IMAGE_STORAGE],
        dose_accepted_transfer_syntaxes=["1.2.840.10008.1.2.4.91"],  # Objekt hat keine TS gesetzt
    )
    state = rules.initial_state(node)

    _store(node, state)

    job = next(iter(state["jobs"].values()))
    assert job["status"] == "failed"
    assert job["reason"] == "transfer_syntaxes_not_supported"


def test_a_route_with_an_unresolvable_destination_fails_defensively() -> None:
    """Abschnitt 37/38: `resolve_destination()` und `check_association()`
    leiten Host/Service/AE/IP/Port aus derselben Host-Definition ab -- ein
    "falscher Called AE" kann eine Route bei gueltigem Content strukturell
    nicht erreichen (Association reuse *ist* der Grund dafuer). Was aber
    passieren kann: der Validator wird umgangen (Content direkt geladen) und
    eine Route zeigt auf einen nicht existierenden Host. Die Engine darf
    dabei nicht abstuerzen -- Job existiert, status failed, route_history
    trotzdem markiert (kein Endlosloop wegen kaputter Destination)."""
    node = _node(dose_accepted_sop_classes=[CT_IMAGE_STORAGE])
    node.host("pacs")["routes"][0]["destination"]["host"] = "nowhere"
    state = rules.initial_state(node)

    result = _store(node, state)

    assert result.exit_code == 0  # der storescu selbst bleibt unberuehrt
    job = next(iter(state["jobs"].values()))
    assert job["status"] == "failed"
    assert job["reason"] == "route_configuration_error"

    object_id = next(iter(state["objects"]))
    assert state["objects"][object_id]["route_history"] == [["pacs", "CT-TO-DOSE"]]
    assert "dose-scp" not in state.get("stored_objects", {})


def test_association_negotiation_is_reused_calling_ae_not_known() -> None:
    node = _node(
        dose_accepted_sop_classes=[CT_IMAGE_STORAGE],
        known_calling_aets=["WORKSTATION"],  # RAD-PACS (calling_ae von pacs) fehlt bewusst
    )
    state = rules.initial_state(node)

    _store(node, state)

    job = next(iter(state["jobs"].values()))
    assert job["status"] == "failed"
    assert job["reason"] == "calling_ae_not_recognized"


# ---------------------------------------------------------------------
# Fan-out: zwei Routen matchen dasselbe Objekt
# ---------------------------------------------------------------------

def test_fan_out_creates_two_sibling_jobs_at_the_same_depth() -> None:
    node = _node(
        dose_accepted_sop_classes=[CT_IMAGE_STORAGE],
        extra_pacs_routes=[{
            "id": "CT-TO-AI",
            "destination": {"host": "ai-scp", "service": "ai-store"},
            "match": {"modality": "CT"},
        }],
        extra_hosts=[{
            "name": "ai-scp", "ip": "10.0.0.40",
            "services": [{
                "id": "ai-store", "port": 104, "ae_title": "AI-NODE",
                "accepted_sop_classes": [CT_IMAGE_STORAGE],
            }],
        }],
    )
    state = rules.initial_state(node)

    _store(node, state)

    assert len(state["jobs"]) == 2
    depths = {job["routing_depth"] for job in state["jobs"].values()}
    assert depths == {1}
    statuses = {job["status"] for job in state["jobs"].values()}
    assert statuses == {"sent"}

    object_id = next(iter(state["objects"]))
    objects_by_id = {job["object"] for job in state["jobs"].values()}
    assert objects_by_id == {object_id}
    assert len(state["objects"]) == 1  # kein Klon trotz zwei Zielen

    assert state["stored_objects"]["pacs"] == [object_id]
    assert state["stored_objects"]["dose-scp"] == [object_id]
    assert state["stored_objects"]["ai-scp"] == [object_id]


def test_fan_out_route_history_has_both_entries_in_deterministic_order() -> None:
    node = _node(
        dose_accepted_sop_classes=[CT_IMAGE_STORAGE],
        extra_pacs_routes=[{
            "id": "CT-TO-AI",
            "destination": {"host": "ai-scp", "service": "ai-store"},
            "match": {"modality": "CT"},
        }],
        extra_hosts=[{
            "name": "ai-scp", "ip": "10.0.0.40",
            "services": [{
                "id": "ai-store", "port": 104, "ae_title": "AI-NODE",
                "accepted_sop_classes": [CT_IMAGE_STORAGE],
            }],
        }],
    )
    state = rules.initial_state(node)

    _store(node, state)

    object_id = next(iter(state["objects"]))
    assert state["objects"][object_id]["route_history"] == [
        ["pacs", "CT-TO-DOSE"], ["pacs", "CT-TO-AI"],
    ]


# ---------------------------------------------------------------------
# Duplicate Guard
# ---------------------------------------------------------------------

def test_restoring_the_same_object_at_the_same_host_does_not_create_a_second_job() -> None:
    node = _node(dose_accepted_sop_classes=[CT_IMAGE_STORAGE])
    state = rules.initial_state(node)

    _store(node, state)
    _store(node, state)  # resend derselben Datei -- idempotentes RuntimeObject

    assert len(state["objects"]) == 1
    assert len(state["jobs"]) == 1


def test_a_failed_job_is_not_automatically_retried_on_a_subsequent_store() -> None:
    node = _node(dose_accepted_sop_classes=[DOSE_SR])  # bleibt dauerhaft unsupported
    state = rules.initial_state(node)

    _store(node, state)
    _store(node, state)

    assert len(state["jobs"]) == 1
    job = next(iter(state["jobs"].values()))
    assert job["status"] == "failed"
    assert job["attempt"] == 1


# ---------------------------------------------------------------------
# Routing-Depth
# ---------------------------------------------------------------------

def test_default_max_routing_depth_of_one_stops_a_second_hop() -> None:
    """PACS -> Dose ist der einzige automatische Forward (Phase 1); Dose's
    eigene Route zu AI darf mit dem Default-Limit nicht mehr feuern."""
    node = _node(
        dose_accepted_sop_classes=[CT_IMAGE_STORAGE],
        extra_hosts=[{
            "name": "ai-scp", "ip": "10.0.0.40",
            "services": [{
                "id": "ai-store", "port": 104, "ae_title": "AI-NODE",
                "accepted_sop_classes": [CT_IMAGE_STORAGE],
            }],
        }],
    )
    dose_host = node.host("dose-scp")
    dose_host["dicom"] = {"calling_ae": "DOSE-SCP"}
    dose_host["routes"] = [{
        "id": "DOSE-TO-AI",
        "destination": {"host": "ai-scp", "service": "ai-store"},
        "match": {"modality": "CT"},
    }]
    state = rules.initial_state(node)

    _store(node, state)

    assert len(state["jobs"]) == 1  # nur PACS -> Dose, kein Dose -> AI
    assert "ai-scp" not in state.get("stored_objects", {})
    assert routing.DEFAULT_MAX_ROUTING_DEPTH == 1


def test_a_higher_max_routing_depth_allows_a_second_hop() -> None:
    """Abschnitt 50: isolierter Fixture-Test, kein neues Authoring-Feld --
    `max_routing_depth` wird direkt an store_and_route() durchgereicht, ohne
    ueber den (immer mit dem Default-Limit arbeitenden) `storescu`-Pfad zu
    gehen."""
    node = _node(
        dose_accepted_sop_classes=[CT_IMAGE_STORAGE],
        known_calling_aets=["WORKSTATION", "RAD-PACS", "DOSE-SCP"],
        extra_hosts=[{
            "name": "ai-scp", "ip": "10.0.0.40",
            "services": [{
                "id": "ai-store", "port": 104, "ae_title": "AI-NODE",
                "accepted_sop_classes": [CT_IMAGE_STORAGE],
            }],
        }],
    )
    dose_host = node.host("dose-scp")
    dose_host["dicom"] = {"calling_ae": "DOSE-SCP"}
    dose_host["routes"] = [{
        "id": "DOSE-TO-AI",
        "destination": {"host": "ai-scp", "service": "ai-store"},
        "match": {"modality": "CT"},
    }]
    state = rules.initial_state(node)
    object_id = runtime_objects.resolve_object_from_environment_object(
        state, node.raw["environment"]["objects"][0], origin_host="workstation",
    )

    jobs.store_and_route(state, node, object_id, "pacs", max_routing_depth=2)

    depths = sorted(job["routing_depth"] for job in state["jobs"].values())
    assert depths == [1, 2]
    assert state["stored_objects"]["pacs"] == [object_id]
    assert state["stored_objects"]["dose-scp"] == [object_id]
    assert state["stored_objects"]["ai-scp"] == [object_id]
    assert len(state["objects"]) == 1


# ---------------------------------------------------------------------
# Disabled Routes
# ---------------------------------------------------------------------

def test_a_disabled_route_creates_no_job_and_no_event() -> None:
    node = _node(dose_accepted_sop_classes=[CT_IMAGE_STORAGE])
    node.host("pacs")["routes"][0]["enabled"] = False
    state = rules.initial_state(node)

    _store(node, state)

    assert state.get("jobs", {}) == {}
    types = [e["type"] for e in state["events"]]
    assert types == ["store.completed"]


# ---------------------------------------------------------------------
# Regression: Nodes ohne routes bekommen keine Jobs
# ---------------------------------------------------------------------

def test_nodes_without_routes_never_create_jobs() -> None:
    node = _node(dose_accepted_sop_classes=[CT_IMAGE_STORAGE])
    node.host("pacs")["routes"] = []
    state = rules.initial_state(node)

    _store(node, state)

    assert state.get("jobs", {}) == {}
    types = [e["type"] for e in state["events"]]
    assert types == ["store.completed"]
