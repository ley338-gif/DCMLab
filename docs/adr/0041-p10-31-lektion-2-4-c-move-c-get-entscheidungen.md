# 0041 — P10.31: Lektion 2.4 (C-MOVE vs. C-GET) — kein neuer Storage-Endpunkt nötig

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Lektion 2.4 ("C-MOVE vs. C-GET: warum C-MOVE drei Beteiligte hat") lag
seit P10.24 als Gerüst vor. `docs/content-todo.md` nahm an, ein echtes
Drei-Parteien-C-MOVE brauche einen zweiten Storage-Endpunkt in der
Spielwiese — ein neues Infrastrukturthema, ähnlich der ursprünglichen
(und in P10.20/P10.21 als unnötig erkannten) Annahme für Worklist,
MPPS und Storage Commitment.

**Vor dem Schreiben empirisch geprüft, nicht angenommen:** Ein
zweiter, unabhängiger `storescp`-Prozess **im selben
Toolbox-Container**, mit eigenem AE Title und Port, plus dynamische
Registrierung bei Orthanc über `PUT /modalities/<name>` (derselbe
Mechanismus wie bei Storage Commitment, ADR 0031), genügt bereits für
ein echtes, standardkonformes Drei-Parteien-C-MOVE. DICOM
unterscheidet Rollen (SCU, Quelle, Move-Ziel) über AE Title und Port,
nicht über die physische Maschine — zwei Prozesse im selben Container,
aber mit unterschiedlichem AE Title, erfüllen die Rollenverteilung
bereits vollständig. Verifiziert zunächst in einem isolierten Testaufbau,
dann end-to-end über den echten Sitzungscontainer bestätigt: Ein
`movescu` mit `-aem DRITTES-ZIEL` löst bei Orthanc eine **eigene,
zweite Association** zum unabhängigen `storescp` aus (real per
`tshark` bestätigt) — der Move-Ziel-Prozess empfängt die Objekte
tatsächlich, verifiziert über die real angekommenen Dateien.

**Nebenfund:** Ein C-MOVE auf STUDY-Ebene mit nur `PatientID` als
Identifier schlägt bei Orthanc mit einem echten, klaren Fehler fehl
(„No DICOM identifier provided in the C-MOVE request for this query
retrieve level") — anders als bei C-FIND verlangt Orthancs
C-MOVE-Handler zwingend die exakte `StudyInstanceUID` als Identifier.

## Entscheidung — Fließtext mit vier echten Beispielen, kein
Infrastruktur-Wiring nötig

**`content/lessons/2.4/de.md`**: vollständig neu geschrieben.
- Ein echtes Setup eines dritten, unabhängigen `storescp` samt
  dynamischer Modality-Registrierung.
- Ein echtes, erfolgreiches Drei-Parteien-C-MOVE mit realer
  StudyInstanceUID.
- Ein echter `tshark`-Mitschnitt, der die zwei getrennten
  Associationen (`SCU→Quelle`, `Quelle→Ziel`) zeigt.
- Ein echter C-GET-Durchlauf derselben Abfrage als direkter Kontrast
  (nur eine Association, `C-STORE-RQ` innerhalb derselben Verbindung)
  — inklusive eigenem `tshark`-Mitschnitt.
- Ein echter C-MOVE-Fehlschlag gegen ein nicht erreichbares Move-Ziel
  (`0xC000`, `Completed: 0`).

**`content/lessons/2.4/meta.yml`**: `tools` unverändert bei vier
Einträgen, aber `storescp` beibehalten (für das reale Setup-Beispiel
gebraucht) und `tshark` neu ergänzt — am Vier-Werkzeuge-Limit.
Kommentar zum Lab aktualisiert (kein neuer Storage-Endpunkt nötig).
`status: draft` → `fertig`.

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Isolierter Vortest (eigenständiges Orthanc + Toolbox, ohne
  Orchestrator): Drei-Parteien-C-MOVE real erfolgreich, C-GET real
  erfolgreich, C-MOVE gegen unerreichbares Ziel real gescheitert
  (`0xC000`), alle drei `tshark`-Mitschnitte real aufgezeichnet.
- **Vollständig wiederholt über den echten Orchestrator:** Session
  über die echte Produkt-Oberfläche gestartet, alle vier Beispiele
  erneut im echten Sitzungscontainer erzeugt — identisches Verhalten,
  frische UIDs.
- Die real angekommenen Dateien am Move-Ziel und am C-GET-Empfänger
  per `ls` bestätigt, nicht nur aus dem Log angenommen.
- `content:validate`: 0 Verstöße (27 Lektionen, 35 Glossarbegriffe).
- `datasets/build` und `services/sandbox`: pytest erneut ausgeführt
  (unverändert von dieser Slice betroffen) — beide grün.
- Lektion 2.4 im Browser gegen den echten Stack aufgerufen — rendert
  vollständig fehlerfrei.
- Sitzung über die echte Oberfläche sauber beendet (`docker ps -a`
  bestätigt vollständige Entfernung).
- Alle Docker-Ressourcen dieser Slice (isolierter Vortest und echter
  Compose-Stack, geteilte Test-Images) nach Abschluss vollständig
  entfernt.

## Nicht Teil dieser Slice

- Kein Node-Stub für Lektion 2.4 (`lab.node` bleibt `null`).
- Mit dieser Slice ist nur noch Lektion 2.8 (DICOMweb) offen — braucht
  weiterhin `curl` als neu zu registrierendes Werkzeug sowie eine
  Verifikation, dass Orthancs QIDO-RS/WADO-RS/STOW-RS ohne weitere
  Konfiguration laufen. Siehe `docs/content-todo.md`.
