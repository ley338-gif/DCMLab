"""POST /internal/cache/clear (docs/offene-fragen.md, "Cache-Invalidierung
bei Engine/Sandbox nach einer Veroeffentlichung")."""

from fastapi.testclient import TestClient

from app import content
from app.main import app

HEADERS = {"X-DCMLAB-KEY": "test-key"}

client = TestClient(app)


def test_cache_clear_requires_internal_key() -> None:
    response = client.post("/internal/cache/clear")
    assert response.status_code == 401


def test_cache_clear_actually_invalidates_the_datasets_cache(tmp_path) -> None:
    datasets_file = tmp_path / "datasets.yml"
    datasets_file.write_text(
        "d1:\n  patient: A\n  patient_id: '1'\n  study: S\n  series: [X]\n  file_count: 1\n",
    )

    original_path = content.settings.content_path
    content.settings.content_path = str(tmp_path)

    try:
        assert content.load_dataset("d1")["patient"] == "A"

        datasets_file.write_text(
            "d1:\n  patient: B\n  patient_id: '1'\n  study: S\n  series: [X]\n  file_count: 1\n",
        )
        assert content.load_dataset("d1")["patient"] == "A"  # noch gecacht

        response = client.post("/internal/cache/clear", headers=HEADERS)
        assert response.status_code == 200
        assert response.json() == {"status": "cleared"}

        assert content.load_dataset("d1")["patient"] == "B"  # frisch gelesen
    finally:
        content.settings.content_path = original_path
        content.clear_cache()
