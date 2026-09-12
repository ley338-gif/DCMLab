"""Das Regelwerk der simulierten Engine (Abschnitt 5.3) -- reine Funktionen,
die auf dem Session-`state`-Dict arbeiten. Keine echte DICOM-Implementierung
(Abschnitt 11): Befehle werden als Text geparst und simuliert, nie ausgefuehrt.
"""

from __future__ import annotations

import hashlib
import shlex
from dataclasses import dataclass, field
from datetime import UTC, datetime
from typing import Any

from app import find
from app.content import NodeDefinition, load_dataset

NON_NETWORK_COMMANDS = {"ping", "ls", "cat", "echo", "clear", "help"}

# DCMTK-Voreinstellungen, wenn -aet/-aec fehlen (Abschnitt 5.3).
DEFAULT_AET = {"echoscu": "ECHOSCU", "findscu": "FINDSCU", "storescu": "STORESCU"}
DEFAULT_AEC = "ANY-SCP"

# Rule 1/2: reine TCP-Ebene, vor jeder DICOM-Verhandlung -- kein Zaehler ruehrt sich.
MSG_HOST_UNKNOWN = "TCP Initialization Error: Connection timed out"
MSG_WRONG_PORT = "Connection refused"


def _rejected_block(reason: str) -> str:
    return (
        "F: Association Rejected:\n"
        "F:   Result: Rejected Permanent, Source: Service User\n"
        f"F:   Reason: {reason}"
    )


MSG_CALLED_AE_REJECTED = _rejected_block("Called AE Title Not Recognized")
MSG_CALLING_AE_REJECTED = _rejected_block("Calling AE Title Not Recognized")

MSG_SERIES_WITHOUT_STUDY = (
    "E: Find Failed, query keys:\nE: Status: 0xa900 Identifier does not match SOP Class"
)
MSG_PLACEHOLDER_LEFT_IN_QUERY = (
    "Ungueltiger Wert fuer VR UI: nur Ziffern und Punkte erlaubt "
    '(Platzhalter "{placeholder}" wurde nicht ersetzt).'
)


@dataclass(frozen=True)
class AssociationResult:
    accepted: bool
    message: str
    target_host: str | None
    reason: str


def now_iso() -> str:
    return datetime.now(UTC).isoformat()


def initial_state(node: NodeDefinition) -> dict[str, Any]:
    archive_host = next((h for h in node.hosts if h.get("services")), None)
    editable_hosts = {
        h["name"]: dict(h.get("config", {})) for h in node.hosts if h.get("config_editable")
    }

    dataset = load_dataset(node.dataset_slug) if node.dataset_slug else None
    file_count = int(dataset.get("file_count", 0)) if dataset else 0

    state: dict[str, Any] = {
        "config": editable_hosts,
        "counters": {},
        "bestand": {},
        "hints_used": [],
        "write_up_seen": False,
        "write_up_seen_before_solve": False,
        "solved": False,
        "created_at": now_iso(),
        "last_progress_at": now_iso(),
        "_dataset_file_count": file_count,
    }

    if archive_host is not None:
        state["counters"][archive_host["name"]] = {"accepted": 0, "rejected": 0}
        state["bestand"][archive_host["name"]] = {"studies": 0, "series": 0, "instances": 0}

    return state


def _touch_progress(state: dict[str, Any]) -> None:
    state["last_progress_at"] = now_iso()


def is_stuck(node: NodeDefinition, state: dict[str, Any], now: datetime | None = None) -> bool:
    if state.get("solved"):
        return False

    now = now or datetime.now(UTC)
    last_progress = datetime.fromisoformat(state["last_progress_at"])
    elapsed_minutes = (now - last_progress).total_seconds() / 60

    return elapsed_minutes >= node.stuck_timeout_minutes


def points(node: NodeDefinition, state: dict[str, Any]) -> int:
    if state.get("write_up_seen_before_solve"):
        return 0

    spent = sum(node.hint_cost(hint_id) or 0 for hint_id in state.get("hints_used", []))

    return max(node.points - spent, 0)


