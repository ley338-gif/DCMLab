"""C-FIND-Matching gegen vordefinierte Archiv-Datensaetze (P10, Engine-Feature
"C-FIND simulieren"). Reine Funktionen, kein Zustand -- ergaenzt das
Association-Regelwerk aus `rules.py` um echtes Query/Matching auf STUDY- und
SERIES-Ebene, statt nur "wurde vorher etwas gesendet".

Ein Archiv-Host kann `records` in seiner node.yml-Definition tragen (siehe
content-schema.md-Erweiterung P10): eine Liste bereits im Archiv vorhandener
Studies, unabhaengig vom Sende-Mechanismus aus P4-P9. Nodes ohne `records`
verhalten sich unveraendert (siehe `rules._exec_findscu`).
"""

from __future__ import annotations

import re
from typing import Any

STUDY_FIELD_MAP = {
    "PatientID": "patient_id",
    "PatientName": "patient_name",
    "StudyDescription": "study_description",
    "StudyInstanceUID": "study_uid",
    "StudyDate": "study_date",
}

SERIES_FIELD_MAP = {
    "SeriesInstanceUID": "series_uid",
    "SeriesDescription": "series_description",
}


def dicom_wildcard_match(pattern: str, value: str) -> bool:
    """DICOM-Wildcard-Matching (PS3.4 C.2.2.2.4): `*` steht fuer eine beliebige
    Zeichenfolge (auch leer), `?` fuer genau ein Zeichen. Ohne Wildcard ist der
    Vergleich zeichengenau -- wie AE Titles in Abschnitt 5.3, keine Toleranz
    fuer Gross-/Kleinschreibung oder abweichende Leerzeichen.
    """
    if "*" not in pattern and "?" not in pattern:
        return pattern == value

    regex = "^" + re.escape(pattern).replace(r"\*", ".*").replace(r"\?", ".") + "$"

    return re.match(regex, value) is not None


def _matches(
    record: dict[str, Any], keys: dict[str, str | None], field_map: dict[str, str],
) -> bool:
    for key, pattern in keys.items():
        if pattern is None or pattern == "":
            continue

        field = field_map.get(key)

        if field is None:
            continue

        if not dicom_wildcard_match(pattern, str(record.get(field, ""))):
            return False

    return True


def find_studies(
    records: list[dict[str, Any]], keys: dict[str, str | None],
) -> list[dict[str, Any]]:
    """Matcht STUDY-Ebene gegen die Query-Keys einer C-FIND-Anfrage."""
    return [record for record in records if _matches(record, keys, STUDY_FIELD_MAP)]


def find_series(
    records: list[dict[str, Any]], study_uid: str, keys: dict[str, str | None],
) -> list[dict[str, Any]]:
    """Matcht SERIES-Ebene: erst die Study per UID finden, dann ihre Serien."""
    study = next((r for r in records if r.get("study_uid") == study_uid), None)

    if study is None:
        return []

    series = study.get("series", [])

    return [s for s in series if _matches(s, keys, SERIES_FIELD_MAP)]
