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
| `nodes/wrong-door/de.md` | 75, 91, 100, 115 | Dieselbe Situation wie bei Silent CT (P6, dieselbe Write-up-Struktur bewusst wiederverwendet): freie Prosa statt fester Marker-Phrase, Inhalt vollständig. |

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

## Track 4 — zehn Gerüste angelegt, Fließtext fehlt

`content/lessons/4.1` bis `4.10` existieren jetzt als Gerüste
(`status: draft`): vollständige `meta.yml` (Track, Reihenfolge, Dauer,
`requires`, höchstens vier Werkzeuge aus der Registry, Glossarbegriffe,
Datensatz) und eine `de.md` mit Titel, Teaser, drei Lernzielen und der
geplanten Gliederung. Titel und Lernziele sind aus der Curriculum-Tabelle in
`konzept-lernplattform.md` (Abschnitt 5, Track 4) abgeleitet, nicht erfunden.

Kein Gerüst enthält Fachprosa oder Werkzeugausgaben — Abschnitt 13 des
Auftrags. Jede Lektion trägt unter „Was zum Schreiben noch fehlt" ihre
eigene Liste offener Punkte. Quer durch alle zehn sind das drei Muster:

- **Ausgaben fehlen.** Jedes Beispiel muss in der Spielwiese erzeugt und
  wörtlich übernommen werden. Das ist der Hauptteil der Arbeit.
- **Die Spielwiese kann das Szenario noch nicht.** Betrifft 4.7 (kein
  Worklist-Dienst, `wlmscpfs` ist nur in der Registry vorgesehen), 4.8 (kein
  MPPS-, kein Storage-Commitment-Gegenpart) und 4.9 (kein TLS-Endpunkt).
  Diese drei Lektionen sind ohne Ausbau der Spielwiese nicht schreibbar.
- **Datensätze fehlen.** 4.3 braucht einen Datensatz mit gemischten SOP
  Classes, 4.5 zwei Studies, die gleich aussehen und verschiedene Study
  Instance UIDs tragen (Lektion 1.4 nennt dieses Lab bereits). Beide wären
  neue Einträge in `datasets.yml` samt Erzeugung in `datasets/build/`.

`content:validate` meldet für die zehn neuen Lektionen nichts. (Der
ursprüngliche Hinweis zu `lab.node: null` in allen zehn ist mit P9
überholt — 4.2 zeigt jetzt auf die Node `verbindung-ohne-bild`, siehe
unten.)

## P9 — Node-Definitionen: eine neue spielbare Node, sieben Gerüste

Track 1 lag mit einem echten Defekt vor: sechs der acht Lektionen nach 1.0
(1.1, 1.2, 1.3, 1.4, 1.7, 1.8) verwiesen bereits im mitgelieferten
Content-Paket auf `lab.node`-Slugs, die es nie gab (`first-contact`,
`zwei-ebenen-tiefer`, `wo-steht-das`, `zwillinge`, `halbe-sache`,
`mitgehoert`) — mit `optional: false`. `LessonController` blendet eine
fehlende Node zwar bereits stillschweigend aus (kein Absturz, keine
sichtbare Lücke), aber das Feld log damit eine falsche Tatsache: „diese
Node ist Pflicht" für eine Node, die nicht existiert.

