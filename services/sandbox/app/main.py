from fastapi import Depends, FastAPI, HTTPException, status

from app.security import require_internal_key

app = FastAPI(
    title="DCM Lab Sandbox Orchestrator",
    description=(
        "Startet und raeumt die Container-Paare der Spielwiese. Einziger Dienst mit "
        "Zugriff auf den Docker-Socket (Abschnitt 3.3)."
    ),
)


@app.get("/health")
async def health() -> dict[str, str]:
    return {"status": "ok"}


router_dependencies = [Depends(require_internal_key)]


@app.post("/v1/sandboxes", dependencies=router_dependencies)
async def create_sandbox() -> dict[str, object]:
    # Container-Orchestrierung, Warteschlange und Kontingent folgen in P7 (Abschnitt 6).
    raise HTTPException(status.HTTP_501_NOT_IMPLEMENTED)


@app.get("/v1/sandboxes/{sandbox_id}", dependencies=router_dependencies)
async def get_sandbox(sandbox_id: str) -> dict[str, object]:
    raise HTTPException(status.HTTP_501_NOT_IMPLEMENTED)


@app.delete("/v1/sandboxes/{sandbox_id}", dependencies=router_dependencies)
async def delete_sandbox(sandbox_id: str) -> dict[str, object]:
    raise HTTPException(status.HTTP_501_NOT_IMPLEMENTED)
