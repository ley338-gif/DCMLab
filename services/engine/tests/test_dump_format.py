"""Ausgabeformat von DCMTK (app/dump.py). Die erwarteten Zeilen sind
Zeichen fuer Zeichen von `dcmdump` aus DCMTK 3.6.7 (Toolbox-Image der
Spielwiese) abgeschrieben, nicht aus der Implementierung abgeleitet.
"""

from app import dump


def test_short_value_is_padded_to_column_56() -> None:
    line = dump.element_line("0008,0060", "CS", "CT", "Modality")

    assert line == "(0008,0060) CS [CT]                                     #   2, 1 Modality"


def test_tag_hex_digits_are_lowercase_and_uid_length_is_even() -> None:
    line = dump.element_line(
        "0020,000D", "UI", "1.2.276.0.7230010.3.1.4.541902387012", "StudyInstanceUID",
    )

    assert line == (
        "(0020,000d) UI [1.2.276.0.7230010.3.1.4.541902387012]   #  36, 1 StudyInstanceUID"
    )


def test_odd_length_value_is_padded_to_an_even_length() -> None:
    line = dump.element_line("0010,0020", "LO", "MEYER, HANS", "PatientID")

    assert line == "(0010,0020) LO [MEYER, HANS]                            #  12, 1 PatientID"


def test_empty_value_has_no_value_available_and_zero_length() -> None:
    line = dump.element_line("0010,0010", "PN", "", "PatientName")

    assert line == "(0010,0010) PN (no value available)                     #   0, 0 PatientName"


def test_well_known_uid_is_shown_by_name() -> None:
    line = dump.element_line("0008,0016", "UI", "1.2.840.10008.5.1.4.1.1.2", "SOPClassUID")

    assert line == "(0008,0016) UI =CTImageStorage                          #  26, 1 SOPClassUID"


def test_well_known_uid_as_number_with_minus_un() -> None:
    line = dump.element_line(
        "0008,0016", "UI", "1.2.840.10008.5.1.4.1.1.2.1", "SOPClassUID", map_uid_names=False,
    )

    assert line == "(0008,0016) UI [1.2.840.10008.5.1.4.1.1.2.1]            #  28, 1 SOPClassUID"


def test_long_content_gets_a_single_space_before_the_hash() -> None:
    line = dump.element_line("0008,0016", "UI", "1.2.840.10008.1.2.4.70", "SOPClassUID")

    assert line == (
        "(0008,0016) UI =JPEGLossless:Non-hierarchical-1stOrderPrediction #  22, 1 SOPClassUID"
    )


def test_sequence_with_one_item_matches_dcmdump() -> None:
    lines = dump.sequence_lines("0040,0100", "ScheduledProcedureStepSequence", [[
        ("0040,0001", "AE", "CT5-RAUM3", "ScheduledStationAETitle"),
        ("0040,0002", "DA", "20260913", "ScheduledProcedureStepStartDate"),
        ("0008,0060", "CS", "CT", "Modality"),
    ]])

    assert lines == [
        "(0040,0100) SQ (Sequence with explicit length #=1)      #  52, 1 ScheduledProcedureStepSequence",  # noqa: E501
        "  (fffe,e000) na (Item with explicit length #=3)          #  44, 1 Item",
        "    (0008,0060) CS [CT]                                     #   2, 1 Modality",
        "    (0040,0001) AE [CT5-RAUM3]                              #  10, 1 ScheduledStationAETitle",  # noqa: E501
        "    (0040,0002) DA [20260913]                               #   8, 1 ScheduledProcedureStepStartDate",  # noqa: E501
        "  (fffe,e00d) na (ItemDelimitationItem for re-encoding)   #   0, 0 ItemDelimitationItem",
        "(fffe,e0dd) na (SequenceDelimitationItem for re-encod.) #   0, 0 SequenceDelimitationItem",
    ]
