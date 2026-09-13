---
title: "IHE-Profile: SWF, PIR, XDS-I — was der Name verspricht"
teaser: "Drei Buchstabenkürzel, die in jeder RIS/PACS-Ausschreibung auftauchen — und die du in dieser Spielwiese schon benutzt hast, ohne ihren Namen zu kennen."
objectives:
  - "Kannst benennen, welches Problem Scheduled Workflow (SWF) löst und welche Transaktionen dazugehören"
  - "Kannst Patient Information Reconciliation (PIR) von SWF abgrenzen"
  - "Kannst einordnen, wofür XDS-I steht und warum sein geteiltes Manifest technisch ein Key Object Selection Document ist"
---

## Ein Name für etwas, das du schon gemacht hast

Modality Worklist (Lektion 2.5) und MPPS (Lektion 2.6) wirken wie zwei
unabhängige Themen. Sind sie nicht: Beide sind Teil **eines** benannten,
IHE-weit standardisierten Ablaufs — **Scheduled Workflow (SWF.b)**. IHE
selbst löst dabei kein neues technisches Problem; es nimmt vorhandene
DICOM-Dienste (Worklist-Abfrage, C-STORE, MPPS) und schreibt fest, **in
welcher Reihenfolge und mit welchen Akteuren** sie zusammenspielen
müssen, damit zwei Systeme verschiedener Hersteller den Ablauf gleich
verstehen. Genau das ist der Unterschied zwischen „kann DICOM" und
„unterstützt SWF.b" — siehe auch Lektion 5.2.

## Die vier Transaktionen, die du schon live gesehen hast

Nach dem offiziellen IHE Radiology Technical Framework Supplement
„Scheduled Workflow.b" (Rev. 1.7, 2019-08-09), Tabelle 34.1-1 und
Abbildung 34.1-1, trägt jede Transaktion eine feste Nummer:

| Transaktion | IHE-Nummer | Akteure | Bereits gezeigt in |
|---|---|---|---|
| Query Modality Worklist | **RAD-5** | Acquisition Modality → DSS/Order Filler | Lektion 2.5 |
| Modality Images Stored | **RAD-8** | Acquisition Modality → Image Manager/Image Archive | jede `storescu`-Lektion |
| Modality PS In Progress | **RAD-6** | Acquisition Modality → Performed Procedure Step Manager | Lektion 2.6, 4.8 |
| Modality PS Completed | **RAD-7** | Acquisition Modality → Performed Procedure Step Manager | Lektion 2.6, 4.8 |

Was in 2.5 „die Worklist-Abfrage" hieß, ist im IHE-Sprachgebrauch
RAD-5. Was du in jeder Storage-Lektion als `storescu` kennst, ist
RAD-8. Die beiden MPPS-Nachrichten aus 2.6/4.8 sind RAD-6 und RAD-7.
Kein neuer Mechanismus — nur ein Name, der Herstellern in einer
Ausschreibung eine gemeinsame Referenz gibt (siehe 5.8).

## Eine zusammenhängende Sequenz, in dieser Spielwiese live gezeigt

Die drei Dienste laufen normalerweise nacheinander an derselben
Modalität. Hier alle vier Transaktionen in einer Sitzung, gegen
dasselbe Patientenpseudonym (`MUSTER^ERIKA`, das auch der bereitgelegte
Datensatz trägt):

```
$ findscu -W -k PatientName -k PatientID -k AccessionNumber \
          -k "0040,0100[0].0008,0060" -k "0040,0100[0].0040,0001" \
          -k "0040,0100[0].0040,0002" -k "0040,0100[0].0040,0003" \
          -aec ORTHANC 127.0.0.1 4242
I: Requesting Association
I: Association Accepted
I: Sending Find Request: MsgID 1
I: Find SCP Response: 1 - 0xFF00 (Pending)
I: 
I: # Response Identifier
I: (0008,0005) CS [ISO_IR 100]                             # 1 SpecificCharacterSet
I: (0008,0050) SH [4711-0001]                              # 1 AccessionNumber
I: (0010,0010) PN [MUSTER^ERIKA]                           # 1 PatientName
I: (0010,0020) LO [4711]                                   # 1 PatientID
I: (0040,0100) SQ (Sequence with 1 item)                   # 1 ScheduledProcedureStepSequence
I:   (Sequence item #1)
I:     (0008,0060) CS [CT]                                     # 1 Modality
I:     (0040,0001) AE [CT01]                                   # 1 ScheduledStationAETitle
I:     (0040,0002) DA [20260913]                               # 1 ScheduledProcedureStepStartDate
I:     (0040,0003) TM [232935]                                 # 1 ScheduledProcedureStepStartTime
I: 
I: Find SCP Result: 0x0000 (Success)
I: Releasing Association
```
**Was du daran abliest:** RAD-5 liefert den geplanten Auftrag für
`MUSTER^ERIKA` — genau das Muster aus Lektion 2.5, hier als erster
Schritt der Kette.

