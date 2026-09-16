# 0117 — Lossless Backfill: `rich-content:migrate` (CMS-7d.2)

## Status

Angenommen, 30.09.2026.

## Kontext

CMS-7d.1 (ADR 0115/0116) hat die Persistenz-Grundlage
(`lessons.rich_content`/`nodes.rich_content`, JSONB, nullable),
`rich-content:audit` (dateibasiert, gegen `content/`) und ein
vollstaendiges v1-Schema geliefert -- der Audit meldet gegen `content/`
0 blockierende Funde. Der urspruengliche CMS-7d.2-Plan sah vor, genau
diese Datei-Quelle auch fuer den Backfill zu verwenden.

Betreiber-Korrektur vor Beginn der Implementierung, zwei echte
Konsistenzthemen, keine Stilfragen:

1. **`content/` ist nicht mehr die kanonische Quelle.** Seit ADR
   0102/0108 schreiben `LessonContentPublisher`/`NodeContentPublisher`
   jede Studio-Freigabe direkt in `Lesson::body`/`Node::body` (DB) --
   nie zurueck nach `content/` (`content:export` fehlt bewusst noch,
   siehe `docs/offene-fragen.md`). `content/` kann seitdem juenger oder
   AELTER sein als die DB, je nachdem, wann zuletzt `content:sync`
   lief. Ein Backfill aus `content/` wuerde deshalb im schlimmsten Fall
   eine bereits ueberholte Fassung in `rich_content` einfrieren.
2. **Kein Read-Cutover in CMS-7d.2.** Nach dem Backfill waere
   `rich_content` befuellt, aber die Publisher schreiben bis CMS-7d.3
   weiterhin nur `body` (Markdown). Wuerde der Lernpfad schon jetzt
   `rich_content` bevorzugen, wuerde ein normaler Publish `body`
   aktualisieren, der Lernende saehe aber weiterhin das beim Backfill
   eingefrorene, jetzt veraltete `rich_content`. Ein temporaerer
   Dual-Write in beide Publisher waere die Alternative gewesen --
   unnoetig kompliziert fuer eine Uebergangsphase.

## Entscheidung

**Quelle ist die DB, `content/` nur Fallback bei `body === null`.**
`rich-content:migrate` liest `Lesson::body`/`Node::body` direkt;
ist eine Spalte `null` (im echten Bestand selten), faellt der Befehl
auf `ContentRepository::lessons()/nodes()` zurueck. `rich-content:audit`
(ADR 0115) bleibt eine wertvolle, fruehe, dateibasierte Kontrolle, ist
aber kein Migrationsnachweis -- `rich-content:migrate` fuehrt seine
EIGENE, vollstaendige Praeflug-Pruefung (Konverter + Validator +
Skip-Check) direkt gegen die tatsaechliche Migrationsquelle durch,
unabhaengig vom Datei-Audit.

**Kein Read-Cutover in diesem PR.** Lernende sehen nach CMS-7d.2
exakt dasselbe wie vorher -- `rich_content` wird befuellt, aber von
keinem Runtime-Pfad gelesen. Der Read-Cutover ist Teil von CMS-7d.3,
zusammen mit dem Editor-/Publisher-Wechsel (atomar, nicht vorgezogen):

```
7d.2 = Backfill, Runtime liest weiterhin body
7d.3 = Editor + Publisher schreiben rich_content
       UND Read-Pfad wechselt atomar auf rich_content
```

**`rich-content:migrate` (neues Artisan-Command).**

- `php artisan rich-content:migrate` (oder explizit mit `--dry-run`,
  reines Synonym) prueft nur, schreibt nie.
- `php artisan rich-content:migrate --apply` schreibt -- aber nur, wenn
  die Praeflug-Pruefung fuer ALLE Ressourcen (Lessons + Nodes, 59 im
  echten Bestand) sauber ist.
- Fuer JEDE Ressource, unabhaengig davon ob `rich_content` schon gesetzt
  ist: effektiver Body (DB, Datei-Fallback) -> Konverter -> Validator ->
  Skip-Check. Jeder Schema-Verstoss oder uebersprungene Knoten ist ein
  blockierender Fund, exakt wie bei `rich-content:audit`.
