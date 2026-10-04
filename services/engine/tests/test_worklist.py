"""Feature 6 (P10): Modality Worklist (PS3.4 Annex K, SOP Class
1.2.840.10008.5.1.4.31). Reales `findscu -W`-Flag statt QueryRetrieveLevel,
dasselbe Wildcard-Matching wie STUDY/SERIES (app/find.py), gegen geplante
Verfahren statt vorhandener Studies.
"""

from app import dump, rules
from app.content import NodeDefinition
from app.find import find_worklist, split_worklist_keys

ENTRIES = [
    {
        "patient_id": "4711",
        "patient_name": "MEYER^HANS",
        "accession_number": "A100",
        "scheduled_station_ae_title": "CT5-RAUM3",
        "scheduled_procedure_step_start_date": "20260913",
        "modality": "CT",
    },
    {
        "patient_id": "4712",
        "patient_name": "SCHMIDT^ANNA",
        "accession_number": "A101",
        "scheduled_station_ae_title": "CT5-RAUM3",
        "scheduled_procedure_step_start_date": "20260913",
        "modality": "CT",
    },
]


def test_find_worklist_matches_on_scheduled_station_ae_title() -> None:
    assert find_worklist(ENTRIES, {"ScheduledStationAETitle": "CT5-RAUM3"}) == ENTRIES
    assert find_worklist(ENTRIES, {"ScheduledStationAETitle": "CT-5"}) == []


def test_find_worklist_matches_on_patient_name_wildcard() -> None:
    assert find_worklist(ENTRIES, {"PatientName": "MEYER*"}) == [ENTRIES[0]]


NODE = NodeDefinition(
    slug="worklist-test",
    raw={
        "environment": {
            "hosts": [
                {"name": "workstation", "ip": "10.0.0.50", "role": "shell"},
                {
                    "name": "archive",
                    "ip": "10.0.0.10",
                    "services": [{"port": 104, "ae_title": "TEST-ARCHIV", "accepts": []}],
                    "worklist": ENTRIES,
                },
            ],
            "known_calling_aets": ["WORKSTATION"],
            "tools": ["findscu"],
        },
    },
)


def fresh_state() -> dict:
    return rules.initial_state(NODE)


def test_findscu_dash_w_queries_the_worklist_not_studies() -> None:
    state = fresh_state()

    result = rules.exec_command(
        NODE, state, "workstation",
        "findscu -W -k ScheduledStationAETitle=CT5-RAUM3 "
        "-aet WORKSTATION -aec TEST-ARCHIV 10.0.0.10 104",
    )

    assert "I: Number of Matches: 2" in result.stdout
    # PatientName war nicht angefragt (PS3.4 C.4.1.1.3.2), die Station steht
    # im Item der Scheduled Procedure Step Sequence (PS3.4 Tabelle K.6-1).
    assert "PatientName" not in result.stdout
    assert "I: (0040,0100) SQ (Sequence with explicit length #=1)" in result.stdout
    assert "I:     " + dump.element_line(
        "0040,0001", "AE", "CT5-RAUM3", "ScheduledStationAETitle",
    ) in result.stdout


def test_findscu_dash_w_with_wrong_station_ae_title_returns_empty() -> None:
    """Genau das Fehlerbild aus Lektion 4.7: ein plausibler, aber falscher
    Modality-Filter liefert eine gueltige, leere Antwort -- kein Fehler."""
    state = fresh_state()

    result = rules.exec_command(
        NODE, state, "workstation",
        "findscu -W -k ScheduledStationAETitle=CT-5 "
        "-aet WORKSTATION -aec TEST-ARCHIV 10.0.0.10 104",
    )

    assert result.stdout == "I: Number of Matches: 0"
    assert result.exit_code == 0


def test_findscu_dash_s_is_unaffected_by_worklist_entries() -> None:
    """-S fragt weiterhin STUDY/SERIES ab, nie die Worklist -- beide
    Informationsmodelle bleiben getrennt (Feature 6 aendert Feature 1 nicht)."""
    state = fresh_state()

    result = rules.exec_command(
        NODE, state, "workstation",
        "findscu -S -k QueryRetrieveLevel=STUDY -k PatientID=4711 "
        "-aet WORKSTATION -aec TEST-ARCHIV 10.0.0.10 104",
    )

    assert result.stdout == "I: Number of Matches: 0"


def test_nodes_without_worklist_keep_the_pre_p10_11_behaviour() -> None:
    node = NodeDefinition(
        slug="no-worklist",
        raw={
            "environment": {
                "hosts": [
                    {"name": "workstation", "ip": "10.0.0.50", "role": "shell"},
                    {
                        "name": "archive", "ip": "10.0.0.10",
                        "services": [{"port": 104, "ae_title": "ARCHIV", "accepts": []}],
                    },
                ],
                "known_calling_aets": ["WORKSTATION"],
                "tools": ["findscu"],
            },
        },
    )
    state = rules.initial_state(node)

    result = rules.exec_command(
        node, state, "workstation",
        "findscu -W -k ScheduledStationAETitle=CT-5 "
        "-aet WORKSTATION -aec ARCHIV 10.0.0.10 104",
    )

    assert result.stdout == "I: Number of Matches: 0"


def test_split_worklist_keys_knows_dcmtk_paths_tags_and_flat_keys() -> None:
    top, sps, sps_all = split_worklist_keys({
        "PatientName": "",
        "ScheduledProcedureStepSequence[0].ScheduledStationAETitle": "CT01",
        "(0040,0100)[0].(0008,0060)": "CT",
        "ScheduledProcedureStepStartDate": "20260913",
    })

    assert top == {"PatientName": ""}
    assert sps == {
        "ScheduledStationAETitle": "CT01",
        "Modality": "CT",
        "ScheduledProcedureStepStartDate": "20260913",
    }
    assert sps_all is False


def test_findscu_dash_w_with_sequence_path_matches_inside_the_item() -> None:
    state = fresh_state()

    result = rules.exec_command(
        NODE, state, "workstation",
        "findscu -W -k PatientName=MEYER* "
        "-k ScheduledProcedureStepSequence[0].ScheduledStationAETitle=CT5-RAUM3 "
        "-aet WORKSTATION -aec TEST-ARCHIV 10.0.0.10 104",
    )

    assert "I: Number of Matches: 1" in result.stdout
    assert "I: " + dump.element_line(
        "0010,0010", "PN", "MEYER^HANS", "PatientName",
    ) in result.stdout
    assert "PatientID" not in result.stdout
    assert "Modality" not in result.stdout


def test_findscu_dash_w_with_the_empty_sequence_returns_the_whole_item() -> None:
    state = fresh_state()

    result = rules.exec_command(
        NODE, state, "workstation",
        "findscu -W -k PatientName=SCHMIDT* -k ScheduledProcedureStepSequence "
        "-aet WORKSTATION -aec TEST-ARCHIV 10.0.0.10 104",
    )

    assert "I: Number of Matches: 1" in result.stdout
    assert "I:   (fffe,e000) na (Item with explicit length #=3)" in result.stdout
    assert "ScheduledProcedureStepStartDate" in result.stdout
    assert "I:     " + dump.element_line("0008,0060", "CS", "CT", "Modality") in result.stdout
