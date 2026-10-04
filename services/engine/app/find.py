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
    "AccessionNumber": "accession_number",
}

SERIES_FIELD_MAP = {
    "SeriesInstanceUID": "series_uid",
    "SeriesDescription": "series_description",
}

# Modality Worklist (PS3.4 Annex K): Patient- und Auftragsattribute stehen auf
# oberster Ebene, Station, Termin und Modalitaet im Item der Scheduled
# Procedure Step Sequence (0040,0100) (PS3.4 Tabelle K.6-1). Ein Eintrag in
# node.yml traegt alle Felder flach; `split_worklist_keys` ordnet die Keys der
# Anfrage den beiden Ebenen zu.
WORKLIST_TOP_FIELD_MAP = {
    "PatientID": "patient_id",
    "PatientName": "patient_name",
    "AccessionNumber": "accession_number",
}

WORKLIST_SPS_FIELD_MAP = {
    "ScheduledStationAETitle": "scheduled_station_ae_title",
    "ScheduledProcedureStepStartDate": "scheduled_procedure_step_start_date",
    "Modality": "modality",
}

WORKLIST_FIELD_MAP = {**WORKLIST_TOP_FIELD_MAP, **WORKLIST_SPS_FIELD_MAP}

SPS_SEQUENCE_KEYS = ("ScheduledProcedureStepSequence", "(0040,0100)", "0040,0100")

SPS_TAG_KEYWORDS = {
    "0040,0001": "ScheduledStationAETitle",
    "0040,0002": "ScheduledProcedureStepStartDate",
    "0008,0060": "Modality",
}

# `ScheduledProcedureStepSequence[0].Modality`, `(0040,0100)[0].(0008,0060)`
_SPS_PATH = re.compile(
    r"^(?:ScheduledProcedureStepSequence|\(0040,0100\)|0040,0100)\[0\]\."
    r"(?:\((?P<tag>[0-9A-Fa-f]{4},[0-9A-Fa-f]{4})\)|(?P<keyword>\w+))$",
)


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
    """Matcht STUDY-Ebene gegen die Query-Keys einer C-FIND-Anfrage.

    Auf STUDY-Ebene ist jeder Treffer eine Study: Ein Record ohne `study_uid`
    (etwa ein angelegter Patient ohne Untersuchung) passt hier nie -- eine
    leere Registrierung zeigt erst eine Abfrage auf PATIENT-Ebene.
    """
    return [
        record for record in records
        if record.get("study_uid") and _matches(record, keys, STUDY_FIELD_MAP)
    ]


def find_series(
    records: list[dict[str, Any]], study_uid: str, keys: dict[str, str | None],
) -> list[dict[str, Any]]:
    """Matcht SERIES-Ebene: erst die Study per UID finden, dann ihre Serien."""
    study = next((r for r in records if r.get("study_uid") == study_uid), None)

    if study is None:
        return []

    series = study.get("series", [])

    return [s for s in series if _matches(s, keys, SERIES_FIELD_MAP)]


def find_worklist(
    entries: list[dict[str, Any]], keys: dict[str, str | None],
) -> list[dict[str, Any]]:
    """Matcht geplante Verfahren (Scheduled Procedure Steps) gegen die
    Query-Keys einer Modality-Worklist-C-FIND-Anfrage."""
    top, sps, _ = split_worklist_keys(keys)

    return [
        entry for entry in entries
        if _matches(entry, {**top, **sps}, WORKLIST_FIELD_MAP)
    ]


def split_worklist_keys(
    keys: dict[str, str | None],
) -> tuple[dict[str, str | None], dict[str, str | None], bool]:
    """Teilt die `-k`-Keys einer Worklist-Anfrage in Keys der obersten Ebene
    und Keys im Item der Scheduled Procedure Step Sequence.

    Erkannt werden die Pfade, die DCMTK und pynetdicom fuer `-k` kennen
    (`ScheduledProcedureStepSequence[0].Modality=CT`, auch mit Tags wie
    `(0040,0100)[0].(0008,0060)=CT`), und die Sequenz ohne Item
    (`-k ScheduledProcedureStepSequence`: alle Attribute des Items zurueck).
    Flach angegebene SPS-Keys (`-k ScheduledStationAETitle=CT01`) zaehlen
    weiter als SPS-Keys, damit bisherige Loesungswege gueltig bleiben; die
    Antwort zeigt sie trotzdem dort, wo sie hingehoeren.

    Rueckgabe: (Keys oberste Ebene, SPS-Keys, ganze Sequenz angefragt).
    """
    top: dict[str, str | None] = {}
    sps: dict[str, str | None] = {}
    sps_all = False

    for key, value in keys.items():
        if key in SPS_SEQUENCE_KEYS:
            sps_all = True
            continue

        path = _SPS_PATH.match(key)
        if path is not None:
            tag = path.group("tag")
            sub_key = SPS_TAG_KEYWORDS.get(tag.lower(), tag) if tag else path.group("keyword")
            sps[sub_key] = value
            continue

        if key in WORKLIST_SPS_FIELD_MAP:
            sps[key] = value
            continue

        top[key] = value

    return top, sps, sps_all
