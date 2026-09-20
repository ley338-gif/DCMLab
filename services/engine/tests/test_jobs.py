"""Job-Modell in Isolation (ADR 0120, Phase B) -- `create_job`/
`mark_job_sent`/`mark_job_failed` ohne die volle Orchestrierung aus
`store_and_route()` (siehe test_routing_execution.py fuer die Integration)."""

from app.operations import jobs


def _destination() -> dict:
    return {
        "host": "dose-scp", "service": "dose-store", "ip": "10.20.0.30",
        "port": 104, "called_ae": "DOSE-SCP", "calling_ae": "RAD-PACS",
    }


def test_create_job_assigns_a_sequential_session_local_id() -> None:
    state: dict = {}

    first = jobs.create_job(
        state, route_id="R1", object_id="obj-001", source_host="pacs",
        destination=_destination(), routing_depth=1,
    )
    second = jobs.create_job(
        state, route_id="R2", object_id="obj-002", source_host="pacs",
        destination=_destination(), routing_depth=1,
    )

    assert first["id"] == "j-001"
    assert second["id"] == "j-002"


def test_create_job_starts_queued_with_attempt_one_and_no_reason() -> None:
    state: dict = {}

    job = jobs.create_job(
        state, route_id="R1", object_id="obj-001", source_host="pacs",
        destination=_destination(), routing_depth=1,
    )

    assert job["status"] == "queued"
    assert job["attempt"] == 1
    assert job["reason"] is None
    assert job["object"] == "obj-001"
    assert job["source"] == "pacs"
    assert job["routing_depth"] == 1
    assert job["destination"] == _destination()
    assert isinstance(job["created_at"], str)


def test_mark_job_sent_updates_status() -> None:
    state: dict = {}
    job = jobs.create_job(
        state, route_id="R1", object_id="obj-001", source_host="pacs",
        destination=_destination(), routing_depth=1,
    )

    jobs.mark_job_sent(state, job["id"])

    assert state["jobs"][job["id"]]["status"] == "sent"


def test_mark_job_failed_updates_status_and_reason() -> None:
    state: dict = {}
    job = jobs.create_job(
        state, route_id="R1", object_id="obj-001", source_host="pacs",
        destination=_destination(), routing_depth=1,
    )

    jobs.mark_job_failed(state, job["id"], "abstract_syntax_not_supported")

    assert state["jobs"][job["id"]]["status"] == "failed"
    assert state["jobs"][job["id"]]["reason"] == "abstract_syntax_not_supported"
