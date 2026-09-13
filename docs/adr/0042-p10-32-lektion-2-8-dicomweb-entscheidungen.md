# 0042 — P10.32: Lektion 2.8 (DICOMweb) — eine Konfigurationszeile statt neuer Infrastruktur

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Lektion 2.8 ("DICOMweb: WADO-RS, QIDO-RS, STOW-RS") lag seit P10.24 als
Gerüst vor. `docs/content-todo.md` markierte sie — wie zuvor Worklist
(P10.20), MPPS/Storage Commitment (P10.21) und C-MOVE/C-GET (P10.31)
— als "braucht wahrscheinlich neue Infrastruktur", zusätzlich mit der
offenen Frage, ob `curl` überhaupt als Werkzeug taugt und ob Orthancs
DICOMweb-Unterstützung ohne weitere Konfiguration läuft.

**Vor dem Schreiben empirisch geprüft, nicht angenommen:** Orthancs
`orthancteam/orthanc`-Image bringt das DICOMweb-Plugin bereits mit,
aktiviert es aber nicht automatisch — exakt dasselbe Muster wie beim
Worklists-Plugin (ADR 0030). Eine einzige zusätzliche Zeile in
`containers/orthanc/orthanc.json` (`"DicomWeb": {"Enable": true}`)
genügt; kein zweiter Container, kein zweiter Prozess, keine neue
Storage-Route. Verifiziert per `VERBOSE_STARTUP`-Log:
„Registering plugin 'dicom-web' (version 1.24)", „DicomWeb is
enabled", URIs `/dicom-web/` und `/wado`.

Alle drei Dienste laufen darüber vollständig real:
- **QIDO-RS** (`GET /dicom-web/studies`) liefert echtes DICOM-JSON,
  Tag-für-Tag identisch mit einer entsprechenden `findscu`-Antwort.
- **WADO-RS** (`GET /dicom-web/studies/<uid>` mit
  `Accept: multipart/related; type=application/dicom`) liefert einen
  echten Multipart-Body mit den DICOM-Bytes.
- **STOW-RS** (`POST /dicom-web/studies`) nimmt echte Uploads an —
  aber nur mit einer präzise kodierten
  `multipart/related`-Anfrage. Ein naiver Body ohne `boundary=`-Angabe
  scheitert nicht still, sondern mit einer echten, klaren
  `415 Unsupported Media Type`-Antwort — eine echte, lehrreiche
  Stolperfalle, keine stille Fehlfunktion.

`curl` selbst war bereits seit P10.21 im Toolbox-Image installiert
(für Storage-Commitment-Aufrufe), aber noch nicht in
`content/tools/de.yml` registriert.

## Entscheidung — Fließtext mit vier echten Beispielen, ein Registry-Eintrag, eine Konfigurationszeile

**`containers/orthanc/orthanc.json`**: `"DicomWeb": {"Enable": true}`
ergänzt.

**`content/tools/de.yml`**: `curl` neu registriert (`suite: extern`,
`kind: netz`), Anker auf den QIDO-RS-Abschnitt.

**`content/glossary/de.yml`**: Begriff `dicomweb` neu ergänzt.

**`content/lessons/2.8/de.md`**: vollständig neu geschrieben.
- Dieselbe Studiensuche einmal per echtem `findscu` (DIMSE), einmal
  per echtem QIDO-RS (`curl`) — Tag-für-Tag identische Werte.
- Ein echter WADO-RS-Abruf (`200 OK`, `multipart/related`-Body mit
  `DICM`-Magic-Byte).
- Ein echter, naiver STOW-RS-Fehlversuch (`415 Unsupported Media
  Type`) als Stolperfalle, gefolgt von einem echten, erfolgreichen
  Upload über ein von Hand kodiertes Multipart (Python-Skript, da
  `curl --data-binary` allein für eine korrekte
  `multipart/related`-Anfrage nicht reicht).
