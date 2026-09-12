"""Liest content/datasets.yml (Abschnitt 4.6) -- der Orchestrator liest nur,
er erzeugt nie eigene Datensatz-Fakten."""

from __future__ import annotations

from functools import lru_cache
from pathlib import Path
from typing import Any, TypedDict

import yaml

from app.config import settings


class DatasetParams(TypedDict):
    patient: str
    patient_id: str
    study: str
    series: list[str]
    file_count: int


class DatasetNotFoundError(Exception):
    pass


@lru_cache(maxsize=1)
def _all(content_path: str) -> dict[str, Any]:
    path = Path(content_path) / "datasets.yml"

    if not path.is_file():
        return {}

    return yaml.safe_load(path.read_text(encoding="utf-8")) or {}


def load_dataset_params(slug: str) -> DatasetParams:
    dataset = _all(settings.content_path).get(slug)

    if dataset is None:
        raise DatasetNotFoundError(slug)

    series = dataset["series"]
    if isinstance(series, str):
        series = [series]

    return {
        "patient": dataset["patient"],
        "patient_id": dataset["patient_id"],
        "study": dataset["study"],
        "series": series,
        "file_count": dataset["file_count"],
    }
