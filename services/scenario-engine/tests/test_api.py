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


def create_session(node_slug: str = "test-scenario-node") -> dict:
    response = client.post("/v1/sessions", json={"node_slug": node_slug}, headers=HEADERS)
    assert response.status_code == 201
    return response.json()


def test_full_correct_path_end_to_end() -> None:
    created = create_session()
    session_id = created["session_id"]

    assert created["state"]["scenario"]["step_id"] == "frage"
    assert created["state"]["points"] == 10

    r = client.post(
        f"/v1/sessions/{session_id}/action",
        json={"host": "player", "action": "richtig"},
        headers=HEADERS,
    )
    assert r.status_code == 200
    assert r.json()["state"]["scenario"]["outcome"] == "correct"

    r = client.post(f"/v1/sessions/{session_id}/hint", json={"hint_id": "h1"}, headers=HEADERS)
    assert r.json()["points"] == 9

    r = client.post(
        f"/v1/sessions/{session_id}/flag",
        json={"value": "test-flag-klartext"},
        headers=HEADERS,
    )
    assert r.json()["correct"] is True
    assert r.json()["points"] == 9

    r = client.get(f"/v1/sessions/{session_id}/state", headers=HEADERS)
    assert r.json()["solved"] is True


def test_wrong_path_then_restart() -> None:
    created = create_session()
    session_id = created["session_id"]

    r = client.post(
        f"/v1/sessions/{session_id}/action",
        json={"host": "player", "action": "falsch"},
        headers=HEADERS,
    )
    assert r.json()["state"]["scenario"]["outcome"] == "wrong"

    r = client.post(
        f"/v1/sessions/{session_id}/action",
        json={"host": "player", "action": "restart"},
        headers=HEADERS,
    )
    assert r.json()["state"]["scenario"]["step_id"] == "frage"


def test_unknown_option_returns_400() -> None:
    created = create_session()
    session_id = created["session_id"]

    r = client.post(
        f"/v1/sessions/{session_id}/action",
        json={"host": "player", "action": "nicht-vorhanden"},
        headers=HEADERS,
    )
    assert r.status_code == 400


def test_exec_and_config_return_ok_not_error() -> None:
    created = create_session()
    session_id = created["session_id"]

    r = client.post(
        f"/v1/sessions/{session_id}/exec",
        json={"host": "player", "command": "ls"},
        headers=HEADERS,
    )
    assert r.status_code == 200
    assert r.json()["exit_code"] != 0

    r = client.post(
        f"/v1/sessions/{session_id}/config",
        json={"host": "player", "field": "x", "value": "y"},
        headers=HEADERS,
    )
    assert r.status_code == 200
    assert r.json()["ok"] is False


def test_write_up_before_solve_zeroes_points() -> None:
    created = create_session()
    session_id = created["session_id"]

    r = client.post(f"/v1/sessions/{session_id}/write-up", headers=HEADERS)
    assert r.json()["points"] == 0


def test_unknown_session_returns_404() -> None:
    r = client.get("/v1/sessions/does-not-exist/state", headers=HEADERS)
    assert r.status_code == 404


def test_unknown_node_returns_404() -> None:
    r = client.post("/v1/sessions", json={"node_slug": "does-not-exist"}, headers=HEADERS)
    assert r.status_code == 404


def test_delete_session() -> None:
    created = create_session()
    session_id = created["session_id"]

    r = client.delete(f"/v1/sessions/{session_id}", headers=HEADERS)
    assert r.status_code == 204

    r = client.get(f"/v1/sessions/{session_id}/state", headers=HEADERS)
    assert r.status_code == 404
