---
title: ServiceRequest, ImagingStudy und DiagnosticReport
teaser: Auftrag, erzeugte Bildstudie und Befund sind in FHIR drei verschiedene Ressourcen — und genau diese Trennung macht den Workflow nachvollziehbar.
objectives:
  - ServiceRequest, ImagingStudy und DiagnosticReport nach ihrer Rolle unterscheiden
  - Den Auftrag über References bis zur Bildstudie und zum Befund verfolgen
  - DICOM Study Instance UID und FHIR Resource ID korrekt auseinanderhalten
---

## Drei Ressourcen für drei Zustände

Ein Imaging-Workflow lässt sich vereinfacht so lesen:

<!-- kein-beispiel -->
```text
ServiceRequest
   │  Was soll gemacht werden?
   ▼
ImagingStudy
   │  Welche DICOM-Studie ist entstanden?
   ▼
DiagnosticReport
      Was wurde diagnostisch berichtet?
```

Die tatsächlichen Beziehungen können komplexer sein. Das Modell hilft aber, Auftrag, Durchführung und Ergebnis nicht zu vermischen.

## ServiceRequest: die Anforderung

FHIR R5 beschreibt ServiceRequest als Anfrage für eine Leistung, etwa eine diagnostische Untersuchung.

```json
{
  "resourceType": "ServiceRequest",
  "id": "sr-93821",
  "identifier": [{
    "system": "https://hospital.example/orders",
    "value": "ORD93821"
  }],
  "status": "active",
  "intent": "order",
  "subject": {"reference": "Patient/pat-4711"}
}
```

**Was du daran abliest:** Der Auftrag existiert unabhängig davon, ob bereits eine DICOM-Studie erzeugt wurde.

## ImagingStudy: Metadaten über die DICOM Study

Vereinfacht, FHIR R5:

```json
{
  "resourceType": "ImagingStudy",
  "id": "img-93821",
  "status": "available",
  "subject": {"reference": "Patient/pat-4711"},
  "basedOn": [{"reference": "ServiceRequest/sr-93821"}],
  "identifier": [{
    "system": "urn:dicom:uid",
    "value": "urn:oid:1.2.276.0.7230010.3.1.2.93821"
  }],
  "endpoint": [{"reference": "Endpoint/ep-wado-1"}],
  "numberOfSeries": 3,
  "numberOfInstances": 412
}
```

**Was du daran abliest:** `img-93821` ist eine FHIR Resource ID. Die DICOM Study Instance UID ist ein separater Identifier (System `urn:dicom:uid`, Wert `urn:oid:<UID>`). `endpoint` verweist zusätzlich auf eine `Endpoint`-Ressource — dort steht, **wo** und **über welchen Dienst** die Studie tatsächlich abrufbar ist.

Und besonders wichtig: ImagingStudy enthält **Informationen über** die Studie. Die DICOM-Instanzen selbst liegen nicht einfach als Pixelblöcke in dieser Ressource.

## Endpoint: die Zieladresse für den Bildabruf

```json
{
  "resourceType": "Endpoint",
  "id": "ep-wado-1",
  "status": "active",
  "connectionType": {
    "system": "http://terminology.hl7.org/CodeSystem/endpoint-connection-type",
    "code": "dicom-wado-rs"
  },
  "address": "https://pacs.example/dicom-web"
}
```

**Was du daran abliest:** `connectionType` sagt, welches Protokoll am `address`-Wert spricht — hier `dicom-wado-rs`, also DICOMweb-Retrieve. Ohne diese Ressource weißt du zwar, dass eine Studie existiert, aber nicht, an welche Basis-URL du für den Bildabruf überhaupt eine Anfrage stellen sollst. Wie aus Study Instance UID und Endpoint-Adresse eine konkrete Abfrage wird, zeigt 8.3.

## DiagnosticReport: das Ergebnis

FHIR R5 kann einen DiagnosticReport mit dem Auftrag und der ImagingStudy verbinden:

```json
{
  "resourceType": "DiagnosticReport",
  "id": "dr-93821",
  "status": "final",
  "subject": {"reference": "Patient/pat-4711"},
  "basedOn": [{"reference": "ServiceRequest/sr-93821"}],
  "imagingStudy": [{"reference": "ImagingStudy/img-93821"}],
  "conclusion": "Kein Nachweis eines fokalen Infiltrats."
}
```

**Was du daran abliest:** Auftrag, Studie und Befund bleiben getrennte Ressourcen und werden über References verbunden — das verbindende Feld im DiagnosticReport heißt `imagingStudy`, nicht `study`.

> Versionshinweis: Das Beispiel ist bewusst FHIR R5. Bei einer realen Schnittstelle prüfst du immer die tatsächlich eingesetzte FHIR-Version und das Implementation Guide/Profile, bevor du Feldnamen oder Kardinalitäten übernimmst.

## Der Trace über drei Identifier-Welten

Ein Administrator sieht parallel:

```text
HL7 / RIS order:        ORD93821
FHIR ServiceRequest:    ServiceRequest/sr-93821
DICOM Study Instance UID: 1.2.276.0.7230010.3.1.2.93821
FHIR ImagingStudy:      ImagingStudy/img-93821
FHIR DiagnosticReport:  DiagnosticReport/dr-93821
```

**Was du daran abliest:** Keine dieser Zeichenketten sollte gedankenlos durch eine andere ersetzt werden. Der Workflow funktioniert, weil explizite References und Identifier die Ebenen verbinden.

## Im Alltag heißt das

Wenn ein FHIR-basierter Befund „keine Bilder“ findet, prüfst du:

1. DiagnosticReport → verweist er auf die erwartete Studie?
2. ImagingStudy → gehört sie zum richtigen Patienten?
3. ImagingStudy → ist die DICOM Study Instance UID korrekt?
4. Endpoint/Viewer → kann die DICOM Study tatsächlich über den vorgesehenen Imaging-Endpunkt abgerufen werden?
5. ServiceRequest → passt der Auftrag zum Befund?

## Stolperfallen

- **FHIR ImagingStudy als Bildspeicher behandeln.**
- **FHIR Resource ID mit Study Instance UID verwechseln.**
- **Nur den Patient-Reference prüfen.** Ein richtiger Patient mit falschem Auftrag ist immer noch ein falscher Workflow.
- **R5-Beispiele ungeprüft auf R4/R4B übertragen.**

## Selbstcheck

1. Welche Ressource beschreibt den Auftrag, welche die erzeugte Studie, welche den Befund?
2. Wo liegt die DICOM Study Instance UID im Denkmodell?
3. Warum reicht `subject: Patient/pat-4711` nicht aus, um einen Bericht zweifelsfrei der richtigen Untersuchung zuzuordnen?

## Quiz

*Wissenskarten — kommen später zur Wiederholung zurück.*

**q1 — Welche FHIR-Ressource beschreibt die eigentliche Anforderung einer Leistung, bevor überhaupt eine DICOM-Studie existiert?**
1. ImagingStudy
2. DiagnosticReport
3. ServiceRequest
4. Endpoint

**q2 — Welche Aussagen stimmen?** *(Mehrfachauswahl)*
1. ImagingStudy enthält Informationen über eine DICOM-Studie, nicht die Pixeldaten selbst
2. Die FHIR Resource ID einer ImagingStudy ist dasselbe wie die DICOM Study Instance UID
3. DiagnosticReport kann per Reference auf ServiceRequest und ImagingStudy verweisen
4. R5-Beispiele lassen sich ungeprüft auf R4/R4B übertragen
