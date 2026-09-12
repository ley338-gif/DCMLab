"""Setzt Testkonfiguration, bevor irgendein Testmodul `app.*` importiert --
`app.config.settings` wird beim Modulimport einmal gebaut, also muessen die
Env-Variablen hier auf Modulebene stehen, nicht in einer Fixture.
"""

import os
from pathlib import Path

FIXTURES_DIR = Path(__file__).parent / "fixtures" / "content"

os.environ.setdefault("DCMLAB_INTERNAL_KEY", "test-key")
os.environ.setdefault("ENGINE_DATABASE_URL", "sqlite:///:memory:")
os.environ.setdefault("CONTENT_PATH", str(FIXTURES_DIR))