def public_state(node: NodeDefinition, state: dict[str, Any]) -> dict[str, Any]:
    """Zustand fuer die Oberfläche -- lässt aus, was der Lernende selbst
    herausfinden soll (Abschnitt 5.4: `config_visible: false`)."""

    hosts: dict[str, Any] = {}

    for host in node.hosts:
        name = host["name"]
        entry: dict[str, Any] = {"ip": host["ip"]}

        if host.get("role"):
            entry["role"] = host["role"]

        if host.get("services"):
            visible = host.get("config_visible", True)
            entry["services"] = [
                {k: v for k, v in service.items() if visible or k != "ae_title"}
                for service in host["services"]
            ]
            entry["counters"] = state["counters"].get(name)
            entry["bestand"] = state["bestand"].get(name)

        if host.get("config_editable"):
            entry["config_editable"] = True
            entry["config"] = state["config"].get(name, {})

        hosts[name] = entry

    return {
        "node_slug": node.slug,
        "hosts": hosts,
        "hints_used": state.get("hints_used", []),
        "write_up_seen": state.get("write_up_seen", False),
        "solved": state.get("solved", False),
        "points": points(node, state),
        "stuck": is_stuck(node, state),
        "created_at": state.get("created_at"),
    }


def check_association(
    node: NodeDefinition,
    state: dict[str, Any],
    calling_ae: str,
    target_ae: str,
    target_ip: str,
    target_port: int,
) -> AssociationResult:
    target_host = node.host_by_ip(target_ip)

    if target_host is None or not target_host.get("services"):
        return AssociationResult(False, MSG_HOST_UNKNOWN, None, "host_unreachable")

    service = next(
        (s for s in target_host["services"] if s.get("port") == target_port), None,
    )

    if service is None:
        return AssociationResult(False, MSG_WRONG_PORT, target_host["name"], "wrong_port")

    name = target_host["name"]

    if service.get("ae_title") != target_ae:
        _bump_counter(state, name, "rejected")

        return AssociationResult(
            False, MSG_CALLED_AE_REJECTED, name, "called_ae_not_recognized",
        )

    if calling_ae not in node.known_calling_aets:
        _bump_counter(state, name, "rejected")

        return AssociationResult(
            False, MSG_CALLING_AE_REJECTED, name, "calling_ae_not_recognized",
        )

    _bump_counter(state, name, "accepted")

    return AssociationResult(True, "", name, "accepted")


def _bump_counter(state: dict[str, Any], host: str, key: str) -> None:
    state.setdefault("counters", {}).setdefault(host, {"accepted": 0, "rejected": 0})
    state["counters"][host][key] += 1


@dataclass
class ExecResult:
    stdout: str = ""
    stderr: str = ""
    exit_code: int = 0
    events: list[dict[str, Any]] = field(default_factory=list)


def exec_command(
    node: NodeDefinition, state: dict[str, Any], host_name: str, command: str,
) -> ExecResult:
    host = node.host(host_name)

    if host is None:
        return ExecResult(stderr="Unbekannter Host.", exit_code=1)

    tokens = shlex.split(command.strip())

    if not tokens:
        return ExecResult()

    tool, args = tokens[0], tokens[1:]

    known_tools = {"echoscu", "storescu", "findscu", "dcmdump"}
    if tool not in NON_NETWORK_COMMANDS and tool not in known_tools:
        return ExecResult(stderr=f"{tool}: command not found", exit_code=127)

    if tool in {"echoscu", "storescu", "findscu"} and tool not in node.tools:
        return ExecResult(stderr=f"{tool}: command not found", exit_code=127)

    if host.get("role") != "shell" and tool not in NON_NETWORK_COMMANDS:
        return ExecResult(stderr="Auf diesem Host gibt es keine Shell.", exit_code=126)

    if tool == "ping":
        return _exec_ping(node, args)
    if tool == "ls":
        return _exec_ls(node)
    if tool == "cat":
        return _exec_cat(node, args)
    if tool == "help":
        return _exec_help(node)
    if tool == "clear":
        return ExecResult()
    if tool == "echo":
        return _exec_echo(state, args)
    if tool == "echoscu":
        return _exec_echoscu(node, state, args)
    if tool == "findscu":
        return _exec_findscu(node, state, args)
    if tool == "storescu":
        return _exec_storescu(host_name)
    if tool == "dcmdump":
        return ExecResult(stderr="dcmdump: keine lokale Datei in dieser Simulation.", exit_code=1)

    return ExecResult(stderr=f"{tool}: command not found", exit_code=127)


def _exec_ping(node: NodeDefinition, args: list[str]) -> ExecResult:
    if not args:
        return ExecResult(stderr="usage: ping <ip>", exit_code=1)

    target_ip = args[-1]

    if node.host_by_ip(target_ip) is None:
        return ExecResult(
            stdout=f"ping: {target_ip}: Destination Host Unreachable", exit_code=1,
        )

    return ExecResult(
        stdout=f"64 bytes from {target_ip}: icmp_seq=1 ttl=64 time=0.31 ms", exit_code=0,
    )


