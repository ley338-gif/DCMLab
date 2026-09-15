# 0079 — W5 (Teil 2): Fragenbank ueber `ref`-Referenzen

## Status

Angenommen, 15.09.2026. Zweiter Teil von Arbeitsphase W5 aus
`dcm-lab-lms-agent-prompt.md`, im Anschluss an ADR 0078 (Voraussetzungen).

## Kontext

W5 verlangt: "Eine Frage wird ein Objekt mit Typ, Antwort, Skill-Tags und
Rückverweis. Lektionsquiz und Prüfung ziehen aus derselben Bank." DoD:
"Dieselbe Frage lässt sich in einem Lektionsquiz und im Prüfungspool
verwenden, ohne sie zweimal zu pflegen."

Lektionsquiz (`quiz:` in `meta.yml`, `**qN —**`-Karten in `de.md`) und
Prüfungspool (`questions:` in `exam.yml`, `### fNN —`-Abschnitte in `de.md`)
sind zwei getrennte Content-Strukturen mit eigenen ID-Namensräumen. Eine
"echte" Fragenbank im Sinn eines dritten, gemeinsamen Speicherorts
(`content/questions/`) hätte bedeutet, den bestehenden Bestand — rund 40
Lektionsfragen und 40 Prüfungsfragen über fünf Tracks — umzuziehen und neu
zu verdrahten: eine Content-Migration von der Größenordnung, vor der ADR
0074 (W2) und ADR 0077 (W4) bereits ausdrücklich zurückschrecken, wenn sie
nicht von einem Menschen geprüft werden kann.

## Entscheidung

**Referenz statt Umzug.** Ein Eintrag in `exam.yml`s `questions`-Liste kann
`ref: {lesson, question}` statt eigener `type`/`answer` tragen. `type`,
`answer`, Fragetext und Optionen werden dann live aus dem `quiz:`-Eintrag
und dem `**qN —**`-Block der referenzierten Lektion gelesen
(`ExamContent::resolveRef()`, wiederverwendet `QuizContent::parseQuestions()`/
`answerFor()` unverändert) — exakt eine Pflegestelle, kein Umzug von
Bestandsdaten. `review`, `difficulty` und `tags` bleiben Sache des
Prüfungspools, weil sie prüfungsspezifisch sind (dieselbe Lektionsfrage
könnte in zwei Prüfungen unterschiedlich getaggt sein).

`ExamContent::parseQuestions()`/`answerFor()` bekommen dafür ein neues,
optionales `$lessons`-Argument (Default `[]`, rückwärtskompatibel) und
iterieren jetzt über `exam.yml`s `questions`-Liste statt über die in `de.md`
gefundenen Abschnitte — für nicht-referenzierte Fragen identisches
Ergebnis (bewiesen durch alle bestehenden `ExamControllerTest`/
`ContentValidateTest`-Fälle, unverändert grün), aber jetzt fähig, eine
Frage ganz ohne eigenen `de.md`-Abschnitt aufzulösen.

**Der reale Bestand wird nicht rückwirkend auf `ref` umgestellt.** Ob eine
der 40 bestehenden Prüfungsfragen tatsächlich wortgleich mit einer
Lektionsfrage ist, ist eine inhaltliche Einschätzung, die ein
Code-Agent nicht zuverlässig automatisiert treffen sollte — falsch
zusammengeführt, würde eine Prüfungsfrage plötzlich eine andere Antwort
verlangen als vorher. Der Mechanismus ist gebaut und vollständig getestet;
seine Anwendung auf echten Content ist eine redaktionelle Entscheidung für
`docs/offene-fragen.md`.

## Konsequenzen

- `ContentValidator::checkExamStructure()` prüft `ref`-Einträge: die
  referenzierte Lektion und Frage müssen existieren, sonst ein Issue statt
  eines stillen Ausfalls. Optionsanzahl für die `answer`-Grenzprüfung
  (Index-Bereich bei single/multi) kommt aus der Lektion selbst
  (`countQuizOptions()`, dieselbe Methode wie für die Lektion), nie ein
  zweites Mal, abweichend gepflegt.
- Ein `ref`-Eintrag braucht keinen `### id — ...`-Abschnitt in `exam/de.md`
  mehr (vorher zwingend); hat er trotzdem einen, gilt die
  `**Erklärung:**`-Pflichtzeile weiterhin.
- `AnswerGrader`/`ExamAttemptService::answerCurrent()` nutzen jetzt
  `ExamContent::typeFor()` statt direkt `$entry['type']`, damit die
  Bewertung auch für `ref`-Fragen den richtigen Typ kennt.

## Verifikation

- `ExamContentRefTest`: `ref`-Auflösung ohne eigenen `de.md`-Abschnitt,
  Antwort/Typ aus der referenzierten Lektion, unbekannte Lektion/Frage
  liefert keine Frage, self-contained Einträge funktionieren unverändert
  neben `ref`-Einträgen.
- `ContentValidateTest`: ein gültiger `ref`-Eintrag validiert sauber ohne
  eigenen `de.md`-Abschnitt; eine unbekannte `ref.question` erzeugt genau
  das erwartete Issue.
- Alle bestehenden `ExamControllerTest`- und `ContentValidateTest`-Fälle
  (Pool-Ziehung, Bewertung, Fehlerverteilung) bleiben unverändert grün.
- `php artisan content:validate` gegen den echten Bestand: weiterhin 0
  Verstöße (kein realer Content nutzt `ref`).
- Alle 300 Tests, `phpstan analyse` (Level 7) und `pint --test` sind grün.
