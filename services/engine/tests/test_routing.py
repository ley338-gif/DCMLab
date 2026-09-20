"""Match-Evaluator, Route-Auswertung, Destination-Aufloesung und die
Routing-Depth-Invariante (ADR 0120, 8.2/8.4/8.11, Phase A) -- reine
Funktionen, kein Session-`state` involviert."""

import pytest

from app.content import NodeDefinition
from app.operations import routing


def test_shorthand_match_normalizes_to_canonical_form() -> None:
    canonical = routing.normalize_match({"modality": "CT"})

    assert canonical == {"all": [{"field": "modality", "op": "equals", "value": "CT"}]}


def test_shorthand_is_restricted_to_exactly_one_field() -> None:
    """Authoring-Vertrag (ADR 0120, Phase-A-Review): die Kurzform ist fuer
    GENAU EINE Gleichheitsbedingung gedacht. Mehrere Felder muessen explizit
    'all'/'any' verwenden, statt stillschweigend zu einem impliziten 'all'
    zusammengefasst zu werden."""
    with pytest.raises(routing.RouteSchemaError):
        routing.normalize_match({"modality": "CT", "sop_class": "1.2.840.10008.5.1.4.1.1.2"})


def test_shorthand_mixed_with_all_is_a_schema_error() -> None:
    with pytest.raises(routing.RouteSchemaError):
        routing.normalize_match({
            "all": [{"field": "modality", "op": "equals", "value": "CT"}],
            "sop_class": "1.2.840.10008.5.1.4.1.1.2",
        })


def test_canonical_all_with_multiple_conditions_is_still_valid() -> None:
    canonical = routing.normalize_match({"all": [
        {"field": "modality", "op": "equals", "value": "CT"},
        {"field": "sop_class", "op": "equals", "value": "1.2.840.10008.5.1.4.1.1.2"},
    ]})

    assert canonical == {
        "all": [
            {"field": "modality", "op": "equals", "value": "CT"},
            {"field": "sop_class", "op": "equals", "value": "1.2.840.10008.5.1.4.1.1.2"},
        ],
    }


def test_canonical_any_is_valid() -> None:
    condition = {"field": "modality", "op": "equals", "value": "CT"}
    canonical = routing.normalize_match({"any": [condition]})

    assert canonical == {"any": [condition]}


def test_canonical_form_passes_through_unchanged() -> None:
    match = {"all": [{"field": "modality", "op": "equals", "value": "CT"}]}

    assert routing.normalize_match(match) == match


def test_all_and_any_together_is_a_schema_error() -> None:
    with pytest.raises(routing.RouteSchemaError):
        routing.normalize_match({
            "all": [{"field": "modality", "op": "equals", "value": "CT"}],
            "any": [],
        })


def test_empty_match_is_a_schema_error() -> None:
    with pytest.raises(routing.RouteSchemaError):
        routing.normalize_match({})


def test_unknown_field_is_a_schema_error() -> None:
    with pytest.raises(routing.RouteSchemaError):
        routing.normalize_match({"source_ae": "CT_RAUM3"})


def test_raw_tag_syntax_is_not_an_allowed_field() -> None:
    with pytest.raises(routing.RouteSchemaError):
        routing.normalize_match({"all": [{"field": "0008,0060", "op": "equals", "value": "CT"}]})


def test_unknown_operator_is_a_schema_error() -> None:
    with pytest.raises(routing.RouteSchemaError):
        routing.normalize_match({"all": [{"field": "modality", "op": "matches", "value": "CT"}]})


def test_in_operator_requires_a_values_list() -> None:
    with pytest.raises(routing.RouteSchemaError):
        routing.normalize_match({"all": [{"field": "modality", "op": "in", "value": "CT"}]})


def test_exists_operator_needs_neither_value_nor_values() -> None:
    canonical = routing.normalize_match({"all": [{"field": "modality", "op": "exists"}]})

    assert canonical == {"all": [{"field": "modality", "op": "exists"}]}


def test_exists_operator_rejects_a_meaningless_value() -> None:
    with pytest.raises(routing.RouteSchemaError):
        routing.normalize_match({"all": [{"field": "modality", "op": "exists", "value": "CT"}]})


def test_in_operator_rejects_an_extra_value_key() -> None:
    with pytest.raises(routing.RouteSchemaError):
        routing.normalize_match({
            "all": [{"field": "modality", "op": "in", "values": ["CT"], "value": "CT"}],
        })


def test_equals_rejects_an_extra_values_key() -> None:
    with pytest.raises(routing.RouteSchemaError):
        routing.normalize_match({
            "all": [{"field": "modality", "op": "equals", "value": "CT", "values": ["CT"]}],
        })


def test_not_equals_rejects_an_extra_values_key() -> None:
    with pytest.raises(routing.RouteSchemaError):
        routing.normalize_match({
            "all": [{"field": "modality", "op": "not_equals", "value": "CT", "values": ["CT"]}],
        })


