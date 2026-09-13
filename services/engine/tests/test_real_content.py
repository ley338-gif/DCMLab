"""Prueft die echten, ausgelieferten Nodes (nicht die Testfixture) gegen das
Regelwerk -- speziell fuer P6 (Wrong Door): "ausschliesslich aus dem Schema
erzeugt, ohne eine Zeile Sonderlogik". Wenn dieser Test nur mit
Node-spezifischem Engine-Code gruen wird, ist das ein Schema-Mangel
(Abschnitt 10, P6-DoD).
"""

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
