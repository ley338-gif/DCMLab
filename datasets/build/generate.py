#!/usr/bin/env python3
"""Erzeugt synthetische CT-Testobjekte fuer die Spielwiese (Abschnitt 6).

Liest die Parameter (Patient, Study, Serien, Dateianzahl) nicht selbst aus
content/datasets.yml -- das bleibt Sache des Aufrufers (Abschnitt 1.4:
content/ ist die Quelle, dieses Skript ist nur der Generator) -- sondern
nimmt sie als CLI-Argumente entgegen. Nie echte Patientendaten, auch nicht
anonymisiert (Leitplanke 2): Name, ID und Inhalte sind rein synthetisch.
"""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

import pydicom
from pydicom.dataset import FileDataset, FileMetaDataset
from pydicom.uid import CTImageStorage, ExplicitVRLittleEndian, generate_uid


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


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--out", required=True, type=Path)
    parser.add_argument("--patient", required=True)
    parser.add_argument("--patient-id", required=True)
    parser.add_argument("--study", required=True)
    parser.add_argument("--series", required=True, action="append")
    parser.add_argument("--file-count", required=True, type=int)
    args = parser.parse_args(argv)

    written = generate(
        out_dir=args.out,
        patient=args.patient,
        patient_id=args.patient_id,
        study=args.study,
        series=args.series,
        file_count=args.file_count,
    )
    print(f"{len(written)} Datei(en) erzeugt in {args.out}")

    return 0


if __name__ == "__main__":
    sys.exit(main())
