"""Setzt Testkonfiguration, bevor irgendein Testmodul `app.*` importiert --
`app.config.settings` wird beim Modulimport einmal gebaut, also muessen die
Env-Variablen hier auf Modulebene stehen, nicht in einer Fixture.

ENGINE_DATABASE_URL wird bewusst ERZWUNGEN (Zuweisung), nicht per
os.environ.setdefault() nur vorbelegt: setdefault() ist ein No-Op, sobald die
Variable schon eine echte Prozessvariable ist -- und genau das ist sie immer,
wenn die Suite im engine-Container laeuft (infra/docker-compose.yml setzt
ENGINE_DATABASE_URL dort fest auf die echte Postgres-Dev-Datenbank). Mit
setdefault() wuerde tests/test_api.py::setup_function() dann
Base.metadata.drop_all() gegen diese echte Datenbank ausfuehren -- dieselbe
Vorfallklasse wie bei der Laravel-Suite (siehe
apps/web/tests/Support/DatabaseSafety.php und
docs/adr/0069-test-datenbank-isolation.md). assert_database_is_test_database()
ist die zweite, unabhaengige Verteidigungslinie fuer den Fall, dass der
Override hier je entfernt oder umgangen wird.
"""

import os
from pathlib import Path

from tests.database_safety import assert_database_is_test_database

FIXTURES_DIR = Path(__file__).parent / "fixtures" / "content"

os.environ["DCMLAB_INTERNAL_KEY"] = "test-key"
os.environ["ENGINE_DATABASE_URL"] = "sqlite:///:memory:"
os.environ["CONTENT_PATH"] = str(FIXTURES_DIR)

assert_database_is_test_database(os.environ["ENGINE_DATABASE_URL"])