def test_equals_without_a_value_is_a_schema_error() -> None:
    with pytest.raises(routing.RouteSchemaError):
        routing.normalize_match({"all": [{"field": "modality", "op": "equals"}]})


# ---------------------------------------------------------------------
# Missing-Field-Semantik (ADR 0120, 8.2, exakte Tabelle)
# ---------------------------------------------------------------------

def test_equals_is_false_when_the_field_is_missing() -> None:
    condition = {"field": "modality", "op": "equals", "value": "CT"}
    assert routing.evaluate_condition(condition, {}) is False


def test_not_equals_is_true_when_the_field_is_missing() -> None:
    condition = {"field": "modality", "op": "not_equals", "value": "CT"}
    assert routing.evaluate_condition(condition, {}) is True


def test_in_is_false_when_the_field_is_missing() -> None:
    condition = {"field": "modality", "op": "in", "values": ["CT", "MR"]}
    assert routing.evaluate_condition(condition, {}) is False


def test_exists_is_false_when_the_field_is_missing() -> None:
    condition = {"field": "modality", "op": "exists"}
    assert routing.evaluate_condition(condition, {}) is False


def test_exists_is_true_when_the_field_is_present() -> None:
    condition = {"field": "modality", "op": "exists"}
    assert routing.evaluate_condition(condition, {"modality": "CT"}) is True


@pytest.mark.parametrize(
    ("op", "value_kwargs", "actual", "expected"),
    [
        ("equals", {"value": "CT"}, "CT", True),
        ("equals", {"value": "CT"}, "MR", False),
        ("not_equals", {"value": "CT"}, "MR", True),
        ("not_equals", {"value": "CT"}, "CT", False),
        ("in", {"values": ["CT", "MR"]}, "CT", True),
        ("in", {"values": ["CT", "MR"]}, "US", False),
    ],
)
def test_operators_against_a_present_field(op, value_kwargs, actual, expected) -> None:
    condition = {"field": "modality", "op": op, **value_kwargs}
    assert routing.evaluate_condition(condition, {"modality": actual}) is expected


def test_all_requires_every_condition() -> None:
    match = {"all": [
        {"field": "modality", "op": "equals", "value": "SR"},
        {"field": "sop_class", "op": "equals", "value": "1.2.840.10008.5.1.4.1.1.88.67"},
    ]}
    runtime_object = {"modality": "SR", "sop_class": "1.2.840.10008.5.1.4.1.1.88.67"}

    assert routing.evaluate_match(match, runtime_object) is True
    assert routing.evaluate_match(match, {**runtime_object, "modality": "CT"}) is False


def test_any_requires_only_one_condition() -> None:
    match = {"any": [
        {"field": "modality", "op": "equals", "value": "CT"},
        {"field": "modality", "op": "equals", "value": "MR"},
    ]}

    assert routing.evaluate_match(match, {"modality": "MR"}) is True
    assert routing.evaluate_match(match, {"modality": "US"}) is False


# ---------------------------------------------------------------------
# evaluate_route(): strukturiertes Ergebnis (ADR 0120, 8.7)
# ---------------------------------------------------------------------

def test_evaluate_route_reports_a_match() -> None:
    route = {"id": "CT-TO-DOSE", "match": {"modality": "CT"}}

    result = routing.evaluate_route(route, {"modality": "CT"})

    assert result == {"matched": True, "route_id": "CT-TO-DOSE"}


def test_evaluate_route_reports_the_failing_field_operator_and_values() -> None:
    """Genau das dosis-bleibt-liegen-Beispiel (ADR 0120, 19): RDSR hat
    Modality SR, die Route filtert auf CT."""
    route = {"id": "CT-TO-DOSE", "match": {"modality": "CT"}}

    result = routing.evaluate_route(route, {"modality": "SR"})

    assert result == {
        "matched": False,
        "route_id": "CT-TO-DOSE",
        "field": "modality",
        "operator": "equals",
        "expected": "CT",
        "actual": "SR",
    }


def test_evaluate_route_with_any_reports_the_first_condition_when_nothing_matches() -> None:
    route = {"id": "R1", "match": {"any": [
        {"field": "modality", "op": "equals", "value": "CT"},
        {"field": "modality", "op": "equals", "value": "MR"},
    ]}}

    result = routing.evaluate_route(route, {"modality": "US"})

    assert result["matched"] is False
    assert result["route_id"] == "R1"


def test_find_matching_routes_includes_disabled_and_non_matching_routes_as_not_matched() -> None:
    host = {"routes": [
        {"id": "enabled-match", "match": {"modality": "CT"}},
        {"id": "enabled-no-match", "match": {"modality": "MR"}},
        {"id": "disabled", "enabled": False, "match": {"modality": "CT"}},
    ]}

    results = routing.find_matching_routes(host, {"modality": "CT"})

    assert {r["route_id"]: r["matched"] for r in results} == {
        "enabled-match": True,
        "enabled-no-match": False,
    }


