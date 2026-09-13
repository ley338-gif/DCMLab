#!/usr/bin/env python3
"""Erzeugt synthetische Testobjekte fuer die Spielwiese (Abschnitt 6):
CT-Bildinstanzen (Subcommand "ct") und Modality-Worklist-Eintraege
(Subcommand "worklist", seit P10.20, Lektion 4.7).

Liest die Parameter (Patient, Study, Serien, Dateianzahl, ...) nicht selbst
aus content/datasets.yml bzw. content/worklists.yml -- das bleibt Sache des
Aufrufers (Abschnitt 1.4: content/ ist die Quelle, dieses Skript ist nur der
Generator) -- sondern nimmt sie als CLI-Argumente entgegen. Nie echte
Patientendaten, auch nicht anonymisiert (Leitplanke 2): Name, ID und Inhalte
sind rein synthetisch.
"""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

import pydicom
from pydicom.dataset import Dataset, FileDataset, FileMetaDataset
from pydicom.uid import UID, CTImageStorage, ExplicitVRLittleEndian, generate_uid

# Kein Konstantenname dafuer in pydicom.uid -- Modality Worklist Information
# Model - FIND, PS3.4 K.6.1.1.
MODALITY_WORKLIST_FIND_SOP_CLASS = UID("1.2.840.10008.5.1.4.31")


def build_instance(
    *,
    patient_name: str,
    patient_id: str,
    study_description: str,
    series_description: str,
    study_uid: str,
    series_uid: str,
    instance_number: int,
) -> FileDataset:
    file_meta = FileMetaDataset()
    file_meta.MediaStorageSOPClassUID = CTImageStorage
    file_meta.MediaStorageSOPInstanceUID = generate_uid()
    file_meta.TransferSyntaxUID = ExplicitVRLittleEndian

    ds = FileDataset("", {}, file_meta=file_meta, preamble=b"\x00" * 128)

    ds.SOPClassUID = CTImageStorage
    ds.SOPInstanceUID = file_meta.MediaStorageSOPInstanceUID
    ds.PatientName = patient_name
    ds.PatientID = patient_id
    ds.Modality = "CT"
    ds.StudyDescription = study_description
    ds.SeriesDescription = series_description
    ds.StudyInstanceUID = study_uid
    ds.SeriesInstanceUID = series_uid
    ds.SeriesNumber = 1
    ds.InstanceNumber = instance_number

    # Ein winziges, gueltiges Pixel-Grid reicht -- die Nodes/die Spielwiese
    # pruefen Metadaten und Transport, nie den Bildinhalt.
    ds.Rows = 4
    ds.Columns = 4
    ds.BitsAllocated = 16
    ds.BitsStored = 16
    ds.HighBit = 15
    ds.PixelRepresentation = 0
    ds.SamplesPerPixel = 1
    ds.PhotometricInterpretation = "MONOCHROME2"
    ds.PixelData = bytes(4 * 4 * 2)

    return ds


def generate(
    *,
    out_dir: Path,
    patient: str,
    patient_id: str,
    study: str,
    series: list[str],
    file_count: int,
) -> list[Path]:
    out_dir.mkdir(parents=True, exist_ok=True)
    study_uid = generate_uid()
    series_uids = {name: generate_uid() for name in series}

    written: list[Path] = []
    for i in range(file_count):
        series_name = series[i % len(series)]
        ds = build_instance(
            patient_name=patient,
            patient_id=patient_id,
            study_description=study,
            series_description=series_name,
            study_uid=study_uid,
            series_uid=series_uids[series_name],
            instance_number=i + 1,
        )
        path = out_dir / f"instance-{i + 1:04d}.dcm"
        pydicom.dcmwrite(path, ds, enforce_file_format=True)
        written.append(path)

    return written


def build_worklist_item(
    *,
    patient_name: str,
    patient_id: str,
    accession_number: str,
    requested_procedure_description: str,
    referring_physician: str,
    modality: str,
    scheduled_station_ae_title: str,
    scheduled_station_name: str,
    scheduled_procedure_step_description: str,
    scheduled_procedure_step_id: str,
    scheduled_date: str,
    scheduled_time: str,
) -> FileDataset:
    file_meta = FileMetaDataset()
    file_meta.MediaStorageSOPClassUID = MODALITY_WORKLIST_FIND_SOP_CLASS
    file_meta.MediaStorageSOPInstanceUID = generate_uid()
    file_meta.TransferSyntaxUID = ExplicitVRLittleEndian

    ds = FileDataset("", {}, file_meta=file_meta, preamble=b"\x00" * 128)

    ds.SpecificCharacterSet = "ISO_IR 100"
    ds.PatientName = patient_name
    ds.PatientID = patient_id
    ds.AccessionNumber = accession_number
    ds.RequestedProcedureID = scheduled_procedure_step_id
    ds.RequestedProcedureDescription = requested_procedure_description
    ds.ReferringPhysicianName = referring_physician
    ds.StudyInstanceUID = generate_uid()

    step = Dataset()
    step.Modality = modality
    step.ScheduledStationAETitle = scheduled_station_ae_title
    step.ScheduledStationName = scheduled_station_name
    step.ScheduledProcedureStepDescription = scheduled_procedure_step_description
    step.ScheduledProcedureStepID = scheduled_procedure_step_id
    step.ScheduledProcedureStepStartDate = scheduled_date
    step.ScheduledProcedureStepStartTime = scheduled_time
    ds.ScheduledProcedureStepSequence = [step]

    return ds


