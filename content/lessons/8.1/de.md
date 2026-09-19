---
title: FHIR für PACS-Admins — Ressourcen statt Segmentzeilen
teaser: FHIR ersetzt nicht einfach HL7 v2. Es modelliert klinische Informationen als verknüpfte Ressourcen und wird oft über HTTP APIs verwendet.
objectives:
  - Das Ressourcenmodell von FHIR gegenüber HL7 v2 einordnen
  - Referenzen und Identifier unterscheiden
  - Eine einfache FHIR-Ressource aus Administrationssicht lesen
---

## Von der Nachricht zum Ressourcenmodell

HL7 v2 denkt häufig in Ereignisnachrichten: Etwas passiert, eine Nachricht wird gesendet.

FHIR modelliert fachliche Objekte als Ressourcen. Für einen Imaging-Workflow sind unter anderem interessant:

<!-- kein-beispiel -->
```text
Patient
ServiceRequest
ImagingStudy
DiagnosticReport
Endpoint
```

Diese fünf begleiten dich durch 8.1–8.3: Patient und ServiceRequest in dieser Lektion, ImagingStudy und DiagnosticReport in 8.2, Endpoint als Bindeglied zum tatsächlichen Bildabruf in 8.3.

Das bedeutet nicht, dass FHIR „HL7 v2 in JSON“ ist. Das Denkmodell ist anders.

## Eine kleine Patient-Ressource

Vereinfachtes FHIR-R5-Beispiel:

```json
{
  "resourceType": "Patient",
  "id": "pat-4711",
  "identifier": [
    {
      "system": "https://hospital.example/mrn",
      "value": "4711"
    }
  ],
  "name": [
    {
      "family": "Muster",
      "given": ["Erika"]
    }
  ]
}
```

**Was du daran abliest:** `id` ist die technische Resource ID auf diesem FHIR-Server. Die klinische Patientennummer steht separat unter `identifier`.

## Resource ID ist nicht Patient ID

Das ist eine der wichtigsten Fallen.

```text
FHIR URL:
Patient/pat-4711
        └─────── technische Resource ID

Identifier:
system = https://hospital.example/mrn
value  = 4711
         └── fachlicher Identifier
```

**Was du daran abliest:** Kopierst du eine FHIR-Ressource in ein anderes System, muss ihre technische `id` nicht gleich bleiben. Der fachliche Identifier besitzt deshalb zusätzlich ein `system`.

## Referenzen verbinden Ressourcen

Eine ServiceRequest kann zum Beispiel auf einen Patienten verweisen:

```json
{
  "resourceType": "ServiceRequest",
  "id": "sr-93821",
  "status": "active",
  "intent": "order",
  "subject": {
    "reference": "Patient/pat-4711"
  }
}
```

**Was du daran abliest:** FHIR bildet Beziehungen explizit über References ab. Für Troubleshooting musst du deshalb nicht nur Werte lesen, sondern auch verfolgen, wohin Ressourcen zeigen.

## Identifier bleiben trotzdem wichtig

Systemübergreifende Integration funktioniert nicht zuverlässig, wenn nur interne Resource IDs ausgetauscht werden.

```json
"identifier": [
  {
    "system": "https://hospital.example/orders",
    "value": "ORD93821"
  }
]
```

**Was du daran abliest:** `system + value` erfüllt dieselbe wichtige Disambiguierungsfunktion, die du bei HL7 v2 bereits über Assigning Authorities kennengelernt hast.

## REST ist Transport, FHIR ist Modell

FHIR wird oft über REST/HTTP genutzt:

<!-- kein-beispiel -->
```http
GET /fhir/Patient/pat-4711
Accept: application/fhir+json
```

Antwort:

```http
HTTP/1.1 200 OK
Content-Type: application/fhir+json
```

**Was du daran abliest:** HTTP 200 sagt, dass eine Ressource geliefert wurde. Ob sie fachlich zum gesuchten Patienten, Auftrag oder Workflow passt, musst du weiterhin anhand von Identifiern und References beurteilen.

## Im Alltag heißt das

Beim FHIR-Troubleshooting trennst du:

1. HTTP/TLS/Auth — komme ich an die API?
2. FHIR-Syntax — ist die Ressource valide/verarbeitbar?
3. Profil — erfüllt sie die lokalen/IG-Anforderungen?
4. Fachliche Identität — ist es wirklich der richtige Patient/Auftrag?
5. Referenzen — zeigen die Ressourcen aufeinander wie erwartet?

## Stolperfallen

- **Resource ID als globale Patienten-ID behandeln.**
- **FHIR mit JSON gleichsetzen.** JSON ist nur eine Repräsentation.
- **HTTP 200 als fachlichen Erfolg interpretieren.**
- **FHIR-Version ignorieren.** Felder und Profile können sich zwischen Versionen unterscheiden.

## Selbstcheck

1. Was ist der Unterschied zwischen `Patient.id` und `Patient.identifier`?
2. Warum reicht ein HTTP-Statuscode nicht für fachliche Diagnose?
3. Welche zwei FHIR-Konzepte entsprechen grob den bereits bekannten Themen „Identifier-Domäne“ und „Beziehung zwischen Objekten“?

## Quiz

*Wissenskarten — kommen später zur Wiederholung zurück.*

**q1 — Was unterscheidet `Patient.id` von einem Eintrag in `Patient.identifier`?**
1. Es ist kein Unterschied, beide meinen dasselbe
2. `id` ist die technische Resource ID auf einem konkreten FHIR-Server, `identifier` trägt den fachlichen Identifier mit System
3. `identifier` wird beim Kopieren zwischen Systemen nie geändert
4. `id` ist immer die Patientennummer aus dem KIS

**q2 — Welche Aussagen stimmen?** *(Mehrfachauswahl)*
1. FHIR-Ressourcen verbinden sich explizit über References
2. Ein HTTP 200 auf eine FHIR-Anfrage beweist automatisch die fachliche Korrektheit der gelieferten Ressource
3. FHIR ist nicht dasselbe wie „HL7 v2 in JSON"
4. `system` + `value` eines Identifiers erfüllen dieselbe Disambiguierungsfunktion wie eine HL7-Assigning-Authority
