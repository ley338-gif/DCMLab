# 0045 — P10.35: Werkzeugleisten 4.3 und 4.7 bereinigt

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Direkt im Anschluss an ADR 0044 (P10.34, dieselbe Bereinigung für 1.8/
4.1/4.2/4.4) wurde geprüft, ob dasselbe Muster — Werkzeuge in
`meta.yml: tools` deklariert, aber im Fließtext nie tatsächlich mit
einem `$ …`-Aufruf gezeigt — noch an anderer Stelle vorliegt.
`content:validate`s `checkToolInverse` (`ContentValidate.php:460`)
erkennt nur die Umkehrung (benutzt, aber nicht deklariert), nie einen
deklarierten, aber ungenutzten Eintrag — solche Fälle bleiben ohne
gezielte Prüfung unsichtbar.

Zwei weitere echte Fälle gefunden:
- **4.3** ("Nur manche Bilder kommen an"): `tools: [storescu,
  storescp, dcmdump, findscu]`, Fließtext zeigt aber nur `storescu`,
  `findscu` und `ls`. `storescp` und `dcmdump` kommen nirgends vor.
- **4.7** ("Worklist ist leer"): `tools: [findscu, wlmscpfs, tshark]`.
  `wlmscpfs` ist ein Überbleibsel von vor P10.20 — seit Orthancs
  Worklists-Plugin aktiv ist (ADR 0030), beantwortet Orthanc selbst
  die Worklist-Anfrage, `wlmscpfs` kommt im fertigen Fließtext nicht
  mehr vor (dieselbe Korrektur wurde für die Schwesterlektion 2.5
  bereits in P10.25 gemacht, hier aber übersehen).

## Entscheidung

**`content/lessons/4.3/meta.yml`**: `tools` auf `[storescu, findscu]`
reduziert.

**`content/lessons/4.7/meta.yml`**: `tools` auf `[findscu, tshark]`
reduziert.

Keine Fließtextänderung nötig — beide Lektionen sind inhaltlich bereits
vollständig und korrekt, nur die Werkzeugleisten-Deklaration war
veraltet.

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Eigener isolierter Compose-Stack (Ports 55335/56335/58335):
  `content:sync` und `content:validate` im echten App-Container — 0
  Verstöße (27 Lektionen, 16 Nodes, 22 Werkzeuge, 36 Glossarbegriffe).
- Lektionen 4.3 und 4.7 im Browser gegen den echten Stack aufgerufen —
  Werkzeugleisten zeigen jetzt exakt die im Fließtext gezeigten
  Werkzeuge.
- `datasets/build` (5 passed), `services/sandbox` (14 passed, ruff
  clean, mypy 0 Fehler — jetzt inklusive der in ADR 0043 behobenen
  Testdatei, da dieser Arbeitsbaum nach deren Merge von `main`
  abgezweigt wurde).
- Alle Docker-Ressourcen dieser Slice nach Abschluss vollständig
  entfernt.

## Nicht Teil dieser Slice

- Keine weiteren Lektionen geprüft — `docs/content-todo.md` verzeichnet
  nach dieser Slice keine bekannten verbleibenden Fälle dieses Musters.
