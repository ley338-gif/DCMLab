"""Alles, was den Docker-Socket anfasst (Abschnitt 3.3: der Orchestrator ist
der einzige Dienst mit diesem Zugriff). Ein Container-Paar pro Sitzung:
Orthanc besitzt die Netzwerk-Namespace, die Toolbox tritt ihr bei (`network_mode:
container:<orthanc>`) -- deshalb erreicht die Toolbox Orthanc unter
`127.0.0.1:4242`, genau wie in Abschnitt 4.6 vorgegeben.
"""

from __future__ import annotations

import time
import uuid
from dataclasses import dataclass
from datetime import datetime

import docker
from docker.errors import ContainerError, NotFound
from docker.models.containers import Container

from app.config import settings
from app.datasets_yaml import DatasetParams
from app.worklists_yaml import WorklistParams, load_worklist_params

RESOURCE_LIMITS = {
    "mem_limit": "256m",
    "nano_cpus": 500_000_000,  # 0.5 CPU
    "security_opt": ["no-new-privileges"],
    "pids_limit": 128,
}

# Die Toolbox braucht eine gezielte Ausnahme von "no-new-privileges":
# `dumpcap` (tshark) laeuft als nicht-root-Nutzer "dcmlab" und bekommt
# CAP_NET_RAW/CAP_NET_ADMIN ausschliesslich ueber eine Datei-Capability
# auf genau dieses eine Binary (`setcap`, siehe
# containers/toolbox/Dockerfile) -- "no-new-privileges" wuerde exakt
# diesen Mechanismus blockieren (das Docker-Daemon-Capability-Bit
# `cap_add` allein reicht nicht, siehe ADR 0029). Orthanc (der
# DICOM-SCP im selben Netzwerk-Namespace) behaelt die Einschraenkung
# unveraendert; die Toolbox bekommt ohnehin nur die beiden minimalen
# Capabilities dazu, keine root-Rechte und weiterhin kein Egress
# (--internal-Netz, unveraendert).
TOOLBOX_RESOURCE_LIMITS = {
    **{k: v for k, v in RESOURCE_LIMITS.items() if k != "security_opt"},
    "cap_add": ["NET_RAW", "NET_ADMIN"],
}


# Fixer Slug (Abschnitt 6, Lektion 4.7): jede Sitzung bekommt genau diesen
# einen, real generierten Worklist-Eintrag -- kein Lernenden-Auswahlfeld,
# analog zu den festen RESOURCE_LIMITS. Siehe content/worklists.yml.
WORKLIST_SLUG = "ct-thorax-worklist"


@dataclass
class SessionContainers:
    network_name: str
    volume_name: str
    orthanc: Container
    toolbox: Container


def client() -> docker.DockerClient:
    return docker.from_env()


def build_session(
    docker_client: docker.DockerClient,
    *,
    sandbox_id: str,
    dataset_slug: str,
    dataset_params: DatasetParams,
) -> SessionContainers:
    network_name = f"dcmlab-sandbox-{sandbox_id}"
    volume_name = f"dcmlab-sandbox-{sandbox_id}-data"
    worklist_volume_name = f"{network_name}-worklists"

    # `internal=True` ist Dockers eingebaute Egress-Sperre: Container in
    # diesem Netz haben keine Route nach aussen (Abschnitt 6).
    docker_client.networks.create(network_name, driver="bridge", internal=True)
    docker_client.volumes.create(volume_name)
    docker_client.volumes.create(worklist_volume_name)

    _run_generator(docker_client, volume_name=volume_name, dataset_params=dataset_params)
    _run_worklist_generator(
        docker_client,
        volume_name=worklist_volume_name,
        worklist_params=load_worklist_params(WORKLIST_SLUG),
    )

    orthanc = docker_client.containers.run(
        settings.orthanc_image,
        detach=True,
        name=f"{network_name}-orthanc",
        network=network_name,
        tmpfs={"/var/lib/orthanc/db": ""},
        volumes={worklist_volume_name: {"bind": "/worklists", "mode": "ro"}},
        **RESOURCE_LIMITS,
    )
    _wait_until_running(orthanc)

    toolbox = docker_client.containers.run(
        settings.toolbox_image,
        detach=True,
        name=f"{network_name}-toolbox",
        network_mode=f"container:{orthanc.id}",
        command=["sleep", "infinity"],
        volumes={volume_name: {"bind": f"/home/dcmlab/daten/{dataset_slug}", "mode": "ro"}},
        **TOOLBOX_RESOURCE_LIMITS,
    )

    return SessionContainers(network_name, volume_name, orthanc, toolbox)


