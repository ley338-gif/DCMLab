# 0103 — Lektions-Editor zeigt Ist-Zustand aus der DB (Nachtrag zu ADR 0102)

## Status

Angenommen, 15.09.2026.

## Kontext

ADR 0102 (CMS-5b) stellte die Lektionsfeld-Freigabe auf einen
DB-direkten Schreibpfad um -- `LessonContentPublisher` schreibt
`title`/`teaser`/`body`/... direkt in die `lessons`-Zeile, ohne
`content/lessons/<id>/{meta.yml,de.md}` mehr anzufassen.

Uebersehen dabei: `LessonEditorController::currentFields()` (der
Ist-Zustand, den das Formular zeigt, wenn kein Entwurf aussteht) las
weiterhin ausschliesslich aus `content/` -- mit der Begruendung (eigener
Klassenkommentar, seit ADR 0080/W6.2), DB-Modelle seien "keine
verlaessliche Quelle", weil sie nur per `content:sync` aktualisiert
wuerden. Diese Begruendung stimmt seit ADR 0102 fuer Lektionsfelder
nicht mehr: eine Freigabe aktualisiert die DB jetzt SOFORT, schreibt
aber nie wieder die Datei. Ergebnis: nach einer erfolgreichen Freigabe
zeigte ein erneutes Oeffnen des Editors den **alten** Datei-Stand statt
des gerade veroeffentlichten -- ein durch ADR 0102 eingefuehrter
Regressions-Fund, kein Bestandsproblem.

## Entscheidung

`currentFields()` bevorzugt jetzt `$lesson`s DB-Spalten (`title`,
`teaser`, `level`, `duration_minutes`, `tools`, `requires`,
`glossary_terms`, `objectives`, `sandbox`, `lab`, `body`), sobald
`$lesson->body !== null` ist. `content/` bleibt Fallback fuer eine
Lektion, deren erster `content:sync`-Lauf noch aussteht (frisch
importierter Bestandscontent, der den Editor noch nie durchlaufen hat)
-- derselbe Fallback-Mechanismus wie in `LessonController::show()`
(ADR 0101).

Andere Editoren (Quiz, Pruefung, Achievement) sind von diesem Fund
nicht betroffen: ihr Ist-Zustand kommt weiterhin korrekt aus `content/`,
weil ihr Freigabe-Pfad unveraendert dorthin schreibt.

## Konsequenzen

- Kein Schema-/Verhaltensaenderung ausserhalb von
  `LessonEditorController::currentFields()`.
- Neuer Testfall in `LessonEditorControllerTest` beweist explizit: nach
  einer vollstaendigen Freigabe zeigt ein erneutes Oeffnen des Editors
  die gerade veroeffentlichten Werte, nicht den (nie geschriebenen)
  alten Dateistand.

## Verifikation

- Alle 476 Tests, PHPStan Level 7 und `pint --test` sind gruen.
