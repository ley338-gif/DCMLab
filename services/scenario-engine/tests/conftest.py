"""Setzt Testkonfiguration, bevor irgendein Testmodul `app.*` importiert --
`app.config.settings` wird beim Modulimport einmal gebaut, also muessen die
Env-Variablen hier auf Modulebene stehen, nicht in einer Fixture.

SCENARIO_ENGINE_DATABASE_URL wird bewusst ERZWUNGEN (Zuweisung), nicht per
os.environ.setdefault() nur vorbelegt -- siehe services/engine/tests/conftest.py
fuer die volle Begruendung (dasselbe Muster, andere Variable).
assert_database_is_test_database() ist die zweite, unabhaengige
Verteidigungslinie fuer den Fall, dass der Override hier je entfernt oder
umgangen wird.
"""

import os
from pathlib import Path

from tests.database_safety import assert_database_is_test_database

FIXTURES_DIR = Path(__file__).parent / "fixtures" / "content"

os.environ["DCMLAB_INTERNAL_KEY"] = "test-key"
os.environ["SCENARIO_ENGINE_DATABASE_URL"] = "sqlite:///:memory:"
os.environ["CONTENT_PATH"] = str(FIXTURES_DIR)

assert_database_is_test_database(os.environ["SCENARIO_ENGINE_DATABASE_URL"])
