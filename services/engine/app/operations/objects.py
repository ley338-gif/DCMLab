"""RuntimeObject und Object Presence (ADR 0120, 8.3): ein logisches,
speicherortunabhaengiges simuliertes DICOM-Objekt, getrennt von der
host-lokalen Frage, wo es gerade liegt. Primaerschluessel ist `object_id`
(sitzungslokal, stabil), nicht der Dateiname und nicht die SOP Instance UID
-- beide sind optional und koennen fehlen (Sendeauftrag-Pfad) oder mehrfach
auftreten.

State-Layout (beide Keys leben direkt im Session-`state`-Dict, JSON-
serialisierbar, keine Dataclass-Instanzen):

    state["objects"]        = {object_id: {..RuntimeObject-Felder..}}
    state["stored_objects"] = {host_name: [object_id, ...]}   # Presence

Absichtlich NICHT Teil von RuntimeObject (ADR 0120, 8.3/8.11): `routing_depth`
(gehoert zum spaeteren Job, Phase B) und `source_ae` (host-lokale
Empfangsinformation, siehe die dort skizzierte Presence/Receipt-Erweiterung).
"""

from __future__ import annotations

import hashlib
from typing import Any

# ADR 0120, 8.3: dieselbe Hash-Technik wie rules._study_instance_uid()/
# _series_instance_uid(), unter der im Repo bereits fuer SOP-Instance-Ebene
# etablierten Testwurzel (siehe content/nodes/letztes-glied-fehlt). Niemals
# durch Anhaengen an eine SOP-Class-UID gebildet -- das waere DICOM-technisch
# unzulaessig (ADR 0120, Review-Runde 4).
_SOP_INSTANCE_UID_PREFIX = "1.2.276.0.7230010.3.1.2."


def synthetic_sop_instance_uid(node_slug: str, key: str) -> str:
    digest = hashlib.sha1(f"{node_slug}:sop-instance:{key}".encode()).hexdigest()

    return _SOP_INSTANCE_UID_PREFIX + str(int(digest[:12], 16)).rjust(12, "0")[:12]


def _next_object_id(state: dict[str, Any]) -> str:
    objects = state.setdefault("objects", {})

    return f"obj-{len(objects) + 1:03d}"


def _find_by_filename(state: dict[str, Any], filename: str) -> str | None:
    objects: dict[str, dict[str, Any]] = state.get("objects", {})

    for object_id, obj in objects.items():
        if obj.get("filename") == filename:
            return object_id

    return None


def _find_by_sop_instance_uid(state: dict[str, Any], sop_instance_uid: str) -> str | None:
    objects: dict[str, dict[str, Any]] = state.get("objects", {})

    for object_id, obj in objects.items():
        if obj.get("sop_instance_uid") == sop_instance_uid:
            return object_id

    return None


def resolve_object_from_environment_object(
    state: dict[str, Any], obj: dict[str, Any], *, origin_host: str,
) -> str:
    """Loest ein RuntimeObject fuer ein `environment.objects[]`-Element auf
    (direkter `storescu`-Pfad). Idempotent ueber den Dateinamen: derselbe
    `filename` liefert bei wiederholtem Aufruf immer dasselbe `object_id`,
    statt ein zweites logisches Objekt anzulegen (ADR 0120, 8.3)."""

    filename = obj.get("filename")
    existing = _find_by_filename(state, filename) if filename else None

    if existing is not None:
        return existing

    object_id = _next_object_id(state)
    state["objects"][object_id] = {
        "object_id": object_id,
        "filename": filename,
        "sop_instance_uid": obj.get("sop_instance_uid"),
        "study_uid": obj.get("study_uid"),
        "series_uid": obj.get("series_uid"),
        "sop_class": obj.get("sop_class"),
        "modality": obj.get("modality"),
        "transfer_syntax": obj.get("transfer_syntax"),
        "study_description": obj.get("study_description"),
        "series_description": obj.get("series_description"),
        "origin_host": origin_host,
        "route_history": [],
    }

    return object_id


def resolve_synthetic_object(
    state: dict[str, Any],
    *,
    node_slug: str,
    host_name: str,
    index: int,
    study_uid: str,
    series_uid: str,
    sop_class: str | None = None,
    modality: str | None = None,
    transfer_syntax: str | None = None,
    study_description: str | None = None,
    series_description: str | None = None,
) -> str:
    """Loest ein RuntimeObject fuer ein synthetisiertes `send_study`-Element
    auf (Datensatz-Pfad: `datasets.yml` kennt keine einzelnen Objekte, nur
    einen `file_count`). Idempotent ueber die deterministische SOP Instance
    UID (aus `node_slug`+`host_name`+`index`) statt ueber einen Dateinamen,
    den es hier nicht gibt."""

    sop_instance_uid = synthetic_sop_instance_uid(node_slug, f"{host_name}:{index}")
    existing = _find_by_sop_instance_uid(state, sop_instance_uid)

    if existing is not None:
        return existing

    object_id = _next_object_id(state)
    state["objects"][object_id] = {
        "object_id": object_id,
        "filename": None,
        "sop_instance_uid": sop_instance_uid,
        "study_uid": study_uid,
        "series_uid": series_uid,
        "sop_class": sop_class,
        "modality": modality,
        "transfer_syntax": transfer_syntax,
        "study_description": study_description,
        "series_description": series_description,
        "origin_host": host_name,
        "route_history": [],
    }

    return object_id


def add_presence(state: dict[str, Any], object_id: str, host_name: str) -> None:
    """Object Presence (ADR 0120, 8.3): haengt `object_id` idempotent an
    `state["stored_objects"][host_name]` an. Dasselbe Objekt kann an
    mehreren Hosts gleichzeitig praesent sein (Multi-Hop) -- kein Klon."""

    presence = state.setdefault("stored_objects", {}).setdefault(host_name, [])

    if object_id not in presence:
        presence.append(object_id)


def store_object(
    state: dict[str, Any],
    object_id: str,
    target_host: str,
    *,
    incoming_routing_depth: int = 0,
) -> dict[str, Any]:
    """Zentrale Storage-Operation (ADR 0120, 8.3), gemeinsamer Endpunkt fuer
    beide bisherigen Storage-Pfade (`storescu`, `send_study`): fuegt Presence
    am Zielhost hinzu. `incoming_routing_depth` ist reiner Aufrufkontext (0
    bei Initial Ingest) -- Phase A wertet noch keine Routen automatisch aus
    (das ist Phase B, ADR 0120, 8.5/8.11); der Parameter existiert bereits,
    damit sich der spaetere Aufruf dort nicht mehr aendern muss.
    """

    del incoming_routing_depth  # Phase B: hier wuerde automatisches Routing ansetzen.
    add_presence(state, object_id, target_host)
    objects: dict[str, dict[str, Any]] = state["objects"]

    return objects[object_id]


def has_route_processed(
    state: dict[str, Any], object_id: str, host_name: str, route_id: str,
) -> bool:
    """Duplicate-/Loop-Schutz (ADR 0120, 8.11): eine Route darf dasselbe
    Objekt hoechstens einmal automatisch verarbeiten. Objektbezogen, absichtlich
    unabhaengig von `routing_depth` -- gilt unveraendert bei Fan-out."""

    return [host_name, route_id] in state["objects"][object_id]["route_history"]


def record_route_processed(
    state: dict[str, Any], object_id: str, host_name: str, route_id: str,
) -> None:
    route_history = state["objects"][object_id]["route_history"]
    entry = [host_name, route_id]

    if entry not in route_history:
        route_history.append(entry)
