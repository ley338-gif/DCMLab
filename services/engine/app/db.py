"""Sitzungszustand in Postgres, nicht im Prozessspeicher (Abschnitt 5.5) --
die Engine muss neu startbar sein, ohne dass ein Lernender seinen Stand
verliert.

`engine_sessions` lebt in derselben Datenbank wie Laravel, aber Laravel
kennt diese Tabelle nicht (Abschnitt 3.3: die Engine kennt keine Nutzer,
Laravel keine DICOM-Semantik) -- deshalb legt die Engine sie selbst an
(`init_db`, idempotent) statt sich auf eine Laravel-Migration zu verlassen.
Siehe docs/adr/0005-engine-session-storage.md.
"""

from __future__ import annotations

import uuid
from collections.abc import Generator
from datetime import datetime
from typing import Any

from sqlalchemy import JSON, DateTime, Engine, String, create_engine, func
from sqlalchemy.dialects.postgresql import JSONB
from sqlalchemy.orm import DeclarativeBase, Mapped, Session, mapped_column, sessionmaker
from sqlalchemy.pool import StaticPool

from app.config import settings


class Base(DeclarativeBase):
    pass


class EngineSession(Base):
    __tablename__ = "engine_sessions"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=lambda: str(uuid.uuid4()))
    node_slug: Mapped[str] = mapped_column(String(255), nullable=False)
    state: Mapped[dict[str, Any]] = mapped_column(
        JSONB().with_variant(JSON(), "sqlite"), nullable=False,
    )
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now())
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(),
    )


def _make_engine() -> Engine:
    url = settings.database_url

    if url.startswith("sqlite"):
        return create_engine(
            url,
            connect_args={"check_same_thread": False},
            poolclass=StaticPool,
        )

    return create_engine(url, pool_pre_ping=True)


engine = _make_engine()
SessionLocal = sessionmaker(bind=engine, expire_on_commit=False)


def init_db() -> None:
    Base.metadata.create_all(bind=engine)


def get_db() -> Generator[Session, None, None]:
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()