```
$ storescu -v -aec ORTHANC 127.0.0.1 4242 instance-0005.dcm
I: Requesting Association
I: Association Accepted
I: Sending file: instance-0005.dcm
I: Sending Store Request: MsgID 1, (CT)
I: Received Store Response (Status: 0x0000 - Success)
I: Releasing Association
```
**Was du daran abliest:** RAD-8 — die Modalität liefert das tatsächlich
akquirierte Bild an das Archiv. Derselbe `storescu`-Aufruf, den du aus
praktisch jeder anderen Lektion kennst, trägt in SWF.b einfach eine
feste Transaktionsnummer.

```
$ python3 /opt/tools/mppsscp.py --port 11112 --ae-title MPPS-SCP &
$ python3 - <<'PY'
from pynetdicom import AE
from pynetdicom.sop_class import ModalityPerformedProcedureStep
from pydicom.dataset import Dataset
from pydicom.uid import generate_uid

ae = AE(ae_title="CT01")
ae.add_requested_context(ModalityPerformedProcedureStep)
assoc = ae.associate("127.0.0.1", 11112, ae_title="MPPS-SCP")
sop_instance = generate_uid()

ds = Dataset()
ds.PerformedProcedureStepStatus = "IN PROGRESS"
ds.PatientName = "MUSTER^ERIKA"
status, _ = assoc.send_n_create(ds, ModalityPerformedProcedureStep, sop_instance)
print("N-CREATE:", status.Status)

ds2 = Dataset()
ds2.PerformedProcedureStepStatus = "COMPLETED"
status2, _ = assoc.send_n_set(ds2, ModalityPerformedProcedureStep, sop_instance)
print("N-SET:", status2.Status)
assoc.release()
PY
N-CREATE: 0
N-SET: 0
```
**Was du daran abliest:** RAD-6 (`N-CREATE`, `IN PROGRESS`) und RAD-7
(`N-SET`, `COMPLETED`) schließen die Kette ab — dieselben zwei
Nachrichten wie in Lektion 4.8, hier explizit als die letzten beiden
SWF.b-Transaktionen benannt. Vier Transaktionen, eine Modalität, ein
Patient — das *ist* Scheduled Workflow, nicht mehr und nicht weniger.

```
$ findscu -S -k QueryRetrieveLevel=STUDY -k PatientName -k NumberOfStudyRelatedInstances -aec ORTHANC 127.0.0.1 4242
I: Requesting Association
I: Association Accepted
I: Sending Find Request: MsgID 1
I: Find SCP Response: 1 - 0xFF00 (Pending)
I: 
I: # Response Identifier
I: (0008,0005) CS [ISO_IR 100]                             # 1 SpecificCharacterSet
I: (0008,0052) CS [STUDY]                                  # 1 QueryRetrieveLevel
I: (0008,0054) AE [ORTHANC]                                # 1 RetrieveAETitle
I: (0010,0010) PN [MUSTER^ERIKA]                           # 1 PatientName
I: (0020,1208) IS [2]                                      # 1 NumberOfStudyRelatedInstances
I: 
I: Find SCP Result: 0x0000 (Success)
I: Releasing Association
```
**Was du daran abliest:** Am Archiv liegt jetzt tatsächlich ein Bild
zu genau dem Patienten, der vorher als geplant abgefragt wurde (RAD-5)
und dessen Untersuchung als abgeschlossen gemeldet wurde (RAD-7) — die
Kette schließt sich nicht nur begrifflich, sondern in echten,
abfragbaren Daten.

## Was SWF.b nicht regelt

SWF.b setzt voraus, dass der Patient bei Untersuchungsbeginn bereits
korrekt im System steht. Zwei reale Fälle liegen außerhalb: ein
Patient, der noch nicht registriert ist (Notfall), und ein Patient, der
versehentlich doppelt oder falsch angelegt wurde. Für beide gibt es ein
eigenes Profil.

## Patient Information Reconciliation (PIR)

