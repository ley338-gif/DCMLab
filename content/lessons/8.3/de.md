---
title: DICOMweb, FHIR und IHE zusammendenken
teaser: FHIR beschreibt klinischen Kontext. DICOMweb liefert Bildobjekte. IHE beschreibt, wie Standards in konkreten Workflows zusammenspielen.
objectives:
  - FHIR und DICOMweb nach ihrer Aufgabe trennen
  - QIDO-RS, WADO-RS und STOW-RS in den Imaging-Workflow einordnen
  - IHE-Profile als Workflow-Spezifikation statt als weiteres Transportprotokoll verstehen
---

## „Ich habe doch die ImagingStudy — wo sind die Bilder?“

Genau diese Frage trennt Ressourcenmetadaten von Bildzugriff.

Eine FHIR `ImagingStudy` beschreibt eine DICOM-Studie und kann Informationen enthalten, mit denen du sie auffindest. Sie ersetzt aber nicht den DICOM-Bildspeicher. Für Suche, Abruf und Speicherung von DICOM-Objekten gibt es DICOMweb.

## Drei DICOMweb-Dienste

In DICOM PS3.18 gehören zu den zentralen Webtransaktionen:

- **QIDO-RS** — Search
- **WADO-RS** — Retrieve
- **STOW-RS** — Store

## Vom FHIR-Endpoint zur WADO-RS-Anfrage

Die `ImagingStudy` aus 8.2 verweist über `endpoint` auf eine `Endpoint`-Ressource:

<!-- kein-beispiel -->
```text
ImagingStudy.identifier:   urn:dicom:uid / urn:oid:1.2.276.0.7230010.3.1.2.93821
ImagingStudy.endpoint  →   Endpoint/ep-wado-1
Endpoint.connectionType:   dicom-wado-rs
Endpoint.address:          https://pacs.example/dicom-web
```

Daraus ergibt sich die konkrete WADO-RS-Anfrage für die gesamte Studie:

<!-- kein-beispiel -->
```http
GET https://pacs.example/dicom-web/studies/1.2.276.0.7230010.3.1.2.93821
Accept: multipart/related; type="application/dicom"
```

**Was du daran abliest:** Weder die Study Instance UID allein noch die Endpoint-Adresse allein reichen. Erst die Kombination ergibt eine gültige WADO-RS-Anfrage: Basis-URL aus `Endpoint.address`, Pfad aus der Study Instance UID, `Accept` passend zur gewünschten WADO-RS-Repräsentation. `connectionType` sagt dir nur, welcher Dienst am Endpoint hängt (hier WADO-RS) — der konkrete Media Type wird über `Accept` ausgehandelt und hängt von der gewünschten Repräsentation und den Serverfähigkeiten ab. Ein Retrieve auf Study- oder Series-Ebene liefert dabei laut PS3.18 immer eine `multipart/related`-Antwort — nicht ein einzelnes `application/dicom`-Objekt ohne Umschlag, selbst wenn die Studie nur eine Instanz enthält.

Die vorhandene Orthanc-Spielwiese kann genau diese Anfrage demonstrieren, jetzt gegen echte Testdaten statt der fiktiven Beispiel-IDs aus 8.2. Ihr Archiv startet leer; spiel zuerst die Teststudie ein (Lektion 2.2):

```
$ storescu -aec ORTHANC 127.0.0.1 4242 daten/ct-thorax-60/
[Exitcode 0]
```

**Was du daran abliest:** Keine Ausgabe, Exitcode `0` — ob alles angekommen ist, zeigt gleich die Suche.

### Suchen mit QIDO-RS

