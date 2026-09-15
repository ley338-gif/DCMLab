import asyncio
from collections.abc import AsyncIterator
from contextlib import asynccontextmanager

import docker
import redis
from fastapi import Depends, FastAPI, HTTPException, status

from app import datasets_yaml, orchestrator, worklists_yaml
from app.config import settings
from app.security import require_internal_key

_redis_client: redis.Redis | None = None
_docker_client: docker.DockerClient | None = None
_cleanup_task: asyncio.Task[None] | None = None


def get_redis() -> redis.Redis:
    global _redis_client
    if _redis_client is None:
        _redis_client = redis.Redis.from_url(settings.redis_url)
    return _redis_client


def get_docker() -> docker.DockerClient:
    global _docker_client
    if _docker_client is None:
        _docker_client = docker.from_env()
    return _docker_client


async def _cleanup_loop() -> None:
    while True:
        await asyncio.sleep(settings.cleanup_interval_seconds)
        try:
            await asyncio.to_thread(orchestrator.run_cleanup_once, get_redis(), get_docker())
        except Exception:  # noqa: BLE001 - ein Fehlschlag darf die Schleife nie stoppen
            pass


@asynccontextmanager
async def lifespan(_app: FastAPI) -> AsyncIterator[None]:
    global _cleanup_task
    _cleanup_task = asyncio.create_task(_cleanup_loop())
    yield
    _cleanup_task.cancel()


app = FastAPI(
    title="DCM Lab Sandbox Orchestrator",
    description=(
        "Startet und raeumt die Container-Paare der Spielwiese. Einziger Dienst mit "
        "Zugriff auf den Docker-Socket (Abschnitt 3.3)."
    ),
    lifespan=lifespan,
)


@app.get("/health")
async def health() -> dict[str, str]:
    return {"status": "ok"}


router_dependencies = [Depends(require_internal_key)]


@app.post("/v1/sandboxes", dependencies=router_dependencies, status_code=status.HTTP_201_CREATED)
async def create_sandbox(body: dict[str, str]) -> dict[str, object]:
    user_id = body["user_id"]
    dataset_slug = body["dataset_slug"]

    try:
        view = await asyncio.to_thread(
            orchestrator.create_sandbox,
            get_redis(),
            get_docker(),
            user_id=user_id,
            dataset_slug=dataset_slug,
        )
    except orchestrator.QuotaExceededError as exc:
        raise HTTPException(
            status.HTTP_429_TOO_MANY_REQUESTS, detail="daily quota exceeded",
        ) from exc

    return _view_response(view)


def _view_response(view: orchestrator.SandboxView) -> dict[str, object]:
    return {
        "status": view.status,
        "sandbox_id": view.sandbox_id,
        "queue_position": view.queue_position,
    }


@app.get("/v1/sandboxes/{sandbox_id}", dependencies=router_dependencies)
async def get_sandbox(sandbox_id: str) -> dict[str, object]:
    try:
        view = await asyncio.to_thread(orchestrator.get_sandbox, get_redis(), sandbox_id)
    except orchestrator.SandboxNotFoundError as exc:
        raise HTTPException(status.HTTP_404_NOT_FOUND, detail="sandbox not found") from exc

    return _view_response(view)


@app.post("/v1/sandboxes/{sandbox_id}/exec", dependencies=router_dependencies)
async def exec_command(sandbox_id: str, body: dict[str, str]) -> dict[str, object]:
    try:
        result = await asyncio.to_thread(
            orchestrator.exec_command,
            get_redis(),
            get_docker(),
            sandbox_id=sandbox_id,
            command=body["command"],
        )
    except orchestrator.SandboxNotFoundError as exc:
        raise HTTPException(status.HTTP_404_NOT_FOUND, detail="sandbox not found") from exc

    return result


@app.delete(
    "/v1/sandboxes/{sandbox_id}",
    dependencies=router_dependencies,
    status_code=status.HTTP_204_NO_CONTENT,
)
async def delete_sandbox(sandbox_id: str) -> None:
    await asyncio.to_thread(
        orchestrator.delete_sandbox, get_redis(), get_docker(), sandbox_id=sandbox_id,
    )


@app.post("/internal/cache/clear", dependencies=router_dependencies)
async def clear_cache() -> dict[str, str]:
    """Loest die offene Frage "Cache-Invalidierung bei Engine/Sandbox nach
    einer Veroeffentlichung" (docs/offene-fragen.md): aufgerufen von
    HttpCacheInvalidator (Laravel-Seite) nach jeder Content-Veroeffentlichung.
    """
    datasets_yaml.clear_cache()
    worklists_yaml.clear_cache()
    return {"status": "cleared"}
