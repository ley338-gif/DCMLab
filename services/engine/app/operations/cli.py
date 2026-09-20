"""Read-only PACS-Operations-CLI (ADR 0120, Phase C): der `pacs`-Tool-
Dispatch fuer `rules.exec_command()`. Parser, Lookup und Formatierung leben
komplett hier -- `rules.py` erkennt nur `tool == "pacs"` und delegiert.

Liest ausschliesslich die bereits vorhandenen generischen Phase-A-/
Phase-B-Daten (`state["objects"]`, `state["stored_objects"]`,
`state["jobs"]`, `state["events"]`, `environment.hosts[].routes`) --
niemals `bestand` als PACS-Operations-Wahrheit (das bleibt das Legacy-
Aggregat bestehender Nodes, ADR 0120 8.8).

Jeder Befehl ist seiteneffektfrei. `pacs route test` ist ein echter Dry
Run: er ruft ausschliesslich `routing.evaluate_route()` (denselben reinen
Matcher, den auch die automatische Routenauswertung in `jobs.py` benutzt)
und mutiert nichts -- kein Job, kein Event, keine `route_history`-
Aenderung, kein `last_progress_at`-Touch (ADR 0120, 8.7).
"""

from __future__ import annotations

from typing import TYPE_CHECKING, Any

from app.operations import routing

if TYPE_CHECKING:
    from app.content import NodeDefinition

MISSING = "-"

_USAGE = "usage: pacs <objects|routes|jobs|events> ..."


class RouteLookupAmbiguous(Exception):
    """Mehrere Hosts deklarieren dieselbe Route-ID (ContentValidator
    erzwingt normalerweise Eindeutigkeit -- Runtime bleibt defensiv, falls
    invalider Content direkt geladen wird, statt zufaellig eine Route
    auszuwaehlen)."""


def run(node: NodeDefinition, state: dict[str, Any], args: list[str]) -> tuple[str, str, int]:
    """Gibt `(stdout, stderr, exit_code)` zurueck -- `rules._exec_pacs()`
    verpackt das Ergebnis in ein `ExecResult` (kein `ExecResult`-Import
    hier, um einen rules.py<->cli.py-Modulzyklus zu vermeiden)."""

    if not args:
        return "", _USAGE, 1

    if args[0] in ("help", "--help"):
        return _USAGE + "\n", "", 0

    subcommand, rest = args[0], args[1:]

    if subcommand == "objects":
        return _cmd_objects(state)
    if subcommand == "object":
        if len(rest) != 2 or rest[0] != "show":
            return "", "usage: pacs object show <object-id>", 1
        return _cmd_object_show(state, rest[1])
    if subcommand == "routes":
        return _cmd_routes(node)
    if subcommand == "route":
        if len(rest) == 2 and rest[0] == "show":
            return _cmd_route_show(node, rest[1])
        if len(rest) == 3 and rest[0] == "test":
            return _cmd_route_test(node, state, rest[1], rest[2])

        return "", "usage: pacs route <show <route-id>|test <route-id> <object-id>>", 1
    if subcommand == "jobs":
        return _cmd_jobs(state)
    if subcommand == "job":
        if len(rest) != 2 or rest[0] != "show":
            return "", "usage: pacs job show <job-id>", 1
        return _cmd_job_show(state, rest[1])
    if subcommand == "events":
        return _cmd_events(state)

    return "", f'pacs: unknown subcommand "{subcommand}"', 1


# ---------------------------------------------------------------------
# Lookup-Helfer
# ---------------------------------------------------------------------

def find_route(node: NodeDefinition, route_id: str) -> tuple[str, dict[str, Any]] | None:
    """Sucht `route_id` ueber alle Hosts, in Node-Dateireihenfolge
    (`node.hosts`, dann `host["routes"]`). Liefert `(source_host_name,
    route)` oder `None`. Wirft `RouteLookupAmbiguous` bei mehreren Treffern
    -- dient sowohl `route show` als auch `route test` (Abschnitt 36, keine
    doppelte Host-Schleife)."""

    matches = [
        (host["name"], route)
        for host in node.hosts
        for route in host.get("routes", [])
        if route.get("id") == route_id
    ]

    if len(matches) > 1:
        raise RouteLookupAmbiguous(route_id)

    return matches[0] if matches else None


# ---------------------------------------------------------------------
# Formatierungs-Helfer (pure)
# ---------------------------------------------------------------------

def _or_dash(value: Any) -> str:
    return MISSING if value is None else str(value)


def _format_list_value(value: Any) -> str:
    if isinstance(value, list):
        return "[" + ", ".join(str(v) for v in value) + "]"

    return _or_dash(value)