```
$ curl -s "http://127.0.0.1:8042/dicom-web/studies?PatientID=4711" | tr -d "\n\t"
[{"00080005" : {"Value" : ["ISO_IR 192"],"vr" : "CS"},"00080061" : {"Value" : ["CT"],"vr" : "CS"},"00081190" : {"Value" : ["http://127.0.0.1:8042/dicom-web/studies/1.2.826.0.1.3680043.8.498.59933747330626005048654467827321321057"],"vr" : "UR"},"00100010" : {"Value" : [{"Alphabetic" : "MUSTER^ERIKA"}],"vr" : "PN"},"00100020" : {"Value" : ["4711"],"vr" : "LO"},"0020000D" : {"Value" : ["1.2.826.0.1.3680043.8.498.59933747330626005048654467827321321057"],"vr" : "UI"},"00201206" : {"Value" : [2],"vr" : "IS"},"00201208" : {"Value" : [60],"vr" : "IS"}}]
[Exitcode 0]
```

**Was du daran abliest:** QIDO-RS liefert DICOM-Metadaten in einer Webrepräsentation — dieselben Tags wie bei DIMSE (`0020000D` ist die Study Instance UID, `00201208` die Zahl der Bilder: 60), nur JSON-kodiert (`tr` fasst Orthancs mehrzeilige Ausgabe nur zu einer Zeile zusammen). `00081190` nennt sogar gleich die URL, unter der die Studie per WADO-RS abrufbar ist — die Kombination aus Endpoint-Adresse und UID von oben. Die Study Instance UID bleibt eine DICOM-Identität, keine FHIR Resource ID; deine ist eine andere, die Testdaten bekommen bei jedem Aufbau neue UIDs.

### Abruf mit WADO-RS

Die UID kommt aus deiner eigenen QIDO-Antwort in eine Datei, der Abruf liest sie von dort:

```
$ curl -s "http://127.0.0.1:8042/dicom-web/studies?PatientID=4711" | grep -o '1\.2\.826[0-9.]*' | head -1 > studie.uid
[Exitcode 0]
$ curl -s -D - -o /dev/null -H 'Accept: multipart/related; type="application/dicom"' "http://127.0.0.1:8042/dicom-web/studies/$(cat studie.uid)" | grep -E "^HTTP|^Content-Type"
HTTP/1.1 200 OK
Content-Type: multipart/related; type="application/dicom"; boundary=f75c3d1a-7d59-41ef-a75b-d8816be529e3-9cea44f3-d9a4-4a0a-a125-66cf228cf
[Exitcode 0]
```

**Was du daran abliest:** Genau wie eben aus Endpoint-Adresse und Study Instance UID hergeleitet, liefert der reale Abruf gegen die Spielwiese dieselbe `multipart/related`-Antwort mit den angeforderten DICOM-Objekten. Der Bildabruf gehört an diesen DICOMweb-Endpunkt — nicht an einen vermeintlichen Bild-Unterpfad des FHIR-Servers.

## FHIR liefert Kontext, DICOMweb liefert Imaging

Ein nützliches Denkmodell:

```text
FHIR
  Patient ── ServiceRequest ── ImagingStudy ── DiagnosticReport
                              │
                              │ DICOM Study Instance UID / Endpoint
                              ▼
DICOMweb
  QIDO-RS ── findet Studie
  WADO-RS ── liefert DICOM-Objekte
  STOW-RS ── speichert DICOM-Objekte
```

**Was du daran abliest:** FHIR und DICOMweb konkurrieren nicht um dieselbe Aufgabe. Sie können sich im selben Workflow ergänzen.

## Und wo kommt IHE hinein?

IHE ist kein Ersatz für DICOM oder HL7. IHE-Profile beschreiben konkrete Integrationsprobleme und legen fest, wie vorhandene Standards von definierten Akteuren in bestimmten Transaktionen eingesetzt werden.

Für Radiologie sind unter anderem Profile rund um Scheduled Workflow, Patient Information Reconciliation, Imaging Object Change Management und Web-based Image Access relevant.

**Was du daran abliest:** Wenn ein Hersteller „unterstützt DICOM, HL7 und FHIR“ sagt, ist die entscheidende Folgefrage weiterhin: **Welche Workflows/Profile, Rollen, Transaktionen und Optionen?**

