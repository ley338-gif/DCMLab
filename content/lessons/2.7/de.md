---
title: "Storage Commitment — hast du's wirklich?"
teaser: Der Dienst, der aus einem „angekommen" ein „gesichert, du darfst löschen" macht.
objectives:
  - Storage Commitment von einer erfolgreichen C-STORE-Response abgrenzen
  - N-ACTION und N-EVENT-REPORT den beiden Seiten des Diensts zuordnen
  - Eine echte Ablehnung von einem echten Erfolg unterscheiden
---

## Eine lokale Kopie, die niemand löschen will

Ein Sender behält seine Objekte oft eine Weile lokal — als Sicherheit,
falls die Übertragung nicht ankam. Aber wann ist es sicher, diese
Kopie zu löschen? Ein erfolgreiches C-STORE beweist nur, dass die
Bytes in diesem Moment ankamen — nichts darüber, ob das Archiv sie
dauerhaft behält. Genau diese Lücke füllt
{{term:storage-commitment}}.

## Zwei Nachrichten, zwei getrennte Verbindungen

Anders als MPPS (Lektion 2.6), wo beide Nachrichten auf derselben
Assoziation laufen, sind die beiden Storage-Commitment-Nachrichten
typischerweise zwei **eigene** Verbindungen: `N-ACTION` stößt die
Prüfung an, `N-EVENT-REPORT` liefert das Ergebnis — oft erst
Sekunden später, initiiert vom Archiv selbst.

```
$ curl -s -X PUT http://127.0.0.1:8042/modalities/self \
    -d '{"AET":"ORTHANC","Host":"127.0.0.1","Port":4242,"AllowStorageCommitment":true}'
$ storescu -aec ORTHANC 127.0.0.1 4242 instance-0001.dcm
$ curl -s -X POST http://127.0.0.1:8042/modalities/self/storage-commitment \
    -d '{"DicomInstances":[["1.2.840.10008.5.1.4.1.1.2","1.2.826.0.1.3680043.8.498.83304813724044050027593685876659206385"]]}'
{"ID":"2.25.303469340912709047195556732745508879479","Path":"/storage-commitment/2.25.303469340912709047195556732745508879479"}

$ curl -s http://127.0.0.1:8042/storage-commitment/2.25.303469340912709047195556732745508879479
{
   "Failures" : [],
   "RemoteAET" : "ORTHANC",
   "Status" : "Success",
   "Success" : [
      {
         "SOPClassUID" : "1.2.840.10008.5.1.4.1.1.2",
         "SOPInstanceUID" : "1.2.826.0.1.3680043.8.498.83304813724044050027593685876659206385"
      }
   ]
}
```
**Was du daran abliest:** `modalities/self` trägt die Gegenstelle für
den Rückruf ein — `AllowStorageCommitment: true` ist die Berechtigung,
ohne die die Anfrage scheitert. Die REST-Anfrage selbst liefert nur
eine Transaktions-ID zurück; das eigentliche Ergebnis (`Status:
"Success"`) steht erst beim zweiten Abruf bereit — dazwischen liegt
genau die Verzögerung, die der Dienst strukturell mitbringt.

## Auf der Leitung: zwei vollständig getrennte Assoziationen