def _exec_ls(node: NodeDefinition) -> ExecResult:
    files = node.raw.get("environment", {}).get("files", [])

    return ExecResult(stdout="\n".join(files))


def _exec_cat(node: NodeDefinition, args: list[str]) -> ExecResult:
    if not args:
        return ExecResult(stderr="usage: cat <datei>", exit_code=1)

    filename = args[0]
    files = node.raw.get("environment", {}).get("files", [])

    if filename not in files:
        return ExecResult(stderr=f"cat: {filename}: No such file or directory", exit_code=1)

    from app.content import content_root  # lokal, um Zirkelimporte zu vermeiden

    asset_path = content_root() / "nodes" / node.slug / "assets" / filename

    if not asset_path.is_file():
        return ExecResult(
            stdout=f"[Inhalt von {filename} noch nicht hinterlegt -- siehe docs/content-todo.md]",
        )

    return ExecResult(stdout=asset_path.read_text(encoding="utf-8").rstrip("\n"))


def _exec_help(node: NodeDefinition) -> ExecResult:
    commands = sorted(NON_NETWORK_COMMANDS | set(node.tools))

    return ExecResult(stdout="Verfuegbare Befehle: " + ", ".join(commands))


def _exec_echo(state: dict[str, Any], args: list[str]) -> ExecResult:
    if args == ["$?"]:
        return ExecResult(stdout=str(state.get("_last_exit_code", 0)))

    return ExecResult(stdout=" ".join(args))


def _parse_dcmtk_args(tool: str, args: list[str]) -> dict[str, Any]:
    aet = DEFAULT_AET.get(tool, tool.upper())
    aec = DEFAULT_AEC
    keys: dict[str, str | None] = {}
    query_root: str | None = None
    positional: list[str] = []

    i = 0
    while i < len(args):
        token = args[i]

        if token == "-aet":
            i += 1
            aet = args[i]
        elif token == "-aec":
            i += 1
            aec = args[i]
        elif token == "-S":
            query_root = "STUDY"
        elif token == "-P":
            query_root = "PATIENT"
        elif token == "-k":
            i += 1
            key_expr = args[i]
            if "=" in key_expr:
                key, value = key_expr.split("=", 1)
                keys[key] = value
            else:
                keys[key_expr] = None
        elif not token.startswith("-"):
            positional.append(token)

        i += 1

    ip = positional[0] if len(positional) >= 1 else None
    port = int(positional[1]) if len(positional) >= 2 else None

    return {"aet": aet, "aec": aec, "query_root": query_root, "keys": keys, "ip": ip, "port": port}


def _exec_echoscu(node: NodeDefinition, state: dict[str, Any], args: list[str]) -> ExecResult:
    parsed = _parse_dcmtk_args("echoscu", args)

    if parsed["ip"] is None or parsed["port"] is None:
        return ExecResult(stderr="usage: echoscu [-aet ...] [-aec ...] <peer> <port>", exit_code=1)

    result = check_association(
        node, state, parsed["aet"], parsed["aec"], parsed["ip"], parsed["port"],
    )

    if result.accepted:
        _touch_progress(state)

        return ExecResult(exit_code=0)

    return ExecResult(stderr=result.message, exit_code=1)


def _study_instance_uid(node: NodeDefinition) -> str:
    digest = hashlib.sha1(f"{node.slug}:study".encode()).hexdigest()

    return "1.2.276.0.7230010.3.1.4." + str(int(digest[:12], 16)).rjust(12, "0")[:12]


def _series_instance_uid(node: NodeDefinition) -> str:
    digest = hashlib.sha1(f"{node.slug}:series".encode()).hexdigest()

    return "1.2.276.0.7230010.3.1.3." + str(int(digest[:12], 16)).rjust(12, "0")[:12]


def _dcmtk_line(tag: str, vr: str, value: str, keyword: str) -> str:
    return f"I: ({tag}) {vr} [{value}]  # xx, 1 {keyword}\n"


