"""Testet die Template-Aufloesung und die Orthanc-Facts-Anreicherung
komplett ohne Docker-Daemon -- `exec_command` selbst wird gefaked, damit
`collect_orthanc_facts()` (das curl INNERHALB der Toolbox ausfuehrt) ohne
echten Container getestet werden kann."""

from __future__ import annotations

import json

import pytest

from app import docker_ops


def test_resolve_template_returns_the_known_template() -> None:
    template = docker_ops.resolve_template("dicom-basic-tools")

    assert template.orthanc_image == docker_ops.settings.orthanc_image
    assert template.toolbox_image == docker_ops.settings.toolbox_image


def test_resolve_template_raises_for_an_unknown_slug() -> None:
    with pytest.raises(docker_ops.UnknownTemplateError):
        docker_ops.resolve_template("does-not-exist")


def test_collect_orthanc_facts_discovers_and_enriches_new_instances(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    changes_payload = {
        "Changes": [
            {"ChangeType": "NewInstance", "ResourceType": "Instance", "ID": "inst-1"},
            # Nicht-Instanz-Aenderungen (z. B. NewStudy) muessen ignoriert werden.
            {"ChangeType": "NewStudy", "ResourceType": "Study", "ID": "study-1"},
        ],
    }
    tags_payload = {
        "SOPClassUID": "1.2.840.10008.5.1.4.1.1.2",
        "PatientID": "4711",
        "StudyInstanceUID": "1.2.3.4.5",
        "Modality": "CT",
    }
    # `simplified-tags` deckt nachweislich NICHT die File Meta Information
    # ab -- TransferSyntaxUID kommt aus einer zweiten Abfrage.
    header_payload = {
        "TransferSyntaxUID": "1.2.840.10008.1.2.1",
    }

    def fake_exec_command(
        _client: object, _toolbox_container_id: str, command: str,
    ) -> dict[str, object]:
        if command.endswith("/changes"):
            return {"exit_code": 0, "stdout": json.dumps(changes_payload), "stderr": ""}
        if command.endswith("/instances/inst-1/simplified-tags"):
            return {"exit_code": 0, "stdout": json.dumps(tags_payload), "stderr": ""}
        if command.endswith("/instances/inst-1/header?simplify"):
            return {"exit_code": 0, "stdout": json.dumps(header_payload), "stderr": ""}
        raise AssertionError(f"unexpected command: {command}")

    monkeypatch.setattr("app.docker_ops.exec_command", fake_exec_command)

    instances = docker_ops.collect_orthanc_facts(None, "toolbox-1")

    assert instances == [
        {
            "instance_id": "inst-1",
            "sop_class": "1.2.840.10008.5.1.4.1.1.2",
            "transfer_syntax": "1.2.840.10008.1.2.1",
            "patient_id": "4711",
            "study_instance_uid": "1.2.3.4.5",
            "modality": "CT",
        },
    ]


def test_collect_orthanc_facts_returns_empty_when_curl_fails(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    def fake_exec_command(
        _client: object, _toolbox_container_id: str, _command: str,
    ) -> dict[str, object]:
        return {"exit_code": 7, "stdout": "", "stderr": "curl: connection refused"}

    monkeypatch.setattr("app.docker_ops.exec_command", fake_exec_command)

    assert docker_ops.collect_orthanc_facts(None, "toolbox-1") == []


def test_collect_orthanc_facts_leaves_transfer_syntax_empty_when_header_query_fails(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """Die SOP-Klasse darf nicht an einer fehlschlagenden zweiten Abfrage
    haengen -- eine leere transfer_syntax ist ein akzeptabler Teilerfolg,
    kein Grund, die ganze Instanz zu verwerfen."""

    changes_payload = {
        "Changes": [{"ChangeType": "NewInstance", "ResourceType": "Instance", "ID": "inst-1"}],
    }
    tags_payload = {"SOPClassUID": "1.2.840.10008.5.1.4.1.1.2"}

    def fake_exec_command(
        _client: object, _toolbox_container_id: str, command: str,
    ) -> dict[str, object]:
        if command.endswith("/changes"):
            return {"exit_code": 0, "stdout": json.dumps(changes_payload), "stderr": ""}
        if command.endswith("/instances/inst-1/simplified-tags"):
            return {"exit_code": 0, "stdout": json.dumps(tags_payload), "stderr": ""}
        if command.endswith("/instances/inst-1/header?simplify"):
            return {"exit_code": 7, "stdout": "", "stderr": "curl: connection refused"}
        raise AssertionError(f"unexpected command: {command}")

    monkeypatch.setattr("app.docker_ops.exec_command", fake_exec_command)

    instances = docker_ops.collect_orthanc_facts(None, "toolbox-1")

    assert instances == [
        {
            "instance_id": "inst-1",
            "sop_class": "1.2.840.10008.5.1.4.1.1.2",
            "transfer_syntax": "",
            "patient_id": "",
            "study_instance_uid": "",
            "modality": "",
        },
    ]


def test_collect_orthanc_facts_leaves_sop_class_empty_when_tags_query_fails(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """Symmetrisch zum Header-Ausfall: Discovery ist selbst schon ein Fact
    -- ein Ausfall von `simplified-tags` darf die Instanz nicht verwerfen,
    solange `header?simplify` verfuegbar ist."""

    changes_payload = {
        "Changes": [{"ChangeType": "NewInstance", "ResourceType": "Instance", "ID": "inst-1"}],
    }
    header_payload = {"TransferSyntaxUID": "1.2.840.10008.1.2.1"}

    def fake_exec_command(
        _client: object, _toolbox_container_id: str, command: str,
    ) -> dict[str, object]:
        if command.endswith("/changes"):
            return {"exit_code": 0, "stdout": json.dumps(changes_payload), "stderr": ""}
        if command.endswith("/instances/inst-1/simplified-tags"):
            return {"exit_code": 7, "stdout": "", "stderr": "curl: connection refused"}
        if command.endswith("/instances/inst-1/header?simplify"):
            return {"exit_code": 0, "stdout": json.dumps(header_payload), "stderr": ""}
        raise AssertionError(f"unexpected command: {command}")

    monkeypatch.setattr("app.docker_ops.exec_command", fake_exec_command)

    instances = docker_ops.collect_orthanc_facts(None, "toolbox-1")

    assert instances == [
        {
            "instance_id": "inst-1",
            "sop_class": "",
            "transfer_syntax": "1.2.840.10008.1.2.1",
            "patient_id": "",
            "study_instance_uid": "",
            "modality": "",
        },
    ]


def test_collect_orthanc_facts_keeps_the_discovery_fact_when_both_enrichment_queries_fail(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """Discovery (`/changes` hat die Instanz gemeldet) ist ein eigener Fact
    -- selbst wenn BEIDE Anreicherungsabfragen scheitern, muss die Instanz
    erhalten bleiben (nur mit leeren Feldern), statt komplett zu
    verschwinden."""

    changes_payload = {
        "Changes": [{"ChangeType": "NewInstance", "ResourceType": "Instance", "ID": "inst-1"}],
    }

    def fake_exec_command(
        _client: object, _toolbox_container_id: str, command: str,
    ) -> dict[str, object]:
        if command.endswith("/changes"):
            return {"exit_code": 0, "stdout": json.dumps(changes_payload), "stderr": ""}
        return {"exit_code": 7, "stdout": "", "stderr": "curl: connection refused"}

    monkeypatch.setattr("app.docker_ops.exec_command", fake_exec_command)

    instances = docker_ops.collect_orthanc_facts(None, "toolbox-1")

    assert instances == [
        {
            "instance_id": "inst-1",
            "sop_class": "",
            "transfer_syntax": "",
            "patient_id": "",
            "study_instance_uid": "",
            "modality": "",
        },
    ]
