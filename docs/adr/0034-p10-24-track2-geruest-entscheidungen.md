# 0034 — P10.24: Track 2 ("Die Services") — acht Gerüste angelegt

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Mit Track 4 vollständig geschrieben und den 19 dokumentierten
`content:validate`-Verstößen behoben (P10.23), wählte der Nutzer
explizit, als Nächstes Track 2 ("Die Services") zu beginnen. Track 2
existierte bis dahin nur als Skeleton-Eintrag in `content/tracks.yml`
(`status: planned`) — keine einzige Lektion, kein `content/lessons/2.*`.

Anders als bei einer komplett neuen Content-Initiative ist Track 2s
Curriculum bereits vollständig vorgegeben: `docs/konzept-lernplattform.md`
(Abschnitt 5) listet alle acht Lektionen mit Titel und Lab-Hinweis in
einer Tabelle — genau dieselbe Quelle, aus der Track 4s ursprüngliche
Gerüste (vor dieser Session, siehe `docs/content-schema.md` Abschnitt
10 und `docs/content-todo.md`, Abschnitt „Track 4") abgeleitet wurden.
Titel und Lernziele sind damit **abgeleitet, nicht erfunden** — genau
das Muster, das für Track 4 bereits etabliert war.

## Entscheidung — dieselbe Gerüst-Struktur wie Track 4, drei Lektionen
mit vorhandener Infrastruktur

**`content/lessons/2.1` bis `2.8`** (neu): jede mit vollständiger
`meta.yml` (Track, Reihenfolge, Dauer, `requires`, höchstens vier
Werkzeuge aus der Registry, Glossarbegriffe, Datensatz,
`status: draft`) und `de.md` (Titel, Teaser, drei Lernziele, geplante
Gliederung als `<!-- kein-beispiel -->`-Block, „Was zum Schreiben noch
fehlt", Selbstcheck-Platzhalter) — exakt dieselbe Struktur wie Track
4s ursprüngliche Gerüste.

**`requires`-Ketten** wurden nach fachlicher Abhängigkeit gewählt
(Abschnitt 2 von `content-schema.md`: „beschreibt fachliche
Abhängigkeit, nicht Reihenfolge im Menü"): 2.1←1.5/1.6, 2.2←2.1/1.7,
2.3←2.1, 2.4←2.3, 2.5←2.3, 2.6←2.5, 2.7←2.2/2.6, 2.8←2.2/2.3 — folgt
derselben Reihenfolge wie die Curriculum-Tabelle (Worklist → MPPS →
Storage Commitment als natürliche Workflow-Kette).

**Fünf neue Glossarbegriffe** (`c-find`, `c-get`, `worklist`, `mpps`,
`storage-commitment`) wurden ergänzt — dieselben bereits etablierten
Dienste haben teils schon Einträge (`c-echo`, `c-store`, `c-move`),
diese fünf fehlten einfach noch. Kurzdefinitionen sind reine
Standarddefinitionen (SOP-Klassen-Namen, DIMSE-Nachrichtentypen), kein
erfundener Fachinhalt.

**Ein wichtiger Unterschied zu Track 4s ursprünglicher Ausgangslage:**
Drei der acht Lektionen (2.5 Worklist, 2.6 MPPS, 2.7 Storage
Commitment) können die in P10.20–P10.21 für Track 4 gebaute
Spielwiesen-Infrastruktur direkt mitnutzen — Orthancs Worklists-Plugin
(ADR 0030), der eigene `pynetdicom`-MPPS-SCP (ADR 0031) und Orthancs
native Storage-Commitment-REST-API (ADR 0031). Track 2 erklärt dabei
den jeweiligen Dienst selbst (Ablauf, Nachrichten, was er beweist),
Track 4 das zugehörige Troubleshooting-Fehlerbild — beide Blickwinkel
sind in den jeweiligen Gerüsten explizit als Abgrenzung vermerkt.

**Zwei Lektionen brauchen neue Infrastruktur:** 2.4 (C-MOVE vs. C-GET)
für ein *echtes* Drei-Parteien-C-MOVE einen zweiten
Storage-Endpunkt in der Spielwiese; 2.8 (DICOMweb) das noch nicht
registrierte Werkzeug `curl` sowie eine Verifikation, dass Orthancs
QIDO-RS/WADO-RS/STOW-RS ohne weitere Konfiguration laufen. `tools: []`
für 2.8s Gerüst, da `curl` noch nicht in `content/tools/de.yml`
existiert — Registrierung folgt beim Schreiben des echten Fließtexts,
mit einem echten Anker in dieser Lektion.

## Manuell verifiziert

- `content:validate` (im echten `app`-Container): vorher 19 Lektionen/
  16 Nodes/21 Werkzeuge/30 Glossarbegriffe, danach **27 Lektionen/16
  Nodes/21 Werkzeuge/35 Glossarbegriffe geprüft — keine Verstöße**.
- Alle acht neuen Lektionsseiten sowie die Track-2-Übersichtsseite im
  Browser gegen den echten Stack aufgerufen — rendern vollständig
  fehlerfrei, in der richtigen Reihenfolge, mit korrekten
  „Vorher"-Verweisen aus `requires`.
- Ein Tippfehler in Lektion 2.7s Titel (nicht geschlossenes deutsches
  Anführungszeichen `„...` ohne `"`) beim Browser-Check entdeckt und
  auf einen Gedankenstrich umgestellt — vor dem Commit korrigiert.
- Die leere `tools: []`-Liste in Lektion 2.8 rendert korrekt ohne
  Werkzeugleiste, kein Fehler.

## Nicht Teil dieser Slice (siehe `docs/content-todo.md`)

- Kein Fließtext für irgendeine der acht Lektionen — reine
  Struktur-Gerüste, wie bei Track 4 vor P10.15–P10.22.
- Kein zweiter Storage-Endpunkt für ein echtes Drei-Parteien-C-MOVE
  (2.4) und keine `curl`-Registrierung bzw. DICOMweb-Verifikation
  (2.8) — beide offen, siehe `docs/content-todo.md`.
- Keine Node-Stubs für Track 2 — `lab.node: null` in allen acht
  Gerüsten, wie im ursprünglichen Curriculum vorgesehen (Track 2 hat
  laut `konzept-lernplattform.md` ohnehin nur bei sechs der acht
  Lektionen überhaupt ein Lab).
