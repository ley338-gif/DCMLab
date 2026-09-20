"""ADR 0120, Phase A: `state["objects"]`/`state["stored_objects"]` muessen
JSON-serialisierbar sein -- die Engine-Session persistiert `state` als JSONB
(app/db.py), keine Dataclass-Instanzen, keine Tupel (werden beim Roundtrip
ohnehin zu Listen, deshalb speichert route_history schon als Liste)."""

import json

from app import rules
from app.content import NodeDefinition

NODE = NodeDefinition(
    slug="serialization-test",
    raw={
        "environment": {
            "hosts": [
                {"name": "workstation", "ip": "10.0.0.50", "role": "shell"},
                {
                    "name": "archive", "ip": "10.0.0.10",
                    "services": [{"port": 104, "ae_title": "ARCHIV"}],
                },
            ],
            "known_calling_aets": ["WORKSTATION"],
            "tools": ["storescu"],
            "objects": [
                {"filename": "bild.dcm", "bytes": 1024, "sop_class": "1.2.840.10008.5.1.4.1.1.2"},
            ],
        },
    },
)


def test_state_with_runtime_objects_survives_a_json_roundtrip() -> None:
    state = rules.initial_state(NODE)
    rules.exec_command(
        NODE, state, "workstation", "storescu -aet WORKSTATION -aec ARCHIV 10.0.0.10 104 bild.dcm",
    )

    roundtripped = json.loads(json.dumps(state))

    assert roundtripped == state
    object_id = next(iter(roundtripped["objects"]))
    assert roundtripped["objects"][object_id]["route_history"] == []
    assert roundtripped["stored_objects"]["archive"] == [object_id]
