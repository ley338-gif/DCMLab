"""POST /internal/cache/clear (docs/offene-fragen.md, "Cache-Invalidierung
bei Engine/Sandbox nach einer Veroeffentlichung")."""

from fastapi.testclient import TestClient

from app import datasets_yaml, worklists_yaml
from app.config import settings
from app.main import app

HEADERS = {"X-DCMLAB-KEY": settings.internal_key}

client = TestClient(app)


def test_cache_clear_requires_internal_key() -> None:
    response = client.post("/internal/cache/clear")
    assert response.status_code == 401


def test_cache_clear_actually_invalidates_the_datasets_cache(tmp_path) -> None:
    datasets_file = tmp_path / "datasets.yml"
    datasets_file.write_text(
        "d1:\n  patient: A\n  patient_id: '1'\n  study: S\n  series: [X]\n  file_count: 1\n",
    )

    original_path = settings.content_path
    settings.content_path = str(tmp_path)

    try:
        assert datasets_yaml.load_dataset_params("d1")["patient"] == "A"

        datasets_file.write_text(
            "d1:\n  patient: B\n  patient_id: '1'\n  study: S\n  series: [X]\n  file_count: 1\n",
        )
        assert datasets_yaml.load_dataset_params("d1")["patient"] == "A"  # noch gecacht

        response = client.post("/internal/cache/clear", headers=HEADERS)
        assert response.status_code == 200
        assert response.json() == {"status": "cleared"}

        assert datasets_yaml.load_dataset_params("d1")["patient"] == "B"  # frisch gelesen
    finally:
        settings.content_path = original_path
        datasets_yaml.clear_cache()


def test_cache_clear_actually_invalidates_the_worklists_cache(tmp_path) -> None:
    worklists_file = tmp_path / "worklists.yml"
    worklist_yaml = (
        "w1:\n"
        "  patient: A\n"
        "  patient_id: '1'\n"
        "  accession_number: ACC1\n"
        "  requested_procedure_description: X\n"
        "  referring_physician: Y\n"
        "  modality: CT\n"
        "  scheduled_station_ae_title: AE1\n"
        "  scheduled_station_name: ST1\n"
        "  scheduled_procedure_step_description: Z\n"
        "  scheduled_procedure_step_id: '1'\n"
    )
    worklists_file.write_text(worklist_yaml)

    original_path = settings.content_path
    settings.content_path = str(tmp_path)

    try:
        assert worklists_yaml.load_worklist_params("w1")["patient"] == "A"

        worklists_file.write_text(worklist_yaml.replace("patient: A", "patient: B"))
        assert worklists_yaml.load_worklist_params("w1")["patient"] == "A"  # noch gecacht

        response = client.post("/internal/cache/clear", headers=HEADERS)
        assert response.status_code == 200

        assert worklists_yaml.load_worklist_params("w1")["patient"] == "B"  # frisch gelesen
    finally:
        settings.content_path = original_path
        worklists_yaml.clear_cache()
