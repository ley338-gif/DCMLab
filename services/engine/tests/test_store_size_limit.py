"""Feature 2 (P10): Groessenlimit beim C-STORE. Konstruiert die Node direkt
(keine Fixture-Datei noetig), um die Regel isoliert vom Content zu pruefen.
"""

from app import rules
from app.content import NodeDefinition

NODE = NodeDefinition(
    slug="size-test",
    raw={
        "environment": {
            "hosts": [
                {"name": "workstation", "ip": "10.0.0.50", "role": "shell"},
                {
                    "name": "archive",
                    "ip": "10.0.0.10",
                    "services": [
                        {
                            "port": 104, "ae_title": "TEST-ARCHIV",
                            "accepts": ["ct-image-storage"], "max_object_bytes": 50_000_000,
                        },
                    ],
                },
            ],
            "known_calling_aets": ["WORKSTATION"],
            "tools": ["storescu"],
            "objects": [
                {"filename": "klein.dcm", "bytes": 524_288},
                {"filename": "gross.dcm", "bytes": 78_643_200},
            ],
        },
    },
)


def fresh_state() -> dict:
    return rules.initial_state(NODE)


def _store(state: dict, filename: str) -> rules.ExecResult:
    return rules.exec_command(
        NODE, state, "workstation",
        f"storescu -aet WORKSTATION -aec TEST-ARCHIV 10.0.0.10 104 {filename}",
    )


def test_an_object_within_the_limit_is_stored() -> None:
    state = fresh_state()

    result = _store(state, "klein.dcm")

    assert result.exit_code == 0
    assert state["bestand"]["archive"] == {"studies": 1, "series": 1, "instances": 1}


def test_an_object_over_the_limit_is_refused_and_not_counted() -> None:
    state = fresh_state()

    result = _store(state, "gross.dcm")

    assert result.exit_code == 1
    assert "0xa700" in result.stderr
    assert "Refused: Out of Resources" in result.stderr
    assert state["bestand"]["archive"] == {"studies": 0, "series": 0, "instances": 0}


def test_only_the_object_within_the_limit_ends_up_in_the_archive() -> None:
    state = fresh_state()

    _store(state, "gross.dcm")
    _store(state, "klein.dcm")

    assert state["bestand"]["archive"] == {"studies": 1, "series": 1, "instances": 1}


def test_ls_shows_real_looking_file_sizes_before_any_store_attempt() -> None:
    state = fresh_state()

    result = rules.exec_command(NODE, state, "workstation", "ls")

    assert "524288" in result.stdout
    assert "78643200" in result.stdout


def test_unknown_filename_is_reported_as_missing() -> None:
    state = fresh_state()

    result = _store(state, "nicht-vorhanden.dcm")

    assert result.exit_code == 1
    assert "No such file or directory" in result.stderr


def test_nodes_without_objects_keep_the_pre_p10_behaviour() -> None:
    """Silent CT & Co: Bilder liegen auf der Modalitaet, storescu von der
    Workstation aus scheitert strukturell (Abschnitt 5.3), unveraendert."""
    node = NodeDefinition(
        slug="no-objects",
        raw={
            "environment": {
                "hosts": [{"name": "workstation", "role": "shell"}],
                "tools": ["storescu"],
            },
        },
    )
    state = rules.initial_state(node)

    result = rules.exec_command(node, state, "workstation", "storescu 10.0.0.10 104 x.dcm")

    assert result.exit_code == 1
    assert result.stderr == "storescu: No such file or directory"
