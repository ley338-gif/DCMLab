"""Liest Node-Definitionen aus content/ (Abschnitt 3.3, 5.1).

Dieser Dienst kennt Nodes nur ueber node.yml -- genau wie services/engine,
liest ausschliesslich, schreibt nie in content/ (das macht `content:build`
auf der Laravel-Seite). Anders als services/engine kennt eine
NodeDefinition hier keine Hosts/Tools/Datasets, sondern nur den
Entscheidungsbaum unter `scenario:` (Abschnitt 13, PoC "Datenschutz").
"""

from __future__ import annotations

from dataclasses import dataclass
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

    @property
    def scenario_start(self) -> str:
        return str(self.raw.get("scenario", {}).get("start", ""))

    @property
    def scenario_steps(self) -> dict[str, Any]:
        return dict(self.raw.get("scenario", {}).get("steps", {}))

    def step(self, step_id: str) -> dict[str, Any] | None:
        return self.scenario_steps.get(step_id)


def content_root() -> Path:
    return Path(settings.content_path)


def load_node(slug: str) -> NodeDefinition:
    path = content_root() / "nodes" / slug / "node.yml"

    if not path.is_file():
        raise NodeNotFoundError(slug)

    raw = yaml.safe_load(path.read_text(encoding="utf-8")) or {}

    return NodeDefinition(slug=slug, raw=raw)
