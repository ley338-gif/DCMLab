#!/usr/bin/env python3
"""Minimaler MPPS-SCP fuer die Spielwiese (Abschnitt 6, Lektion 4.8).

Orthanc unterstuetzt den Modality-Performed-Procedure-Step-Dienst nicht
(anders als Storage Commitment, das Orthanc nativ beherrscht) -- deshalb
braucht diese eine Lektion eine eigene, echte Gegenstelle. Kein Fake:
ein echter pynetdicom-SCP, der echte N-CREATE-/N-SET-Anfragen entgegennimmt
und mit einem echten DIMSE-Status antwortet.
"""

from __future__ import annotations

import argparse
import sys

from pydicom.dataset import Dataset
from pynetdicom import AE, evt
from pynetdicom.sop_class import ModalityPerformedProcedureStep


def handle_n_create(event: evt.Event) -> tuple[int, Dataset | None]:
    ds = event.attribute_list
    status = ds.get("PerformedProcedureStepStatus", "?")
    patient = ds.get("PatientName", "?")
    print(f"N-CREATE empfangen: PerformedProcedureStepStatus={status}, PatientName={patient}", flush=True)
    return 0x0000, ds


def handle_n_set(event: evt.Event) -> tuple[int, Dataset | None]:
    ds = event.modification_list
    status = ds.get("PerformedProcedureStepStatus", "?")
    print(f"N-SET empfangen: PerformedProcedureStepStatus={status}", flush=True)
    return 0x0000, ds


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--port", type=int, required=True)
    parser.add_argument("--ae-title", required=True)
    args = parser.parse_args(argv)

    ae = AE(ae_title=args.ae_title)
    ae.add_supported_context(ModalityPerformedProcedureStep)

    handlers = [
        (evt.EVT_N_CREATE, handle_n_create),
        (evt.EVT_N_SET, handle_n_set),
    ]

    print(f"MPPS-SCP hoert auf Port {args.port} (AE Title {args.ae_title})", flush=True)
    ae.start_server(("0.0.0.0", args.port), evt_handlers=handlers)

    return 0


if __name__ == "__main__":
    sys.exit(main())
