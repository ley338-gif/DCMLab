---
title: „Bilder da, aber Befund geht nicht raus"
teaser: Der Transfer hat geklappt, die Statuskette nicht — und genau die entscheidet, ob der Workflow weiterläuft.
objectives:
  - Die Statuskette aus MPPS und Storage Commitment als eigenen Fehlerort begreifen
  - Erkennen, an welchem Glied die Kette reißt
  - Die Rückrichtung als eigene Verbindung prüfen, die eigene Konfiguration braucht
---

## Ein Ticket: Untersuchung bleibt im RIS offen

Die Bilder liegen im Archiv, ein Radiologe kann sie öffnen — trotzdem
zeigt das RIS die Untersuchung weiterhin als „geplant", nicht als
„durchgeführt". Der Bildtransfer war nicht das Problem. Etwas in der
Statuskette drumherum ist stehengeblieben.

## Die Kette hinter dem Transfer

Ein C-STORE allein sagt einem RIS nichts darüber, *dass* eine
Untersuchung stattgefunden hat — dafür gibt es zwei eigene DICOM-Dienste:

- **MPPS** (Modality Performed Procedure Step): Die Modalität meldet
  „Untersuchung begonnen" (`N-CREATE`, Status `IN PROGRESS`) und später
  „Untersuchung beendet" (`N-SET`, Status `COMPLETED`). Das ist eine
  eigene Verbindung, unabhängig vom Bildtransfer selbst.
- **Storage Commitment**: Das Archiv bestätigt einem Absender aktiv,
  dass es ein Objekt dauerhaft übernommen hat — nicht nur „angekommen",
  sondern „gesichert, du darfst deine lokale Kopie löschen". Diese
  Antwort kommt verzögert und läuft in der Praxis oft über eine eigene
  Verbindung, bei der das Archiv den Absender zurückruft.

Beide Dienste sind vom eigentlichen Bildtransfer unabhängig — Bilder
können vollständig ankommen, während MPPS oder Storage Commitment an
ganz anderer Stelle hängen bleiben.

## MPPS: eine Meldung, keine Bilddatei

Orthanc (das Archiv dieser Spielwiese) beantwortet MPPS nicht — anders
als Storage Commitment, das weiter unten folgt, gehört MPPS nicht zu
seinem Funktionsumfang. Die Gegenstelle in dieser Lektion ist deshalb
ein eigener, echter MPPS-Dienst (`/opt/tools/mppsscp.py`, ein
`pynetdicom`-Skript) statt Orthanc selbst.

```
$ python3 /opt/tools/mppsscp.py --port 11112 --ae-title MPPS-SCP > /tmp/mppsscp.log 2>&1 &
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
$ cat /tmp/mppsscp.log
MPPS-SCP hoert auf Port 11112 (AE Title MPPS-SCP)
N-CREATE empfangen: PerformedProcedureStepStatus=IN PROGRESS, PatientName=MUSTER^ERIKA
N-SET empfangen: PerformedProcedureStepStatus=COMPLETED
```
**Was du daran abliest:** Zwei getrennte Nachrichten auf derselben
Verbindung — `N-CREATE` beim Start (`IN PROGRESS`), `N-SET` beim Ende
(`COMPLETED`), beide mit Status `0` (Success) beantwortet. Kein einziges
Bild ist dabei geflossen — MPPS trägt nur den Status der Untersuchung
als Ganzes, nicht ihre Bilddaten.

## Auf der Leitung: zwei DIMSE-Meldungen, eine Assoziation

