"""Integrationstests gegen die HTTP-API (Abschnitt 5.5): kompletter Weg von
Sitzung anlegen bis Flag einreichen, gegen eine eigene SQLite-Instanz pro Test
(siehe app.db._make_engine: StaticPool haelt die In-Memory-DB waehrend des
gesamten Tests am Leben)."""

from fastapi.testclient import TestClient

from app.db import Base, engine
from app.main import app

HEADERS = {"X-DCMLAB-KEY": "test-key"}


def setup_function() -> None:
    Base.metadata.drop_all(bind=engine)
    Base.metadata.create_all(bind=engine)


client = TestClient(app)


def create_session(node_slug: str = "test-node") -> dict:
    response = client.post("/v1/sessions", json={"node_slug": node_slug}, headers=HEADERS)
    assert response.status_code == 201
    return response.json()


def test_full_solve_path_end_to_end() -> None:
    created = create_session()
    session_id = created["session_id"]

    assert created["state"]["node_slug"] == "test-node"
    assert created["state"]["points"] == 10
    # Der Called AE Title des Archivs ist geheim (config_visible: false).
    assert "ae_title" not in created["state"]["hosts"]["archive"]["services"][0]

    # 1. Ohne Parameter -> DCMTK-Defaults -> abgelehnt.
    r = client.post(
        f"/v1/sessions/{session_id}/exec",
        json={"host": "workstation", "command": "echoscu 10.0.0.10 104"},
        headers=HEADERS,
    )
    assert r.json()["exit_code"] == 1

    # 2. Mit den (kaputten) Konsolenwerten nachgestellt -> Called AE Title.
    r = client.post(
        f"/v1/sessions/{session_id}/exec",
        json={"host": "workstation", "command": "echoscu -aet MOD1 -aec WRONG_AE 10.0.0.10 104"},
        headers=HEADERS,
    )
    assert "Called AE Title Not Recognized" in r.json()["stderr"]

    # 3. Sendeauftrag mit falscher Konfiguration scheitert.
    r = client.post(
        f"/v1/sessions/{session_id}/action",
        json={"host": "modality", "action": "send_study"},
        headers=HEADERS,
    )
    assert r.json()["events"][0]["type"] == "association_rejected"

    # 4. Ein Hint wird benutzt -- kostet einen Punkt.
    r = client.post(f"/v1/sessions/{session_id}/hint", json={"hint_id": "h1"}, headers=HEADERS)
    assert r.json()["points"] == 9

    # 5. Konfiguration korrigieren und erneut senden.
    r = client.post(
        f"/v1/sessions/{session_id}/config",
        json={"host": "modality", "field": "remote_ae", "value": "TEST-ARCHIV"},
        headers=HEADERS,
    )
    assert r.json()["ok"] is True

    r = client.post(
        f"/v1/sessions/{session_id}/action",
        json={"host": "modality", "action": "send_study"},
        headers=HEADERS,
    )
    assert r.json()["events"][-1]["type"] == "store_completed"

    state = client.get(f"/v1/sessions/{session_id}/state", headers=HEADERS).json()
    assert state["hosts"]["archive"]["bestand"] == {"studies": 1, "series": 1, "instances": 2}

    # 6. Flag holen und einreichen.
    r = client.post(
        f"/v1/sessions/{session_id}/flag", json={"value": "Test Series"}, headers=HEADERS,
    )
    body = r.json()
    assert body["correct"] is True
    assert body["points"] == 9

    r = client.post(f"/v1/sessions/{session_id}/flag", json={"value": "falsch"}, headers=HEADERS)
    assert r.json()["correct"] is False


def test_state_survives_a_fresh_process_because_it_lives_in_postgres() -> None:
    """Simuliert einen Engine-Neustart: eine neue TestClient-Instanz gegen
    dieselbe (In-Memory-)DB muss den Zustand weiterhin sehen (Abschnitt 5.5:
    Zustand in Postgres, nicht im Prozessspeicher)."""

    session_id = create_session()["session_id"]
    client.post(
        f"/v1/sessions/{session_id}/config",
        json={"host": "modality", "field": "local_ae", "value": "CHANGED"},
        headers=HEADERS,
    )

    fresh_client = TestClient(app)
    state = fresh_client.get(f"/v1/sessions/{session_id}/state", headers=HEADERS).json()

    assert state["hosts"]["modality"]["config"]["local_ae"] == "CHANGED"


def test_unknown_node_slug_is_404() -> None:
    response = client.post("/v1/sessions", json={"node_slug": "does-not-exist"}, headers=HEADERS)
    assert response.status_code == 404


def test_unknown_session_id_is_404() -> None:
    response = client.get("/v1/sessions/does-not-exist/state", headers=HEADERS)
    assert response.status_code == 404


def test_every_endpoint_requires_the_internal_key() -> None:
    session_id = create_session()["session_id"]

    endpoints = [
        ("get", f"/v1/sessions/{session_id}/state", None),
        ("post", f"/v1/sessions/{session_id}/exec", {"host": "workstation", "command": "ls"}),
        (
            "post",
            f"/v1/sessions/{session_id}/config",
            {"host": "modality", "field": "local_ae", "value": "x"},
        ),
        ("post", f"/v1/sessions/{session_id}/action", {"host": "modality", "action": "send_study"}),
        ("post", f"/v1/sessions/{session_id}/hint", {"hint_id": "h1"}),
        ("post", f"/v1/sessions/{session_id}/write-up", None),
        ("post", f"/v1/sessions/{session_id}/flag", {"value": "x"}),
        ("delete", f"/v1/sessions/{session_id}", None),
    ]

    for method, path, body in endpoints:
        call = getattr(client, method)
        response = call(path, json=body) if body is not None else call(path)
        assert response.status_code == 401, path


def test_delete_session_removes_it() -> None:
    session_id = create_session()["session_id"]

    r = client.delete(f"/v1/sessions/{session_id}", headers=HEADERS)
    assert r.status_code == 204

    r = client.get(f"/v1/sessions/{session_id}/state", headers=HEADERS)
    assert r.status_code == 404


def test_viewing_write_up_zeroes_points_via_api() -> None:
    session_id = create_session()["session_id"]

    r = client.post(f"/v1/sessions/{session_id}/write-up", headers=HEADERS)
    assert r.json()["points"] == 0

    state = client.get(f"/v1/sessions/{session_id}/state", headers=HEADERS).json()
    assert state["write_up_seen"] is True
    assert state["points"] == 0