def _format_table(headers: list[str], rows: list[list[str]]) -> str:
    widths = [len(h) for h in headers]

    for row in rows:
        for i, cell in enumerate(row):
            widths[i] = max(widths[i], len(cell))

    def _line(cells: list[str]) -> str:
        return "  ".join(cell.ljust(widths[i]) for i, cell in enumerate(cells)).rstrip()

    return "\n".join([_line(headers), *[_line(row) for row in rows]])


def _format_fields(pairs: list[tuple[str, str]]) -> str:
    width = max(len(label) for label, _ in pairs) + 1  # +1 fuer den Doppelpunkt

    return "\n".join(f"{(label + ':').ljust(width + 1)}{value}" for label, value in pairs)


def _format_condition(condition: dict[str, Any]) -> str:
    field, op = condition["field"], condition["op"]

    if op == "exists":
        return f"{field} exists"
    if op == "in":
        return f"{field} in {_format_list_value(condition['values'])}"

    return f"{field} {op} {condition['value']}"


def _presence_for(state: dict[str, Any], object_id: str) -> list[str]:
    return [
        host_name
        for host_name, object_ids in state.get("stored_objects", {}).items()
        if object_id in object_ids
    ]


def _destination_label(destination: dict[str, Any] | None) -> str:
    destination = destination or {}

    return f"{destination.get('host', MISSING)} / {destination.get('service', MISSING)}"


# ---------------------------------------------------------------------
# pacs objects / pacs object show
# ---------------------------------------------------------------------

def _cmd_objects(state: dict[str, Any]) -> tuple[str, str, int]:
    objects: dict[str, dict[str, Any]] = state.get("objects", {})

    if not objects:
        return "No runtime objects.\n", "", 0

    headers = ["OBJECT", "SOP CLASS", "MODALITY", "PRESENCE"]
    rows = [
        [
            object_id,
            _or_dash(obj.get("sop_class")),
            _or_dash(obj.get("modality")),
            ", ".join(_presence_for(state, object_id)) or MISSING,
        ]
        for object_id, obj in objects.items()
    ]

    return _format_table(headers, rows) + "\n", "", 0


def _cmd_object_show(state: dict[str, Any], object_id: str) -> tuple[str, str, int]:
    obj = state.get("objects", {}).get(object_id)

    if obj is None:
        return "", f'pacs: unknown object "{object_id}"', 1

    presence = ", ".join(_presence_for(state, object_id)) or MISSING
    fields = _format_fields([
        ("Object", object_id),
        ("SOP Instance UID", _or_dash(obj.get("sop_instance_uid"))),
        ("SOP Class UID", _or_dash(obj.get("sop_class"))),
        ("Transfer Syntax", _or_dash(obj.get("transfer_syntax"))),
        ("Modality", _or_dash(obj.get("modality"))),
        ("Study UID", _or_dash(obj.get("study_uid"))),
        ("Series UID", _or_dash(obj.get("series_uid"))),
        ("Study Description", _or_dash(obj.get("study_description"))),
        ("Series Description", _or_dash(obj.get("series_description"))),
        ("Origin Host", _or_dash(obj.get("origin_host"))),
        ("Presence", presence),
    ])

    return fields + "\n", "", 0


# ---------------------------------------------------------------------
# pacs routes / pacs route show / pacs route test
# ---------------------------------------------------------------------

def _cmd_routes(node: NodeDefinition) -> tuple[str, str, int]:
    rows = [
        [
            route.get("id", MISSING),
            host.get("name", MISSING),
            (route.get("destination") or {}).get("host", MISSING),
            "yes" if route.get("enabled", True) else "no",
        ]
        for host in node.hosts
        for route in host.get("routes", [])
    ]

    if not rows:
        return "No routes.\n", "", 0

    headers = ["ROUTE", "SOURCE", "DESTINATION", "ENABLED"]

    return _format_table(headers, rows) + "\n", "", 0


def _cmd_route_show(node: NodeDefinition, route_id: str) -> tuple[str, str, int]:
    try:
        found = find_route(node, route_id)
    except RouteLookupAmbiguous:
        return "", f'pacs: route id is ambiguous: "{route_id}"', 1

    if found is None:
        return "", f'pacs: unknown route "{route_id}"', 1

    source_host, route = found
    header = _format_fields([
        ("Route", route.get("id", route_id)),
        ("Source", source_host),
        ("Destination", _destination_label(route.get("destination"))),
        ("Enabled", "yes" if route.get("enabled", True) else "no"),
    ])

    try:
        canonical = routing.normalize_match(route["match"])
    except routing.RouteSchemaError:
        return f"{header}\n\nMatch: <invalid>\n", "", 0

    key = "all" if "all" in canonical else "any"
    conditions = canonical[key]
    label = "Match:" if len(conditions) == 1 else f"Match {key.upper()}:"
    condition_lines = "\n".join(f"  {_format_condition(c)}" for c in conditions)

    return f"{header}\n\n{label}\n{condition_lines}\n", "", 0