def _wait_until_running(container: Container, timeout_seconds: float = 10.0) -> None:
    """Wartet, bis Dockers Netzwerk-Namespace fuer `container` tatsaechlich
    existiert -- ohne das kann ein direkt danach gestarteter
    `network_mode=container:<id>`-Container mit einer Race-Condition
    scheitern ("namespace path: ... no such file or directory")."""

    deadline = time.monotonic() + timeout_seconds

    while time.monotonic() < deadline:
        container.reload()
        if container.status == "running":
            return
        time.sleep(0.1)

    raise RuntimeError(
        f"Container {container.name} ist nicht rechtzeitig gestartet (Status: {container.status}).",
    )


def _run_generator(
    docker_client: docker.DockerClient,
    *,
    volume_name: str,
    dataset_params: DatasetParams,
) -> None:
    series_args: list[str] = []
    for series_name in dataset_params["series"]:
        series_args += ["--series", str(series_name)]

    command = [
        "python3",
        "/opt/datasets-build/generate.py",
        "ct",
        "--out",
        "/data",
        "--patient",
        str(dataset_params["patient"]),
        "--patient-id",
        str(dataset_params["patient_id"]),
        "--study",
        str(dataset_params["study"]),
        *series_args,
        "--file-count",
        str(dataset_params["file_count"]),
    ]

    try:
        docker_client.containers.run(
            settings.toolbox_image,
            command,
            remove=True,
            # Ein frisches Docker-Volume gehoert root -- der langlebige
            # Toolbox-Container laeuft als "dcmlab" (Dockerfile: USER dcmlab),
            # aber dieser kurzlebige, vom Orchestrator kontrollierte
            # Generator-Lauf braucht Root, um /data einmalig zu befuellen.
            user="root",
            volumes={volume_name: {"bind": "/data", "mode": "rw"}},
            mem_limit="128m",
            nano_cpus=250_000_000,
        )
    except ContainerError as exc:
        raise RuntimeError(f"Datensatz-Generator fehlgeschlagen: {exc.stderr!r}") from exc


def _run_worklist_generator(
    docker_client: docker.DockerClient,
    *,
    volume_name: str,
    worklist_params: WorklistParams,
) -> None:
    # Datum/Uhrzeit kommen bewusst nicht aus content/worklists.yml (Abschnitt
    # 6: kein Zustand ueber Sitzungen hinweg) -- der Auftrag ist immer "fuer
    # heute" geplant, gerechnet zum Zeitpunkt des Sitzungsstarts.
    now = datetime.now()

    command = [
        "python3",
        "/opt/datasets-build/generate.py",
        "worklist",
        "--out",
        "/data",
        "--patient",
        str(worklist_params["patient"]),
        "--patient-id",
        str(worklist_params["patient_id"]),
        "--accession-number",
        str(worklist_params["accession_number"]),
        "--requested-procedure-description",
        str(worklist_params["requested_procedure_description"]),
        "--referring-physician",
        str(worklist_params["referring_physician"]),
        "--modality",
        str(worklist_params["modality"]),
        "--scheduled-station-ae-title",
        str(worklist_params["scheduled_station_ae_title"]),
        "--scheduled-station-name",
        str(worklist_params["scheduled_station_name"]),
        "--scheduled-procedure-step-description",
        str(worklist_params["scheduled_procedure_step_description"]),
        "--scheduled-procedure-step-id",
        str(worklist_params["scheduled_procedure_step_id"]),
        "--scheduled-date",
        now.strftime("%Y%m%d"),
        "--scheduled-time",
        now.strftime("%H%M%S"),
    ]

    try:
        docker_client.containers.run(
            settings.toolbox_image,
            command,
            remove=True,
            user="root",
            volumes={volume_name: {"bind": "/data", "mode": "rw"}},
            mem_limit="128m",
            nano_cpus=250_000_000,
        )
    except ContainerError as exc:
        raise RuntimeError(f"Worklist-Generator fehlgeschlagen: {exc.stderr!r}") from exc


def exec_command(
    docker_client: docker.DockerClient, toolbox_container_id: str, command: str,
) -> dict[str, object]:
    container = docker_client.containers.get(toolbox_container_id)
    exit_code, (stdout, stderr) = container.exec_run(
        ["/bin/sh", "-c", command],
        demux=True,
        user="dcmlab",
    )

    return {
        "exit_code": exit_code,
        "stdout": (stdout or b"").decode("utf-8", errors="replace"),
        "stderr": (stderr or b"").decode("utf-8", errors="replace"),
    }


def teardown_session(
    docker_client: docker.DockerClient, *, network_name: str, volume_name: str,
) -> None:
    for container_name in (f"{network_name}-toolbox", f"{network_name}-orthanc"):
        try:
            container = docker_client.containers.get(container_name)
            container.remove(force=True)
        except NotFound:
            pass

    try:
        docker_client.networks.get(network_name).remove()
    except NotFound:
        pass

    try:
        docker_client.volumes.get(volume_name).remove(force=True)
    except NotFound:
        pass

    try:
        docker_client.volumes.get(f"{network_name}-worklists").remove(force=True)
    except NotFound:
        pass


def new_sandbox_id() -> str:
    return str(uuid.uuid4())