**Node `neue-node` (Lektion 1.6, „AE Title, Host, Port: das Adress-Trio")
ist jetzt vollständig und spielbar.** Sie ist ausschließlich aus dem
bestehenden Schema erzeugt (wie Wrong Door in P6, keine Engine-Änderung):
ein neu installierter MR-Scanner mit Werkseinstellungen in allen drei
Adressfeldern (`remote_host`, `remote_port`, `remote_ae`), die der
Lernende nacheinander korrigieren muss — jede Korrektur deckt exakt die
nächste Stufe der Association-Prüfung aus Abschnitt 5.3 auf. Verifiziert
gegen den echten Stack (siehe ADR 0010).

**Die anderen sechs benannten Slugs plus ein neuer Track-4-Slug
(`verbindung-ohne-bild`, Lektion 4.2) existieren jetzt als echte, aber
absichtlich leere Gerüste** (`node.yml` ohne `environment`, `de.md` mit
einer klar markierten „Gerüst"-Sektion statt Prosa) — dieselbe Diszplin wie
bei den Track-4-Lektionen oben. Jedes Gerüst nennt seine konkrete
technische Blockade in seiner eigenen `de.md`:

| Node | Lektion | Blockiert durch |
|---|---|---|
| `first-contact` | 1.1 | `dcmdump` auf eine lokale `.dcm`-Datei ist in der Engine nicht gebaut (liefert nur einen Platzhaltertext) |
| `zwei-ebenen-tiefer` | 1.2 | `findscu` kennt nur STUDY/SERIES, keine PATIENT-/INSTANCE-Ebene; ein Archiv-Host hat höchstens einen Bestand |
| `wo-steht-das` | 1.3 | dasselbe `dcmdump`-Limit wie `first-contact` |
| `zwillinge` | 1.4 | kein Datensatz mit zwei gleich aussehenden Studies unterschiedlicher UID; Engine kennt nur einen Bestand pro Archiv-Host |
| `halbe-sache` | 1.7 | keine Presentation-Context-/Transfer-Syntax-Aushandlung in der Engine |
| `mitgehoert` | 1.8 | dasselbe Aushandlungs-Limit wie `halbe-sache` |
| `verbindung-ohne-bild` | 4.2 | dasselbe Aushandlungs-Limit wie `halbe-sache` |

Diese sieben Lücken sind **Engine-Features, keine Content-Lücken** — anders
als bei Track 4 oben ist hier nicht Fließtext das Fehlende, sondern eine
Fähigkeit der simulierten Engine selbst (`services/engine/app/rules.py`
kennt ausschließlich Host/Port/Called-AE/Calling-AE als Prüfstufen). Sobald
eine dieser Fähigkeiten gebaut ist, kann das jeweilige Gerüst mit echtem
Szenario, Hints und Write-up gefüllt werden, exakt wie bei `neue-node`.

**Hardening:** Die sechs betroffenen Track-1-Lektionen (1.1, 1.2, 1.3, 1.4,
1.7, 1.8) sowie 4.2 haben jetzt `lab.optional: true` mit einem Kommentar,
der auf diesen Abschnitt verweist — die einzige Lektion, deren Node
tatsächlich fertig ist (1.6, `neue-node`), bleibt `optional: false`.

## P10 — Fortschritt gegen `P10-Roadmap-DCMLab.md`

Laufende Liste, damit der Stand jederzeit aus dieser Datei ablesbar ist,
nicht nur aus Commit-Historie. Die Roadmap selbst ist ein extern
geliefertes Dokument (kein Teil des ursprünglichen Auftrags) — wo sie
unbelegte Fakten verlangt, gilt weiterhin Abschnitt 13.

**Engine-Features (sieben laut Roadmap Abschnitt III):**

| Feature | Status | Node(s) freigeschaltet |
|---|---|---|
| C-FIND-Matching (Query-Level, Wildcards) | ✅ P10.1 | `c-find-mismatch` (neu) |
| Größenlimit (C-STORE) | ✅ P10.2 | `oversized-image` (neu) |
| Transfer-Syntax-Aushandlung | ✅ P10.3 | `syntax-negotiation-fails` (neu) |
| Multiframe-Generator | offen | — (Track 3) |
| Worklist-Query | offen | `worklist-query-empty` |
| Patient-Merge / Study-Split | offen | `patient-merge-discovery`, `merge-patient` |

**Nebeneffekte, noch nicht genutzt:**

- `zwei-ebenen-tiefer` und `zwillinge` waren blockiert, weil ein
  Archiv-Host nur einen Bestand kannte. Mit `records` (ADR 0011,
  `content-schema.md` Abschnitt 6a) kann ein Archiv jetzt mehrere
  Studies vorhalten — `zwillinge`s eigentliche Blockade ("zwei gleich
  aussehende Studies, verschiedene UID") ist damit technisch lösbar.
- `halbe-sache` (1.7), `mitgehoert` (1.8) und `verbindung-ohne-bild`
  (4.2) waren blockiert, weil die Engine keine Presentation-Context-
  Aushandlung kannte. Mit Feature 3 (ADR 0013, `content-schema.md`
  Abschnitt 6c) ist auch das technisch lösbar.

Alle fünf bleiben trotzdem als Gerüst stehen, bis ihr jeweiliger
Fließtext geschrieben ist — kein Content erfunden, nur weil die Engine
es jetzt könnte.

**Nodes (vier laut Roadmap Abschnitt IV):**

| Node | Status |
|---|---|
| `c-find-mismatch` | ✅ P10.1, vollständig und live verifiziert |
| `oversized-image` | ✅ P10.2, vollständig und live verifiziert (Lektion 4.3, teilweise — SOP-Class-Ablehnung fehlt weiterhin) |
| `syntax-negotiation-fails` | ✅ P10.3, vollständig und live verifiziert |
| `patient-merge-discovery` | offen (braucht Feature „Patient-Merge") |

**Track 2, Track 3, Track-4-Vervollständigung:** noch nicht begonnen.

## CI

`content:validate` läuft in der CI-Pipeline (`content`-Job), aber mit
`continue-on-error: true`, solange diese Liste nicht leer ist. Sobald die
obigen Punkte entschieden und behoben sind, das `continue-on-error` in
`.github/workflows/ci.yml` entfernen.
