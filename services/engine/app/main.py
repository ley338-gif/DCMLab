from collections.abc import AsyncIterator
from contextlib import asynccontextmanager

from fastapi import Depends, FastAPI, HTTPException, status
from sqlalchemy.orm import Session
from sqlalchemy.orm.attributes import flag_modified

from app import content, rules
from app.db import EngineSession, get_db, init_db
from app.schemas import (
    ActionRequest,
    ConfigRequest,
    CreateSessionRequest,
    ExecRequest,
    FlagRequest,
    HintRequest,
)
from app.security import require_internal_key


@asynccontextmanager
async def lifespan(_app: FastAPI) -> AsyncIterator[None]:
    init_db()
    yield


app = FastAPI(
    title="DCM Lab Engine",
    description="Simulierte Node-Engine. Kennt Nodes und Sitzungen, keine Nutzer (Abschnitt 3.3).",
    lifespan=lifespan,
)


@app.get("/health")
async def health() -> dict[str, str]:
    return {"status": "ok"}


router_dependencies = [Depends(require_internal_key)]


def _get_session_or_404(session_id: str, db: Session) -> EngineSession:
    row = db.get(EngineSession, session_id)

    if row is None:
        raise HTTPException(status.HTTP_404_NOT_FOUND, detail="session not found")

    return row


def _load_node_or_404(node_slug: str) -> content.NodeDefinition:
    try:
        return content.load_node(node_slug)
    except content.NodeNotFoundError as exc:
        raise HTTPException(status.HTTP_404_NOT_FOUND, detail="node not found") from exc


def _save(db: Session, row: EngineSession) -> None:
    flag_modified(row, "state")
    db.commit()


@app.post("/v1/sessions", dependencies=router_dependencies, status_code=status.HTTP_201_CREATED)
def create_session(body: CreateSessionRequest, db: Session = Depends(get_db)) -> dict[str, object]:
    node = _load_node_or_404(body.node_slug)

    row = EngineSession(node_slug=node.slug, state=rules.initial_state(node))
    db.add(row)
    db.commit()
    db.refresh(row)

    return {"session_id": row.id, "state": rules.public_state(node, row.state)}


@app.get("/v1/sessions/{session_id}/state", dependencies=router_dependencies)
def get_session_state(session_id: str, db: Session = Depends(get_db)) -> dict[str, object]:
    row = _get_session_or_404(session_id, db)
    node = _load_node_or_404(row.node_slug)

    return rules.public_state(node, row.state)


@app.post("/v1/sessions/{session_id}/exec", dependencies=router_dependencies)
def exec_command(
    session_id: str, body: ExecRequest, db: Session = Depends(get_db),
) -> dict[str, object]:
    row = _get_session_or_404(session_id, db)
    node = _load_node_or_404(row.node_slug)

    result = rules.exec_command(node, row.state, body.host, body.command)
    row.state["_last_exit_code"] = result.exit_code
    _save(db, row)

    return {
        "stdout": result.stdout,
        "stderr": result.stderr,
        "exit_code": result.exit_code,
        "events": result.events,
    }


@app.post("/v1/sessions/{session_id}/config", dependencies=router_dependencies)
def set_config(
    session_id: str, body: ConfigRequest, db: Session = Depends(get_db),
) -> dict[str, object]:
    row = _get_session_or_404(session_id, db)
    node = _load_node_or_404(row.node_slug)

    error = rules.set_config(node, row.state, body.host, body.field, body.value)
    _save(db, row)

    return {
        "ok": error is None,
        "error": error,
        "state": rules.public_state(node, row.state),
    }


@app.post("/v1/sessions/{session_id}/action", dependencies=router_dependencies)
def trigger_action(
    session_id: str, body: ActionRequest, db: Session = Depends(get_db),
) -> dict[str, object]:
    row = _get_session_or_404(session_id, db)
    node = _load_node_or_404(row.node_slug)

    result = rules.trigger_action(node, row.state, body.host, body.action)
    _save(db, row)

    if result.error is not None:
        raise HTTPException(status.HTTP_400_BAD_REQUEST, detail=result.error)

    return {
        "log": result.log,
        "events": result.events,
        "state": rules.public_state(node, row.state),
    }


@app.post("/v1/sessions/{session_id}/hint", dependencies=router_dependencies)
def use_hint(
    session_id: str, body: HintRequest, db: Session = Depends(get_db),
) -> dict[str, object]:
    row = _get_session_or_404(session_id, db)
    node = _load_node_or_404(row.node_slug)

    error = rules.use_hint(node, row.state, body.hint_id)
    _save(db, row)

    if error is not None:
        raise HTTPException(status.HTTP_400_BAD_REQUEST, detail=error)

    return {"points": rules.points(node, row.state)}


@app.post("/v1/sessions/{session_id}/write-up", dependencies=router_dependencies)
def view_write_up(session_id: str, db: Session = Depends(get_db)) -> dict[str, object]:
    row = _get_session_or_404(session_id, db)
    node = _load_node_or_404(row.node_slug)

    rules.view_write_up(row.state)
    _save(db, row)

    return {"points": rules.points(node, row.state)}


@app.post("/v1/sessions/{session_id}/flag", dependencies=router_dependencies)
def submit_flag(
    session_id: str, body: FlagRequest, db: Session = Depends(get_db),
) -> dict[str, object]:
    row = _get_session_or_404(session_id, db)
    node = _load_node_or_404(row.node_slug)

    correct = rules.check_flag(node, row.state, body.value)
    _save(db, row)

    response: dict[str, object] = {"correct": correct}

    if correct:
        response["points"] = rules.points(node, row.state)

    return response


@app.delete(
    "/v1/sessions/{session_id}",
    dependencies=router_dependencies,
    status_code=status.HTTP_204_NO_CONTENT,
)
def delete_session(session_id: str, db: Session = Depends(get_db)) -> None:
    row = _get_session_or_404(session_id, db)
    db.delete(row)
    db.commit()
