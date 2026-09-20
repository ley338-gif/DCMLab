"""Routing-Jobs und die automatische Routenausfuehrung (ADR 0120, Phase B).

Ein Job ist rein simuliertes Betriebsmodell -- kein Thread, kein Worker,
keine Queue-Verzoegerung: er entsteht und wird im selben Request synchron
ausgefuehrt (ADR 0120, 8.5/8.9). Persistiert in `state["jobs"]` (dict,
JSON-kompatibel), Primaerschluessel `job.id`, sitzungslokal/fortlaufend wie
`object_id` (kein UUID, keine DB-Sequenz).

`store_and_route()` ist der EINE Phase-B-Einstiegspunkt fuer beide
Storage-Pfade (`storescu`, `send_study`) UND fuer jeden automatischen
Forward: er ruft zuerst `objects.store_object()` (Presence, unveraendert
aus Phase A) auf und wertet danach -- sofern die Routing-Depth-Grenze das
erlaubt -- die Routes des Ziel-Hosts aus. `objects.store_object()` selbst
bleibt die reine, ungeaenderte Phase-A-Praesenz-Operation: die Orchestrierung
lebt hier, nicht dort, um einen Objects<->Jobs-Modulzyklus zu vermeiden
(`app.rules` wird aus demselben Grund nur lokal innerhalb der Funktion
importiert, die `check_association()` tatsaechlich braucht, statt auf
Modulebene -- `rules.py` importiert bereits `jobs.py`).
"""

from __future__ import annotations

from typing import TYPE_CHECKING, Any

from app.operations import objects as runtime_objects
from app.operations import routing
from app.operations.events import now_iso, record_event

if TYPE_CHECKING:
    from app.content import NodeDefinition


def _next_job_id(state: dict[str, Any]) -> str:
    jobs = state.setdefault("jobs", {})

    return f"j-{len(jobs) + 1:03d}"


def create_job(
    state: dict[str, Any],
    *,
    route_id: str,
    object_id: str,
    source_host: str,
    destination: dict[str, Any],
    routing_depth: int,
) -> dict[str, Any]:
    """Erzeugt einen Job im Status `queued` (ADR 0120, Phase B). `attempt`
    ist immer 1 -- Phase B hat keinen Retry (ADR 0120, 8.9)."""

    job_id = _next_job_id(state)
    job: dict[str, Any] = {
        "id": job_id,
        "route_id": route_id,
        "object": object_id,
        "source": source_host,
        "destination": destination,
        "routing_depth": routing_depth,
        "attempt": 1,
        "status": "queued",
        "reason": None,
        "created_at": now_iso(),
    }
    state["jobs"][job_id] = job

    return job


def mark_job_sent(state: dict[str, Any], job_id: str) -> None:
    state["jobs"][job_id]["status"] = "sent"


def mark_job_failed(state: dict[str, Any], job_id: str, reason: str) -> None:
    state["jobs"][job_id]["status"] = "failed"
    state["jobs"][job_id]["reason"] = reason


def store_and_route(
    state: dict[str, Any],
    node: NodeDefinition,
    object_id: str,
    target_host: str,
    *,
    incoming_routing_depth: int = 0,
    max_routing_depth: int = routing.DEFAULT_MAX_ROUTING_DEPTH,
) -> dict[str, Any]:
    """Presence setzen, `store.completed` erfassen, dann -- falls die
    Routing-Depth-Grenze (`routing.can_forward`) das erlaubt -- die Routes
    des Ziel-Hosts auswerten, matchende und noch nicht verarbeitete Routen zu
    Jobs machen und synchron ausfuehren. Ein erfolgreicher Job ruft diese
    Funktion fuer sein Ziel rekursiv mit `incoming_routing_depth =
    job.routing_depth` erneut auf (Multi-Hop, Fan-out inklusive) -- dasselbe
    `object_id` bleibt dabei immer erhalten, es wird nie ein neues Objekt
    erzeugt (ADR 0120, 8.3/8.5). `max_routing_depth` bleibt eine
    Engine-Konstante (Default `DEFAULT_MAX_ROUTING_DEPTH = 1`, Phase 1, ADR
    0120 8.11) -- kein neues Authoring-Feld; der Parameter existiert nur,
    damit ein hoeheres Limit isoliert getestet werden kann (YAGNI, solange
    kein realer Node mehr als einen automatischen Hop braucht)."""

    runtime_object = runtime_objects.store_object(
        state, object_id, target_host, incoming_routing_depth=incoming_routing_depth,
    )
    record_event(state, "store.completed", object=object_id, host=target_host)

    if not routing.can_forward(incoming_routing_depth, max_routing_depth):
        return runtime_object

    host = node.host(target_host)

    if host is None:
        return runtime_object

    try:
        route_results = routing.find_matching_routes(host, runtime_object)
    except routing.RouteSchemaError:
        # Der ContentValidator (docs/content-schema.md, Abschnitt 9)
        # verhindert das normalerweise; Runtime bleibt defensiv, statt die
        # ganze Session an invalidem, direkt geladenem Content scheitern zu
        # lassen (ADR 0120, Phase-B-Review, Abschnitt 37/67).
        return runtime_object

    routes_by_id = {r.get("id"): r for r in host.get("routes", []) if r.get("id") is not None}

    for result in route_results:
        route_id = result["route_id"]
        # ADR 0120, 8.6: bei matched=false traegt `result` bereits die
        # diagnostisch relevanten Felder (field/operator/expected/actual)
        # aus evaluate_route() -- einfach weiterreichen, kein zweiter
        # Matcher/keine eigene Formatierung noetig.
        record_event(state, "route.evaluated", object=object_id, host=target_host, **result)

        if not result["matched"]:
            continue

        # Duplicate-/Loop-Schutz (ADR 0120, 8.11): dieselbe (host, route_id)
        # -Kombination verarbeitet dasselbe Objekt hoechstens einmal
        # automatisch -- ein erneuter Store desselben Objekts am selben Host
        # (Presence bleibt idempotent) darf keinen zweiten Job erzeugen.
        if runtime_objects.has_route_processed(state, object_id, target_host, route_id):
            continue

        route = routes_by_id.get(route_id)

        if route is None:  # pragma: no cover -- route_results stammt aus denselben host["routes"]
            continue

        _create_and_execute_job(
            state, node, object_id, target_host, route, incoming_routing_depth, max_routing_depth,
        )

    return runtime_object


