# 0033 — P10.23: Die 19 dokumentierten `content:validate`-Verstöße geschlossen

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Mit Track 4 vollständig geschrieben (P10.1–P10.22) blieb
`docs/content-todo.md` als einzige noch offene, dokumentierte
Content-Lücke: 19 Verstöße aus `content:validate`, die seit P1
(12.09.2026) bewusst nicht mechanisch gefixt wurden, weil jede
Behebung entweder eine redaktionelle Entscheidung oder — schlimmer —
erfundene Prosa bedeutet hätte (Abschnitt 13 des Auftrags). Der Nutzer
wählte explizit, diese offenen Punkte vor einem neuen Track (2 oder 3)
zu bearbeiten.

Die 19 Verstöße fielen in zwei Kategorien:

1. **15 Fälle „Beispielregel-Grenzfall":** Ein Codeblock ohne die feste
   Marker-Phrase `**Was du daran abliest:**` innerhalb von drei Zeilen
   danach. In allen 15 Fällen war die inhaltliche Erklärung bereits
   vorhanden — nur eben als freie Prosa, an anderer Stelle (vor dem
   Block statt danach) oder für den zweiten von zwei direkt
   aufeinanderfolgenden Blöcken.
2. **1 Fall „Werkzeuge-Grenze":** `lessons/1.7/de.md` benutzte
   `storescu` in einem Beispiel, obwohl `meta.yml: tools` das
   Vier-Werkzeuge-Limit aus `content-schema.md` bereits ausschöpfte.

## Entscheidung — Marker-Phrase nachtragen, ein Beispiel durch
Verweis ersetzen

**Für alle 15 Beispielregel-Fälle:** Die fehlende Marker-Phrase wurde
nachgetragen, ohne den Inhalt der jeweiligen Erklärung zu ändern — in
den meisten Fällen durch ein einfaches Voranstellen von „**Was du
daran abliest:**" vor bereits vorhandene Prosa (`lessons/1.0/de.md`
Zeilen 145/216/241/315, `nodes/silent-ct/de.md` und
`nodes/wrong-door/de.md`, jeweils alle Fundstellen), in einem Fall
(`lessons/1.0/de.md`, PowerShell-Zusatzbefehl) durch einen neuen,
minimalen Ein-Satz-Verweis auf die bereits gegebene Erklärung des
Unix-Äquivalents ("Dieselbe Auswertung, nur in PowerShell-Syntax").
Für `lessons/1.3/de.md` (Tag/VR/Wert-Diagramm mit echter
`dcmdump`-Zeile) wurde bewusst **keine** `<!-- kein-beispiel -->`-
Markierung verwendet — dieser Marker ist laut etablierter Konvention
für tatsächlich nicht reproduzierbare Fälle reserviert (siehe ADR
0017/0025/0026), nicht für real gezeigte, nur anders erklärte
Ausgaben; stattdessen bekam der Block seine eigene, echte Marker-Phrase.

**Für den Werkzeuge-Grenze-Fall:** Alle vier ursprünglich in
`lessons/1.7/de.md` deklarierten Werkzeuge (`dcmdump`, `dcmconv`,
`dcmcjpeg`, `dcmdjpeg`) sind für den Kernstoff dieser Lektion
(Transfer Syntax, Kompression, verlustfrei vs. verlustbehaftet)
unverzichtbar — keines davon war ein sinnvoller Kandidat zum Streichen.
Das `storescu`-Beispiel selbst ("kein Presentation Context für diese
Kodierung") duplizierte inhaltlich, was Lektion 4.2 seit P10.15 bereits
mit einem echten, ausführlich erklärten Mitschnitt behandelt (eigene
`tools: [storescu, storescp, dcmdump, tshark]`-Deklaration). Der
Codeblock wurde durch zwei Sätze Fließtext ersetzt, die denselben Punkt
ohne Codeblock (und damit ohne erneute Werkzeug-Deklaration) machen und
explizit auf Lektion 4.2 für den echten Mitschnitt verweisen — kein
Inhaltsverlust, keine neue Werkzeugdeklaration nötig.

## Manuell verifiziert

- `content:validate` (im echten `app`-Container, PHP-Versionsproblem
  lokal unverändert): vorher 19 Verstöße, danach **0** — „keine
  Verstoesse (19 Lektionen, 16 Nodes, 21 Werkzeuge, 30 Glossarbegriffe
  geprueft)".
- Alle vier betroffenen Dateien (`lessons/1.0`, `lessons/1.3`,
  `lessons/1.7`, `nodes/silent-ct`, `nodes/wrong-door`) im Browser
  gegen den echten Stack aufgerufen — rendern vollständig fehlerfrei,
  die neuen Marker-Phrasen und der neue Fließtext in Lektion 1.7 fügen
  sich sichtbar sauber in den bestehenden Text ein.
- Keine inhaltliche Änderung an Befehlen oder Ausgaben — nur Marker
  ergänzt bzw. ein Codeblock durch gleichwertigen Fließtext ersetzt.
  Kein neuer Fachinhalt erfunden (Abschnitt 13).

## Nicht Teil dieser Slice (siehe `docs/content-todo.md`)

- **P3 (Quiz-Karten ohne strukturierten Antwortschlüssel)** und
  **P4 (zwei Node-Dateien der Node Silent CT ohne Textinhalt)** bleiben
  offen — beide würden das Erfinden konkreter, prüfbarer
  Szenario-Fakten erfordern (echte Antwortschlüssel bzw. echte
  Backup-Konfigurationswerte), was Abschnitt 13 des Auftrags
  ausdrücklich verbietet, solange diese Fakten nicht anderswo im
  Auftrag vorgegeben sind.
- Track 2 ("services") und Track 3 ("bild") bleiben unbegonnen — beide
  brauchen eine eigene Lektionsplanung, die über eine mechanische
  Content-Bereinigung hinausgeht.
