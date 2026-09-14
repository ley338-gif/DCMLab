"""Sitzungszustand in Postgres, nicht im Prozessspeicher (Abschnitt 5.5) --
die Engine muss neu startbar sein, ohne dass ein Lernender seinen Stand
verliert.

`scenario_sessions` lebt in derselben Datenbank wie Laravel und die
DICOM-Engine, aber Laravel kennt diese Tabelle nicht -- deshalb legt dieser
Dienst sie selbst an (`init_db`, idempotent), genau wie `services/engine`
das fuer `engine_sessions` tut (siehe dortiges db.py und
docs/adr/0005-p4-node-engine-entscheidungen.md, das denselben Trade-off
schon akzeptiert).
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


class ScenarioSession(Base):
    __tablename__ = "scenario_sessions"

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
