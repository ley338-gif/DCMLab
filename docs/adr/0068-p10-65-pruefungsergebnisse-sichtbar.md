# 0068 — P10.65: Prüfungsergebnisse sichtbar machen

## Kontext

Seit PR #67–#71 (ADR 0066, 0067) vergibt `ExamAttemptService::complete()`
bei bestandener Track-Abschlussprüfung über
`ProfileService::recomputeAfterExamAttempt()` ein `TrackBadge` (einmalig,
`unique(user_id, track_id)`) und 50 Punkte
(`ProfileService::TRACK_PASS_POINTS`). Das öffentliche Profil
(`Profiles/Show.vue`) zeigt diese Badges bereits unter „Bestandene Tracks“
an. Drei Stellen zeigten das Ergebnis bisher nicht:

1. Der PDF-Export (`resources/views/profiles/pdf.blade.php`) nutzt
   dieselbe `profileData()`-Quelle wie die Profilseite (bestätigt durch
   Lesen von `PublicProfileController::exportPdf()`), rendert
   `track_badges` aber nicht.
2. Das Dashboard (`DashboardController`/`Dashboard.vue`) zeigt nur
   Lektionsfortschritt je Track, keinen Prüfungsstatus.
3. Die Ergebnisseite (`Exams/Result.vue`) zeigt Bestehen/Nichtbestehen,
   aber nicht, *ob dieser Versuch* das Badge/die Punkte ausgelöst hat.

## Entscheidung 1 — `badge_awarded`-Spalte statt Session-Flash

Die Ergebnisseite darf die Belohnung nur beim *erstmaligen* Bestehen
anzeigen: ein zweiter, ebenfalls bestandener Versuch auf einem bereits
bestandenen Track vergibt laut `TrackBadge::firstOrCreate()` kein zweites
Badge mehr und darf auf der Ergebnisseite auch nichts mehr versprechen.

Zwei Optionen standen zur Wahl:

- **Session-Flash** beim Redirect von `answer()`/`show()` zu `result()`.
  Bewusst **nicht** gewählt: die Ergebnisseite ist eine eigenständige,
  per GET erreichbare Inertia-Seite (auch über einen erneuten Aufruf der
  URL, nicht nur per Redirect direkt nach der letzten Antwort) — ein
  Flash-Wert wäre nach einem Reload verschwunden, obwohl die Aussage
  „dieser Versuch hat das Badge ausgelöst“ weiterhin korrekt und für
  Feature-Tests nur über HTTP schwer reproduzierbar ist.
- **Neue Spalte `exam_attempts.badge_awarded`** (Migration
  `2026_09_17_000002_add_badge_awarded_to_exam_attempts_table.php`,
  `boolean`, `default(false)`), gesetzt in
  `ExamAttemptService::complete()` anhand des Rückgabewerts von
  `ProfileService::recomputeAfterExamAttempt()` (jetzt `bool` statt
  `void`, via `TrackBadge::firstOrCreate()`s `wasRecentlyCreated`).
  **Gewählt**: der Zustand ist Teil des Versuchs selbst, bleibt über
  Reloads korrekt und ist mit `assertDatabaseHas`/`assertInertia` direkt
  testbar (siehe `ExamControllerTest::test_a_second_passing_attempt_on_an_already_passed_track_awards_nothing_again`).

`ProfileService::TRACK_PASS_POINTS` wurde von `private` zu `public`
geändert, damit `ExamController::result()` denselben Wert für
`points_awarded` anzeigen kann, ohne ihn zu duplizieren.

## Entscheidung 2 — Verfügbarkeitslogik einmalig in `ExamAttemptService`

`TrackController::show()` berechnete `exam_available`,
`all_lessons_completed`, `in_progress_attempt_id` und `passed` bereits
inline. Für das Dashboard (mehrere Tracks gleichzeitig, andere
Datenquelle für Lektions-Fortschritt: `withCount` statt geladener
Lesson-Collection) wäre dieselbe Logik ein zweites Mal nötig gewesen.

