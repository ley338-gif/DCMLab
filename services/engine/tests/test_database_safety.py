"""Regressionstest fuer die Sperre aus docs/adr/0069-test-datenbank-isolation.md
(Testsuite lief einmal gegen die echte Dev-Datenbank). Prueft die Funktion
direkt, ohne selbst eine Datenbankaktion auszuloesen -- dass die Suite
ueberhaupt laeuft, ist bereits der Beweis, dass conftest.py's eigener Aufruf
von assert_database_is_test_database() nicht abgebrochen hat.
"""

import os

import pytest

from tests.database_safety import UnsafeTestDatabaseError, assert_database_is_test_database


def test_it_accepts_sqlite_regardless_of_name() -> None:
    assert_database_is_test_database("sqlite:///:memory:")
    assert_database_is_test_database("sqlite:////tmp/whatever.db")


def test_it_accepts_a_named_test_database() -> None:
    assert_database_is_test_database(
        "postgresql+psycopg://dcmlab:secret@postgres:5432/dcmlab_engine_test",
    )


@pytest.mark.parametrize("database_name", ["dcmlab", "dcmlab_engine"])
def test_it_rejects_known_development_databases(database_name: str) -> None:
    with pytest.raises(UnsafeTestDatabaseError, match="Entwicklungsdatenbank"):
        assert_database_is_test_database(
            f"postgresql+psycopg://dcmlab:secret@postgres:5432/{database_name}",
        )


def test_it_rejects_a_database_name_without_a_test_marker() -> None:
    with pytest.raises(UnsafeTestDatabaseError, match="_test"):
        assert_database_is_test_database(
            "postgresql+psycopg://dcmlab:secret@postgres:5432/dcmlab_staging",
        )


def test_it_rejects_an_empty_database_name() -> None:
    with pytest.raises(UnsafeTestDatabaseError):
        assert_database_is_test_database("postgresql+psycopg://dcmlab:secret@postgres:5432/")


def test_conftest_actually_forces_a_safe_url_for_this_run() -> None:
    # Beweist, dass der Override in conftest.py wirklich gegriffen hat -- und
    # nicht etwa eine reale ENGINE_DATABASE_URL aus dem Container durchsickert.
    assert os.environ["ENGINE_DATABASE_URL"] == "sqlite:///:memory:"
