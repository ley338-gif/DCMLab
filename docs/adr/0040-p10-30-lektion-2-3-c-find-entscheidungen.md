# 0040 — P10.30: Lektion 2.3 (C-FIND) — Dienst-Blickwinkel

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Lektion 2.3 ("C-FIND: Query-Level, Matching-Keys, Wildcards") lag seit
P10.24 als Gerüst vor, ohne offene Infrastrukturfrage — nur Fließtext
fehlte. Mit dieser Lektion sind alle drei Track-2-Lektionen fertig,
die per `docs/content-todo.md` als reiner Schreibaufwand markiert
waren (2.1, 2.2, 2.3).

**Abgrenzung zu Lektion 1.0 und 2.5:** 1.0 stellt `findscu` als
Werkzeug vor. 2.5 behandelt die Modality Worklist als eigenständiges,
verwandtes Informationsmodell. 2.3 vertieft C-FIND selbst: die
Ebenen-Hierarchie, den Unterschied zwischen Matching-Key und
Rückgabefeld, und Wildcards.

## Entscheidung — Fließtext mit fünf echten Beispielen

**`content/lessons/2.3/de.md`**: vollständig neu geschrieben.
- Ein echter exakter STUDY-Level-Treffer mit vollständigem
  Rückgabefeld-Satz.
- Dieselbe Suche mit Wildcard (`MUST*`) statt exaktem Namen — echter,
  identischer Treffer.
- Ein echter SERIES-Level-Treffer, der zwei getrennte
  `Find SCP Response`-Blöcke liefert (die reale Zwei-Serien-Struktur
  des `ct-thorax-60`-Datensatzes) — zeigt konkret, dass tiefere Ebenen
  mehrere Treffer liefern können.
- Eine echte leere Antwort für einen nicht vorkommenden Namen —
  derselbe Erfolgsstatus wie bei einem Treffer, nur ohne
  Antwortzeile.
- Ein echter `tshark`-Mitschnitt eines vollständigen C-FIND-Zyklus mit
  getrennten `-RQ`/`-RQ-DATA`- und `-RSP`/`-RSP-DATA`-Paketen.

**`content/lessons/2.3/meta.yml`**: `tools` von `[findscu]` auf
`[findscu, tshark]` ergänzt. `status: draft` → `fertig`.

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Alle fünf Beispiele in einem echten, vom Orchestrator erzeugten
  Sitzungscontainer erzeugt — der reale `ct-thorax-60`-Datensatz wurde
  zuerst vollständig ins Archiv gesendet (Voraussetzung für
  sinnvolle C-FIND-Treffer), dann real abgefragt.
- Die Zwei-Serien-Struktur der Studie real über eine SERIES-Level-
  Abfrage bestätigt (nicht nur aus `datasets.yml`s Beschreibung
  übernommen).
- `content:validate`: 0 Verstöße (27 Lektionen, 35 Glossarbegriffe).
- `datasets/build` und `services/sandbox`: pytest erneut ausgeführt
  (unverändert von dieser Slice betroffen) — beide grün.
- Lektion 2.3 im Browser gegen den echten Stack aufgerufen — rendert
  vollständig fehlerfrei.
- Sitzung über die echte Oberfläche sauber beendet (`docker ps -a`
  bestätigt vollständige Entfernung).
- Alle Docker-Ressourcen dieser Slice nach Abschluss vollständig
  entfernt.

## Nicht Teil dieser Slice

- Kein Node-Stub für Lektion 2.3 (`lab.node` bleibt `null`).
- Mit 2.1, 2.2 und 2.3 abgeschlossen sind alle Track-2-Lektionen ohne
  offene Infrastrukturfrage jetzt geschrieben. Offen bleiben nur noch
  2.4 (braucht einen zweiten Storage-Endpunkt für ein echtes
  Drei-Parteien-C-MOVE) und 2.8 (braucht `curl` als registriertes
  Werkzeug sowie eine DICOMweb-Verifikation) — siehe
  `docs/content-todo.md`.
