# 0054 — P10.45: Lektion 3.6 (Specific Character Set) — drei echte, unterschiedliche Werkzeugreaktionen; Track 3 vollständig

Status: akzeptiert
Datum: 2026-09-14

## Kontext

Lektion 3.6 war die letzte der drei in ADR 0048 markierten Lektionen
mit einer offenen Infrastrukturfrage — ADR 0048 erwartete sie als
unkompliziert lösbar (kein neues IOD nötig, nur ein Testobjekt mit
echtem Umlautnamen). Diese Erwartung wurde empirisch bestätigt, aber
mit einem deutlich reichhaltigeren realen Befund als ursprünglich
angenommen.

**Vor dem Schreiben empirisch geprüft, nicht angenommen:**

1. Zwei reale Testobjekte per `pydicom` gebaut: eines mit
   `SpecificCharacterSet=ISO_IR 100` und Patientenname
   „Müller^Jürgen", eines mit identischem Namen, aber **ganz ohne**
   `SpecificCharacterSet`.
2. **Realer Byte-Vergleich:** Ein Hex-Vergleich der `PatientName`-Rohbytes
   in beiden Dateien zeigt **identische Bytes** (`\xfc` für „ü" in
   beiden) — `pydicom` kodiert den String beim Schreiben unabhängig
   davon, ob `SpecificCharacterSet` gesetzt ist. Der einzige
   Unterschied zwischen den Dateien ist die *Deklaration*, nicht der
   Inhalt.
3. **Reale, unterschiedliche Reaktionen dreier Werkzeuge auf dieselbe
   Lücke:**
   - `dcmdump` zeigt beide unproblematisch an (reine Byte-Ausgabe,
     keine Interpretation nötig).
   - `dcm2json` **verweigert** die Umwandlung der Datei ohne
     `SpecificCharacterSet` mit der exakten, realen Fehlermeldung
     „dataset contains extended characters but no SpecificCharacterSet
     (0008,0005)" — bei der Datei mit korrektem Attribut liefert es
     korrekt `"Alphabetic": "Müller^Jürgen"`.
   - `storescu` gegen Orthanc nimmt **beide** Objekte anstandslos an
     (`0x0000`) — dasselbe permissive Muster wie bei den
     Type-1-Attributen aus Lektion 3.1/3.2.
   - Orthancs eigene DICOMweb-REST-API (`GET /dicom-web/studies`) zeigt
     für **beide** Objekte den korrekt dekodierten Namen (`ü`) und
     gibt dabei für beide `SpecificCharacterSet: ISO_IR 192` (UTF-8) in
     der eigenen JSON-Antwort an — unabhängig davon, was die
     Ursprungsdatei deklarierte. Orthanc rät intern (vermutlich
     Latin-1-Fallback) und normalisiert still auf UTF-8 in der
     Ausgabe.

Damit ist die ursprünglich erwartete „zeigt kaputte Zeichen"-Hypothese
widerlegt und durch einen genaueren, echten Befund ersetzt: Das
eigentliche Risiko ist nicht garantierter sichtbarer Zeichensalat,
sondern uneinheitliches, werkzeugabhängiges Verhalten — von harter
Verweigerung (`dcm2json`) bis zu stillschweigendem Korrigieren
(Orthancs REST-API).

## Entscheidung — Fließtext mit vier echten Beispielen

**`content/lessons/3.6/de.md`**: vollständig neu geschrieben, um den
tatsächlichen (reichhaltigeren) Befund abzubilden statt der
ursprünglich angenommenen einfachen Zeichensalat-Geschichte.
- Realer `dcmdump`-Kontrast (korrekt deklariert vs. nicht deklariert).
- Realer `dcm2json`-Kontrast: korrekte Umwandlung vs. echte
  Fehlermeldung, mit explizitem Hinweis auf den identischen
  Byte-Inhalt.
- Realer `storescu`- und DICOMweb-Kontrast: Orthanc nimmt beide an und
  normalisiert beide beim Auslesen auf UTF-8.

**`content/lessons/3.6/meta.yml`**: `tools` von `[]` auf `[dcmdump,
dcm2json, storescu, curl]` gesetzt (am Vier-Werkzeuge-Limit),
`glossary_terms: [specific-character-set]`, `status: draft` →
`fertig`.

**`content/glossary/de.yml`**: Begriff `specific-character-set` neu
ergänzt.

**`content/tracks.yml`**: `bild.status` von `planned` auf `published`
gesetzt — mit dieser Slice sind alle sechs Lektionen aus Track 3
vollständig geschrieben, derselbe Maßstab wie bei Track 2 (ADR 0042).

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Isolierter Vortest (eigenständiges Orthanc + Toolbox, eigene
  `dcmlab/*:test`-Tags): Byte-Vergleich, `dcmdump`, `dcm2json`,
  `storescu`, DICOMweb-Kontrast alle real reproduziert.
- **Vollständig wiederholt über den echten Orchestrator:** Session
  über die echte Produkt-Oberfläche gestartet (Lektion 2.1, „Spielwiese
  starten"), realer Sitzungscontainer per `docker ps` gefunden. Das
  identische Python-Skript erneut darin ausgeführt — identisches
  Verhalten, für die Lektion übernommen.
- `content:validate`: 0 Verstöße (33 Lektionen, 16 Nodes, 23
  Werkzeuge, 44 Glossarbegriffe) — erneut geprüft nach dem
  `tracks.yml`-Statuswechsel.
- `datasets/build` (5 passed) und `services/sandbox` (14 passed, ruff
  clean, mypy 0 Fehler) — unverändert, von dieser Slice nicht betroffen.
- Lektion 3.6 im Browser gegen den echten Stack aufgerufen — rendert
  vollständig fehlerfrei, inklusive korrekt dargestellter Umlaute im
  Fließtext selbst und im Tooltip.
- Tracks-Übersicht im Browser erneut aufgerufen — „Das Bild selbst"
  zeigt jetzt „Verfügbar" mit 6 Lektionen.
- Alle Docker-Ressourcen dieser Slice entfernt — die geteilten
  `dcmlab/toolbox:latest`/`dcmlab/orthanc:latest`-Images blieben
  unangetastet.

## Nicht Teil dieser Slice

- Kein Node-Stub für Lektion 3.6 (`lab.node` bleibt `null`).
- Mit dieser Slice sind alle sechs Lektionen aus Track 3 vollständig
  geschrieben und der Track veröffentlicht.
