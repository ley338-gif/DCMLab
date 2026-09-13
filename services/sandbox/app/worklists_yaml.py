"""Liest content/worklists.yml (Abschnitt 6, Lektion 4.7) -- der Orchestrator
liest nur, er erzeugt nie eigene Worklist-Fakten."""

from __future__ import annotations

from functools import lru_cache
from pathlib import Path
from typing import Any, TypedDict

import yaml

from app.config import settings


class WorklistParams(TypedDict):
    patient: str
    patient_id: str
    accession_number: str
    requested_procedure_description: str
    referring_physician: str
    modality: str
    scheduled_station_ae_title: str
    scheduled_station_name: str
    scheduled_procedure_step_description: str
    scheduled_procedure_step_id: str


class WorklistNotFoundError(Exception):
    pass


@lru_cache(maxsize=1)
def _all(content_path: str) -> dict[str, Any]:
    path = Path(content_path) / "worklists.yml"

    if not path.is_file():
        return {}

    return yaml.safe_load(path.read_text(encoding="utf-8")) or {}


def load_worklist_params(slug: str) -> WorklistParams:
    worklist = _all(settings.content_path).get(slug)

    if worklist is None:
        raise WorklistNotFoundError(slug)

    return {
        "patient": worklist["patient"],
        "patient_id": worklist["patient_id"],
        "accession_number": worklist["accession_number"],
        "requested_procedure_description": worklist["requested_procedure_description"],
        "referring_physician": worklist["referring_physician"],
        "modality": worklist["modality"],
        "scheduled_station_ae_title": worklist["scheduled_station_ae_title"],
        "scheduled_station_name": worklist["scheduled_station_name"],
        "scheduled_procedure_step_description": worklist["scheduled_procedure_step_description"],
        "scheduled_procedure_step_id": worklist["scheduled_procedure_step_id"],
    }