def generate_worklist(
    *,
    out_dir: Path,
    patient: str,
    patient_id: str,
    accession_number: str,
    requested_procedure_description: str,
    referring_physician: str,
    modality: str,
    scheduled_station_ae_title: str,
    scheduled_station_name: str,
    scheduled_procedure_step_description: str,
    scheduled_procedure_step_id: str,
    scheduled_date: str,
    scheduled_time: str,
) -> Path:
    out_dir.mkdir(parents=True, exist_ok=True)
    ds = build_worklist_item(
        patient_name=patient,
        patient_id=patient_id,
        accession_number=accession_number,
        requested_procedure_description=requested_procedure_description,
        referring_physician=referring_physician,
        modality=modality,
        scheduled_station_ae_title=scheduled_station_ae_title,
        scheduled_station_name=scheduled_station_name,
        scheduled_procedure_step_description=scheduled_procedure_step_description,
        scheduled_procedure_step_id=scheduled_procedure_step_id,
        scheduled_date=scheduled_date,
        scheduled_time=scheduled_time,
    )
    path = out_dir / "worklist-0001.wl"
    pydicom.dcmwrite(path, ds, enforce_file_format=True)
    return path


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    subparsers = parser.add_subparsers(dest="mode", required=True)

    ct_parser = subparsers.add_parser("ct", help="CT-Bildinstanzen erzeugen")
    ct_parser.add_argument("--out", required=True, type=Path)
    ct_parser.add_argument("--patient", required=True)
    ct_parser.add_argument("--patient-id", required=True)
    ct_parser.add_argument("--study", required=True)
    ct_parser.add_argument("--series", required=True, action="append")
    ct_parser.add_argument("--file-count", required=True, type=int)

    wl_parser = subparsers.add_parser("worklist", help="Worklist-Eintrag erzeugen")
    wl_parser.add_argument("--out", required=True, type=Path)
    wl_parser.add_argument("--patient", required=True)
    wl_parser.add_argument("--patient-id", required=True)
    wl_parser.add_argument("--accession-number", required=True)
    wl_parser.add_argument("--requested-procedure-description", required=True)
    wl_parser.add_argument("--referring-physician", required=True)
    wl_parser.add_argument("--modality", required=True)
    wl_parser.add_argument("--scheduled-station-ae-title", required=True)
    wl_parser.add_argument("--scheduled-station-name", required=True)
    wl_parser.add_argument("--scheduled-procedure-step-description", required=True)
    wl_parser.add_argument("--scheduled-procedure-step-id", required=True)
    wl_parser.add_argument("--scheduled-date", required=True)
    wl_parser.add_argument("--scheduled-time", required=True)

    args = parser.parse_args(argv)

    if args.mode == "ct":
        written = generate(
            out_dir=args.out,
            patient=args.patient,
            patient_id=args.patient_id,
            study=args.study,
            series=args.series,
            file_count=args.file_count,
        )
        print(f"{len(written)} Datei(en) erzeugt in {args.out}")
    else:
        path = generate_worklist(
            out_dir=args.out,
            patient=args.patient,
            patient_id=args.patient_id,
            accession_number=args.accession_number,
            requested_procedure_description=args.requested_procedure_description,
            referring_physician=args.referring_physician,
            modality=args.modality,
            scheduled_station_ae_title=args.scheduled_station_ae_title,
            scheduled_station_name=args.scheduled_station_name,
            scheduled_procedure_step_description=args.scheduled_procedure_step_description,
            scheduled_procedure_step_id=args.scheduled_procedure_step_id,
            scheduled_date=args.scheduled_date,
            scheduled_time=args.scheduled_time,
        )
        print(f"Worklist-Eintrag erzeugt: {path}")

    return 0


if __name__ == "__main__":
    sys.exit(main())
