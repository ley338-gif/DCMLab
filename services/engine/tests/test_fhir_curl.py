"""Abschnitt 6l: `curl` gegen simulierte FHIR-/DICOMweb-Ressourcen -- exaktes
(method, path)-Matching gegen node.yml-Fixtures, kein echter HTTP-Server.
"""

from app import rules
from app.content import NodeDefinition

IMAGING_STUDY_BODY = '{"resourceType":"ImagingStudy","id":"img-93821"}'

NODE = NodeDefinition(
    slug="fhir-is-not-wado-test",
    raw={
        "environment": {
            "hosts": [
                {"name": "workstation", "ip": "10.0.0.50", "role": "shell"},
                {
                    "name": "fhir-server", "ip": "10.0.0.30",
                    "resources": [
                        {
                            "path": "/fhir/ImagingStudy/img-93821",
                            "method": "GET",
                            "status": 200,
                            "content_type": "application/fhir+json",
                            "body": IMAGING_STUDY_BODY,
                        },
                    ],
                },
            ],
            "tools": ["curl"],
        },
    },
)


def fresh_state() -> dict:
    return rules.initial_state(NODE)


def test_curl_returns_200_for_declared_imaging_study() -> None:
    state = fresh_state()

    result = rules.exec_command(
        NODE, state, "workstation", "curl -s http://10.0.0.30/fhir/ImagingStudy/img-93821",
    )

    assert result.stdout == IMAGING_STUDY_BODY
    assert result.exit_code == 0


def test_curl_dash_i_shows_404_for_wrong_pixels_path() -> None:
    state = fresh_state()

    result = rules.exec_command(
        NODE, state, "workstation",
        "curl -i http://10.0.0.30/fhir/ImagingStudy/img-93821/pixels",
    )

    assert "HTTP/1.1 404 Not Found" in result.stdout
    assert "OperationOutcome" in result.stdout
    assert result.exit_code == 0


def test_curl_without_dash_i_hides_status_line() -> None:
    state = fresh_state()

    result = rules.exec_command(
        NODE, state, "workstation", "curl http://10.0.0.30/fhir/ImagingStudy/img-93821",
    )

    assert "HTTP/1.1" not in result.stdout
    assert result.stdout == IMAGING_STUDY_BODY


def test_curl_unknown_host_reports_could_not_resolve() -> None:
    state = fresh_state()

    result = rules.exec_command(
        NODE, state, "workstation", "curl http://10.0.0.99/fhir/Patient/pat-4711",
    )

    assert result.exit_code == 6
    assert "Could not resolve host" in result.stdout


def test_curl_is_gated_behind_node_tools_declaration() -> None:
    node = NodeDefinition(
        slug="fhir-no-tools-declared",
        raw={
            "environment": {
                "hosts": [
                    {"name": "workstation", "ip": "10.0.0.50", "role": "shell"},
                    {
                        "name": "fhir-server", "ip": "10.0.0.30",
                        "resources": [
                            {"path": "/fhir/Patient/pat-1", "method": "GET", "status": 200, "body": "{}"},
                        ],
                    },
                ],
            },
        },
    )
    state = rules.initial_state(node)

    result = rules.exec_command(
        node, state, "workstation", "curl http://10.0.0.30/fhir/Patient/pat-1",
    )

    assert result.exit_code == 127
