from pathlib import Path

import pydicom
from pydicom.uid import CTImageStorage

from generate import MODALITY_WORKLIST_FIND_SOP_CLASS, generate, generate_worklist


def test_generate_writes_the_requested_number_of_files(tmp_path: Path) -> None:
    written = generate(
        out_dir=tmp_path,
        patient="MUSTER^ERIKA",
        patient_id="4711",
        study="CT Thorax nativ",
        series=["Thorax 5.0 B31f"],
        file_count=3,
    )

    assert len(written) == 3
    assert all(p.is_file() for p in written)


def test_generated_files_are_valid_dicom_with_the_requested_metadata(tmp_path: Path) -> None:
    generate(
        out_dir=tmp_path,
        patient="MUSTER^ERIKA",
        patient_id="4711",
        study="CT Thorax nativ",
        series=["Thorax 5.0 B31f"],
        file_count=1,
    )

    ds = pydicom.dcmread(tmp_path / "instance-0001.dcm")

    assert str(ds.PatientName) == "MUSTER^ERIKA"
    assert ds.PatientID == "4711"
    assert ds.StudyDescription == "CT Thorax nativ"
    assert ds.SeriesDescription == "Thorax 5.0 B31f"
    assert ds.SOPClassUID == CTImageStorage


def test_all_instances_share_the_same_study_but_series_round_robin(tmp_path: Path) -> None:
    generate(
        out_dir=tmp_path,
        patient="MUSTER^ERIKA",
        patient_id="4711",
        study="CT Thorax nativ",
        series=["Thorax 1.0 B70f", "Thorax 5.0 B31f"],
        file_count=4,
    )

    datasets = [pydicom.dcmread(tmp_path / f"instance-{i:04d}.dcm") for i in range(1, 5)]

    study_uids = {ds.StudyInstanceUID for ds in datasets}
    assert len(study_uids) == 1

    series_descriptions = [ds.SeriesDescription for ds in datasets]
    expected = ["Thorax 1.0 B70f", "Thorax 5.0 B31f", "Thorax 1.0 B70f", "Thorax 5.0 B31f"]
    assert series_descriptions == expected


def test_generate_worklist_writes_a_valid_worklist_item(tmp_path: Path) -> None:
    path = generate_worklist(
        out_dir=tmp_path,
        patient="MUSTER^ERIKA",
        patient_id="4711",
        accession_number="4711-0001",
        requested_procedure_description="CT Thorax nativ",
        referring_physician="ARZT^HANS",
        modality="CT",
        scheduled_station_ae_title="CT01",
        scheduled_station_name="CT Raum 1",
        scheduled_procedure_step_description="CT Thorax nativ",
        scheduled_procedure_step_id="SPS-0001",
        scheduled_date="20260913",
        scheduled_time="090000",
    )

    assert path.is_file()

    ds = pydicom.dcmread(path)

    assert ds.file_meta.MediaStorageSOPClassUID == MODALITY_WORKLIST_FIND_SOP_CLASS
    assert str(ds.PatientName) == "MUSTER^ERIKA"
    assert ds.PatientID == "4711"
    assert ds.AccessionNumber == "4711-0001"

    step = ds.ScheduledProcedureStepSequence[0]
    assert step.Modality == "CT"
    assert step.ScheduledStationAETitle == "CT01"


def test_no_real_patient_data_placeholder_stays_synthetic(tmp_path: Path) -> None:
    # Leitplanke 2: nie echte Patientendaten, auch nicht anonymisiert --
    # dieser Test haelt fest, dass der Generator ausschliesslich die
    # uebergebenen, synthetischen Werte einsetzt und nichts "aufhuebscht".
    written = generate(
        out_dir=tmp_path,
        patient="TEST^SYNTH",
        patient_id="0000",
        study="Test Study",
        series=["Test Series"],
        file_count=1,
    )

    ds = pydicom.dcmread(written[0])
    assert str(ds.PatientName) == "TEST^SYNTH"
    assert ds.PatientID == "0000"
