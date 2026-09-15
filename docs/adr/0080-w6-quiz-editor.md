# 0080 — W6.1: Quiz-Editor

## Status

Angenommen, 15.09.2026. Erster Editor aus Arbeitsphase W6
(`dcm-lab-lms-agent-prompt.md` Abschnitt 5, "Größter Einzelgewinn, deshalb
zuerst"), im Anschluss an ADR 0073–0079.

## Kontext

Bis hierhin war `ActivityContract::serialize()` bewusst Identität: es gibt
den unveränderten Ist-Zustand zurück (ADR 0073), weil kein Editor existierte,
der etwas anderes hätte erzeugen können. `ContentVersioningService` (ADR
0075) verwaltet Entwürfe rein als Buchführung, ohne sie je nach `content/`
zurückzuschreiben. W6 ist die Phase, die diese Lücke tatsächlich schließt —
für den Quiz-Editor als ersten Fall.

## Entscheidung

**`serialize()`/`validate()` bekommen einen optionalen `$draft`-Parameter
(Default `null`).** Ohne ihn bleibt das Verhalten alle fünf
Aktivitätstypen unverändert identisch zu vorher (rückwärtskompatibel, kein
bestehender Aufrufer musste sich ändern). Mit ihm generiert
`LessonActivity` aus einem `{quiz: [...]}`-Entwurf echten Dateiinhalt:

- **`App\Content\LessonQuizGenerator`** ersetzt chirurgisch nur den
  `quiz:`-Block in `meta.yml` und den `## Quiz`-Abschnitt in `de.md` --
  jeder andere Teil beider Dateien (Kommentare eingeschlossen) bleibt
  Zeile für Zeile erhalten, nach demselben Grundsatz wie
  `ContentBuilder::writeHash()` beim Flag-Hash. Ein Freitext-Vorspann
  direkt unter der `## Quiz`-Überschrift (z. B. "*Wissenskarten — kommen
  später zur Wiederholung zurück.*") bleibt ebenfalls erhalten.
- **`LessonActivity::validate($draft)`** prüft nicht den Ist-Zustand,
  sondern das, was `serialize($draft)` erzeugen würde: es baut einen
  synthetischen `ContentRepository::lessons()`-Eintrag aus den generierten
  Dateien (dieselbe `FrontMatter::parse()`/`Yaml::parse()`-Kette, die
  `ContentRepository` selbst benutzt) und lässt `ContentValidator`
  unverändert darüber laufen -- keine zweite, schwächere Prüfung für
  Entwürfe.

**Ein reales Byte-Detail, das der Test gegen echten Content aufgedeckt
hat:** `content/lessons/1.1/meta.yml` hat CRLF-Zeilenenden. Der erste
Entwurf des `quiz:`-Ersetzungsregex kannte nur `\n` und ließ den alten
Block stehen, was zu einem doppelten `quiz:`-Schlüssel führte. Behoben,
bevor es je an echten Content gegangen wäre.

**`QuizEditorController`** verdrahtet das zum vollen Kreislauf:
`GET .../quiz` (Ist-Zustand oder laufender Entwurf, Policy-geprüft über
`ActivityPolicy::update`), `POST .../quiz/validate` (Live-Befunde ohne zu
speichern), `POST .../quiz` (`ContentVersioningService::createDraft()`),
`POST .../quiz-versions/{v}/submit` (`submitForReview()`),
`POST .../quiz-versions/{v}/publish` (nur `ActivityPolicy::publish`,
also Reviewer): schreibt zuerst über `ContentWriter::write($activity,
$version->payload)` echte Dateien, erst bei Erfolg wird die Version als
veröffentlicht gebucht -- ein fehlgeschlagener Schreibversuch hinterlässt
nie eine Version, die "published" behauptet, ohne dass `content/` dazu
passt.

## Konsequenzen

- `ActivityContract::serialize()`/`validate()` haben jetzt die Signatur
  `(?array $draft = null): array` für alle fünf Typen; nur `LessonActivity`
  wertet `$draft` bisher aus (nur den `quiz`-Schlüssel). Node-/Exam-/
  Sandbox-Editoren (W6.2–W6.4) erweitern dasselbe Muster um ihre eigenen
  Entwurfsfelder, ohne die Vertragsform erneut zu ändern.
- `resources/js/pages/Author/QuizEditor.vue`: Fragen hinzufügen/entfernen,
  Optionen bearbeiten, Live-Prüfen, Entwurf speichern, einreichen,
  freigeben -- eine Seite, ein Formular, Policy-gesteuerte Aktions-Buttons
  (`can_publish`).
- Route-Gruppe `de/author/lessons/{lesson}/quiz` und
  `de/author/quiz-versions/{version}` (auth+verified, wie der Rest der
  Anwendung).

## Verifikation

- `LessonQuizGeneratorTest`: erzeugter `quiz:`-Block parst zurück auf
  dieselben Werte, jedes andere Feld/jeder Kommentar in `meta.yml` bleibt
  erhalten, Vorspann-Freitext in `de.md` bleibt erhalten, alte Karten
  verschwinden, ein No-Op-Regenerieren von `content/lessons/1.1` (echter
  Bestand, CRLF) bleibt gültig und geparst identisch.
- `LessonActivityTest`: `serialize($draft)`/`validate($draft)` decken
  einen gültigen und einen ungültigen Entwurf (Antwort-Index außerhalb der
  Optionen) ab; `validate()` ohne Argument bleibt unverändert.
- `QuizEditorControllerTest::test_the_full_lifecycle_from_draft_to_publish_actually_writes_content`
  durchläuft den kompletten W6-DoD einmal end-to-end über echte
  HTTP-Endpunkte: prüfen, Entwurf anlegen, einreichen, ein Autor darf nicht
  freigeben (403), ein Reviewer gibt frei -- und `content/lessons/1.0/`
  enthält danach tatsächlich die neuen Fragen.
- Alle 316 Tests, `phpstan analyse` (Level 7), `pint --test`,
  `npm run check` und `npm run build` sind grün. Keine volle
  Browser-Verifikation gegen die Postgres-Dev-Umgebung (wie schon in ADR
  0078 begründet) -- kompensiert durch den vollständigen HTTP-Lebenszyklus-
  Test, der denselben Code-Pfad wie ein Browser durchläuft.
