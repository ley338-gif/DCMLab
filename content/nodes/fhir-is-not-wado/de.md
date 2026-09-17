---
title: FHIR ist nicht WADO
scenario_title: ImagingStudy gefunden, Bilder trotzdem 404
---

## Briefing

Ein neues Portal kann den Patienten, den Auftrag und die `ImagingStudy`
problemlos über FHIR lesen. Erst beim Öffnen der Bilder kommt ein 404.

Das ist ein typischer Integrationsfehler: Die Metadatenebene funktioniert,
aber der Client erwartet die Bilddaten an derselben API. Nutze `curl -i
http://10.60.0.30<pfad>`, um den fhir-server selbst zu befragen, und finde
heraus, welche Schicht den Bildabruf tatsächlich übernehmen sollte.

## Hints

### h1

Eine FHIR `ImagingStudy` beschreibt eine DICOM Study. Sie ist nicht selbst die
Sammlung aller DICOM-Instanzen.

### h2

QIDO = suchen, WADO = abrufen, STOW = speichern.

### h3

Gesucht ist `WADO-RS`.

## Write-up

FHIR funktioniert:

```text
$ curl -i http://10.60.0.30/fhir/ImagingStudy/img-93821
HTTP/1.1 200 OK
Content-Type: application/fhir+json

{"resourceType":"ImagingStudy","id":"img-93821","status":"available",
 "subject":{"reference":"Patient/pat-4711"},
 "identifier":[{"system":"urn:dicom:uid",
   "value":"urn:oid:1.2.276.0.7230010.3.1.2.93821"}],
 "numberOfSeries":3,"numberOfInstances":412}
```

**Was du daran abliest:** Die Ressource liefert Kontext und unter anderem
die DICOM Study UID — kein Hinweis darauf, dass hier auch Pixel liegen.

Der fehlerhafte Client macht anschließend:

```text
$ curl -i http://10.60.0.30/fhir/ImagingStudy/img-93821/pixels
HTTP/1.1 404 Not Found
Content-Type: application/fhir+json

{"resourceType":"OperationOutcome","issue":[{"severity":"error",
  "code":"not-found","diagnostics":"Not Found"}]}
```

**Was du daran abliest:** Nicht die Study ist kaputt, sondern die Annahme des
Clients. FHIR liefert hier Kontext über die Studie, nicht einen proprietären
`/pixels`-Unterpfad.

Der Imaging-Abruf gehört auf die DICOMweb-Seite, zum Beispiel sinngemäß:

```http
GET /dicom-web/studies/1.2.276.0.7230010.3.1.2.93821
Accept: application/dicom
```

**Was du daran abliest:** Derselbe reale Untersuchungsfall wird über die DICOM
Study UID zwischen FHIR-Metadaten und DICOMweb korreliert. Für den tatsächlichen
Abruf ist WADO-RS die passende Schicht.
