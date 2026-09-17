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

Die vorhandene Orthanc-Spielwiese kann DICOMweb bereits demonstrieren.

### Suchen mit QIDO-RS

```bash
$ curl -s http://127.0.0.1:8042/dicom-web/studies
[{
  "00100010": { "vr": "PN", "Value": [{ "Alphabetic": "MUSTER^ERIKA" }] },
  "00100020": { "vr": "LO", "Value": ["4711"] },
  "0020000D": { "vr": "UI", "Value": ["1.2.826.0.1.3680043.8.498.25891700843541702377744771512561342346"] },
  "00201208": { "vr": "IS", "Value": [3] }
}]
```

**Was du daran abliest:** QIDO-RS liefert DICOM-Metadaten in einer Webrepräsentation — dieselben Tags wie bei DIMSE (`0020000D` ist die Study Instance UID), nur JSON-kodiert. Die Study Instance UID bleibt eine DICOM-Identität, keine FHIR Resource ID.

### Abruf mit WADO-RS

```bash
$ curl -s -D - -o /dev/null \
  -H 'Accept: multipart/related; type="application/dicom"' \
  http://127.0.0.1:8042/dicom-web/studies/1.2.826.0.1.3680043.8.498.25891700843541702377744771512561342346
HTTP/1.1 200 OK
Content-Type: multipart/related; type="application/dicom"; boundary=9c27af7e-...
```

**Was du daran abliest:** Der Bildabruf passiert über den DICOMweb-Endpunkt mit `Accept: multipart/related`, nicht durch `GET /fhir/ImagingStudy/.../pixels` — genau der Fehler, den die Node „FHIR ist nicht WADO“ zeigt. Ein `HEAD`-Request auf dieselbe URL liefert dagegen `404`, weil das WADO-RS-Retrieve nur auf `GET` reagiert.

## FHIR liefert Kontext, DICOMweb liefert Imaging

Ein nützliches Denkmodell:

```text
FHIR
  Patient ── ServiceRequest ── ImagingStudy ── DiagnosticReport
                              │
                              │ DICOM Study UID / Endpoint
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

In „FHIR ist nicht WADO“ bekommst du eine valide ImagingStudy und einen funktionierenden Viewer-Link — trotzdem versucht ein Client, die Pixel über den FHIR-Endpunkt zu laden. Du musst die falsche Schicht identifizieren.

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