def _exec_findscu(node: NodeDefinition, state: dict[str, Any], args: list[str]) -> ExecResult:
    parsed = _parse_dcmtk_args("findscu", args)

    if parsed["ip"] is None or parsed["port"] is None:
        return ExecResult(stderr="usage: findscu [-S|-P] -k ... <peer> <port>", exit_code=1)

    result = check_association(
        node, state, parsed["aet"], parsed["aec"], parsed["ip"], parsed["port"],
    )

    if not result.accepted:
        return ExecResult(stderr=result.message, exit_code=1)

    _touch_progress(state)

    level = parsed["keys"].get("QueryRetrieveLevel")
    archive_name = result.target_host
    archive_host = node.host(archive_name) if archive_name else None
    records = archive_host.get("records", []) if archive_host else []

    if records:
        return _exec_findscu_against_records(records, level, parsed["keys"])

    bestand = state["bestand"].get(
        archive_name, {"studies": 0, "series": 0, "instances": 0},
    )

    if level == "SERIES":
        study_uid = parsed["keys"].get("StudyInstanceUID")

        if not study_uid:
            return ExecResult(stderr=MSG_SERIES_WITHOUT_STUDY, exit_code=1)

        if study_uid == "UID_AUS_SCHRITT_1":
            return ExecResult(
                stderr=MSG_PLACEHOLDER_LEFT_IN_QUERY.format(placeholder="UID_AUS_SCHRITT_1"),
                exit_code=1,
            )

        if bestand["series"] == 0 or study_uid != _study_instance_uid(node):
            return ExecResult(stdout="I: Number of Matches: 0")

        dataset = load_dataset(node.dataset_slug) if node.dataset_slug else None
        series_description = (dataset or {}).get("series", ["?"])[0]

        lines = [
            _dcmtk_line("0008,0052", "CS", "SERIES", "QueryRetrieveLevel"),
            _dcmtk_line("0020,000e", "UI", _series_instance_uid(node), "SeriesInstanceUID"),
            _dcmtk_line("0008,103e", "LO", series_description, "SeriesDescription"),
        ]

        stdout = "I: # Dicom-Data-Set\n" + "".join(lines) + "I: Number of Matches: 1"

        return ExecResult(stdout=stdout)

    # STUDY-Ebene (Default, sofern kein anderes Level angegeben ist)
    if bestand["studies"] == 0:
        return ExecResult(stdout="I: Number of Matches: 0")

    dataset = load_dataset(node.dataset_slug) if node.dataset_slug else None
    dataset = dataset or {}
    lines = [
        _dcmtk_line("0008,0052", "CS", "STUDY", "QueryRetrieveLevel"),
        _dcmtk_line("0010,0020", "LO", dataset.get("patient_id", "?"), "PatientID"),
        _dcmtk_line("0020,000d", "UI", _study_instance_uid(node), "StudyInstanceUID"),
        _dcmtk_line("0008,1030", "LO", dataset.get("study", "?"), "StudyDescription"),
    ]

    return ExecResult(stdout="I: # Dicom-Data-Set\n" + "".join(lines) + "I: Number of Matches: 1")


def _exec_findscu_against_records(
    records: list[dict[str, Any]], level: str | None, keys: dict[str, str | None],
) -> ExecResult:
    """C-FIND gegen vordefinierte Archiv-Records (P10, Feature 1) -- echtes
    Matching statt nur "wurde vorher etwas gesendet" (siehe `app/find.py`).
    """
    if level == "SERIES":
        study_uid = keys.get("StudyInstanceUID")

        if not study_uid:
            return ExecResult(stderr=MSG_SERIES_WITHOUT_STUDY, exit_code=1)

        series_matches = find.find_series(records, study_uid, keys)

        blocks = [
            "".join([
                _dcmtk_line("0008,0052", "CS", "SERIES", "QueryRetrieveLevel"),
                _dcmtk_line(
                    "0020,000e", "UI", series.get("series_uid", "?"), "SeriesInstanceUID",
                ),
                _dcmtk_line(
                    "0008,103e", "LO", series.get("series_description", "?"), "SeriesDescription",
                ),
            ])
            for series in series_matches
        ]

        stdout = "".join(f"I: # Dicom-Data-Set\n{block}" for block in blocks)
        stdout += f"I: Number of Matches: {len(series_matches)}"

        return ExecResult(stdout=stdout)

    # STUDY-Ebene (Default, sofern kein anderes Level angegeben ist)
    study_matches = find.find_studies(records, keys)

    field_order = [
        ("patient_id", "0010,0020", "LO", "PatientID"),
        ("patient_name", "0010,0010", "PN", "PatientName"),
        ("study_uid", "0020,000d", "UI", "StudyInstanceUID"),
        ("study_description", "0008,1030", "LO", "StudyDescription"),
        ("study_date", "0008,0020", "DA", "StudyDate"),
    ]

    blocks = []
    for study in study_matches:
        lines = [_dcmtk_line("0008,0052", "CS", "STUDY", "QueryRetrieveLevel")]
        lines += [
            _dcmtk_line(tag, vr, study[field], keyword)
            for field, tag, vr, keyword in field_order
            if field in study
        ]
        blocks.append("".join(lines))

    stdout = "".join(f"I: # Dicom-Data-Set\n{block}" for block in blocks)
    stdout += f"I: Number of Matches: {len(study_matches)}"

    return ExecResult(stdout=stdout)


