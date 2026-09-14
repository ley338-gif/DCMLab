"""Sicherheitssperre gegen dieselbe Vorfallklasse wie in apps/web und
services/engine (siehe dortiges tests/database_safety.py und
docs/adr/0069-test-datenbank-isolation.md): die Testsuite ruft
``Base.metadata.drop_all(bind=engine)`` vor jedem Test auf
(``tests/test_api.py::setup_function``) -- laeuft sie versehentlich gegen
die echte Entwicklungsdatenbank, loescht das alle Sitzungsdaten.

``conftest.py`` erzwingt ``SCENARIO_ENGINE_DATABASE_URL`` bereits auf
Modulebene (nicht per ``setdefault``, siehe dortiger Kommentar). Diese
Funktion ist die zweite, unabhaengige Verteidigungslinie: sie prueft den
tatsaechlich gebundenen Wert, statt sich darauf zu verlassen, dass der
Override wirklich gegriffen hat -- und bricht im Zweifel hart ab (fail
closed).
"""

from __future__ import annotations

KNOWN_DEVELOPMENT_DATABASES = {"dcmlab", "dcmlab_scenario_engine"}


class UnsafeTestDatabaseError(RuntimeError):
    """Die Tests laufen nicht klar isoliert -- Abbruch vor jeder
    Datenbankaktion, statt im Zweifel gegen die echte DB weiterzumachen."""


def assert_database_is_test_database(url: str) -> None:
    if url.startswith("sqlite"):
        # SQLite kann per Bauart nie die echte Postgres-Dev-Datenbank sein --
        # das ist der von conftest.py erzwungene Normalfall.
        return

    database_name = url.rsplit("/", 1)[-1].split("?", 1)[0]

    if database_name == "":
        raise UnsafeTestDatabaseError(
            f"SCENARIO_ENGINE_DATABASE_URL ({url!r}) hat keinen erkennbaren Datenbanknamen.",
        )

    if database_name in KNOWN_DEVELOPMENT_DATABASES:
        raise UnsafeTestDatabaseError(
            f"SCENARIO_ENGINE_DATABASE_URL zeigt auf {database_name!r} -- das ist "
            "eine echte Entwicklungsdatenbank, keine Testdatenbank. Abbruch, bevor "
            "setup_function() Base.metadata.drop_all() darauf ausfuehrt.",
        )

    if "_test" not in database_name.lower():
        raise UnsafeTestDatabaseError(
            f"SCENARIO_ENGINE_DATABASE_URL zeigt auf {database_name!r} -- der Name "
            'enthaelt kein "_test" und identifiziert sich damit nicht '
            "eindeutig als Testdatenbank (Konvention: <name>_test, z. B. "
            "dcmlab_scenario_engine_test).",
        )
