# 0038 — P10.28: Lektion 2.1 (C-ECHO) — Dienst-Blickwinkel

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Lektion 2.1 ("C-ECHO: der Ping, der keiner ist") lag seit P10.24 als
Gerüst vor, ohne offene Infrastrukturfrage — nur Fließtext fehlte.

**Abgrenzung zu Lektion 1.0 und 4.1:** 1.0 stellt `echoscu` als
Werkzeug vor, 4.1 behandelt eine echte C-ECHO-Ablehnung als
Fehlerbild. 2.1 vertieft den Dienst selbst: was er als eigener
DIMSE-Dienst mit eigener Association tatsächlich prüft, und — als
zentraler Punkt — was er ausdrücklich nicht beweist.

**Erneut bestätigter, bereits dokumentierter Fund:** Ein
AE-Title-basierter Association-Ablehnungsfall lässt sich in dieser
Spielwiese nicht live erzeugen — Orthanc akzeptiert jeden Called AE
Title (ADR 0017). In dieser Slice erneut empirisch bestätigt (nicht
nur aus früheren ADRs übernommen): `echoscu -aec VOELLIG-FALSCH` gegen
eine frische Sitzung war ebenso erfolgreich wie mit dem korrekten AE
Title. Für diesen Fall verweist die Lektion ehrlich auf Node
„Silent CT", statt einen erfundenen Ablehnungstext zu zeigen.

## Entscheidung — Fließtext mit vier echten Beispielen, eine ehrliche
Auslassung

**`content/lessons/2.1/de.md`**: vollständig neu geschrieben.
- Ein echter erfolgreicher `echoscu -v` mit explizitem DIMSE-Status
  `0x0000`.
- Ein echter `echoscu -d`-Mitschnitt der Presentation-Context-
  Aushandlung — bestätigt: genau eine vorgeschlagene und akzeptierte
  Context (Verification SOP Class, real `1.2.840.10008.1.1`, per
  `pynetdicom.sop_class.Verification` verifiziert).
- Ein echter `tshark`-Mitschnitt des vollständigen Zyklus (sechs
  Pakete: Aufbau, ein Anfrage/Antwort-Paar, Abbau).
- Ein echter Transport-Fehler (`Connection refused` gegen einen
  geschlossenen Port) als Kontrast zur DICOM-Ebene.
- Eine ehrliche, in dieser Slice frisch verifizierte Auslassung für
  den AE-Title-Ablehnungsfall, mit Verweis auf Node „Silent CT" —
  keine erfundene Ablehnungsmeldung.

**`content/lessons/2.1/meta.yml`**: `tools` von `[echoscu]` auf
`[echoscu, tshark]` ergänzt (`tshark` wurde im Text gebraucht).
`status: draft` → `fertig`.

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Alle vier gezeigten Beispiele in einem echten, vom Orchestrator
  erzeugten Sitzungscontainer erzeugt — Session über die echte
  Produkt-Oberfläche gestartet.
- Presentation-Context-Aushandlung real per `echoscu -d` bestätigt
  (DCMTK-Binary, `/usr/bin/echoscu`): genau eine Context, Verification
  SOP Class.
- Die reale UID `1.2.840.10008.1.1` per
  `pynetdicom.sop_class.Verification` im selben Sitzungscontainer
  bestätigt, nicht aus dem Gedächtnis übernommen.
- AE-Title-Großzügigkeit in einer zweiten, separat gestarteten
  Sitzung erneut real geprüft (nicht nur aus ADR 0017 zitiert).
- `content:validate`: 0 Verstöße (27 Lektionen, 35 Glossarbegriffe).
- `datasets/build` und `services/sandbox`: pytest erneut ausgeführt
  (unverändert von dieser Slice betroffen) — beide grün.
- Lektion 2.1 im Browser gegen den echten Stack aufgerufen — rendert
  vollständig fehlerfrei.
- Beide Sitzungen über die echte Oberfläche sauber beendet
  (`docker ps -a` bestätigt vollständige Entfernung).
- Alle Docker-Ressourcen dieser Slice (Compose-Stack, geteilte
  Test-Images) nach Abschluss vollständig entfernt.

## Nicht Teil dieser Slice

- Kein Lab für Lektion 2.1 — laut Curriculum ohnehin nicht vorgesehen
  (Lab-Spalte „—").
- Track 2 offen: 2.2, 2.3 (reiner Schreibaufwand), 2.4 und 2.8 (neue
  Infrastruktur nötig) — siehe `docs/content-todo.md`.
