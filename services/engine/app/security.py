from fastapi import Header, HTTPException, status

from app.config import settings


async def require_internal_key(x_dcmlab_key: str = Header(default="")) -> None:
    """Schuetzt jeden Endpunkt ausser /health (Abschnitt 3.3: internes Secret)."""

    if x_dcmlab_key != settings.internal_key:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="invalid internal key")