```
$ tshark -i lo -f "tcp port 11112" -Y dicom
4   0.000369   127.0.0.1 → 127.0.0.1   DICOM 359  A-ASSOCIATE request CT01 --> MPPS-SCP
6   0.004477   127.0.0.1 → 127.0.0.1   DICOM 260  A-ASSOCIATE accept  CT01 <-- MPPS-SCP
8   0.007270   127.0.0.1 → 127.0.0.1   DICOM 224  P-DATA, N-CREATE-RQ ID=1
10  0.048720   127.0.0.1 → 127.0.0.1   DICOM 118  P-DATA, N-CREATE-RQ-DATA
12  0.050344   127.0.0.1 → 127.0.0.1   DICOM 234  P-DATA, N-CREATE-RSP ID=1 (Success)
14  0.092677   127.0.0.1 → 127.0.0.1   DICOM 118  P-DATA, N-CREATE-RSP-DATA
16  0.094341   127.0.0.1 → 127.0.0.1   DICOM 224  P-DATA, N-SET-RQ ID=1
18  0.136687   127.0.0.1 → 127.0.0.1   DICOM 96   P-DATA, N-SET-RQ-DATA
20  0.138520   127.0.0.1 → 127.0.0.1   DICOM 234  P-DATA, N-SET-RSP ID=1 (Success)
22  0.184681   127.0.0.1 → 127.0.0.1   DICOM 96   P-DATA, N-SET-RSP-DATA
24  0.186891   127.0.0.1 → 127.0.0.1   DICOM 76   A-RELEASE request
25  0.188829   127.0.0.1 → 127.0.0.1   DICOM 76   A-RELEASE response
```
**Was du daran abliest:** Eine einzige Assoziation trägt beide
Meldungen nacheinander — `N-CREATE-RQ`/`-RSP`, dann `N-SET-RQ`/`-RSP`,
erst danach `A-RELEASE`. Wireshark erkennt hier den Dienst am
DIMSE-Kommando, nicht an einem Bildobjekt — es gibt keins.

## Storage Commitment: das Archiv meldet sich zurück

Anders als MPPS beherrscht Orthanc Storage Commitment nativ — kein
eigener Gegenpart nötig. Zuerst ein reales Objekt speichern und Orthanc
als eigenen Rückruf-Partner eintragen:

```
$ storescu -aec ORTHANC 127.0.0.1 4242 instance-0001.dcm
$ curl -s -X PUT http://127.0.0.1:8042/modalities/self \
    -d '{"AET":"ORTHANC","Host":"127.0.0.1","Port":4242,"AllowStorageCommitment":true}'
```
**Was du daran abliest:** `modalities/self` trägt Orthanc als eigene
Gegenstelle für sich selbst ein — in echten Häusern trägt man hier den
tatsächlichen Sender ein, der später zurückgerufen wird.
`AllowStorageCommitment: true` ist genau die Berechtigung, ohne die der
nächste Schritt scheitert.

```
$ curl -s -X POST http://127.0.0.1:8042/modalities/self/storage-commitment \
    -d '{"DicomInstances":[["1.2.840.10008.5.1.4.1.1.2","1.2.826.0.1.3680043.8.498.77387010640904175698082817117421166398"]]}'
{"ID":"2.25.7312585241035896279251430226340800562","Path":"/storage-commitment/2.25.7312585241035896279251430226340800562"}

$ curl -s http://127.0.0.1:8042/storage-commitment/2.25.7312585241035896279251430226340800562
{
   "Failures" : [],
   "RemoteAET" : "ORTHANC",
   "Status" : "Success",
   "Success" : [
      {
         "SOPClassUID" : "1.2.840.10008.5.1.4.1.1.2",
         "SOPInstanceUID" : "1.2.826.0.1.3680043.8.498.77387010640904175698082817117421166398"
      }
   ]
}
```
**Was du daran abliest:** Die REST-Anfrage stößt im Hintergrund ein
echtes `N-ACTION`/`N-EVENT-REPORT`-Gespräch zwischen Orthanc und sich
selbst an — die Antwort kommt verzögert (`Status` erst beim zweiten
Abruf `Success`), genau wie in echten Häusern, wo diese Rückmeldung oft
Sekunden bis Minuten braucht, weil sie über eine eigene, später
aufgebaute Verbindung läuft.

## Wenn die Kette reißt: eine echte Ablehnung

