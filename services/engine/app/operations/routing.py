"""Routing-Definition, Matcher und Destination-Aufloesung (ADR 0120, 8.2/8.4).

Reine, seiteneffektfreie Funktionen -- werten nur aus, mutieren nie den
`state`. Dieselbe Auswertung wird spaeter sowohl von der automatischen
Routenauswertung (Phase B) als auch von einem seiteneffektfreien
`pacs route test` (Phase C) benutzt (ADR 0120, 8.7).

Phase A fuehrt hier ausdruecklich noch keine Association aus und loest noch
keine Jobs aus -- nur die Bausteine dafuer (Matching, Ziel-Aufloesung,
Tiefen-Invariante).
"""

from __future__ import annotations

from typing import Any

# ADR 0120, 8.2: das Phase-1-Match-Vokabular ist bewusst eine Whitelist aus
# genau den Feldern, die auch auf RuntimeObject existieren -- kein
# `source_ae` (host-lokal, siehe objects.py) und keine rohe Tag-Syntax.
MATCH_FIELDS = ("modality", "sop_class", "study_description", "series_description")
MATCH_OPERATORS = ("equals", "not_equals", "in", "exists")

# ADR 0120, 8.11: Phase 1 erlaubt genau einen automatischen Forward nach
# Initial Ingest (der selbst nicht zaehlt) -- kein weiterer Hop danach.
DEFAULT_MAX_ROUTING_DEPTH = 1


class RouteSchemaError(ValueError):
    """Strukturfehler in einer Route-/Match-Definition (ADR 0120, 9.3)."""


def normalize_match(match: dict[str, Any]) -> dict[str, list[dict[str, Any]]]:
    """Normalisiert Authoring-Sugar (`{modality: CT}`) und die kanonische
    Form (`{all|any: [...]}`) auf EINEN internen Code-Pfad -- nie zwei
    parallele Validatoren (ADR 0120, 8.2). Die Kurzform ist ein Authoring-
    Vertrag fuer GENAU EINE Gleichheitsbedingung (ADR 0120, Phase-A-Review)
    -- mehrere Bedingungen muessen explizit `all`/`any` verwenden, statt
    stillschweigend zu einem impliziten `all` zusammengefasst zu werden."""

    if not isinstance(match, dict) or not match:
        raise RouteSchemaError("match darf nicht leer sein.")

    if "all" in match or "any" in match:
        if "all" in match and "any" in match:
            raise RouteSchemaError("match darf nicht gleichzeitig 'all' und 'any' verwenden.")

        if len(match) != 1:
            raise RouteSchemaError(
                "match darf neben 'all'/'any' keine weiteren Schluessel enthalten "
                "(auch keine Kurzform-Felder gemischt mit 'all'/'any').",
            )

        key = "all" if "all" in match else "any"
        conditions = match[key]

        if not isinstance(conditions, list) or not conditions:
            raise RouteSchemaError(f"match.{key} muss eine nicht-leere Liste sein.")

        return {key: [_normalize_condition(c) for c in conditions]}

    # Authoring-Sugar: GENAU EIN Feld -> all: [{field, op: equals, value}].
    # Fuer mehrere Bedingungen ist 'all'/'any' Pflicht (kein implizites
    # Multi-Key-'all', das war der urspruengliche, zu lockere Vertrag).
    if len(match) != 1:
        raise RouteSchemaError(
            "match-Kurzform erlaubt genau ein Feld -- fuer mehrere Bedingungen "
            "'all'/'any' mit field/op/value verwenden.",
        )

    ((field, value),) = match.items()

    return {"all": [_normalize_condition({"field": field, "op": "equals", "value": value})]}


def _normalize_condition(condition: dict[str, Any]) -> dict[str, Any]:
    if "field" not in condition:
        raise RouteSchemaError("Jede match-Bedingung braucht 'field'.")

    field = condition["field"]
    op = condition.get("op", "equals")

    if field not in MATCH_FIELDS:
        raise RouteSchemaError(f"Unbekanntes match-Feld '{field}'.")

    if op not in MATCH_OPERATORS:
        raise RouteSchemaError(f"Unbekannter Operator '{op}'.")

    has_value = "value" in condition
    has_values = "values" in condition

    if op == "exists":
        if has_value or has_values:
            raise RouteSchemaError("op: exists erlaubt weder 'value' noch 'values'.")

        return {"field": field, "op": op}

    if op == "in":
        if has_value:
            raise RouteSchemaError("op: in erlaubt kein zusaetzliches 'value' (nur 'values').")

        values = condition.get("values")

        if not isinstance(values, list) or not values:
            raise RouteSchemaError("op: in erfordert eine nicht-leere 'values'-Liste.")

        return {"field": field, "op": op, "values": list(values)}

    # equals / not_equals
    if has_values:
        raise RouteSchemaError(f"op: {op} erlaubt kein zusaetzliches 'values' (nur 'value').")

    if not has_value:
        raise RouteSchemaError(f"op: {op} erfordert 'value'.")

    return {"field": field, "op": op, "value": condition["value"]}


def evaluate_condition(condition: dict[str, Any], runtime_object: dict[str, Any]) -> bool:
    """Missing-Field-Semantik (ADR 0120, 8.2, exakte Tabelle): fehlt das
    Feld am Objekt (`None`), gilt `equals`/`in`/`exists` als nicht erfuellt,
    `not_equals` als erfuellt."""

    op: str = condition["op"]
    actual = runtime_object.get(condition["field"])

    if op == "exists":
        return actual is not None

    if actual is None:
        return op == "not_equals"

    if op == "equals":
        return bool(actual == condition["value"])
    if op == "not_equals":
        return bool(actual != condition["value"])
    if op == "in":
        return actual in condition["values"]

    # unerreichbar: normalize_match() laesst nur MATCH_OPERATORS durch.
    raise RouteSchemaError(f"Unbekannter Operator '{op}'.")  # pragma: no cover