def _fail_job(
    state: dict[str, Any], job: dict[str, Any], *, object_id: str, route_id: str, reason: str,
) -> None:
    mark_job_failed(state, job["id"], reason)
    record_event(
        state, "job.failed",
        object=object_id, route_id=route_id, job_id=job["id"], reason=reason,
    )


def _create_and_execute_job(
    state: dict[str, Any],
    node: NodeDefinition,
    object_id: str,
    source_host_name: str,
    route: dict[str, Any],
    incoming_routing_depth: int,
    max_routing_depth: int,
) -> None:
    from app import rules  # lokal: rules.py importiert bereits jobs.py (siehe Moduldoc).

    route_id = route["id"]
    new_depth = incoming_routing_depth + 1

    try:
        destination = routing.resolve_destination(node, source_host_name, route)
    except routing.RouteSchemaError:
        # Abschnitt 37: die Route hat gematcht, aber ihre destination ist
        # nicht aufloesbar (invalider Content, den der Validator eigentlich
        # verhindert). Job existiert trotzdem, mit Rohangaben und status
        # failed, statt eines Python-Crashs -- und route_history wird
        # trotzdem markiert (Abschnitt 38: kein Endlosloop wegen kaputter
        # Destination).
        declared_destination = route.get("destination") or {}
        raw_destination = {
            "host": declared_destination.get("host"),
            "service": declared_destination.get("service"),
        }
        job = create_job(
            state, route_id=route_id, object_id=object_id, source_host=source_host_name,
            destination=raw_destination,
            routing_depth=new_depth,
        )
        runtime_objects.record_route_processed(state, object_id, source_host_name, route_id)
        record_event(
            state, "job.created",
            object=object_id, route_id=route_id, job_id=job["id"],
            source=source_host_name, destination=raw_destination, routing_depth=new_depth,
        )
        _fail_job(
            state, job, object_id=object_id, route_id=route_id, reason="route_configuration_error",
        )

        return

    job = create_job(
        state, route_id=route_id, object_id=object_id, source_host=source_host_name,
        destination=destination, routing_depth=new_depth,
    )
    runtime_objects.record_route_processed(state, object_id, source_host_name, route_id)
    record_event(
        state, "job.created",
        object=object_id, route_id=route_id, job_id=job["id"],
        source=source_host_name, destination=destination, routing_depth=new_depth,
    )

    association = rules.check_association(
        node, state,
        destination["calling_ae"], destination["called_ae"],
        destination["ip"], destination["port"],
    )

    if not association.accepted:
        _fail_job(state, job, object_id=object_id, route_id=route_id, reason=association.reason)

        return

    runtime_object = state["objects"][object_id]
    target_host_def = node.host(destination["host"]) or {}
    service = next(
        (s for s in target_host_def.get("services", []) if s.get("id") == destination["service"]),
        None,
    ) or {}

    accepted_sop_classes = service.get("accepted_sop_classes")
    object_sop_class = runtime_object.get("sop_class")

    if accepted_sop_classes is not None and object_sop_class not in accepted_sop_classes:
        _fail_job(
            state, job, object_id=object_id, route_id=route_id,
            reason="abstract_syntax_not_supported",
        )

        return

    accepted_syntaxes = service.get("accepted_transfer_syntaxes")
    object_transfer_syntax = runtime_object.get("transfer_syntax")

    if accepted_syntaxes is not None and object_transfer_syntax not in accepted_syntaxes:
        _fail_job(
            state, job, object_id=object_id, route_id=route_id,
            reason="transfer_syntaxes_not_supported",
        )

        return

    mark_job_sent(state, job["id"])
    record_event(
        state, "job.sent",
        object=object_id, route_id=route_id, job_id=job["id"], host=destination["host"],
    )

    store_and_route(
        state, node, object_id, destination["host"],
        incoming_routing_depth=new_depth, max_routing_depth=max_routing_depth,
    )