## Ein Ende-zu-Ende-Blick

```text
KIS/EHR
 │
 │ Auftrag / Patientenkontext
 ▼
RIS / Workflow-System
 │
 ├──── DICOM MWL ─────► Modalität
 │                         │
 │                         └── DICOM / DICOMweb Store ──► PACS/VNA
 │
 └──── Befund / Kontext ───────────────────────────────► EHR
                                                        │
                                                        └─ FHIR API / Viewer
```

**Was du daran abliest:** Moderne Umgebungen sind selten „FHIR statt DICOM“. Häufig existieren klassische und webbasierte Integrationspfade parallel.

## Im Alltag heißt das

Für jede Integration stellst du vier Fragen:

1. **Fachlicher Workflow:** Was soll passieren?
2. **Informationsmodell:** Welche Identitäten und Zustände müssen erhalten bleiben?
3. **Transport/API:** DICOM DIMSE, DICOMweb, HL7 v2, FHIR REST?
4. **Integrationsprofil:** Welche IHE-/lokalen Profile definieren das Zusammenspiel?

## Stolperfallen

- **ImagingStudy als WADO-Ersatz verwenden wollen.**
- **DICOMweb als „FHIR für Bilder“ bezeichnen.** Beide sind webfähig, aber unterschiedliche Standards und Modelle.
- **IHE als zusätzliches Netzwerkprotokoll behandeln.**
- **Nur Featurelisten vergleichen.** Für Beschaffung und Integration sind unterstützte Profile, Rollen und Optionen wichtiger.

## Lab

In „FHIR ist nicht WADO” bekommst du eine valide ImagingStudy mit eigenem Endpoint. Du musst nicht nur die falsche Schicht erkennen, sondern aus Endpoint-Adresse, Study Instance UID und `connectionType` den tatsächlich korrekten Abruf rekonstruieren.

## Selbstcheck

1. Welcher Dienst sucht DICOM-Studien, welcher ruft sie ab?
2. Warum enthält eine ImagingStudy nicht automatisch die DICOM-Pixel?
3. Was ist die Aufgabe von IHE in diesem Modell?

## Quiz

*Wissenskarten — kommen später zur Wiederholung zurück.*

**q1 — Welcher DICOMweb-Dienst ruft konkrete DICOM-Objekte ab?**
1. QIDO-RS
2. WADO-RS
3. STOW-RS
4. MWL

**q2 — Welche Aussagen stimmen?** *(Mehrfachauswahl)*
1. FHIR und DICOMweb konkurrieren nicht um dieselbe Aufgabe, sondern können sich ergänzen
2. IHE ersetzt DICOM und HL7 als eigenes Transportprotokoll
3. IHE-Profile legen fest, wie Standards von definierten Akteuren in konkreten Transaktionen eingesetzt werden
4. Eine FHIR ImagingStudy ersetzt den DICOM-Bildspeicher

**q3 — Welchen Media Type (`Content-Type`) liefert ein WADO-RS-Abruf einer ganzen Studie als DICOM-Objekte? Nur der Typ, ohne Parameter.** *(Freitext)*

**q4 — Eine QIDO-RS-Antwort nennt eine Study Instance UID. Was brauchst du zusätzlich, um die Bilder per WADO-RS abzurufen?**
1. Die FHIR Resource ID der ImagingStudy
2. Die Basis-URL des DICOMweb-Endpoints; daraus wird `…/studies/{UID}` mit passendem `Accept`
3. Einen Eintrag in der Modality Worklist
4. Nichts, die UID allein genügt

---

Werkzeuglage geprüft am 04.10.2026 (Spielwiese: `curl`, `storescu` aus pynetdicom 3.0.4, Orthanc 1.13.0 im Image `dcmlab/orthanc` mit DICOMweb-Plugin).
