# 0049 — P10.40: Lektion 3.1 (IOD und Module) — Type-1-Ablehnung real bestätigt

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Lektion 3.1 ("IOD und Module: woraus ein CT-Bild besteht") lag seit
P10.39 als Gerüst vor, mit einer offenen Frage: Lehnt Orthanc ein
Objekt mit fehlendem Type-1-Pflichtattribut tatsächlich ab, und mit
welcher realen Fehlermeldung?

**Vor dem Schreiben empirisch geprüft, nicht angenommen:** Ein reales
Testobjekt aus `ct-thorax-60`, per `dcmodify -e` gezielt um ein
einzelnes Tag reduziert, dann per `storescu` gegen ein echtes Orthanc
gesendet — einmal für ein Type-1-Attribut (`StudyInstanceUID`), einmal
für ein Type-2-Attribut (`PatientID`), zum direkten, echten Kontrast.

**Ergebnis:** Ein fehlendes `StudyInstanceUID` wird real mit `0xA700
(Failure)` abgelehnt, und Orthancs eigenes Log nennt den Grund im
Klartext: „Store has failed because required tags (StudyInstanceUID)
are missing". Ein fehlendes `PatientID` dagegen wird anstandslos mit
`0x0000 (Success)` angenommen — derselbe Vorgang (Tag per `dcmodify -e`
komplett entfernt), zwei völlig unterschiedliche, jeweils real
beobachtete Ergebnisse.

## Entscheidung — Fließtext mit vier echten Beispielen

**`content/lessons/3.1/de.md`**: vollständig neu geschrieben.
- Ein vollständiger, echter `dcmdump` eines Testobjekts, modulweise
  einer neuen Tabelle (Patient/General Study/General Series/Image
  Pixel/SOP Common Module) zugeordnet — dieselbe Tag-Gruppenlogik wie
  in Lektion 1.3, jetzt mit den echten Modulnamen aus PS3.3.
- Eine Type-1/2/3-Tabelle, direkt gefolgt vom oben beschriebenen realen
  Kontrastversuch (Type-1-Ablehnung vs. Type-2-Erfolg).

**`content/lessons/3.1/meta.yml`**: `tools` von `[]` auf `[dcmdump,
dcmodify, storescu]` gesetzt (alle drei bereits in `content/tools/de.yml`
registriert, keine neue Registrierung nötig), `glossary_terms: [iod]`,
`status: draft` → `fertig`.

**`content/glossary/de.yml`**: Begriff `iod` neu ergänzt (Information
Object Definition).

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Isolierter Vortest (eigenständiges Orthanc + Toolbox, eigene
  `dcmlab/*:test`-Tags): Type-1-Ablehnung und Type-2-Erfolg beide real
  reproduziert, Orthancs Klartext-Fehlermeldung im Log bestätigt.
- **Vollständig wiederholt über den echten Orchestrator:** Session über
  die echte Produkt-Oberfläche gestartet (Lektion 2.1, „Spielwiese
  starten"), realer Sitzungscontainer per `docker ps` gefunden. Beide
  Kontrastversuche darin erneut ausgeführt — identisches Verhalten,
  frische UIDs, für die Lektion übernommen.
- `content:validate`: 0 Verstöße (33 Lektionen, 16 Nodes, 22
  Werkzeuge, 37 Glossarbegriffe).
- `datasets/build` (5 passed) und `services/sandbox` (14 passed, ruff
  clean, mypy 0 Fehler) erneut ausgeführt — unverändert, von dieser
  Slice nicht betroffen.
- Lektion 3.1 im Browser gegen den echten Stack aufgerufen — rendert
  vollständig fehlerfrei, inklusive `{{term:iod}}`-Tooltip.
- Eine leere, verwaiste Sandbox-Sitzung aus einem früheren, bereits
  abgebauten Compose-Projekt wurde beim Aufräumen dieser Slice
  gefunden und entfernt (kein Bezug zu dieser Slice, reine Hygiene).
- Alle Docker-Ressourcen dieser Slice (isolierter Vortest mit eigenen
  `:test`-Tags, echter Sitzungscontainer, projekt-eigene
  `infra-p10-40-*`-Images) nach Abschluss vollständig entfernt — die
  geteilten `dcmlab/toolbox:latest`/`dcmlab/orthanc:latest`-Images
  wurden dabei ausdrücklich nicht angefasst (siehe ADR 0048, Nebenfund).

## Nicht Teil dieser Slice

- Kein Node-Stub für Lektion 3.1 (`lab.node` bleibt `null`).
- Die drei in ADR 0048 markierten echten Infrastrukturfragen für 3.4,
  3.5 und 3.6 bleiben offen und werden erst bei diesen Lektionen
  geprüft.