```
$ curl -s -X POST http://127.0.0.1:8042/modalities/self/storage-commitment \
    -d '{"DicomInstances":[["1.2.840.10008.5.1.4.1.1.2","1.2.826.0.1.3680043.8.498.53552263009351530673710551491962059037"]]}'
{"ID":"2.25.135214567284750214697666105137948041604", ...}

$ curl -s http://127.0.0.1:8042/storage-commitment/2.25.135214567284750214697666105137948041604
{
   "Failures" : [
      {
         "Description" : "One or more of the elements in the Referenced SOP Instance Sequence was not available",
         "FailureReason" : 274,
         "SOPClassUID" : "1.2.840.10008.5.1.4.1.1.2",
         "SOPInstanceUID" : "1.2.826.0.1.3680043.8.498.53552263009351530673710551491962059037"
      }
   ],
   "RemoteAET" : "ORTHANC",
   "Status" : "Failure"
}
```
**Was du daran abliest:** Dieselbe Anfrage für eine SOP Instance UID,
die nie gespeichert wurde, liefert eine echte Ablehnung mit
Klartextgrund — `FailureReason 274` ("Referenced SOP Instance nicht
vorhanden", PS3.4 J.3.4). Genau das ist der Fall aus dem Ticket: Bilder
können vollständig da sein, während die Commitment-Anfrage für ein
*anderes*, nie übermitteltes Objekt scheitert — zwei getrennte
Prüfungen, zwei getrennte Fehlerquellen.

## Wo die Rückmeldung landet

Weder MPPS noch Storage Commitment landen im PACS-Log des Archivs als
„Bildtransfer" — beide sind eigene Verbindungen mit eigenem Status. In
echten Häusern nimmt meist das RIS oder ein Broker die MPPS-Meldung
entgegen, nicht das Archiv selbst (dieselbe Rollenverteilung wie bei
der Worklist, Lektion 4.7). Storage Commitment dagegen ist tatsächlich
Sache des Archivs — aber der Rückruf zum Absender ist eine *eigene*,
oft separat konfigurierte Verbindung, die getrennt von der
Sendeverbindung stehen kann.

## Im Alltag

| Frage | Wo nachsehen |
|---|---|
| Kam überhaupt eine MPPS-Meldung an? | RIS/Broker-Log, nicht das PACS |
| Ist die Untersuchung als „Completed" markiert? | MPPS-Status (`N-SET`), nicht der Bildbestand |
| Hat das Archiv den Empfang bestätigt? | Storage-Commitment-Status, oft verzögert |
| Kommt die Commitment-Antwort überhaupt zurück? | Eigene Rückrufverbindung/-konfiguration prüfen |

## Stolperfallen

- **MPPS im PACS-Log suchen.** Das Archiv ist an MPPS oft gar nicht
  beteiligt — die Meldung geht direkt an RIS/Broker.
- **Den Rückweg für Storage Commitment nicht konfiguriert.** Damit das
  Archiv den Absender zurückrufen kann, muss dieser als eigene
  Gegenstelle eingetragen sein (`AllowStorageCommitment`) — fehlt das,
  bleibt die Anfrage unbeantwortet, ohne dass der eigentliche
  Speichervorgang je fehlschlägt.
- **Bildtransfer und Statuskette für dasselbe System halten.** Ein
  erfolgreiches C-STORE beweist nur, dass die Bytes angekommen sind —
  nichts über MPPS-Status oder Commitment-Bestätigung.

## Selbstcheck

1. Ein C-STORE ist erfolgreich, das RIS zeigt die Untersuchung trotzdem
   als „geplant". Welche zwei Dienste prüfst du als Nächstes — und wo,
   nicht am Archiv?
2. Eine Storage-Commitment-Anfrage für eine echte, gerade gespeicherte
   SOP Instance UID liefert `FailureReason 274`. Was schließt du daraus?
3. MPPS `N-CREATE` und `N-SET` laufen über dieselbe Assoziation. Was
   bedeutet das für den Fall, dass genau diese eine Verbindung
   scheitert?
