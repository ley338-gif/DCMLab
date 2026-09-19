---
title: Name ohne Schlüssel
scenario_title: Derselbe Name, drei Werkzeuge, drei Reaktionen
---

## Briefing

Ticket: Der Referral-Export-Dienst BRIEF-EXPORT bricht beim Erstellen
eines Zuweiserbriefs für einen bestimmten Patienten mit einer
Kodierungs-Fehlermeldung ab. Im Archiv ist derselbe Patient vollständig
gespeichert und über die Web-Oberfläche einsehbar — der Name sieht dort
unauffällig aus.

Erfolgreich gespeichert zu sein und von einem Werkzeug korrekt
angezeigt zu werden, sind zwei getrennte Aussagen. Deine Aufgabe:
herausfinden, ob die Patientendaten tatsächlich falsch sind oder ob nur
die Kodierung fehlt, die verschiedene Werkzeuge zur eindeutigen
Interpretation derselben Bytes brauchen.

Vorkenntnisse: Lektion 3.6. Rechne mit 15 Minuten.

## Hints

### h1

Erfolgreiche Speicherung sagt nichts darüber aus, ob nachgelagerte
Werkzeuge dieselben Textwerte gleich interpretieren. Grenze zuerst ein,
ob der Fehler in den Daten selbst liegt oder erst bei ihrer
Verarbeitung entsteht.

### h2

Vergleiche die Rohbytes des Namens zwischen Registrierungssystem und
Archiv, bevor du einen Tippfehler vermutest — und sieh dir an, welches
Attribut die Kodierung aller Textwerte im Objekt überhaupt festlegt.

### h3

Prüfe gezielt, ob (0008,0005) Specific Character Set für dieses Objekt
gesetzt ist — ohne schon zu unterstellen, dass ein einzelnes Werkzeug
für das Problem verantwortlich ist.

## Write-up

### Symptom

Ein Referral-Export-Dienst bricht beim Erstellen eines Zuweiserbriefs
für einen bestimmten Patienten mit einer Kodierungs-Fehlermeldung ab.
Im Archiv ist derselbe Patient vollständig vorhanden, und dessen Name
erscheint dort unauffällig. Zwei scheinbar widersprüchliche
Beobachtungen zu demselben Namen.

### Technisch erfolgreiche Ebenen

<!-- kein-beispiel -->
```text
fachlicher Name (was das Registrierungssystem erfasst hat)
        ≠
Rohbytes im gespeicherten Objekt
        ≠
deklarierte Kodierung (SpecificCharacterSet)
        ≠
Darstellung eines konkreten Werkzeugs
```

Vier getrennte Ebenen. In diesem Fall sind die ersten beiden
nachweislich in Ordnung: Speicherung erfolgreich (C-STORE Success,
gültiges DICOM-Format), und die Rohbytes des Namens im Objekt sind
byteidentisch mit dem, was das Anmeldesystem KIS-ANMELDUNG bei der
Registrierung erzeugt hat.

### Hypothesen

1. **Der Patientenname wurde bei der Registrierung falsch erfasst.**
   Widerlegt: Byte-Vergleich zeigt identische Rohbytes zwischen
   Registrierungssystem und gespeichertem Objekt.
2. **Die Datei ist beim Transport beschädigt worden.** Widerlegt:
   gültiges, vollständig gespeichertes DICOM-Objekt, unveränderte
   Rohbytes.
3. **Der Name enthält Zeichen außerhalb des zulässigen Wertebereichs
   von PatientName und ist damit ungültig.** Widerlegt: PatientName
   darf durchaus erweiterte Zeichen enthalten, wenn das verwendete
   Repertoire über SpecificCharacterSet korrekt deklariert ist — genau
   das fehlt hier. Die Textkodierung dieses Objekts ist damit nicht
   DICOM-konform, aber das ist ein Deklarationsfehler, kein Beleg für
   grundsätzlich unzulässige Zeichen in PN.
4. **SpecificCharacterSet fehlt für dieses Objekt, wodurch verschiedene
   Werkzeuge dieselben Rohbytes unterschiedlich interpretieren.**
   Bestätigt: (0008,0005) fehlt vollständig, obwohl PatientName Bytes
   über 127 enthält — und drei Werkzeuge reagieren tatsächlich
   unterschiedlich auf genau diese Lücke.

### Evidenz

- **Speicherung**: C-STORE Success, gültiges DICOM-Format, Patient und
  Study im Archiv vollständig auffindbar.
- **Rohbytes**: PatientName im gespeicherten Objekt byteidentisch mit
  dem Wert aus KIS-ANMELDUNG.
- **Deklaration**: PatientName enthält Bytes über 127 (kein reines
  7-Bit-ASCII); `SpecificCharacterSet (0008,0005)` fehlt im Objekt
  vollständig.
- **Drei Werkzeuge, dieselben Rohbytes**: Die Web-Oberfläche des
  Archivs STUDIEN-ARCHIV zeigt den Namen unauffällig an (rät intern
  eine Kodierung). `BRIEF-EXPORT` bricht mit einer expliziten
  Kodierungs-Fehlermeldung ab. Die Schnittstellen-Engine `IF-ENGINE-3`
  zeigt beim Weiterreichen an ein zweites System sichtbar falsche
  Zeichen an derselben Stelle.

### Erste fehlerhafte Stelle

Nicht die Registrierung, nicht der Transport, nicht ein einzelnes
Werkzeug — das Objekt selbst deklariert seine Zeichenkodierung nicht,
obwohl PatientName Bytes außerhalb des Default-Repertoires
(7-Bit-ASCII) enthält. Damit ist die Textkodierung dieses konkreten
Objekts nicht DICOM-konform kodiert. Die gemeinsame Ursache ist dieser
eine, nicht konform deklarierte Textwert — die drei unterschiedlichen
Werkzeugreaktionen (raten, verweigern, falsch interpretieren) sind
produktspezifische Toleranz- bzw. Fehlerstrategien auf dieselbe
Nichtkonformität, keine drei unabhängigen Werkzeugfehler.

### Betriebliche Maßnahme

Die Ursache dort beheben, wo die Deklaration verloren geht — hier: die
Konfiguration prüfen, die PatientName in Objekte für diesen Workflow
schreibt, und `SpecificCharacterSet` passend zur tatsächlich
verwendeten Kodierung ergänzen. Bereits betroffene, archivierte Objekte
gezielt nachträglich korrigieren. Keine manuelle Neuerfassung des
Namens und kein erneuter Transfer — beide würden nur einen bereits
korrekten Wert unnötig anfassen.

### Was du mitnimmst

Ein Name, der in einem Werkzeug korrekt aussieht, beweist nicht, dass
ein anderes Werkzeug dieselben Bytes ebenso verarbeitet. Fehlt
`SpecificCharacterSet`, obwohl der Text Zeichen außerhalb des
Default-Repertoires enthält, ist das kein Tippfehler und keine
Transportbeschädigung, sondern ein DICOM-Encoding-/Deklarationsfehler.
Wie einzelne Werkzeuge in der Praxis darauf reagieren — raten,
verweigern, falsch anzeigen —, ist produktspezifisches
Implementierungsverhalten, nicht die vom Standard definierte
Interpretation.

### Verwandte Inhalte

Lektion 3.6 — Specific Character Set — Umlaute und was schiefgeht
