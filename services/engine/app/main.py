from fastapi import Depends, FastAPI, HTTPException, status

from app.security import require_internal_key

app = FastAPI(
    title="DCM Lab Engine",
    description="Simulierte Node-Engine. Kennt Nodes und Sitzungen, keine Nutzer (Abschnitt 3.3).",
)


@app.get("/health")
async def health() -> dict[str, str]:
    return {"status": "ok"}


router_dependencies = [Depends(require_internal_key)]


@app.post("/v1/sessions", dependencies=router_dependencies)
async def create_session() -> dict[str, object]:
    # Regelwerk folgt in P4 (Abschnitt 5.3/5.5 des Auftrags).
    raise HTTPException(status.HTTP_501_NOT_IMPLEMENTED)


@app.get("/v1/sessions/{session_id}/state", dependencies=router_dependencies)
async def get_session_state(session_id: str) -> dict[str, object]:
    raise HTTPException(status.HTTP_501_NOT_IMPLEMENTED)


@app.post("/v1/sessions/{session_id}/exec", dependencies=router_dependencies)
async def exec_command(session_id: str) -> dict[str, object]:
    raise HTTPException(status.HTTP_501_NOT_IMPLEMENTED)


@app.post("/v1/sessions/{session_id}/config", dependencies=router_dependencies)
async def set_config(session_id: str) -> dict[str, object]:
    raise HTTPException(status.HTTP_501_NOT_IMPLEMENTED)


@app.post("/v1/sessions/{session_id}/action", dependencies=router_dependencies)
async def trigger_action(session_id: str) -> dict[str, object]:
    raise HTTPException(status.HTTP_501_NOT_IMPLEMENTED)


@app.post("/v1/sessions/{session_id}/flag", dependencies=router_dependencies)
async def submit_flag(session_id: str) -> dict[str, object]:
    raise HTTPException(status.HTTP_501_NOT_IMPLEMENTED)


@app.delete("/v1/sessions/{session_id}", dependencies=router_dependencies)
async def delete_session(session_id: str) -> dict[str, object]:
    raise HTTPException(status.HTTP_501_NOT_IMPLEMENTED)