def test_a_route_without_enabled_defaults_to_true() -> None:
    host = {"routes": [{"id": "r1", "match": {"modality": "CT"}}]}

    results = routing.find_matching_routes(host, {"modality": "CT"})

    assert results == [{"matched": True, "route_id": "r1"}]


def test_enabled_true_is_evaluated() -> None:
    host = {"routes": [{"id": "r1", "enabled": True, "match": {"modality": "CT"}}]}

    results = routing.find_matching_routes(host, {"modality": "CT"})

    assert len(results) == 1


def test_enabled_false_is_skipped_entirely() -> None:
    host = {"routes": [{"id": "r1", "enabled": False, "match": {"modality": "CT"}}]}

    results = routing.find_matching_routes(host, {"modality": "CT"})

    assert results == []


@pytest.mark.parametrize("enabled", ["false", "true", 0, 1, "yes", "no"])
def test_a_non_boolean_enabled_is_a_schema_error_not_a_truthiness_check(enabled) -> None:
    """ADR 0120, Phase-A-Review: routing.py interpretiert keine Strings/
    Zahlen als Wahrheitswert -- kein bool("false") == True."""
    host = {"routes": [{"id": "r1", "enabled": enabled, "match": {"modality": "CT"}}]}

    with pytest.raises(routing.RouteSchemaError):
        routing.find_matching_routes(host, {"modality": "CT"})


def test_find_matching_routes_supports_fan_out_two_routes_can_match_the_same_object() -> None:
    host = {"routes": [
        {"id": "to-dose", "match": {"sop_class": "1.2.840.10008.5.1.4.1.1.88.67"}},
        {"id": "to-ai", "match": {"modality": "CT"}},
    ]}
    runtime_object = {"modality": "CT", "sop_class": "1.2.840.10008.5.1.4.1.1.88.67"}

    results = routing.find_matching_routes(host, runtime_object)

    assert all(r["matched"] for r in results)


# ---------------------------------------------------------------------
# Destination-Aufloesung (ADR 0120, 8.4)
# ---------------------------------------------------------------------

NODE = NodeDefinition(
    slug="routing-test",
    raw={
        "environment": {
            "hosts": [
                {
                    "name": "pacs", "ip": "10.20.0.10",
                    "dicom": {"calling_ae": "RAD-PACS"},
                    "services": [
                        {"id": "pacs-store", "type": "scp", "port": 104, "ae_title": "RAD-ARCHIV"},
                    ],
                    "routes": [
                        {
                            "id": "CT-TO-DOSE",
                            "destination": {"host": "dose-scp", "service": "dose-store"},
                            "match": {"modality": "CT"},
                        },
                    ],
                },
                {
                    "name": "dose-scp", "ip": "10.20.0.30",
                    "services": [
                        {"id": "dose-store", "type": "scp", "port": 104, "ae_title": "DOSE-SCP"},
                    ],
                },
            ],
        },
    },
)


def test_resolve_destination_returns_ip_port_and_both_ae_titles() -> None:
    route = NODE.host("pacs")["routes"][0]

    destination = routing.resolve_destination(NODE, "pacs", route)

    assert destination == {
        "host": "dose-scp",
        "service": "dose-store",
        "ip": "10.20.0.30",
        "port": 104,
        "called_ae": "DOSE-SCP",
        "calling_ae": "RAD-PACS",
    }


def test_resolve_destination_rejects_an_unknown_host() -> None:
    route = {
        "id": "BAD",
        "destination": {"host": "nowhere", "service": "dose-store"},
        "match": {"modality": "CT"},
    }

    with pytest.raises(routing.RouteSchemaError):
        routing.resolve_destination(NODE, "pacs", route)


def test_resolve_destination_rejects_an_unknown_service_on_a_known_host() -> None:
    route = {
        "id": "BAD",
        "destination": {"host": "dose-scp", "service": "nope"},
        "match": {"modality": "CT"},
    }

    with pytest.raises(routing.RouteSchemaError):
        routing.resolve_destination(NODE, "pacs", route)


def test_resolve_destination_rejects_a_bare_host_without_a_service() -> None:
    route = {"id": "BAD", "destination": {"host": "dose-scp"}, "match": {"modality": "CT"}}

    with pytest.raises(routing.RouteSchemaError):
        routing.resolve_destination(NODE, "pacs", route)


# ---------------------------------------------------------------------
# Routing-Depth-Invariante (ADR 0120, 8.11) -- Phase B bereitet sich vor,
# ohne dass es schon einen Job gibt.
# ---------------------------------------------------------------------

def test_can_forward_below_the_default_limit() -> None:
    assert routing.can_forward(0) is True


def test_cannot_forward_at_the_default_limit() -> None:
    assert routing.can_forward(1) is False


def test_can_forward_respects_a_custom_limit() -> None:
    assert routing.can_forward(1, max_routing_depth=2) is True
    assert routing.can_forward(2, max_routing_depth=2) is False
