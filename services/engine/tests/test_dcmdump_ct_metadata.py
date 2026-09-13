"""Feature 8 (P10): weitere reale `dcmdump`-Felder fuer CT-Metadaten
(AcquisitionDate, SliceThickness, ConvolutionKernel) -- fuer Nodes, in denen
Werte per Tag-Name gesucht werden sollen, ohne dass die Tag-Nummer vorher
bekannt ist (Lektion 1.3).
"""

from app import rules
from app.content import NodeDefinition

NODE = NodeDefinition(
    slug="ct-metadata-node",
    raw={
        "environment": {
            "hosts": [{"name": "workstation", "ip": "10.0.0.50", "role": "shell"}],
            "tools": ["dcmdump"],
            "objects": [
                {
                    "filename": "schicht-0001.dcm", "bytes": 524288,
                    "acquisition_date": "20260910",
                    "slice_thickness": "3.0",
                    "convolution_kernel": "B60f",
                },
            ],
        },
    },
)


def fresh_state() -> dict:
    return rules.initial_state(NODE)


def test_dcmdump_shows_all_three_ct_metadata_fields_in_ascending_tag_order() -> None:
    state = fresh_state()

    result = rules.exec_command(NODE, state, "workstation", "dcmdump schicht-0001.dcm")

    assert result.stdout == (
        "I: (0008,0022) DA [20260910]  # xx, 1 AcquisitionDate\n"
        "I: (0018,0050) DS [3.0]  # xx, 1 SliceThickness\n"
        "I: (0018,1210) SH [B60f]  # xx, 1 ConvolutionKernel\n"
    )
