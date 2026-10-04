---
title: Abschlussprüfung — FHIR & Web
intro: 10 Fragen aus Track 8 (FHIR & Web). Ab 80 % ist der Track abgeschlossen. Beliebig oft wiederholbar.
---

### f01 — Was unterscheidet die technische FHIR Resource ID vom fachlichen Identifier?

1. Es gibt keinen Unterschied
2. Die Resource ID gilt nur auf einem konkreten Server, der fachliche Identifier trägt zusätzlich ein `system`
3. Der Identifier ist immer kürzer
4. Nur die Resource ID darf sich ändern

**Erklärung:** `Patient.id` ist die technische Resource ID auf diesem FHIR-Server. Die klinische Patientennummer steht separat unter `identifier`, mit einem `system`, das die ausstellende Domäne benennt.

### f02 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. FHIR-Ressourcen verweisen über References explizit aufeinander
2. Ein HTTP 200 beweist die fachliche Korrektheit der gelieferten Ressource
3. `system` + `value` disambiguieren einen Identifier ähnlich wie eine HL7-Assigning-Authority
4. FHIR ist kein reines „HL7 v2 in JSON"

**Erklärung:** FHIR bildet Beziehungen explizit über References ab, `system`+`value` erfüllen dieselbe Disambiguierungsfunktion wie eine HL7-Assigning-Authority, und FHIR modelliert fachliche Objekte anders als HL7 v2s Ereignisnachrichten. Ein HTTP 200 sagt dagegen nur, dass eine Ressource geliefert wurde — nicht, dass sie fachlich zum gesuchten Fall passt.

### f17 — Eine kopierte FHIR-Ressource kann auf dem Zielserver eine andere technische `id` bekommen.

**Richtig / Falsch**

**Erklärung:** Richtig. Die Resource ID vergibt der jeweilige Server; beim Kopieren muss sie nicht gleich bleiben. Deshalb ist der fachliche Identifier mit `system` und `value` zusätzlich wichtig.

### f04 — Über welches HTTP-Verb wird eine einzelne FHIR-Ressource typischerweise gelesen? *(Freitext)*

**Erklärung:** `GET`. Die Antwort mit `HTTP 200` bestätigt nur die Auslieferung, nicht die fachliche Richtigkeit.

### f05 — Welche FHIR-Ressource enthält Informationen über eine DICOM-Studie, aber nicht die Pixeldaten selbst?

1. ServiceRequest
2. ImagingStudy
3. DiagnosticReport
4. Observation

**Erklärung:** ImagingStudy beschreibt eine DICOM-Studie und trägt ihre Study Instance UID als Identifier — die DICOM-Instanzen selbst liegen nicht als Pixelblöcke in der Ressource.

### f06 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. ServiceRequest existiert unabhängig davon, ob bereits eine DICOM-Studie erzeugt wurde
2. DiagnosticReport kann per Reference auf ServiceRequest und ImagingStudy verweisen
3. Die FHIR Resource ID einer ImagingStudy ersetzt die DICOM Study Instance UID
4. R5-Beispiele sollten nicht ungeprüft auf R4/R4B übertragen werden

**Erklärung:** Der Auftrag (ServiceRequest) existiert vor der Studie, DiagnosticReport verweist per `basedOn`/`study` auf beide, und R5-Beispiele gelten nicht automatisch für andere Versionen. Die FHIR Resource ID ersetzt dabei nicht die DICOM Study Instance UID — beide sind separate Identifier.

### f07 — `subject: Patient/pat-4711` allein garantiert, dass ein DiagnosticReport zur richtigen Untersuchung gehört.

**Richtig / Falsch**

**Erklärung:** Falsch. Ein richtiger Patient mit falschem Auftrag ist immer noch ein falscher Workflow — dafür braucht es zusätzlich die References auf ServiceRequest und ImagingStudy.

### f08 — Welche FHIR-Ressource beschreibt die Anforderung einer diagnostischen Leistung? *(Freitext)*

**Erklärung:** `ServiceRequest`. Sie existiert unabhängig davon, ob bereits eine DICOM-Studie erzeugt wurde.

### f09 — Welcher DICOMweb-Dienst speichert DICOM-Objekte über HTTP?

1. QIDO-RS
2. WADO-RS
3. STOW-RS
4. FHIR REST

**Erklärung:** STOW-RS (Store Over the Web) ist der DICOMweb-Speicherdienst — QIDO-RS sucht, WADO-RS ruft ab.

