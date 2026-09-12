# Content-Lücken

Von `content:validate` gefundene, echte Lücken im vorhandenen Content, die
**nicht** mechanisch von der Plattform gefixt wurden — jede Behebung wäre
entweder eine redaktionelle Entscheidung oder hätte Prosa erfunden
(Abschnitt 13 des Auftrags: „Wo du Prosa für Lektionen oder Nodes erfinden
müsstest: tu es nicht"). Diese Datei ist die Ablage dafür.

Stand: 12.09.2026, nach Einführung von `content:validate` (P1). Aktueller
Befund: `CONTENT_PATH=../../content php artisan content:validate`.

## Was schon automatisch gefixt wurde

Rein technische, nicht-redaktionelle Lücken wurden direkt behoben:

- `content/datasets.yml` fehlte komplett — angelegt mit den Datensätzen, auf
  die Lektionen und die Node `silent-ct` bereits verweisen (`ct-thorax-60`,
  `ct-thorax-3-slices`), basierend auf den in Abschnitt 4.6 des Auftrags
  bereits festgelegten Referenzwerten.
- Fünf reine ASCII-Diagramme ohne jeden Befehl waren nicht mit
  `<!-- kein-beispiel -->` markiert (1.0, 1.2, 1.4, 1.6, 1.8) — nachgetragen,
  keine Textänderung.
- `nodes/silent-ct/node.yml: related_lessons` verwies auf `"4.1"`, eine noch
  nicht existierende Lektion — Referenz entfernt (Kommentar verweist hierher).
  Der Hinweis auf Lektion 4.1 steht weiterhin in der Prosa unter
  „Verwandte Inhalte" in `nodes/silent-ct/de.md`.

## Was offen bleibt — redaktionelle Entscheidung nötig

**Beispielregel, Grenzfälle des `**Was du daran abliest:**`-Musters:**

| Datei | Zeile(n) | Befund |
|---|---|---|
| `lessons/1.0/de.md` | 120 | Windows-Zusatzbefehl (PowerShell) ohne eigene Leseanleitung — teilt sich die Erklärung mit dem Befehl darüber. Content-Schema Abschnitt "Weitere Regeln" sagt ausdrücklich, der Windows-Zusatz solle "kurz" bleiben; ob er trotzdem eine eigene Leseanleitung braucht, ist eine Stilfrage. |
| `lessons/1.0/de.md` | 145 | Exitcode-Demo (`echo $?`) direkt nach einem bereits erklärten Beispiel — die Erklärung steht als Ankündigung *vor* dem Block, nicht danach. |
| `lessons/1.0/de.md` | 216 | Zwei sehr kurze Wireshark-Filter hintereinander, eine gemeinsame Leseanleitung erst nach dem zweiten. |
| `lessons/1.0/de.md` | 241 | Python-Quelltext und sein Konsolen-Output stehen in zwei getrennten Codeblöcken; die Leseanleitung folgt erst nach dem zweiten (Output-)Block — für den ersten (Quelltext) wird sie nicht gefunden. |
| `lessons/1.0/de.md` | 315 | Abschließende Drei-Befehle-Übung; die Erklärung danach ist freie Prosa statt des festen Wortlauts. |
| `lessons/1.3/de.md` | 25 | `dcmdump`-Ausgabe mit angehefteter Pfeil-Grafik, die Tag/VR/Wert erklärt — Grenzfall zwischen echtem Beispiel und Diagramm. |
| `nodes/silent-ct/de.md` | 38, 81, 91, 105, 114, 131, 147, 162 | Das komplette Write-up erklärt jeden Schritt in freier Prosa statt mit dem festen Wortlaut. Inhaltlich vollständig (Befehl, Ausgabe, Erklärung sind alle da), nur die Marker-Phrase fehlt durchgehend. |

Für `lessons/1.0` und die Node ist das vermutlich am saubersten mit einer
bewussten Entscheidung zu lösen: entweder den festen Wortlaut nachtragen
(reine Formsache, kein neuer Fachinhalt) oder die Regel für "verkettete
Beispiele mit gemeinsamer Erklärung" im Schema explizit als Ausnahme
zulassen. Beides ist eine redaktionelle/schema-Entscheidung, keine
Bugfix-Aufgabe.

**Werkzeuge-Grenze:**

- `lessons/1.7/de.md` Zeile 112 benutzt `storescu` in einem Beispiel, aber
  `meta.yml: tools` hat bereits vier Einträge (`dcmdump`, `dcmconv`,
  `dcmcjpeg`, `dcmdjpeg`) — das Limit aus Abschnitt 1 von
  `content-schema.md`. Eine Entscheidung ist nötig: `storescu` gegen eines
  der vier tauschen, oder das Beispiel umbauen.

## P3 — Quiz-Karten ohne strukturierte Antworten

Abschnitt 4.1 des Auftrags skizziert `meta.yml: quiz` als strukturiertes
Array mit Fragen, Antwortoptionen und einer korrekten Antwort, aus dem sich
graded Spaced-Repetition-Karten bauen ließen. Der tatsächliche Content
(`content/lessons/*/meta.yml`) hat kein solches Feld — die Quiz-Fragen samt
Antwortoptionen stehen ausschließlich als freie Prosa unter der
Markdown-Überschrift `## Quiz` in `de.md`, ohne maschinenlesbaren
Antwortschlüssel.

P3 rendert diese Prosa unverändert als Teil des normalen Lektionstexts
(korrekt lesbar, wie von der DoD gefordert), baut aber **keine** interaktiven,
graded Quiz-Karten und keine Spaced-Repetition-Wiederholung. Eine
Antwort-Auswertung zu implementieren hätte bedeutet, die korrekten Antworten
selbst zu erfinden — das verbietet Abschnitt 13 ausdrücklich. Sobald die
Quiz-Fragen ein strukturiertes `quiz:`-Feld mit echtem Antwortschlüssel
bekommen (redaktionelle Entscheidung, kein Plattform-Bug), kann die
interaktive Karten-Funktion nachgezogen werden.

## P4 — Zwei Dateien der Node "silent-ct" ohne Textinhalt

`content/nodes/silent-ct/node.yml: environment.files` listet drei Dateien,
die der Lernende in der simulierten Shell per `cat` lesen kann:
`netzplan-radiologie.txt`, `notizen.txt`, `conformance-pacs-archiv.txt`.

Nur `netzplan-radiologie.txt` hat echten Inhalt (`content/nodes/silent-ct/
assets/netzplan-radiologie.txt`) — wörtlich aus dem mitgelieferten
`de.md`-Write-up übernommen (Abschnitt "4. Vergleichen"), keine eigene
Prosa. Für `notizen.txt` (Wartungsprotokoll, Konfig aus Backup 02/2024) und
`conformance-pacs-archiv.txt` (nennt beide Ablehnungsgründe, nicht den AE
Title) gibt es keine im Auftrag oder in `de.md` vorgegebene Textfassung —
sie zu erfinden wäre Abschnitt-13-Verstoß, da beide Dateien konkrete,
prüfbare Fakten des Szenarios enthalten müssten (welche Ablehnungsgründe
genau, welche Konfigurationswerte im Backup standen).

Die Node bleibt ohne diese beiden Dateien vollständig lösbar (Hint h3 nennt
die Lösung bereits direkt), die Engine liefert für sie einen expliziten
Platzhaltertext statt eines Fehlers oder erfundener Prosa (siehe ADR 0005).
Redaktionelle Entscheidung nötig: Text für beide Dateien schreiben (macht
die Node reichhaltiger, ändert aber nicht ihre Lösbarkeit) oder aus
`environment.files` entfernen.

## CI

`content:validate` läuft in der CI-Pipeline (`content`-Job), aber mit
`continue-on-error: true`, solange diese Liste nicht leer ist. Sobald die
obigen Punkte entschieden und behoben sind, das `continue-on-error` in
`.github/workflows/ci.yml` entfernen.
