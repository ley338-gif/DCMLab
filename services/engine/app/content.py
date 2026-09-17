"""Liest Node-Definitionen aus content/ (Abschnitt 3.3, 5.1).

Die Engine kennt Nodes nur ueber node.yml -- genau wie apps/web liest sie
ausschliesslich, sie schreibt nie in content/ (das macht `content:build`,
Abschnitt 5.2, auf der Laravel-Seite).
"""

from __future__ import annotations

from dataclasses import dataclass
from functools import lru_cache
from pathlib import Path
from typing import Any

import yaml

from app.config import settings


class NodeNotFoundError(Exception):
    pass


@dataclass(frozen=True)
class NodeDefinition:
    slug: str
    raw: dict[str, Any]

    @property
    def points(self) -> int:
        return int(self.raw.get("points", 0))

    @property
    def hosts(self) -> list[dict[str, Any]]:
        return list(self.raw.get("environment", {}).get("hosts", []))

    def host(self, name: str) -> dict[str, Any] | None:
        return next((h for h in self.hosts if h.get("name") == name), None)

    def host_by_ip(self, ip: str) -> dict[str, Any] | None:
        return next((h for h in self.hosts if h.get("ip") == ip), None)

    @property
    def known_calling_aets(self) -> list[str]:
        return list(self.raw.get("environment", {}).get("known_calling_aets", []))

    @property
    def tools(self) -> list[str]:
        return list(self.raw.get("environment", {}).get("tools", []))

    @property
    def messages(self) -> list[dict[str, Any]]:
        return list(self.raw.get("environment", {}).get("messages", []))

    @property
    def dataset_slug(self) -> str | None:
        slug = self.raw.get("environment", {}).get("dataset")

        return str(slug) if slug is not None else None

    @property
    def hints(self) -> list[dict[str, Any]]:
        return list(self.raw.get("hints", []))

    def hint_cost(self, hint_id: str) -> int | None:
        for hint in self.hints:
            if hint.get("id") == hint_id:
                return int(hint.get("cost", 0))
        return None

    @property
    def flag_hash(self) -> str:
        raw_hash = str(self.raw.get("flag", {}).get("hash", ""))
        return raw_hash.removeprefix("sha256:")

    @property
    def flag_case_sensitive(self) -> bool:
        return bool(self.raw.get("flag", {}).get("case_sensitive", False))

    @property
    def stuck_timeout_minutes(self) -> int:
        return int(self.raw.get("stuck_timeout_minutes", 20))


def content_root() -> Path:
    return Path(settings.content_path)


def load_node(slug: str) -> NodeDefinition:
    path = content_root() / "nodes" / slug / "node.yml"

    if not path.is_file():
        raise NodeNotFoundError(slug)

    raw = yaml.safe_load(path.read_text(encoding="utf-8")) or {}

    return NodeDefinition(slug=slug, raw=raw)


@lru_cache(maxsize=1)
def _datasets_cached(content_path: str) -> dict[str, Any]:
    path = Path(content_path) / "datasets.yml"

    if not path.is_file():
        return {}

    return yaml.safe_load(path.read_text(encoding="utf-8")) or {}


def load_dataset(slug: str) -> dict[str, Any] | None:
    return _datasets_cached(settings.content_path).get(slug)


def clear_cache() -> None:
    """Aufgerufen ueber POST /internal/cache/clear (docs/offene-fragen.md,
    "Cache-Invalidierung bei Engine/Sandbox nach einer Veroeffentlichung")."""

    _datasets_cached.cache_clear()
