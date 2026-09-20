"""Prueft die echten, ausgelieferten Nodes (nicht die Testfixture) gegen das
Regelwerk -- speziell fuer P6 (Wrong Door): "ausschliesslich aus dem Schema
erzeugt, ohne eine Zeile Sonderlogik". Wenn dieser Test nur mit
Node-spezifischem Engine-Code gruen wird, ist das ein Schema-Mangel
(Abschnitt 10, P6-DoD).
"""

import copy
from pathlib import Path

import pytest

from app import content, rules

REAL_CONTENT = Path(__file__).parent.parent.parent.parent / "content"


@pytest.fixture(autouse=True)
def _use_real_content(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.setattr(content.settings, "content_path", str(REAL_CONTENT))
    content._datasets_cached.cache_clear()


def test_silent_ct_is_solvable_from_the_real_content() -> None:
    node = content.load_node("silent-ct")
    state = rules.initial_state(node)

    rejected = rules.check_association(node, state, "CT_RAUM3", "PACS_ARCHIV", "10.20.0.10", 104)
    assert rejected.message == rules.MSG_CALLED_AE_REJECTED

    ok = rules.set_config(node, state, "ct-console", "remote_ae", "PACS-ARCHIV")
    assert ok is None

    result = rules.trigger_action(node, state, "ct-console", "send_study")
    assert result.events[-1]["type"] == "store_completed"
    assert state["bestand"]["archive"] == {"studies": 1, "series": 1, "instances": 3}

    assert rules.check_flag(node, state, "Thorax 5.0 B31f") is True


def test_wrong_door_is_solvable_from_the_real_content_with_zero_special_casing() -> None:
    """Dasselbe Regelwerk, derselbe Code -- nur die YAML-Werte unterscheiden
    sich von silent-ct (Calling- statt Called-Seite)."""

    node = content.load_node("wrong-door")
    state = rules.initial_state(node)

    # Der eingebaute Fehler liegt diesmal auf der Calling-Seite.
    rejected = rules.check_association(node, state, "MR-2", "PACS-ARCHIV", "10.20.0.10", 104)
    assert rejected.message == rules.MSG_CALLING_AE_REJECTED

    ok = rules.set_config(node, state, "mr-console", "local_ae", "MR_2")
    assert ok is None

    result = rules.trigger_action(node, state, "mr-console", "send_study")
    assert result.events[-1]["type"] == "store_completed"
    assert state["bestand"]["archive"] == {"studies": 1, "series": 1, "instances": 1}

    assert rules.check_flag(node, state, "Thorax 1.0 B70f") is True


def test_wrong_door_and_silent_ct_have_different_flags() -> None:
    silent_ct = content.load_node("silent-ct")
    wrong_door = content.load_node("wrong-door")

    assert silent_ct.flag_hash != wrong_door.flag_hash


def test_c_find_mismatch_is_solvable_from_the_real_content() -> None:
    """P10, Feature 1: die Study liegt von Anfang an im Archiv (`records`),
    keine Sendeaktion noetig. Die falsch abgetippte Patient ID liefert null
    Treffer, das Wildcard-Muster deckt die echte auf."""

    node = content.load_node("c-find-mismatch")
    state = rules.initial_state(node)

    wrong = rules.exec_command(
        node, state, "workstation",
        "findscu -S -k QueryRetrieveLevel=STUDY -k PatientID=MEYER,HANS "
        "-aet DCMLAB-WS -aec KLINIK-ARCHIV 10.50.0.10 104",
    )
    assert wrong.stdout == "I: Number of Matches: 0"

    found = rules.exec_command(
        node, state, "workstation",
        "findscu -S -k QueryRetrieveLevel=STUDY -k PatientID=MEYER* "
        "-k PatientName -k StudyDescription -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.50.0.10 104",
    )
    assert "I: (0010,0020) LO [MEYER, HANS]  # xx, 1 PatientID" in found.stdout
    assert "I: Number of Matches: 1" in found.stdout


def test_oversized_image_is_solvable_from_the_real_content() -> None:
    """P10, Feature 2: das grosse Objekt wird mit dem echten DIMSE-Status
    0xA7xx abgelehnt, das kleine kommt an -- dessen Groesse ist das Flag."""

    node = content.load_node("oversized-image")
    state = rules.initial_state(node)

    big = rules.exec_command(
        node, state, "workstation",
        "storescu -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.60.0.10 104 gross.dcm",
    )
    assert big.exit_code == 1
    assert "0xa700" in big.stderr
    assert state["bestand"]["archive"] == {"studies": 0, "series": 0, "instances": 0}

    small = rules.exec_command(
        node, state, "workstation",
        "storescu -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.60.0.10 104 klein.dcm",
    )
    assert small.exit_code == 0
    assert state["bestand"]["archive"] == {"studies": 1, "series": 1, "instances": 1}

    assert rules.check_flag(node, state, "524288") is True


def test_teiltransfer_is_solvable_from_the_real_content() -> None:
    """P10, Feature 5: die beiden CT-Schichten kommen an, der Screenshot
    wird wegen einer nicht registrierten SOP Class abgelehnt -- dessen
    SOP Class UID ist das Flag. dcmdump zeigt sie schon vor dem Senden."""

    node = content.load_node("teiltransfer")
    state = rules.initial_state(node)

    dump = rules.exec_command(node, state, "workstation", "dcmdump screenshot.dcm")
    assert "1.2.840.10008.5.1.4.1.1.7" in dump.stdout

    ct_1 = rules.exec_command(
        node, state, "workstation",
        "storescu -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.65.0.10 104 schicht-01.dcm",
    )
    assert ct_1.exit_code == 0

    ct_2 = rules.exec_command(
        node, state, "workstation",
        "storescu -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.65.0.10 104 schicht-02.dcm",
    )
    assert ct_2.exit_code == 0

    screenshot = rules.exec_command(
        node, state, "workstation",
        "storescu -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.65.0.10 104 screenshot.dcm",
    )
    assert screenshot.exit_code == 1
    assert "abstract-syntax-not-supported" in screenshot.stderr

    assert state["bestand"]["archive"] == {"studies": 1, "series": 1, "instances": 2}
    assert rules.check_flag(node, state, "1.2.840.10008.5.1.4.1.1.7") is True


def test_syntax_negotiation_fails_is_solvable_from_the_real_content() -> None:
    """P10, Feature 3: JPEG 2000 wird abgelehnt (Archiv kennt nur Implicit
    VR Little Endian), nach der Korrektur kommt die Study an."""

    node = content.load_node("syntax-negotiation-fails")
    state = rules.initial_state(node)

    wrong = rules.trigger_action(node, state, "ct-3", "send_study")
    assert wrong.events[-1]["type"] == "presentation_context_rejected"
    assert state["bestand"]["archive"] == {"studies": 0, "series": 0, "instances": 0}

    rules.set_config(node, state, "ct-3", "transfer_syntax", "1.2.840.10008.1.2")
    fixed = rules.trigger_action(node, state, "ct-3", "send_study")
    assert fixed.events[-1]["type"] == "store_completed"
    assert state["bestand"]["archive"]["studies"] == 1

    assert rules.check_flag(node, state, "1.2.840.10008.1.2") is True


def test_verbindung_ohne_bild_is_solvable_from_the_real_content() -> None:
    """P10, Feature 4: Enhanced CT Image Storage wird abgelehnt (Archiv
    kennt nur klassisches CT Image Storage), nach der Korrektur kommt
    die Study an. C-ECHO bleibt davon unberuehrt (andere Aushandlung)."""

    node = content.load_node("verbindung-ohne-bild")
    state = rules.initial_state(node)

    echo_cmd = "echoscu -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.90.0.10 104"
    echo = rules.exec_command(node, state, "workstation", echo_cmd)
    assert echo.exit_code == 0

    wrong = rules.trigger_action(node, state, "ct-9", "send_study")
    assert wrong.events[-1]["type"] == "presentation_context_rejected"
    assert wrong.events[-1]["reason"] == "abstract_syntax_not_supported"
    assert state["bestand"]["archive"] == {"studies": 0, "series": 0, "instances": 0}

    rules.set_config(node, state, "ct-9", "sop_class", "1.2.840.10008.5.1.4.1.1.2")
    fixed = rules.trigger_action(node, state, "ct-9", "send_study")
    assert fixed.events[-1]["type"] == "store_completed"
    assert state["bestand"]["archive"]["studies"] == 1

    assert rules.check_flag(node, state, "1.2.840.10008.5.1.4.1.1.2") is True


def test_patient_merge_discovery_is_solvable_from_the_real_content() -> None:
    """P10, keine neue Engine-Logik noetig: derselbe records-Mechanismus
    aus Feature 1 (c-find-mismatch) traegt zwei Registrierungen derselben
    Person unter zwei Patient IDs, nur eine davon hat eine echte Study."""

    node = content.load_node("patient-merge-discovery")
    state = rules.initial_state(node)

    from_referral = rules.exec_command(
        node, state, "workstation",
        "findscu -S -k QueryRetrieveLevel=STUDY -k PatientID=00123 "
        "-aet DCMLAB-WS -aec KLINIK-ARCHIV 10.80.0.10 104",
    )
    assert "StudyInstanceUID" not in from_referral.stdout
    assert "I: Number of Matches: 1" in from_referral.stdout

    by_name = rules.exec_command(
        node, state, "workstation",
        "findscu -S -k QueryRetrieveLevel=STUDY -k PatientName=WEBER* -k PatientID "
        "-k StudyInstanceUID -k StudyDescription -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.80.0.10 104",
    )
    assert "I: Number of Matches: 2" in by_name.stdout
    assert "I: (0010,0020) LO [000123]  # xx, 1 PatientID" in by_name.stdout
    assert "I: (0008,1030) LO [CT Abdomen nativ]  # xx, 1 StudyDescription" in by_name.stdout

    assert rules.check_flag(node, state, "000123") is True
    assert rules.check_flag(node, state, "00123") is False


def test_zwillinge_is_solvable_from_the_real_content() -> None:
    """P10.5, keine neue Engine-Logik noetig: zwei Studies derselben
    Patientin mit identischer Beschreibung, nur Accession Number und UID
    unterscheiden sie -- dieselbe records-Mechanik aus Feature 1."""

    node = content.load_node("zwillinge")
    state = rules.initial_state(node)

    by_patient = rules.exec_command(
        node, state, "workstation",
        "findscu -S -k QueryRetrieveLevel=STUDY -k PatientID=4711 "
        "-k StudyInstanceUID -k StudyDescription -k AccessionNumber "
        "-aet DCMLAB-WS -aec KLINIK-ARCHIV 10.90.0.10 104",
    )
    assert "I: Number of Matches: 2" in by_patient.stdout

    by_accession = rules.exec_command(
        node, state, "workstation",
        "findscu -S -k QueryRetrieveLevel=STUDY -k AccessionNumber=R2026-08812 "
        "-k StudyInstanceUID -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.90.0.10 104",
    )
    assert "I: Number of Matches: 1" in by_accession.stdout
    assert "1.2.276.0.7230010.3.1.4.541902387012" in by_accession.stdout

    assert rules.check_flag(node, state, "1.2.276.0.7230010.3.1.4.541902387012") is True
    assert rules.check_flag(node, state, "1.2.276.0.7230010.3.1.4.541902387011") is False


def test_mitgehoert_is_solvable_from_the_real_content() -> None:
    """P10.6: drei unabhaengige Fehler nacheinander (Host, Port, Transfer
    Syntax) -- jede Stufe zeigt eine andere Verhandlungsebene im Log."""

    node = content.load_node("mitgehoert")
    state = rules.initial_state(node)

    host_stage = rules.trigger_action(node, state, "us-2", "send_study")
    assert host_stage.events[-1]["reason"] == "host_unreachable"

    rules.set_config(node, state, "us-2", "remote_host", "10.100.0.10")
    port_stage = rules.trigger_action(node, state, "us-2", "send_study")
    assert port_stage.events[-1]["reason"] == "wrong_port"

    rules.set_config(node, state, "us-2", "remote_port", 104)
    ts_stage = rules.trigger_action(node, state, "us-2", "send_study")
    assert ts_stage.events[-1]["type"] == "presentation_context_rejected"
    assert state["bestand"]["archive"] == {"studies": 0, "series": 0, "instances": 0}

    rules.set_config(node, state, "us-2", "transfer_syntax", "1.2.840.10008.1.2.1")
    success = rules.trigger_action(node, state, "us-2", "send_study")
    assert success.events[-1]["type"] == "store_completed"
    assert state["bestand"]["archive"] == {"studies": 1, "series": 1, "instances": 1}

    assert rules.check_flag(node, state, "1.2.840.10008.1.2.1") is True


def test_worklist_query_empty_is_solvable_from_the_real_content() -> None:
    """P10, Feature 6: der plausible, aber falsche Scheduled Station AE
    Title liefert null Treffer -- ohne den Filter sind alle fuenf
    geplanten Verfahren sichtbar, mit dem korrigierten Filter ebenfalls."""

    node = content.load_node("worklist-query-empty")
    state = rules.initial_state(node)

    wrong = rules.exec_command(
        node, state, "workstation",
        "findscu -W -k PatientName= -k ScheduledStationAETitle=CT-5 "
        "-k ScheduledProcedureStepStartDate=20260913 "
        "-aet CT-5 -aec RIS-BROKER 10.70.0.10 104",
    )
    assert wrong.stdout == "I: Number of Matches: 0"

    unfiltered = rules.exec_command(
        node, state, "workstation",
        "findscu -W -k PatientName= -k ScheduledStationAETitle= "
        "-k ScheduledProcedureStepStartDate=20260913 "
        "-aet CT-5 -aec RIS-BROKER 10.70.0.10 104",
    )
    assert "I: Number of Matches: 5" in unfiltered.stdout
    assert "I: (0040,0001) AE [CT5-RAUM3]  # xx, 1 ScheduledStationAETitle" in unfiltered.stdout

    fixed = rules.exec_command(
        node, state, "workstation",
        "findscu -W -k PatientName= -k ScheduledStationAETitle=CT5-RAUM3 "
        "-k ScheduledProcedureStepStartDate=20260913 "
        "-aet CT-5 -aec RIS-BROKER 10.70.0.10 104",
    )
    assert "I: Number of Matches: 5" in fixed.stdout

    assert rules.check_flag(node, state, "CT5-RAUM3") is True


def test_halbe_sache_is_solvable_from_the_real_content() -> None:
    """PR #158: echter Partial-C-STORE-Incident -- drei CT-Schichten kommen
    an, der automatisch miterzeugte Dosisbericht (RDSR) wird wegen einer
    nicht registrierten SOP Class abgelehnt (Result 3, nicht 4 -- die
    Transfer Syntax unterscheidet sich absichtlich von den CT-Schichten,
    genau das beweist fuer sich genommen nichts ueber die Ursache).
    Flag ist der Dateiname des abgelehnten Objekts, kein Wert aus der
    Fehlermeldung."""

    node = content.load_node("halbe-sache")
    state = rules.initial_state(node)

    echo = rules.exec_command(
        node, state, "workstation", "echoscu -aet DCMLAB-WS -aec RAD-ARCHIV 10.77.0.10 104",
    )
    assert echo.exit_code == 0

    bild_1 = rules.exec_command(
        node, state, "workstation",
        "storescu -aet DCMLAB-WS -aec RAD-ARCHIV 10.77.0.10 104 bild-1.dcm",
    )
    assert bild_1.exit_code == 0

    bild_2 = rules.exec_command(
        node, state, "workstation",
        "storescu -aet DCMLAB-WS -aec RAD-ARCHIV 10.77.0.10 104 bild-2.dcm",
    )
    assert bild_2.exit_code == 0

    bild_3 = rules.exec_command(
        node, state, "workstation",
        "storescu -aet DCMLAB-WS -aec RAD-ARCHIV 10.77.0.10 104 bild-3.dcm",
    )
    assert bild_3.exit_code == 0

    dosisbericht = rules.exec_command(
        node, state, "workstation",
        "storescu -aet DCMLAB-WS -aec RAD-ARCHIV 10.77.0.10 104 dosisbericht.dcm",
    )
    assert dosisbericht.exit_code == 1
    assert "abstract-syntax-not-supported" in dosisbericht.stderr

    dump_bild_1 = rules.exec_command(node, state, "workstation", "dcmdump bild-1.dcm")
    assert "1.2.840.10008.1.2.4.70" in dump_bild_1.stdout

    dump_dosisbericht = rules.exec_command(
        node, state, "workstation", "dcmdump dosisbericht.dcm",
    )
    assert "1.2.840.10008.1.2.1" in dump_dosisbericht.stdout
    assert "1.2.840.10008.5.1.4.1.1.88.67" in dump_dosisbericht.stdout

    assert state["bestand"]["archive"] == {"studies": 1, "series": 1, "instances": 3}
    assert rules.check_flag(node, state, "dosisbericht.dcm") is True


def test_first_contact_is_solvable_from_the_real_content() -> None:
    """P10, Feature 8: dcmftest bestaetigt das Format, dcmdump zeigt
    Modality und StudyDescription -- die Untersuchung ist das Flag."""

    node = content.load_node("first-contact")
    state = rules.initial_state(node)

    format_check = rules.exec_command(node, state, "workstation", "dcmftest datei-ohne-namen")
    assert format_check.stdout == "yes: datei-ohne-namen"

    dump = rules.exec_command(node, state, "workstation", "dcmdump datei-ohne-namen")
    assert "(0008,0060) CS [US]  # xx, 1 Modality" in dump.stdout
    assert "(0008,1030) LO [Abdomen komplett]  # xx, 1 StudyDescription" in dump.stdout

    assert rules.check_flag(node, state, "Abdomen komplett") is True


def test_wo_steht_das_is_solvable_from_the_real_content() -> None:
    """P10, Feature 8: alle drei gesuchten Werte stehen im vollen dcmdump,
    ohne dass eine Tag-Nummer vorher bekannt sein muss."""

    node = content.load_node("wo-steht-das")
    state = rules.initial_state(node)

    dump = rules.exec_command(node, state, "workstation", "dcmdump schicht-0001.dcm")
    assert "(0008,0022) DA [20260910]  # xx, 1 AcquisitionDate" in dump.stdout
    assert "(0018,0050) DS [3.0]  # xx, 1 SliceThickness" in dump.stdout
    assert "(0018,1210) SH [B60f]  # xx, 1 ConvolutionKernel" in dump.stdout

    assert rules.check_flag(node, state, "B60f") is True


def test_zwei_ebenen_tiefer_is_solvable_from_the_real_content() -> None:
    """P10, Feature 9: fuenf Instances, zwei Patienten, zwei Studies, aber
    nur drei Series -- eine Study hat zwei Series, die andere nur eine."""

    node = content.load_node("zwei-ebenen-tiefer")
    state = rules.initial_state(node)

    dumps = [
        rules.exec_command(node, state, "workstation", f"dcmdump bild-0{i}.dcm").stdout
        for i in range(1, 6)
    ]

    assert "4711" in dumps[0]
    assert "4712" in dumps[3]
    assert "4712" in dumps[4]

    series_a = "1.2.276.0.7230010.3.1.3.881100011"
    series_b = "1.2.276.0.7230010.3.1.3.881100012"
    series_c = "1.2.276.0.7230010.3.1.3.881100021"
    all_series = {series_a if series_a in d else series_b if series_b in d else series_c
                  for d in dumps}
    assert all_series == {series_a, series_b, series_c}

    assert rules.check_flag(node, state, "4712") is True


def test_nodes_without_routes_never_create_phase_b_jobs_from_the_real_content() -> None:
    """ADR 0120, Phase B, Abschnitt 56: keiner der heute ausgelieferten Nodes
    deklariert `environment.hosts[].routes` -- ein RuntimeObject-Store dort
    darf deshalb nie automatisch einen Job erzeugen, unabhaengig vom
    Storage-Pfad (`send_study` vs. direktes `storescu`)."""

    silent_ct = content.load_node("silent-ct")
    state = rules.initial_state(silent_ct)
    rules.set_config(silent_ct, state, "ct-console", "remote_ae", "PACS-ARCHIV")
    rules.trigger_action(silent_ct, state, "ct-console", "send_study")
    assert state.get("jobs", {}) == {}
    assert "route.evaluated" not in {e["type"] for e in state.get("events", [])}

    halbe_sache = content.load_node("halbe-sache")
    state = rules.initial_state(halbe_sache)
    for filename in ["bild-1.dcm", "bild-2.dcm", "bild-3.dcm"]:
        rules.exec_command(
            halbe_sache, state, "workstation",
            f"storescu -aet DCMLAB-WS -aec RAD-ARCHIV 10.77.0.10 104 {filename}",
        )
    assert state.get("jobs", {}) == {}
    assert "route.evaluated" not in {e["type"] for e in state.get("events", [])}


def test_nodes_without_pacs_tool_do_not_expose_the_pacs_cli_from_the_real_content() -> None:
    """ADR 0120, Phase C, Abschnitt 60: keiner der heute ausgelieferten
    Nodes deklariert `pacs` in `environment.tools` -- `help` darf es dort
    nicht zeigen, und der Befehl selbst bleibt gesperrt wie jedes andere
    nicht freigegebene Werkzeug."""

    for slug in ["silent-ct", "halbe-sache"]:
        node = content.load_node(slug)
        state = rules.initial_state(node)

        help_result = rules.exec_command(node, state, "workstation", "help")
        assert "pacs" not in help_result.stdout

        pacs_result = rules.exec_command(node, state, "workstation", "pacs objects")
        assert pacs_result.exit_code == 127
        assert pacs_result.stderr == "pacs: command not found"


def test_gefiltert_zero_action_solve_is_impossible() -> None:
    """Phase D.1, Abschnitt 47: der zentrale Regressionstest fuer den
    Betreiber-Realitaetscheck nach PR #170 -- der frueher moegliche
    Zero-Action-Solve (korrekter Flag ohne jeden Ingest) muss verschwunden
    sein."""

    node = content.load_node("gefiltert")
    state = rules.initial_state(node)

    assert rules.check_flag(node, state, "PACS-TO-DOSE") is False
    assert state["solved"] is False


def test_gefiltert_zero_action_correct_flag_is_correct_but_incomplete() -> None:
    """Phase D.2, Abschnitt 31: der urspruengliche Betreiber-Befund war nicht
    nur "loest nicht", sondern konkret "eine korrekte Antwort sieht wie eine
    falsche aus" -- `evaluate_flag()` muss das jetzt als korrekt-aber-
    unvollstaendig unterscheiden, nicht als schlicht falsch."""

    node = content.load_node("gefiltert")
    state = rules.initial_state(node)

    outcome = rules.evaluate_flag(node, state, "PACS-TO-DOSE")

    assert outcome.correct is True
    assert outcome.solved is False
    assert outcome.reason == rules.REASON_PREREQUISITES_NOT_MET
    assert state["solved"] is False


def test_gefiltert_read_only_cli_alone_does_not_solve() -> None:
    """Phase D.1, Abschnitt 48: `pacs routes`/`route show` lesen nur die
    statische Node-Definition, nicht den Session-State -- selbst nach
    beiden bleibt der Node ungeloest, und beide Befehle bleiben dabei
    state-rein."""

    node = content.load_node("gefiltert")
    state = rules.initial_state(node)

    before = copy.deepcopy(state)
    rules.exec_command(node, state, "workstation", "pacs routes")
    rules.exec_command(node, state, "workstation", "pacs route show PACS-TO-DOSE")
    assert state == before

    assert rules.check_flag(node, state, "PACS-TO-DOSE") is False
    assert state["solved"] is False


def test_gefiltert_rdsr_only_does_not_solve() -> None:
    """Phase D.1, Abschnitt 49: ohne den positiven CT-Kontrollpfad ist die
    Aussage "CT-Bilder funktionieren, RDSR nicht" nie reproduziert
    worden."""

    node = content.load_node("gefiltert")
    state = rules.initial_state(node)
    result = rules.exec_command(
        node, state, "workstation",
        "storescu -aet CT-KONSOLE -aec PACS-ARCHIV 10.83.0.10 104 dose-report.dcm",
    )
    assert result.exit_code == 0

    assert rules.check_flag(node, state, "PACS-TO-DOSE") is False
    assert state["solved"] is False


def test_gefiltert_ct_only_does_not_solve() -> None:
    """Phase D.1, Abschnitt 50: ohne das RDSR fehlt der eigentliche
    Incident -- die Route funktioniert nur nachweislich fuer CT."""

    node = content.load_node("gefiltert")
    state = rules.initial_state(node)

    for filename in ["schicht-1.dcm", "schicht-2.dcm", "schicht-3.dcm"]:
        result = rules.exec_command(
            node, state, "workstation",
            f"storescu -aet CT-KONSOLE -aec PACS-ARCHIV 10.83.0.10 104 {filename}",
        )
        assert result.exit_code == 0

    assert rules.check_flag(node, state, "PACS-TO-DOSE") is False
    assert state["solved"] is False


def test_gefiltert_prerequisite_evaluation_is_read_only() -> None:
    """Phase D.1, Abschnitt 52: State vor/nach der reinen
    Prerequisite-Auswertung (ueber wiederholte, teils falsche
    `check_flag()`-Aufrufe) bleibt identisch -- keine Mutation durch die
    Auswertung selbst, unabhaengig vom Ergebnis."""

    node = content.load_node("gefiltert")
    state = rules.initial_state(node)
    rules.exec_command(
        node, state, "workstation",
        "storescu -aet CT-KONSOLE -aec PACS-ARCHIV 10.83.0.10 104 dose-report.dcm",
    )

    before = copy.deepcopy(state)
    rules.check_flag(node, state, "PACS-TO-DOSE")  # richtiger Flag, Prerequisites nicht erfuellt
    rules.check_flag(node, state, "not-even-close")
    assert state == before


def test_gefiltert_is_solvable_from_the_real_content() -> None:
    """ADR 0120, Phase D: der erste echte Hands-on-PACS-Routing-Node.
    Treibt den realen Ingest (vier `storescu`-Aufrufe) und beweist end to
    end, dass Content -> Engine -> Phase-B-Routing -> Phase-C-CLI
    zusammenspielen -- keine handgeschriebenen Jobs/Events, echte
    `rules.exec_command()`-Aufrufe statt direkter `cli._cmd_*()`-Tests."""

    node = content.load_node("gefiltert")
    state = rules.initial_state(node)

    for filename in ["schicht-1.dcm", "schicht-2.dcm", "schicht-3.dcm", "dose-report.dcm"]:
        result = rules.exec_command(
            node, state, "workstation",
            f"storescu -aet CT-KONSOLE -aec PACS-ARCHIV 10.83.0.10 104 {filename}",
        )
        assert result.exit_code == 0

    # --- RuntimeObject/Presence (Abschnitt 38) ---
    rdsr_id = next(
        object_id for object_id, obj in state["objects"].items() if obj["modality"] == "SR"
    )
    rdsr = state["objects"][rdsr_id]
    assert rdsr["sop_class"] == "1.2.840.10008.5.1.4.1.1.88.67"
    assert rdsr_id in state["stored_objects"]["pacs"]
    assert rdsr_id not in state["stored_objects"].get("dose-scp", [])

    ct_ids = [object_id for object_id, obj in state["objects"].items() if obj["modality"] == "CT"]
    assert len(ct_ids) == 3
    for ct_id in ct_ids:
        assert ct_id in state["stored_objects"]["dose-scp"]

    # --- Jobs (Abschnitt 39): CT sent, RDSR ohne jeden Eintrag ---
    jobs_by_object = {job["object"]: job for job in state["jobs"].values()}
    for ct_id in ct_ids:
        assert jobs_by_object[ct_id]["status"] == "sent"
        assert jobs_by_object[ct_id]["route_id"] == "PACS-TO-DOSE"
    assert rdsr_id not in jobs_by_object

    # --- Events (Abschnitt 40): real entstanden, nicht handgeschrieben ---
    rdsr_route_evaluated = next(
        e for e in state["events"]
        if e["type"] == "route.evaluated" and e["object"] == rdsr_id
    )
    assert rdsr_route_evaluated["matched"] is False
    assert rdsr_route_evaluated["field"] == "modality"
    assert rdsr_route_evaluated["operator"] == "equals"
    assert rdsr_route_evaluated["expected"] == "CT"
    assert rdsr_route_evaluated["actual"] == "SR"
    assert not any(
        e["type"] == "job.created" and e.get("object") == rdsr_id for e in state["events"]
    )

    # --- CLI End-to-End (Abschnitt 41): echte rules.exec_command()-Aufrufe ---
    objects_out = rules.exec_command(node, state, "workstation", "pacs objects").stdout
    assert f"{rdsr_id}  1.2.840.10008.5.1.4.1.1.88.67  SR        pacs" in objects_out

    jobs_out = rules.exec_command(node, state, "workstation", "pacs jobs").stdout
    assert "sent" in jobs_out
    assert rdsr_id not in jobs_out

    events_out = rules.exec_command(node, state, "workstation", "pacs events").stdout
    assert "matched=false modality expected CT actual SR" in events_out

    # --- Phase D.2, Abschnitt 37/38/48: `--object`-Filter am echten RDSR --
    # verbessert genau den urspruenglichen Playtest-Befund ("17 Events" bei
    # `pacs events`, siehe PR-Beschreibung) -- das RDSR hat KEINEN Job (ein
    # valides Diagnoseergebnis, kein Fehler), und seine Event-Kette zeigt nur
    # die eigene Chronologie, keine der drei CT-Objekt-Events.
    rdsr_jobs_out = rules.exec_command(
        node, state, "workstation", f"pacs jobs --object {rdsr_id}",
    ).stdout
    assert rdsr_jobs_out == f"No routing jobs for object {rdsr_id}.\n"

    rdsr_events_out = rules.exec_command(
        node, state, "workstation", f"pacs events --object {rdsr_id}",
    ).stdout
    assert "store.completed" in rdsr_events_out
    assert "matched=false modality expected CT actual SR" in rdsr_events_out
    for ct_id in ct_ids:
        assert ct_id not in rdsr_events_out
    assert "job.created" not in rdsr_events_out
    assert "job.sent" not in rdsr_events_out

    route_show_out = rules.exec_command(
        node, state, "workstation", "pacs route show PACS-TO-DOSE",
    ).stdout
    assert "modality equals CT" in route_show_out

    route_test_out = rules.exec_command(
        node, state, "workstation", f"pacs route test PACS-TO-DOSE {rdsr_id}",
    ).stdout
    assert route_test_out == (
        "matched: false\n"
        "field: modality\n"
        "operator: equals\n"
        "expected: CT\n"
        "actual: SR\n"
    )

    # --- Route-Test-/Objekt-Filter-Purity am echten Node (Abschnitt 23/39/42) ---
    before = copy.deepcopy(state)
    rules.exec_command(node, state, "workstation", f"pacs route test PACS-TO-DOSE {rdsr_id}")
    rules.exec_command(node, state, "workstation", f"pacs jobs --object {rdsr_id}")
    rules.exec_command(node, state, "workstation", f"pacs events --object {rdsr_id}")
    assert state == before

    # --- Flag (Abschnitt 43/44): die Route-ID, nicht die Modality (Lektion
    # 3.8 nennt Modality=SR fuer RDSR bereits explizit -- ein reiner
    # Modality-Flag waere ohne einen einzigen pacs-Befehl erratbar,
    # Betreiber-Review nach PR #170). Plausible falsche Werte duerfen nicht
    # loesen: weder die Evidenz selbst (SR) noch der Name der Route aus dem
    # verwandten Node dosis-bleibt-liegen (CT-TO-DOSE). ---
    # Phase D.2, Abschnitt 31: nach dem vollstaendigen Incident ist das
    # strukturierte Ergebnis ein echter Solve, kein correct-aber-incomplete.
    outcome = rules.evaluate_flag(node, state, "PACS-TO-DOSE")
    assert outcome.correct is True
    assert outcome.solved is True
    assert outcome.reason is None

    assert rules.check_flag(node, state, "PACS-TO-DOSE") is True
    assert rules.check_flag(node, state, "SR") is False
    assert rules.check_flag(node, state, "CT-TO-DOSE") is False


# ---------------------------------------------------------------------
# "zweiter-hop": zweiter Hands-on-PACS-Operations-Node -- Generalisierungs-
# test fuer Phase A-D.2 an einer anderen Betriebsgrenze als "gefiltert".
# "gefiltert" endet VOR der Job-Erzeugung (matched=false, kein Job).
# "zweiter-hop" prueft die naechste Grenze dahinter: Route matcht, ein Job
# entsteht, aber der Zustellversuch scheitert am Ziel (SOP-Class-Ablehnung).
# Kein neues Engine-Primitiv -- reine Content-/Real-Content-Test-Erweiterung.
# ---------------------------------------------------------------------

CT_IMAGE_STORAGE = "1.2.840.10008.5.1.4.1.1.2"
ENHANCED_CT_IMAGE_STORAGE = "1.2.840.10008.5.1.4.1.1.2.1"
EXPLICIT_VR_LITTLE_ENDIAN = "1.2.840.10008.1.2.1"


def _ingest_classic(node: content.NodeDefinition, state: dict) -> rules.ExecResult:
    return rules.exec_command(
        node, state, "workstation",
        "storescu -aet CT-KONSOLE -aec PACS-ARCHIV 10.84.0.10 104 ct-classic.dcm",
    )


def _ingest_enhanced(node: content.NodeDefinition, state: dict) -> rules.ExecResult:
    return rules.exec_command(
        node, state, "workstation",
        "storescu -aet CT-KONSOLE -aec PACS-ARCHIV 10.84.0.10 104 ct-enhanced.dcm",
    )


def _object_id_by_sop_class(state: dict, sop_class: str) -> str:
    # Nie obj-001/obj-002 annehmen (Ingest-Reihenfolge ist Lernenden-frei,
    # Abschnitt "Testregel" des Auftrags) -- immer ueber die SOP Class
    # nachschlagen, die die beiden Vergleichsobjekte eindeutig unterscheidet.
    return next(oid for oid, obj in state["objects"].items() if obj["sop_class"] == sop_class)


def test_zweiter_hop_zero_action_correct_flag_is_correct_but_incomplete() -> None:
    """Phase D.2-Generalisierung (Abschnitt 38): die korrekte SOP-Class-UID
    ohne jeden Ingest ist fachlich korrekt, aber der Incident wurde nie
    reproduziert."""

    node = content.load_node("zweiter-hop")
    state = rules.initial_state(node)

    outcome = rules.evaluate_flag(node, state, ENHANCED_CT_IMAGE_STORAGE)

    assert outcome.correct is True
    assert outcome.solved is False
    assert outcome.reason == rules.REASON_PREREQUISITES_NOT_MET
    assert state["solved"] is False


def test_zweiter_hop_classic_only_does_not_solve() -> None:
    """Abschnitt 39: nur die positive Kontrolle senden reicht nicht -- der
    eigentliche Incident (das abgelehnte Enhanced-Objekt) fehlt."""

    node = content.load_node("zweiter-hop")
    state = rules.initial_state(node)
    result = _ingest_classic(node, state)
    assert result.exit_code == 0

    outcome = rules.evaluate_flag(node, state, ENHANCED_CT_IMAGE_STORAGE)

    assert outcome.correct is True
    assert outcome.solved is False


def test_zweiter_hop_enhanced_only_does_not_solve() -> None:
    """Abschnitt 40: der Failed Job darf entstehen (und tut es), trotzdem
    bleibt der Node ungeloest, solange der positive Kontrollpfad fehlt."""

    node = content.load_node("zweiter-hop")
    state = rules.initial_state(node)
    result = _ingest_enhanced(node, state)
    assert result.exit_code == 0

    enhanced_id = _object_id_by_sop_class(state, ENHANCED_CT_IMAGE_STORAGE)
    jobs_by_object = {job["object"]: job for job in state["jobs"].values()}
    assert jobs_by_object[enhanced_id]["status"] == "failed"
    assert jobs_by_object[enhanced_id]["reason"] == "abstract_syntax_not_supported"

    outcome = rules.evaluate_flag(node, state, ENHANCED_CT_IMAGE_STORAGE)

    assert outcome.correct is True
    assert outcome.solved is False


def test_zweiter_hop_is_solvable_from_the_real_content() -> None:
    """ADR 0120, zweiter Hands-on-Node: Generalisierungstest fuer Phase
    A-D.2 an einer anderen Betriebsgrenze als "gefiltert" (Route matcht,
    Job entsteht, Zustellung scheitert am Ziel) -- echte
    `rules.exec_command()`-Aufrufe, keine handgeschriebenen Jobs/Events."""

    node = content.load_node("zweiter-hop")
    state = rules.initial_state(node)

    for result in (_ingest_classic(node, state), _ingest_enhanced(node, state)):
        assert result.exit_code == 0

    classic_id = _object_id_by_sop_class(state, CT_IMAGE_STORAGE)
    enhanced_id = _object_id_by_sop_class(state, ENHANCED_CT_IMAGE_STORAGE)

    # --- Beide Objekte: identische Transfer Syntax, nur die SOP Class
    # unterscheidet sie (Abschnitt 2/18/45: keine Transfer-Syntax-Falle) ---
    classic = state["objects"][classic_id]
    enhanced = state["objects"][enhanced_id]
    assert classic["transfer_syntax"] == EXPLICIT_VR_LITTLE_ENDIAN
    assert enhanced["transfer_syntax"] == EXPLICIT_VR_LITTLE_ENDIAN

    postproc_service = next(
        s for h in node.hosts if h["name"] == "postproc-scp" for s in h["services"]
        if s["id"] == "postproc-store"
    )
    assert EXPLICIT_VR_LITTLE_ENDIAN in postproc_service["accepted_transfer_syntaxes"]
    assert ENHANCED_CT_IMAGE_STORAGE not in postproc_service["accepted_sop_classes"]

    # --- RuntimeObject/Presence (Abschnitt 41) ---
    assert classic_id in state["stored_objects"]["pacs"]
    assert classic_id in state["stored_objects"]["postproc-scp"]
    assert enhanced_id in state["stored_objects"]["pacs"]
    assert enhanced_id not in state["stored_objects"].get("postproc-scp", [])

    # --- Jobs (Abschnitt 41): classic sent, enhanced failed -- BEIDE haben
    # tatsaechlich einen Job, anders als der "kein Job"-Fall bei gefiltert ---
    jobs_by_object = {job["object"]: job for job in state["jobs"].values()}
    assert jobs_by_object[classic_id]["route_id"] == "PACS-TO-POSTPROC"
    assert jobs_by_object[classic_id]["status"] == "sent"
    assert jobs_by_object[enhanced_id]["route_id"] == "PACS-TO-POSTPROC"
    assert jobs_by_object[enhanced_id]["status"] == "failed"
    # Zwingender Test (Abschnitt 18/45): NICHT transfer_syntaxes_not_supported.
    assert jobs_by_object[enhanced_id]["reason"] == "abstract_syntax_not_supported"
    assert jobs_by_object[enhanced_id]["reason"] != "transfer_syntaxes_not_supported"

    # --- Events fuer Enhanced (Abschnitt 42): route.evaluated matched=true
    # -- die Route HAT ausgewaehlt, anders als bei gefiltert -- gefolgt von
    # job.created/job.failed, kein job.sent fuer dieses Objekt. ---
    enhanced_events = [e for e in state["events"] if e.get("object") == enhanced_id]
    enhanced_event_types = [e["type"] for e in enhanced_events]
    assert enhanced_event_types == [
        "store.completed", "route.evaluated", "job.created", "job.failed",
    ]
    route_evaluated = next(e for e in enhanced_events if e["type"] == "route.evaluated")
    assert route_evaluated["matched"] is True
    job_failed = next(e for e in enhanced_events if e["type"] == "job.failed")
    assert job_failed["reason"] == "abstract_syntax_not_supported"
    assert "job.sent" not in enhanced_event_types

    # --- CLI End-to-End (Abschnitt 43): --object-Filter zeigt AUSSCHLIESSLICH
    # das jeweils gefilterte Objekt, keine Vermischung mit dem Kontrollobjekt ---
    enhanced_jobs_out = rules.exec_command(
        node, state, "workstation", f"pacs jobs --object {enhanced_id}",
    ).stdout
    assert enhanced_id in enhanced_jobs_out
    assert "failed" in enhanced_jobs_out
    assert classic_id not in enhanced_jobs_out

    enhanced_events_out = rules.exec_command(
        node, state, "workstation", f"pacs events --object {enhanced_id}",
    ).stdout
    assert "route.evaluated" in enhanced_events_out
    assert "matched=true" in enhanced_events_out
    assert "reason=abstract_syntax_not_supported" in enhanced_events_out
    assert classic_id not in enhanced_events_out

    # --- `pacs route test` (Abschnitt 44): beweist, dass NICHT die Route
    # das Objekt ausgeschlossen hat -- konzeptionelle Abgrenzung zu
    # "gefiltert". Bleibt dabei ein echter Dry Run (State unveraendert). ---
    before = copy.deepcopy(state)
    route_test_out = rules.exec_command(
        node, state, "workstation", f"pacs route test PACS-TO-POSTPROC {enhanced_id}",
    ).stdout
    assert route_test_out == "matched: true\n"
    assert state == before

    # --- Purity (Abschnitt 48): read-only Befehle mutieren nichts ---
    before = copy.deepcopy(state)
    rules.exec_command(node, state, "workstation", "pacs objects")
    rules.exec_command(node, state, "workstation", f"pacs jobs --object {enhanced_id}")
    rules.exec_command(node, state, "workstation", f"pacs events --object {enhanced_id}")
    rules.exec_command(node, state, "workstation", "pacs route show PACS-TO-POSTPROC")
    assert state == before

    # --- Flag (Abschnitt 46/47): die SOP-Class-UID des abgelehnten
    # Objekttyps -- nicht die Failure-Reason, nicht die Route-ID, nicht die
    # (bei beiden Objekten identische) Transfer Syntax, nicht die SOP Class
    # des Kontrollobjekts. ---
    outcome = rules.evaluate_flag(node, state, ENHANCED_CT_IMAGE_STORAGE)
    assert outcome.correct is True
    assert outcome.solved is True
    assert outcome.reason is None

    for plausible_wrong_value in (
        CT_IMAGE_STORAGE, "abstract_syntax_not_supported", "PACS-TO-POSTPROC",
        EXPLICIT_VR_LITTLE_ENDIAN,
    ):
        assert rules.check_flag(node, state, plausible_wrong_value) is False