```
$ tshark -i lo -f "tcp port 4242" -Y dicom
4   0.000059   127.0.0.1 → 127.0.0.1   DICOM 303 A-ASSOCIATE request ORTHANC --> ORTHANC
6   0.000215   127.0.0.1 → 127.0.0.1   DICOM 258 A-ASSOCIATE accept  ORTHANC <-- ORTHANC
9   0.000408   127.0.0.1 → 127.0.0.1   DICOM 176 P-DATA, N-ACTION-RQ ID=1
11  0.000427   127.0.0.1 → 127.0.0.1   DICOM 244 P-DATA, N-ACTION-RQ-DATA
15  0.000762   127.0.0.1 → 127.0.0.1   DICOM 176 P-DATA, N-ACTION-RSP ID=1 (Success)
17  0.000813   127.0.0.1 → 127.0.0.1   DICOM 76  A-RELEASE request
18  0.000863   127.0.0.1 → 127.0.0.1   DICOM 76  A-RELEASE response
25  0.001110   127.0.0.1 → 127.0.0.1   DICOM 331 A-ASSOCIATE request ORTHANC --> ORTHANC
27  0.001210   127.0.0.1 → 127.0.0.1   DICOM 286 A-ASSOCIATE accept  ORTHANC <-- ORTHANC
30  0.001368   127.0.0.1 → 127.0.0.1   DICOM 176 P-DATA, N-EVENT-REPORT-RQ ID=1
33  0.001389   127.0.0.1 → 127.0.0.1   DICOM 266 P-DATA, N-EVENT-REPORT-RQ-DATA
36  0.001499   127.0.0.1 → 127.0.0.1   DICOM 176 P-DATA, N-EVENT-REPORT-RSP ID=1 (Success)
38  0.001544   127.0.0.1 → 127.0.0.1   DICOM 76  A-RELEASE request
39  0.001594   127.0.0.1 → 127.0.0.1   DICOM 76  A-RELEASE response
```
**Was du daran abliest:** Zwei vollständig eigenständige
Assoziationen mit eigenem `A-ASSOCIATE`/`A-RELEASE` — nicht zwei
Nachrichten derselben Verbindung wie bei MPPS. Die erste trägt
`N-ACTION-RQ` (die Anfrage „prüf das bitte"), die zweite —
vom Archiv selbst aufgebaut, hier zufällig an sich selbst — trägt
`N-EVENT-REPORT-RQ` (die Rückmeldung). In echten Häusern öffnet das
Archiv für diese zweite Verbindung häufig eine neue Assoziation zum
ursprünglichen Sender zurück, oft über einen separat konfigurierten
Port.

## Eine echte Ablehnung mit echtem Grund

```
$ curl -s -X POST http://127.0.0.1:8042/modalities/self/storage-commitment \
    -d '{"DicomInstances":[["1.2.840.10008.5.1.4.1.1.2","1.2.826.0.1.3680043.8.498.55277674279693220879529206884704852800"]]}'
{"ID":"2.25.223015034389401016166705023436183844583", ...}

$ curl -s http://127.0.0.1:8042/storage-commitment/2.25.223015034389401016166705023436183844583
{
   "Failures" : [
      {
         "Description" : "One or more of the elements in the Referenced SOP Instance Sequence was not available",
         "FailureReason" : 274,
         "SOPClassUID" : "1.2.840.10008.5.1.4.1.1.2",
         "SOPInstanceUID" : "1.2.826.0.1.3680043.8.498.55277674279693220879529206884704852800"
      }
   ],
   "RemoteAET" : "ORTHANC",
   "Status" : "Failure",
   "Success" : []
}
```
**Was du daran abliest:** Dieselbe Anfrage für eine SOP Instance UID,
die nie gespeichert wurde, liefert eine echte Ablehnung mit
Klartextgrund — `FailureReason 274` ("Referenced SOP Instance nicht
vorhanden", PS3.4 Annex J.3.4). Kein vages „Störung", sondern ein
konkreter, im Standard definierter Wert — genau wie bei einer
Presentation-Context-Ablehnung (Lektion 1.7) trägt auch diese
Ablehnung einen exakten, nachschlagbaren Grund.

## Was Storage Commitment nicht ist

Ein erfolgreiches C-STORE (Lektion 2.2) beweist nur den Moment der
Übertragung. Storage Commitment beweist etwas anderes: dass das
Archiv das Objekt zu einem *späteren* Zeitpunkt noch hat — die
eigentliche Zusicherung für „du darfst deine Kopie löschen". Beide
Dienste beantworten unterschiedliche Fragen zu demselben Objekt.

## Im Alltag

| Frage | Womit prüfen |
|---|---|
| Wurde die Prüfung überhaupt angestoßen? | `N-ACTION`-Log auf der SCU-Seite |
| Kam die Rückmeldung zurück? | `N-EVENT-REPORT` auf einer eigenen, oft neuen Verbindung |
| Warum wurde ein Objekt abgelehnt? | Der reale `FailureReason`-Wert (PS3.4 Annex J.3.4) |

## Stolperfallen

- **C-STORE-Erfolg mit einer Speichergarantie verwechseln.** Ein
  erfolgreiches C-STORE sagt nichts darüber, ob das Archiv das Objekt
  dauerhaft behält.
- **Den Rückweg vergessen.** Ohne eine Gegenstelle, die für den
  Rückruf erreichbar ist (`AllowStorageCommitment`), bleibt die
  Anfrage unbeantwortet — ohne dass der ursprüngliche Speichervorgang
  je fehlschlägt.
- **Eine Ablehnung für einen Netzwerkfehler halten.** Ein
  `FailureReason` ist ein Inhalt der Antwort, keine gescheiterte
  Zustellung.

## Selbstcheck

1. Warum reicht ein erfolgreiches C-STORE allein nicht, um eine lokale
   Kopie sicher zu löschen?
2. Welche Nachricht stößt die Prüfung an, welche liefert das Ergebnis
   — und warum laufen sie typischerweise über getrennte Verbindungen?
3. Eine Storage-Commitment-Anfrage für eine echte, gerade gespeicherte
   SOP Instance UID liefert trotzdem `FailureReason 274`. Was schließt
   du daraus?