PIR behandelt genau diese Nachbereitung: Bilder werden unter einer
vorläufigen oder generischen Patientenkennung akquiriert (Trauma-Fall,
kein vorheriger Termin), und **nachträglich**, sobald die echte
Identität feststeht, mit dem korrekten Patientenstamm zusammengeführt.
Auf HL7-Seite geschieht das über eine `A40`-Merge-Nachricht vom
ADT-System an Order Placer und DSS/Order Filler.

Die DICOM-seitige Konsequenz dieses Problems hast du bereits gesehen —
nur nicht unter dem Namen PIR: Lektion 4.6 zeigt genau den Fall, dass
ein Archiv ankommende Patientendaten aktiv gegen seinen eigenen Bestand
überschreibt ({{term:coercion}}, Status `0xB000`). PIR ist der
IHE-Prozessrahmen für „wie ein solcher Fall überhaupt erst korrekt
entsteht und aufgelöst wird" — Coercion ist die DICOM-Mechanik, die an
seinem Ende steht.

## XDS-I: dieselben Bilder, aber zwischen Einrichtungen

SWF.b und PIR spielen **innerhalb** eines Hauses. Sollen Bilder
**zwischen** Einrichtungen geteilt werden — etwa eine externe
Voraufnahme für einen Befund — ist das ein anderes Problem: kein
direkter Archiv-zu-Archiv-Transfer, sondern ein Registry/Repository-Modell
(**Cross-Enterprise Document Sharing for Imaging**, XDS-I.b, ein
Content-Profil auf dem allgemeinen IHE-ITI-XDS-Muster). Ein *Imaging
Document Source* (das PACS der abgebenden Einrichtung) veröffentlicht
ein Manifest im *Document Repository*, das *Document Registry* darüber
informiert; ein *Imaging Document Consumer* fragt die Registry, nicht
das Quell-PACS direkt, ab.

Das veröffentlichte Manifest ist dabei kein neuer Objekttyp: Es ist
technisch ein **Key Object Selection Document** — dieselbe SOP-Klasse,
die du in Lektion 3.5 bereits von Hand gebaut und real gegen Orthanc
verifiziert hast. XDS-I fügt kein neues DICOM-Objekt hinzu; es packt ein
KOS in einen einrichtungsübergreifenden Registry-Prozess.

## Drei Profile, drei verschiedene Grenzen

| Profil | Löst welches Problem | Grenze |
|---|---|---|
| SWF.b | Auftrag → Akquise → Speicherung → Statusmeldung | Innerhalb einer Einrichtung, Patient bereits korrekt registriert |
| PIR | Falsch/vorläufig registrierter Patient wird korrigiert | Innerhalb einer Einrichtung, nachträglich |
| XDS-I.b | Bilder zwischen Einrichtungen auffindbar machen | Einrichtungsübergreifend, über Registry/Repository |

## Stolperfallen

- **„Unterstützt DICOM" mit „unterstützt SWF.b" verwechseln.** DICOM
  liefert die Bausteine (C-FIND, C-STORE, MPPS); SWF.b legt nur fest, in
  welcher Reihenfolge und mit welchen Pflicht-Transaktionen sie
  zusammenspielen müssen.
- **PIR für einen Sonderfall von SWF.b halten.** PIR ist ein eigenes
  Profil für eine Situation, die SWF.b explizit voraussetzt (korrekt
  registrierter Patient) und deshalb nicht selbst löst.
- **XDS-I mit einer direkten Archiv-zu-Archiv-Verbindung verwechseln.**
  Der Zugriff läuft über eine Registry, nicht über eine direkte
  C-MOVE/C-GET-Verbindung zwischen zwei PACS.

## Selbstcheck

1. Welche vier IHE-Transaktionsnummern hast du in dieser Lektion live
   in der Spielwiese ausgelöst, und welcher DICOM-Dienst steckt jeweils
   dahinter?
2. Ein Patient wird im Trauma-Fall zunächst unter einer generischen
   Kennung untersucht und später korrekt zugeordnet. Welches IHE-Profil
   beschreibt diesen Fall — und welche DICOM-Mechanik aus Lektion 4.6
   tritt dabei am Archiv auf?
3. Ein PACS soll eine Voraufnahme aus einem anderen Krankenhaus
   anzeigen. Warum reicht dafür kein einfacher C-MOVE an das externe
   Archiv, und welches DICOM-Objekt trägt das dafür veröffentlichte
   Manifest tatsächlich?
