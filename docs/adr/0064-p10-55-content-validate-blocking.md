# 0064 — P10.55: `content:validate` in CI blockierend gemacht

Status: akzeptiert
Datum: 2026-09-14

## Kontext

Beim Aufräumen offener Punkte nach Abschluss von Track 5 (Nutzerwunsch:
„Offene Punkte zuerst") fiel eine reale Inkonsistenz zwischen
`docs/content-todo.md` und dem tatsächlichen CI-Zustand auf:
`.github/workflows/ci.yml`s `content`-Job trägt seit der Einführung von
`content:validate` (P1) ein `continue-on-error: true` mit dem
Kommentar „Sobald die Liste [in content-todo.md] leer ist, hier
continue-on-error entfernen." Diese Bedingung ist seit P10.23 erfüllt:
Alle 19 damals gefundenen Verstöße wurden behoben, und jede einzelne
seither in diesem Projekt durchgeführte Content-Slice (P10.24 bis
P10.54, dutzende PRs) hat `content:validate` real mit „keine
Verstoesse" bestätigt — zuletzt in P10.54 mit 41 Lektionen, 16 Nodes,
23 Werkzeugen, 52 Glossarbegriffen.

`docs/content-todo.md` selbst ist längst kein „Liste offener
Verstöße" mehr, sondern ein chronologisches Protokoll bereits
behobener Lücken und dokumentierter Design-Entscheidungen — die
ursprüngliche Bedingung für das Entfernen von `continue-on-error` war
also seit über 30 Slices erfüllt, aber nie umgesetzt.

## Entscheidung

**`.github/workflows/ci.yml`**: `continue-on-error: true` (samt
zugehörigem Kommentar) aus dem `content:validate`-Schritt des
`content`-Jobs entfernt. `content:validate` ist jetzt ein
blockierender Check wie jeder andere CI-Job — ein künftiger
Content-Verstoß lässt die entsprechende PR-Prüfung fehlschlagen, statt
nur informativ zu bleiben.

**`docs/content-todo.md`**: Abschnitt „## CI" aktualisiert, um den
neuen, tatsächlichen Zustand zu dokumentieren, statt weiter eine
längst erfüllte Bedingung als offen zu führen.

**Nebenbei entdeckt und korrigiert (dieselbe „Offene Punkte
zuerst"-Durchsicht):** Die P10-Roadmap-Tabelle listete
„Multiframe-Generator | offen" weiter als unerledigt, obwohl Lektion
3.4 (P10.43, ADR 0052) bereits real geklärt hatte, dass kein neuer
Generator nötig ist — die mitgelieferten `pydicom`-Testdaten genügten.
Tabellenzeile und zugehöriger Fließtext korrigiert.

## Manuell verifiziert

- Isolierter Compose-Stack (`docker compose -p p10-55`): `php artisan
  content:validate` real ausgeführt — 0 Verstöße (41 Lektionen, 16
  Nodes, 23 Werkzeuge, 52 Glossarbegriffe) — bestätigt, dass das
  Entfernen von `continue-on-error` keinen bestehenden, versteckten
  Verstoß aufdeckt, bevor die Änderung live geht.
- `.github/workflows/ci.yml` bleibt syntaktisch valide YAML (reine
  Entfernung von zwei Zeilen plus Kommentar, keine Struktur geändert).

## Nicht Teil dieser Slice

- Kein neuer Content geschrieben — reine Infrastruktur-/
  Dokumentationskorrektur.
- Keine weitere Durchsicht von `docs/content-todo.md` auf zusätzliche
  stale Einträge über die zwei hier gefundenen hinaus — bei Bedarf
  eigene, spätere Slice.
