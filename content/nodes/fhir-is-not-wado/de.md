---
title: FHIR ist nicht WADO
scenario_title: ImagingStudy gefunden, Bilder trotzdem 404
---

## Briefing

Ein neues Portal kann Patient 2290, den Auftrag und die `ImagingStudy`
`img-55814` problemlos über FHIR lesen — auch die referenzierte
`Endpoint`-Ressource ist sichtbar. Erst beim Öffnen der Bilder kommt ein
404.

Das ist kein Ratespiel zwischen drei Diensten. Du musst aus den
vorhandenen Ressourcen die tatsächlich korrekte Anfrage rekonstruieren:
richtige Basis-URL, richtiger Pfad, richtiger `Accept`-Header.

Notiere währenddessen:

<!-- kein-beispiel -->
```text
Study Instance UID:  1.2.276.0.7230010.3.1.2.771205
Endpoint:             ep-55
connectionType:       dicom-wado-rs
address:              https://bildarchiv.example/dicomweb
```

## Hints

### h1

Eine FHIR `ImagingStudy` beschreibt eine DICOM Study. Sie ist nicht selbst
die Sammlung aller DICOM-Instanzen und liegt nicht zwingend am selben
Server wie der Bildabruf.

### h2

Die referenzierte `Endpoint`-Ressource sagt dir, an welche Basis-URL und
über welches Protokoll (`connectionType`) du den Bildabruf richten musst
— nicht der FHIR-Server selbst.

### h3

Ein WADO-RS-Retrieve adressiert die Study Instance UID im **Pfad**, nicht
als Query-Parameter wie QIDO-RS, und liefert für Study- oder Series-Ebene
immer `multipart/related; type="application/dicom"` zurück — auch wenn
die Studie nur eine Instanz enthält.

## Write-up

### Erst die Ressourcen lesen

```text
ImagingStudy.identifier:  urn:dicom:uid / urn:oid:1.2.276.0.7230010.3.1.2.771205
ImagingStudy.endpoint  →  Endpoint/ep-55
Endpoint.connectionType:  dicom-wado-rs
Endpoint.address:         https://bildarchiv.example/dicomweb
```

**Was du daran abliest:** Die Study Instance UID identifiziert die
DICOM-Studie. `Endpoint.address` und `connectionType` sagen dir, wohin
und über welches Protokoll der Bildabruf tatsächlich gehört — nicht an
den FHIR-Server, an dem die `ImagingStudy` liegt.

### Dann die Anfrage zusammensetzen — nicht raten

Vier Kandidaten, drei davon mit je einem eigenen Fehler:

```text
A) GET https://bildarchiv.example/dicomweb/studies/1.2.276.0.7230010.3.1.2.771205
   Accept: application/dicom
   → falscher Accept-Header: ein Study-Retrieve liefert immer multipart/related

B) GET https://bildarchiv.example/dicomweb/studies/1.2.276.0.7230010.3.1.2.771205
   Accept: multipart/related; type="application/dicom"
   → korrekt

C) GET https://bildarchiv.example/fhir/ImagingStudy/img-55814/pixels
   → falsche Basis-URL/Schicht: FHIR-Server statt DICOMweb-Endpoint

D) GET https://bildarchiv.example/dicomweb/studies?StudyInstanceUID=1.2.276.0.7230010.3.1.2.771205
   Accept: multipart/related; type="application/dicom"
   → falsche Syntax: das ist eine QIDO-RS-Suche (Query-Parameter), kein WADO-RS-Retrieve (Pfad)
```

**Was du daran abliest:** Drei unabhängige Dinge müssen gleichzeitig
stimmen — Basis-URL (aus `Endpoint.address`, nicht dem FHIR-Server),
Pfadstruktur (Study Instance UID im Pfad, WADO-RS-Retrieve statt
QIDO-RS-Suche) und `Accept`-Header (`multipart/related`, nicht ein
nacktes `application/dicom`). Nur B kombiniert alle drei richtig.

### Was du mitnimmst

Weder ein anderer Viewer noch ein erneut angestoßener Auftrag behebt die
Ursache — der Fehler liegt in der Anfrage selbst, nicht im Client-Produkt
oder in der Auftragskette. Erst die Kombination aus `Endpoint.address`,
Study Instance UID im Pfad und dem passenden `Accept`-Header ergibt eine
gültige WADO-RS-Anfrage.