def _exec_storescu(host_name: str) -> ExecResult:
    # Abschnitt 5.3: Die Bilder liegen auf der Modalitaet, nicht auf der
    # Workstation -- storescu von dort scheitert strukturell an der Datei.
    return ExecResult(stderr="storescu: No such file or directory", exit_code=1)


def set_config(
    node: NodeDefinition, state: dict[str, Any], host_name: str, field_name: str, value: str,
) -> str | None:
    """Gibt eine Fehlermeldung zurueck, oder None bei Erfolg."""

    host = node.host(host_name)

    if host is None or not host.get("config_editable"):
        return "Diese Konfiguration ist gesperrt."

    if field_name not in host.get("config", {}):
        return f'Unbekanntes Feld "{field_name}".'

    state.setdefault("config", {}).setdefault(host_name, {})[field_name] = value
    _touch_progress(state)

    return None


@dataclass
class ActionResult:
    log: list[str] = field(default_factory=list)
    events: list[dict[str, Any]] = field(default_factory=list)
    error: str | None = None


def trigger_action(
    node: NodeDefinition, state: dict[str, Any], host_name: str, action: str,
) -> ActionResult:
    if action != "send_study":
        return ActionResult(error=f'Unbekannte Aktion "{action}".')

    host = node.host(host_name)

    if host is None or host.get("role") != "modality-simulator":
        return ActionResult(error="Diese Aktion gibt es auf diesem Host nicht.")

    config = state.get("config", {}).get(host_name, {})
    calling_ae = config.get("local_ae", "")
    target_ae = config.get("remote_ae", "")
    target_ip = config.get("remote_host", "")
    target_port = int(config.get("remote_port", 0) or 0)

    result = check_association(node, state, calling_ae, target_ae, target_ip, target_port)
    timestamp = datetime.now(UTC).strftime("%H:%M:%S")

    if not result.accepted:
        return ActionResult(
            log=[
                f"{timestamp}  Sendeauftrag – Verbindungsaufbau {target_ip}:{target_port} …",
                f"{timestamp}  {result.message}",
            ],
            events=[
                {
                    "type": "association_rejected",
                    "host": result.target_host,
                    "reason": result.reason,
                },
            ],
        )

    _touch_progress(state)

    file_count = state.get("_dataset_file_count", 0)
    bestand = state["bestand"].setdefault(
        result.target_host, {"studies": 0, "series": 0, "instances": 0},
    )
    bestand["studies"] = 1
    bestand["series"] = 1
    bestand["instances"] = file_count

    log = [
        f"{timestamp}  Sendeauftrag – Verbindungsaufbau {target_ip}:{target_port} …",
        f"{timestamp}  Association akzeptiert (Max PDU 16372)",
    ]
    for i in range(1, file_count + 1):
        log.append(f"{timestamp}  Bild {i}/{file_count} gesendet – Status Success")
    log.append(
        f"{timestamp}  Auftrag abgeschlossen – {file_count} von {file_count} Objekten übertragen",
    )

    return ActionResult(
        log=log,
        events=[
            {"type": "association_accepted", "host": result.target_host},
            {"type": "store_completed"},
        ],
    )


def use_hint(node: NodeDefinition, state: dict[str, Any], hint_id: str) -> str | None:
    """Gibt eine Fehlermeldung zurueck, oder None bei Erfolg (idempotent)."""

    if node.hint_cost(hint_id) is None:
        return f'Unbekannter Hint "{hint_id}".'

    if hint_id not in state.setdefault("hints_used", []):
        state["hints_used"].append(hint_id)

    return None


def view_write_up(state: dict[str, Any]) -> None:
    # Abschnitt 5.3: "Write-up VORAB ansehen setzt die Node auf 0 Punkte" --
    # nach dem Loesen darf man es sich straflos ansehen.
    if not state.get("solved"):
        state["write_up_seen_before_solve"] = True

    state["write_up_seen"] = True


def check_flag(node: NodeDefinition, state: dict[str, Any], value: str) -> bool:
    from app.flag import hash_value

    correct = hash_value(value, node.flag_case_sensitive) == node.flag_hash

    if correct:
        state["solved"] = True

    return correct