- Ein echter `findscu` auf Bildebene, der beide `SOPInstanceUID`s (die
  per DIMSE gesendete und die per STOW-RS hochgeladene) im selben
  Index zeigt — der Beleg, dass DICOMweb und DIMSE dieselbe Ablage
  teilen, keine getrennten Systeme sind.

**`content/lessons/2.8/meta.yml`**: `tools: [curl, findscu,
storescu]`, `status: draft` → `fertig`.

**Nebenfund — Folgekorrektur in bereits gemergten Lektionen:** Mit
`curl` als neu registriertem Werkzeug flaggte `content:validate` drei
bereits fertige Lektionen (2.4, 2.7, 4.8), die `curl` in Beispielen
verwenden, aber nicht deklarieren:
- **2.7** und **4.8** hatten beide noch Platz unter dem
  Vier-Werkzeuge-Limit — `curl` einfach ergänzt.
- **2.4** war bereits bei vier Werkzeugen. Statt ein für die Lektion
  zentrales Werkzeug zu verdrängen, wurden die zwei ursprünglich
  getrennten Befehle (`storescp` im Hintergrund starten, dann `curl`
  zur Registrierung) zu einer einzigen, weiterhin real
  copy-paste-fähigen Zeile zusammengefasst
  (`storescp ... 11113 & curl ...`) — dieselbe Anweisungsfolge wie
  zuvor, nur nicht mehr als zwei separate Kommandozeilen dargestellt.
  `content/lessons/2.4/meta.yml` blieb dadurch unverändert bei vier
  Werkzeugen.

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Isolierter Vortest (eigenständiges Orthanc + Toolbox, ohne
  Orchestrator): DicomWeb-Plugin-Aktivierung über Log bestätigt,
  QIDO-RS/WADO-RS/STOW-RS real erfolgreich, STOW-RS-Stolperfalle real
  reproduziert (`415`).
- **Vollständig wiederholt über den echten Orchestrator:** Session
  über die echte Produkt-Oberfläche gestartet (Login, Lektion
  aufgerufen, „Spielwiese starten" geklickt), realer
  Sitzungscontainer per `docker ps` gefunden. Darin erneut: `findscu`
  (DIMSE) und QIDO-RS (`curl`) für dieselbe Studie mit identischen
  Werten, WADO-RS-Abruf mit echtem Multipart-Body, naiver
  STOW-RS-Fehlversuch (`415`), erfolgreicher STOW-RS-Upload über das
  Python-Skript, abschließender `findscu` auf Bildebene mit beiden
  `SOPInstanceUID`s im selben Index — alle Werte in der Lektion sind
  aus diesem echten Orchestrator-Lauf übernommen, nicht aus dem
  isolierten Vortest.
- `content:validate`: 0 Verstöße (27 Lektionen, 16 Nodes, 22
  Werkzeuge, 36 Glossarbegriffe) — nach Korrektur der drei
  Folge-Verstöße in 2.4/2.7/4.8.
- `datasets/build` (5 passed) und `services/sandbox` (14 passed,
  ruff clean) erneut ausgeführt — beide unverändert grün, von dieser
  Slice nicht betroffen. (`services/sandbox` mypy zeigt zehn
  vorbestehende Fehler in `tests/test_orchestrator.py`, unverändert
  von dieser Slice, nicht Teil der hier vorgenommenen Änderungen.)
- Lektion 2.8 im Browser gegen den echten Stack aufgerufen — Werkzeugleiste
  (curl, findscu, storescu), Fließtext, Tooltips (`{{term:dicomweb}}`,
  `{{term:c-find}}`, `{{term:c-get}}`) und Selbstcheck rendern
  vollständig fehlerfrei.

## Nicht Teil dieser Slice

- Kein Node-Stub für Lektion 2.8 (`lab.node` bleibt `null`,
  Curriculum-Vorschlag "Dieselbe Abfrage per DIMSE und per REST"
  bleibt in `docs/content-todo.md` als offener Punkt vermerkt).
- Mit dieser Slice sind alle acht Lektionen von Track 2 ("Die
  Services") vollständig geschrieben.