- Ist `rich_content` bereits gesetzt: das neu berechnete Dokument wird
  zusaetzlich gegen den gespeicherten Wert gestellt.
  - Gespeicherter Wert selbst ungueltig (Schema-Verstoss) ->
    blockierender Fund `invalid_existing`, Abbruch.
  - Gespeicherter Wert weicht vom frisch berechneten ab -> `diverged`,
    ebenfalls Abbruch. Niemals stilles Ueberschreiben.
  - Stimmt ueberein -> "bereits vorhanden", kein Schreibvorgang, kein
    Fund.
- **Ist auch nur eine einzige Ressource nicht bereit, schreibt der
  Befehl NICHTS** -- auch nicht mit `--apply`, auch nicht fuer die
  anderen, sauberen Ressourcen. Alle Funde werden gemeldet (wie bei
  `rich-content:audit`), nicht nur der erste.
- Erst wenn ALLE Ressourcen bereit sind, oeffnet `--apply` eine
  einzige DB-Transaktion. Jede Zeile wird per `lockForUpdate()`
  unmittelbar vor dem Schreiben ZWEIFACH erneut geprueft: `rich_content`
  ist noch `NULL` (Schutz vor einem konkurrierenden Rich-Content-
  Backfill derselben Zeile), UND `body` entspricht noch dem beim
  Preflight gelesenen Snapshot (Schutz vor einer ganz normalen
  Autoren-Freigabe zwischen Preflight und Schreibvorgang, die `body`
  aendert, ohne `rich_content` anzufassen -- ohne diesen zweiten Check
  wuerde `--apply` sonst ein bereits veraltetes Dokument einfrieren,
  siehe "Nachtrag" unten). Jede Abweichung rollt die gesamte Transaktion
  zurueck.
- **Kein `--force`.** Es gibt keinen Weg, ein bestehendes `rich_content`
  per Flag zu ueberschreiben.

Sicherheitskette (Betreiber-Formulierung):

```
effective DB content
      ↓
Converter
      ↓
Validator
      ↓
0 skipped nodes
      ↓
alle Ressourcen vorbereitet
      ↓
eine DB-Transaktion
      ↓
nur rich_content IS NULL schreiben
      ↓
bestehendes rich_content niemals ueberschreiben
```

**Node-Envelope wie in ADR 0115 festgezogen**, hier tatsaechlich befuellt:

```
Node.body
   ↓ NodeSections
briefing ─→ RichContentDocument
h1       ─→ RichContentDocument
h2       ─→ RichContentDocument
...
write_up ─→ RichContentDocument
   ↓
node_content-Umschlag ({type: "node_content", version: 1, briefing, hints, write_up})
```

**Bewusst nicht Teil dieser ADR:** jeder Lesepfad-Wechsel (CMS-7d.3);
jede Aenderung an `LessonContentPublisher`/`NodeContentPublisher`
(schreiben weiterhin nur `body`, CMS-7d.3); ein `--force`-Flag; ein
Dual-Write-Mechanismus in den Publishern.

## Nachtrag (Betreiber-Review vor dem Merge)

Zwei Korrekturen, bevor dieser PR gemerged wurde:

1. **body-Race geschlossen.** Der urspruengliche Row-Lock pruefte nur
   `rich_content IS NULL` -- das schuetzt vor einem zweiten
   Migrationslauf derselben Zeile, nicht vor einer ganz normalen
   Autoren-Freigabe zwischen Preflight und Schreibvorgang, die `body`
   aendert, ohne `rich_content` anzufassen. `finalize()` nimmt seitdem
   den beim Preflight gelesenen `body`-Snapshot mit in
   `pendingWrites`; `writePending()` gleicht ihn nach dem Row-Lock
   erneut ab (auch beim `content/`-Fallback: dann muss `body`
   weiterhin `NULL` sein) und bricht sonst die gesamte Transaktion ab.
   Mit `DB::listen()` regressionsgetestet (simuliert eine Freigabe
   exakt zwischen Preflight-Abfrage und Row-Lock). Dabei ausserdem eine
   echte Luecke behoben: eine daraus resultierende `RuntimeException`
   waere zuvor unbehandelt aus `handle()` entkommen statt als
   kontrollierter Command-Fehlschlag gemeldet zu werden.