def _cmd_route_test(
    node: NodeDefinition, state: dict[str, Any], route_id: str, object_id: str,
) -> tuple[str, str, int]:
    try:
        found = find_route(node, route_id)
    except RouteLookupAmbiguous:
        return "", f'pacs: route id is ambiguous: "{route_id}"', 1

    if found is None:
        return "", f'pacs: unknown route "{route_id}"', 1

    _source_host, route = found
    runtime_object = state.get("objects", {}).get(object_id)

    if runtime_object is None:
        return "", f'pacs: unknown object "{object_id}"', 1

    # Reiner Dry Run (ADR 0120, 8.7): evaluate_route() ist eine pure
    # Funktion, es gibt hier keinen Schreibzugriff auf `state`.
    result = routing.evaluate_route(route, runtime_object)

    if result["matched"]:
        return "matched: true\n", "", 0

    lines = [
        "matched: false",
        f"field: {result['field']}",
        f"operator: {result['operator']}",
        f"expected: {_format_list_value(result.get('expected'))}",
        f"actual: {_or_dash(result.get('actual'))}",
    ]

    return "\n".join(lines) + "\n", "", 0


# ---------------------------------------------------------------------
# pacs jobs / pacs job show
# ---------------------------------------------------------------------

def _cmd_jobs(state: dict[str, Any]) -> tuple[str, str, int]:
    jobs: dict[str, dict[str, Any]] = state.get("jobs", {})

    if not jobs:
        return "No routing jobs.\n", "", 0

    headers = ["JOB", "ROUTE", "OBJECT", "SOURCE", "DESTINATION", "DEPTH", "STATUS", "REASON"]
    rows = [
        [
            job_id,
            _or_dash(job.get("route_id")),
            _or_dash(job.get("object")),
            _or_dash(job.get("source")),
            (job.get("destination") or {}).get("host", MISSING),
            _or_dash(job.get("routing_depth")),
            _or_dash(job.get("status")),
            _or_dash(job.get("reason")),
        ]
        for job_id, job in jobs.items()
    ]

    return _format_table(headers, rows) + "\n", "", 0


def _cmd_job_show(state: dict[str, Any], job_id: str) -> tuple[str, str, int]:
    job = state.get("jobs", {}).get(job_id)

    if job is None:
        return "", f'pacs: unknown job "{job_id}"', 1

    fields = _format_fields([
        ("Job", job_id),
        ("Route", _or_dash(job.get("route_id"))),
        ("Object", _or_dash(job.get("object"))),
        ("Source", _or_dash(job.get("source"))),
        ("Destination", _destination_label(job.get("destination"))),
        ("Routing Depth", _or_dash(job.get("routing_depth"))),
        ("Attempt", _or_dash(job.get("attempt"))),
        ("Status", _or_dash(job.get("status"))),
        ("Reason", _or_dash(job.get("reason"))),
        ("Created", _or_dash(job.get("created_at"))),
    ])

    return fields + "\n", "", 0


# ---------------------------------------------------------------------
# pacs events
# ---------------------------------------------------------------------

def _event_details(event: dict[str, Any]) -> str:
    event_type = event.get("type")

    if event_type == "store.completed":
        return f"host={event.get('host', MISSING)}"

    if event_type == "route.evaluated":
        if event.get("matched"):
            return "matched=true"

        return (
            f"matched=false {event.get('field', MISSING)} "
            f"expected {_format_list_value(event.get('expected'))} "
            f"actual {_or_dash(event.get('actual'))}"
        )

    if event_type == "job.created":
        destination = event.get("destination") or {}

        return f"{event.get('source', MISSING)} -> {destination.get('host', MISSING)}"

    if event_type == "job.sent":
        return str(event.get("host", MISSING))

    if event_type == "job.failed":
        return f"reason={event.get('reason', MISSING)}"

    return ""


def _cmd_events(state: dict[str, Any]) -> tuple[str, str, int]:
    events: list[dict[str, Any]] = state.get("events", [])

    if not events:
        return "No PACS events.\n", "", 0

    headers = ["EVENT", "TYPE", "OBJECT", "ROUTE", "JOB", "DETAILS"]
    # Kanonische Reihenfolge ist die Append-Reihenfolge von state["events"]
    # (ADR 0120, 8.6) -- keine Neusortierung, auch nicht nach Timestamp.
    rows = [
        [
            _or_dash(event.get("id")),
            _or_dash(event.get("type")),
            _or_dash(event.get("object")),
            _or_dash(event.get("route_id")),
            _or_dash(event.get("job_id")),
            _event_details(event),
        ]
        for event in events
    ]

    return _format_table(headers, rows) + "\n", "", 0