### f10 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. IHE-Profile legen fest, wie Standards von definierten Akteuren in konkreten Transaktionen eingesetzt werden
2. IHE ersetzt DICOM oder HL7 als eigenes Transportprotokoll
3. FHIR und DICOMweb können sich im selben Workflow ergänzen
4. „Unterstützt DICOM, HL7 und FHIR" beantwortet allein, welche Workflows/Profile konkret unterstützt werden

**Erklärung:** IHE beschreibt, wie vorhandene Standards in konkreten Workflows zusammenspielen, und FHIR/DICOMweb konkurrieren nicht um dieselbe Aufgabe. IHE ist aber kein Ersatz-Transportprotokoll, und eine reine Featureliste beantwortet nicht, welche Profile/Rollen/Optionen tatsächlich unterstützt werden.

### f18 — Den Bildabruf zu einer FHIR ImagingStudy übernimmt der DICOMweb-Dienst, den die Endpoint-Ressource nennt, etwa WADO-RS — kein FHIR-Unterpfad der ImagingStudy.

**Richtig / Falsch**

**Erklärung:** Richtig. FHIR liefert Kontext über die Studie; die Bilder kommen über einen DICOMweb-Endpunkt wie WADO-RS, adressiert mit der Study Instance UID. Einen Pixel-Pfad an der ImagingStudy gibt es nicht.

### f12 — Welcher DICOMweb-Dienst sucht Studien, Serien und Instanzen?

1. STOW-RS
2. WADO-RS
3. QIDO-RS
4. FHIR REST

**Erklärung:** `QIDO-RS` sucht. WADO-RS ruft die gefundenen Objekte anschließend ab, STOW-RS speichert sie.

### f13 — Ein Client verwechselt `ImagingStudy.id` mit der DICOM Study Instance UID. Was ist die Konsequenz laut Track?

1. Keine, beide Werte sind austauschbar
2. Er verwechselt eine technische, serverlokale Resource ID mit einer global gemeinten DICOM-Identität
3. Die FHIR-Ressource wird dadurch automatisch ungültig
4. QIDO-RS liefert dann keine Treffer mehr

**Erklärung:** `ImagingStudy.id` ist eine technische FHIR Resource ID auf einem konkreten Server. Die DICOM Study Instance UID ist ein eigener, separater Identifier — beide zu verwechseln ist derselbe Fehler wie bei `Patient.id` vs. `Patient.identifier`.

### f14 — Ein Portal liest eine ImagingStudy erfolgreich, erhält beim Bildabruf aber 404. Wo liegt der Fehler am wahrscheinlichsten?

1. Die ImagingStudy ist beschädigt
2. Der Client ruft die Bilder nicht über den DICOMweb-Endpoint (WADO-RS) mit der Study Instance UID ab, sondern über die falsche Schicht oder mit der Resource ID
3. Der HL7-Auftrag muss erneut gesendet werden
4. QIDO-RS ist nicht erreichbar

**Erklärung:** Die ImagingStudy liefert Kontext und Identifikatoren korrekt. Ein 404 beim Abruf heißt: Unter dieser URL gibt es nichts — typisch, wenn statt `…/studies/{Study Instance UID}` am DICOMweb-Endpoint ein FHIR-Pfad oder die Resource ID verwendet wird. Auch eine falsche Basisadresse führt dorthin. Eine beschädigte ImagingStudy, ein fehlender HL7-Auftrag oder ein nicht erreichbarer QIDO-Dienst erklären dagegen keine 404 auf genau diesen Abruf.

### f15 — Welche Aussagen gelten track-übergreifend? *(Mehrfachauswahl)*

1. FHIR-Referenzen und DICOMweb-Identifikatoren erfüllen unterschiedliche, aber verwandte Zwecke
2. Ein HTTP-Statuscode allein reicht für eine vollständige fachliche Diagnose
3. QIDO-RS, WADO-RS und STOW-RS bilden Suchen, Abrufen und Speichern getrennt ab
4. Ein Denkmodell mit vier Prüffragen (Workflow, Informationsmodell, Transport/API, Integrationsprofil) hilft, eine Integration strukturiert zu prüfen

**Erklärung:** FHIR-References und DICOMweb-Identifikatoren dienen verwandten, aber unterschiedlichen Zwecken, die drei DICOMweb-Dienste sind sauber getrennt, und das Vier-Fragen-Modell hilft, strukturiert vorzugehen. Ein HTTP-Statuscode allein reicht dagegen nicht für die fachliche Diagnose.

### f16 — Referenzen in FHIR-Ressourcen machen separate fachliche Identifier (system+value) überflüssig.

**Richtig / Falsch**

**Erklärung:** Falsch. Systemübergreifende Integration funktioniert nicht zuverlässig, wenn nur interne Resource IDs ausgetauscht werden — Identifier mit `system`+`value` bleiben zusätzlich wichtig.
