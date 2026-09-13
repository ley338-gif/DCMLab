"""Feature 1 (P10): C-FIND-Matching gegen vordefinierte Archiv-Records."""

from app.find import dicom_wildcard_match, find_series, find_studies


def test_exact_match_requires_the_exact_string() -> None:
    assert dicom_wildcard_match("MEYER, HANS", "MEYER, HANS") is True
    assert dicom_wildcard_match("MEYER,HANS", "MEYER, HANS") is False


def test_asterisk_matches_any_sequence_including_empty() -> None:
    assert dicom_wildcard_match("MEYER*", "MEYER, HANS") is True
    assert dicom_wildcard_match("MEYER*", "MEYER") is True
    assert dicom_wildcard_match("MEYER*", "SCHMIDT") is False


def test_question_mark_matches_exactly_one_character() -> None:
    assert dicom_wildcard_match("PACS-?", "PACS-1") is True
    assert dicom_wildcard_match("PACS-?", "PACS-12") is False


def test_find_studies_matches_on_patient_id() -> None:
    records = [
        {"patient_id": "MEYER, HANS", "study_description": "MR Kopf"},
        {"patient_id": "SCHMIDT, ANNA", "study_description": "CT Thorax"},
    ]

    assert find_studies(records, {"PatientID": "MEYER, HANS"}) == [records[0]]
    assert find_studies(records, {"PatientID": "MEYER,HANS"}) == []
    assert find_studies(records, {"PatientID": "MEYER*"}) == [records[0]]
    assert find_studies(records, {"PatientID": "*"}) == records


def test_find_studies_ignores_keys_without_a_value() -> None:
    records = [{"patient_id": "MEYER, HANS"}]

    assert find_studies(records, {"PatientID": "MEYER, HANS", "StudyDate": None}) == records


def test_find_studies_ignores_unknown_keys() -> None:
    records = [{"patient_id": "MEYER, HANS"}]

    assert find_studies(records, {"PatientID": "MEYER, HANS", "UnbekannterKey": "x"}) == records


def test_find_studies_disambiguates_look_alike_studies_by_accession_number() -> None:
    """P10.5 (zwillinge): zwei Studies mit identischer Beschreibung, nur die
    Accession Number unterscheidet sie zuverlaessig."""
    older = {
        "patient_id": "4711", "study_description": "CT Thorax nativ",
        "accession_number": "R2026-04471", "study_uid": "1.2.3.old",
    }
    newer = {
        "patient_id": "4711", "study_description": "CT Thorax nativ",
        "accession_number": "R2026-08812", "study_uid": "1.2.3.new",
    }
    records = [older, newer]

    assert find_studies(records, {"PatientID": "4711"}) == records
    assert find_studies(records, {"AccessionNumber": "R2026-08812"}) == [newer]


def test_find_series_requires_the_right_study_uid() -> None:
    records = [
        {
            "study_uid": "1.2.3",
            "series": [{"series_uid": "1.2.3.1", "series_description": "Kopf nativ"}],
        },
    ]

    assert find_series(records, "1.2.3", {"SeriesDescription": "Kopf*"}) == records[0]["series"]
    assert find_series(records, "1.2.3.wrong", {}) == []


def test_find_series_matches_within_the_named_study_only() -> None:
    records = [
        {"study_uid": "1.2.3", "series": [{"series_uid": "a", "series_description": "Kopf"}]},
        {"study_uid": "9.9.9", "series": [{"series_uid": "b", "series_description": "Kopf"}]},
    ]

    result = find_series(records, "1.2.3", {"SeriesDescription": "Kopf"})

    assert result == [{"series_uid": "a", "series_description": "Kopf"}]