Neue Methode `ExamAttemptService::statusForTracks(?User $user, iterable
$tracks): array` bündelt für eine beliebige Menge Tracks in wenigen
gruppierten Queries (`Lesson`-Zählungen je Track, `ExamAttempt`,
`TrackBadge`) genau das, was vorher in `TrackController::show()` inline
stand. `TrackController` und `DashboardController` rufen jetzt beide
diese eine Methode auf; `TrackController`s bisheriges Verhalten
(`exam.available` = Content definiert eine Prüfung für den Track) bleibt
unverändert, nur die Berechnung ist jetzt zentral. Das Dashboard nutzt
zusätzlich `all_lessons_completed`, um „Prüfung verfügbar“ (Link zum
direkten Start) von „Prüfung noch nicht verfügbar“ zu unterscheiden — eine
dritte, dashboardspezifische Ableitung aus denselben Rohdaten, keine
Dopplung der Query-Logik selbst.

## Konsequenzen

- Migration `add_badge_awarded_to_exam_attempts_table` (additiv, keine
  Datenmigration nötig — bestehende Zeilen bekommen `false`).
- `ProfileService::recomputeAfterExamAttempt()` gibt jetzt `bool` zurück
  (Breaking Change der Methodensignatur, aber `final class` mit genau
  einem Aufrufer: `ExamAttemptService::complete()`).
- `ExamController::result()` liefert `attempt.badge_awarded` und
  `attempt.points_awarded` zusätzlich zu den bisherigen Feldern.
- `TrackController::show()` und `DashboardController::index()` rufen
  `ExamAttemptService::statusForTracks()` statt eigener Inline-Queries.
- `resources/views/profiles/pdf.blade.php` rendert `track_badges` jetzt
  (Abschnitt „Bestandene Abschlussprüfungen“, Leerzustand inklusive).
- `Dashboard.vue`, `Exams/Result.vue`: neue Anzeigen für Prüfungsstatus
  bzw. Badge-/Punktevergabe.
- `lang/de.json`: fehlende Keys nachgetragen, darunter auch drei
  Alt-Lücken aus PR #67–#71 (`"Bestandene Tracks"`, `"Abschlussprüfung"`,
  `"Prüfung starten"` u.a.), die schon vorher per `trans()` verwendet,
  aber nie als Key erfasst waren (fielen bisher unbemerkt auf den
  Schlüssel als Fallback-Text zurück, weil Schlüssel und deutscher Text
  zufällig identisch sind).
- Bewusst **nicht** angefasst: `achievements`/`first_blood` bleiben vom
  neuen `track_badges`-Mechanismus getrennt (siehe ADR 0066/0067) — eine
  Vereinheitlichung ist eine eigene, spätere Slice.

## Verifikation

- `ExamControllerTest`: neuer Test für Erstvergabe (`badge_awarded: true`,
  `points_awarded: 50` auf der Ergebnisseite) und für den zweiten,
  ebenfalls bestandenen Versuch (`badge_awarded: false`, kein zweites
  `TrackBadge`, Punkte bleiben bei 50).
- `PublicProfileControllerTest`: PDF-Vorlage direkt gerendert (nicht das
  von DomPDF erzeugte, komprimierte Binärformat — darin lässt sich Text
  nicht zuverlässig suchen, siehe bereits bestehender Test, der nur den
  Content-Type prüft) — Tracktitel nach Bestehen, Leerzustand ohne.
- `DashboardTest`: alle drei Zustände (bestanden mit Datum, verfügbar,
  noch nicht verfügbar) in einem Testfall mit drei Tracks.
- `php artisan test`: 160 passed, 12 failed (identisch zur dokumentierten
  Baseline aus ADR 0066/0067 — fehlender Vite-Manifest im minimalen
  Testcontainer, keine neuen Fehlschläge), 1 skipped.
- `./vendor/bin/pint --test`: 134 files, PASS.
- `php artisan content:validate`: keine Verstöße (41 Lektionen, 16 Nodes,
  23 Werkzeuge, 52 Glossarbegriffe, 5 Prüfungen).
- `npm run check`: 86 files formatted, keine Lint-Fehler.
