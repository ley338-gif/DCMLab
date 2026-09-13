"""Feature 7 (P10): `dcmdump` zeigt die Transfer Syntax UID eines Objekts
(File Meta Information, Gruppe 0002) -- fuer Nodes, die dieselbe Serie in
mehreren Transfer Syntaxen vergleichen lassen, ohne dass ueberhaupt gesendet
wird (reines Inspizieren, kein Netzwerk-Roundtrip).
"""

from app import rules
from app.content import NodeDefinition

IMPLICIT_VR_LE = "1.2.840.10008.1.2"
JPEG_LOSSLESS = "1.2.840.10008.1.2.4.70"
JPEG_BASELINE = "1.2.840.10008.1.2.4.50"

NODE = NodeDefinition(
    slug="dcmdump-ts-test",
    raw={
        "environment": {
            "hosts": [{"name": "workstation", "ip": "10.0.0.50", "role": "shell"}],
            "tools": ["dcmdump"],
            "objects": [
                {
                    "filename": "unkomprimiert.dcm", "bytes": 524288,
                    "transfer_syntax": IMPLICIT_VR_LE,
                },
                {
                    "filename": "verlustfrei.dcm", "bytes": 350000,
                    "transfer_syntax": JPEG_LOSSLESS,
                },
                {
                    "filename": "komprimiert.dcm", "bytes": 52000,
                    "transfer_syntax": JPEG_BASELINE,
                },
            ],
        },
    },
)


def fresh_state() -> dict:
    return rules.initial_state(NODE)


def test_dcmdump_shows_the_real_transfer_syntax_uid() -> None:
    state = fresh_state()

    result = rules.exec_command(NODE, state, "workstation", "dcmdump komprimiert.dcm")

    assert result.exit_code == 0
    assert "(0002,0010)" in result.stdout
    assert JPEG_BASELINE in result.stdout
    assert "TransferSyntaxUID" in result.stdout


def test_dcmdump_with_both_transfer_syntax_and_sop_class_shows_both() -> None:
    node = NodeDefinition(
        slug="dcmdump-both-fields",
        raw={
            "environment": {
                "hosts": [{"name": "workstation", "ip": "10.0.0.50", "role": "shell"}],
                "tools": ["dcmdump"],
                "objects": [
                    {
                        "filename": "bild.dcm", "bytes": 1024,
                        "transfer_syntax": IMPLICIT_VR_LE,
                        "sop_class": "1.2.840.10008.5.1.4.1.1.2",
                    },
                ],
            },
        },
    )
    state = rules.initial_state(node)

    result = rules.exec_command(node, state, "workstation", "dcmdump bild.dcm")

    assert "(0002,0010)" in result.stdout
    assert "(0008,0016)" in result.stdout


def test_dcmdump_on_an_unknown_file_keeps_the_pre_p10_12_behaviour() -> None:
    state = fresh_state()

    result = rules.exec_command(NODE, state, "workstation", "dcmdump nicht-vorhanden.dcm")

    assert result.exit_code == 1
    assert "keine lokale Datei in dieser Simulation" in result.stderr


def test_dcmdump_shows_lossy_image_compression_flag_when_declared() -> None:
    node = NodeDefinition(
        slug="dcmdump-lossy-flag",
        raw={
            "environment": {
                "hosts": [{"name": "workstation", "ip": "10.0.0.50", "role": "shell"}],
                "tools": ["dcmdump"],
                "objects": [
                    {
                        "filename": "korrekt-geflaggt.dcm", "bytes": 52000,
                        "transfer_syntax": JPEG_BASELINE,
                        "lossy_image_compression": "01",
                    },
                    {
                        "filename": "halbe-sache.dcm", "bytes": 51000,
                        "transfer_syntax": JPEG_BASELINE,
                    },
                ],
            },
        },
    )
    state = rules.initial_state(node)

    flagged = rules.exec_command(node, state, "workstation", "dcmdump korrekt-geflaggt.dcm")
    assert "(0028,2110)" in flagged.stdout
    assert "LossyImageCompression" in flagged.stdout

    unflagged = rules.exec_command(node, state, "workstation", "dcmdump halbe-sache.dcm")
    assert "(0028,2110)" not in unflagged.stdout
    assert "(0002,0010)" in unflagged.stdout


def test_ls_shows_all_three_files_with_real_looking_sizes() -> None:
    state = fresh_state()

    result = rules.exec_command(NODE, state, "workstation", "ls")

    assert "524288" in result.stdout
    assert "350000" in result.stdout
    assert "52000" in result.stdout