2. **`before` UND `after` gehoeren beide ins Lesson-Dokument.** Die
   urspruengliche Umsetzung (wie `rich-content:audit`, ADR 0115) nutzte
   nur `QuizContent::splitBody()['before']` -- `LessonController::show()`
   fuegt aber `before` UND `after` (die Fussnote/Navigation NACH dem
   Quiz-Abschnitt, z. B. "**Als Naechstes:** ...") zu EINEM
   zusammenhaengenden Content-Block zusammen, `after` ist real in 9 von
   42 Lektionen nicht leer. Ein `rich_content`, das nur `before`
   enthaelt, haette diesen Teil unbemerkt verloren -- genau das, was
   das gesamte CMS-7d-Audit-Konzept verhindern soll. Sowohl
   `rich-content:audit` als auch `rich-content:migrate` pruefen/
   konvertieren `before` und `after` jetzt separat (mit korrektem
   Datei-Zeilen-Offset im Audit) und fuegen ihre `content`-Arrays im
   fertigen Lesson-Dokument zusammen. Der Datei-Bestand blieb dabei
   weiterhin audit-rein (0 blockierende Funde); der bereits gegen die
   Dev-Datenbank gelaufene Backfill wurde zurueckgesetzt und mit der
   Korrektur neu ausgefuehrt (59/59, danach wieder idempotent).

## Konsequenzen

- Ein erster `--apply`-Lauf gegen den echten Bestand befuellt alle 59
  `rich_content`-Spalten, ohne dass sich fuer Lernende oder Autoren
  irgendetwas sichtbar aendert.
- Ein zweiter `--apply`-Lauf ist idempotent: 0 zu migrieren, 59 bereits
  vorhanden, 0 geschrieben -- solange sich `body` nicht geaendert hat.
- Aendert sich `body` durch einen normalen Studio-Publish, NACHDEM
  `rich_content` schon befuellt wurde, meldet ein erneuter
  `rich-content:migrate`-Lauf `diverged` fuer genau diese Ressource --
  ein bewusstes, sichtbares Signal, kein stiller Drift. (Das ist exakt
  der Zustand, den CMS-7d.3 durch echten Dual-Write/Cutover aufloest.)
- `docs/offene-fragen.md` unveraendert.
- Naechster Schritt CMS-7d.3: `RichContentEditor.vue` in Lesson-/
  Node-Studio verdrahten, `LessonContentPublisher`/`NodeContentPublisher`
  auf `rich_content` umstellen, Lesepfad atomar mitwechseln, alte
  `content_versions` weiterhin `payload.body` behalten (Normalisierung
  erst zur Laufzeit beim Restore, nicht rueckwirkend).

## Verifikation

- PHP: 624/624 Tests gruen (14 neu fuer `rich-content:migrate`,
  2 neu fuer `rich-content:audit`: Dry-Run/Apply, Idempotenz,
  DB-vor-Datei-Praezedenz, Datei-Fallback nur bei `body === null`,
  Abbruch ohne Schreibvorgang bei fehlendem Body/unbekanntem
  Konstrukt/ungueltigem oder abweichendem bestehenden `rich_content`/
  geaendertem `body` zwischen Preflight und Schreibvorgang,
  Quiz-Ausschluss, `before`+`after`-Zusammenfuehrung, Envelope-Struktur),
  PHPStan Level 7 und `pint --test` gruen.
- Gegen den echten Dev-Bestand (Docker, Postgres) verifiziert: nach der
  `before`+`after`-Korrektur zurueckgesetzt und neu migriert -- 59/59
  geschrieben, danach wieder idempotent (0/59/0). Stichprobe Lektion
  1.0 bestaetigt, dass der komplette Nach-Quiz-Abschnitt (inklusive
  eines eigenen `horizontal_rule` und Folgeabsatzes, nicht nur der
  "Als Naechstes"-Zeile) jetzt im Dokument steckt.