def _condition_detail(condition: dict[str, Any], runtime_object: dict[str, Any]) -> dict[str, Any]:
    detail = {
        "matched": evaluate_condition(condition, runtime_object),
        "field": condition["field"],
        "operator": condition["op"],
        "actual": runtime_object.get(condition["field"]),
    }

    if condition["op"] == "in":
        detail["expected"] = condition["values"]
    elif condition["op"] != "exists":
        detail["expected"] = condition["value"]

    return detail


def evaluate_match(match: dict[str, Any], runtime_object: dict[str, Any]) -> bool:
    canonical = normalize_match(match)

    if "all" in canonical:
        return all(evaluate_condition(c, runtime_object) for c in canonical["all"])

    return any(evaluate_condition(c, runtime_object) for c in canonical["any"])


def evaluate_route(route: dict[str, Any], runtime_object: dict[str, Any]) -> dict[str, Any]:
    """Reine Auswertungsfunktion mit strukturiertem Ergebnis (ADR 0120,
    8.2/8.7) statt nur einem bool -- dieselbe Funktion traegt spaeter sowohl
    die automatische Routenauswertung (Phase B) als auch `pacs route test`
    (Phase C, seiteneffektfrei)."""

    route_id = route.get("id")
    canonical = normalize_match(route["match"])
    key = "all" if "all" in canonical else "any"
    details = [_condition_detail(c, runtime_object) for c in canonical[key]]

    if key == "all":
        failing = next((d for d in details if not d["matched"]), None)

        if failing is None:
            return {"matched": True, "route_id": route_id}

        return {"matched": False, "route_id": route_id, **_without_matched(failing)}

    passing = next((d for d in details if d["matched"]), None)

    if passing is not None:
        return {"matched": True, "route_id": route_id}

    return {"matched": False, "route_id": route_id, **_without_matched(details[0])}


def _without_matched(detail: dict[str, Any]) -> dict[str, Any]:
    return {k: v for k, v in detail.items() if k != "matched"}


def _route_enabled(route: dict[str, Any]) -> bool:
    """`enabled` muss, wenn vorhanden, ein echtes Boolean sein (ADR 0120,
    Phase-A-Review) -- `ContentValidator.php` ist die Authoring-Guardrail
    dafuer, aber `routing.py` interpretiert selbst keine Strings/Zahlen als
    Wahrheitswert (kein `bool("false") == True`)."""

    enabled = route.get("enabled", True)

    if not isinstance(enabled, bool):
        raise RouteSchemaError(
            f"Route '{route.get('id')}': enabled muss Boolean sein, nicht {enabled!r}.",
        )

    return enabled


def find_matching_routes(
    host: dict[str, Any], runtime_object: dict[str, Any],
) -> list[dict[str, Any]]:
    """Wertet alle `enabled` Routes eines Hosts gegen ein RuntimeObject aus
    (auch nicht-matchende Routen, im Ergebnis erkennbar an `matched: False`)
    -- Fan-out heisst: mehrere Routes koennen gleichzeitig matchen (ADR 0120,
    8.2). Loest in Phase A noch nichts aus, dient Tests und Phase B/C."""

    routes = [r for r in host.get("routes", []) if _route_enabled(r)]

    return [evaluate_route(route, runtime_object) for route in routes]


def resolve_destination(node: Any, source_host_name: str, route: dict[str, Any]) -> dict[str, Any]:
    """Loest `route.destination` (Host + Service-ID) auf die fuer eine
    Association noetigen Werte auf (ADR 0120, 8.4). Fuehrt noch keine
    Association aus -- das bleibt Phase B (Wiederverwendung von
    `rules.check_association`)."""

    destination = route.get("destination") or {}
    host_name = destination.get("host")
    service_id = destination.get("service")

    if not host_name or not service_id:
        raise RouteSchemaError(
            f"Route '{route.get('id')}': destination braucht sowohl 'host' als auch 'service'.",
        )

    target_host = node.host(host_name)

    if target_host is None:
        raise RouteSchemaError(f"Route '{route.get('id')}': unbekannter Ziel-Host '{host_name}'.")

    service = next(
        (s for s in target_host.get("services", []) if s.get("id") == service_id), None,
    )

    if service is None:
        raise RouteSchemaError(
            f"Route '{route.get('id')}': unbekannter Service '{service_id}' an Host '{host_name}'.",
        )

    source_host = node.host(source_host_name) or {}

    return {
        "host": host_name,
        "service": service_id,
        "ip": target_host.get("ip"),
        "port": service.get("port"),
        "called_ae": service.get("ae_title"),
        "calling_ae": source_host.get("dicom", {}).get("calling_ae"),
    }


def can_forward(
    parent_routing_depth: int, max_routing_depth: int = DEFAULT_MAX_ROUTING_DEPTH,
) -> bool:
    """Routing-Depth-Invariante (ADR 0120, 8.11), vorbereitend fuer Phase B:
    ein Folgejob darf nur entstehen, wenn die bisherige Tiefe die Grenze noch
    nicht erreicht hat. Initial Ingest zaehlt nicht mit (`parent_routing_depth
    = 0`); Phase A erzeugt hier noch keine Jobs, nur die reine Invariante."""

    return parent_routing_depth < max_routing_depth
