# 0091 — Pionier-System (first_blood/track_badges) nach achievement_unlocks migriert

## Status

Angenommen, 15.09.2026. Setzt die in `docs/offene-fragen.md` festgehaltene
Empfehlung um, nachdem der Betreiber die dafür nötigen sieben Bild-Assets
geliefert hat.

## Kontext

ADR 0070 hat das aeltere "Pionier"-System (`achievements`-Tabelle mit
`type: first_blood`, global pro Node, ADR 0009; `track_badges`-Tabelle,
ein Abzeichen pro Nutzer und bestandenem Track, ADR 0066) bewusst neben
dem neuen, deklarativen Achievement-System (ADR 0071/0077:
`achievement_definitions`/`achievement_unlocks`, gespeist aus
`content/achievements.yml`) bestehen lassen -- eine Migration haette sechs
Track-Badges plus eine globale "Wer war zuerst"-Definition gebraucht, und
damit sieben neue Bild-Assets, die es nicht gab. `docs/offene-fragen.md`
hat das als "nur migrieren, wenn jemand tatsaechlich Kartenoptik dafuer
will" offengehalten.

## Entscheidung

**Beide Mechaniken sind jetzt echte `achievement_definitions`-Eintraege in
`content/achievements.yml`, keine eigene Lesestelle mehr:**

- `trailblazer` (`scope: global`, `unlock_when: {type: activity_completed,
  activity_type: node}`, kein `key`): globaler Wettlauf um die Erstloesung
  *irgendeiner* Node -- die vorhandene, aber bislang nie mit echtem Content
  ausgeuebte `scope: global`-Infrastruktur aus ADR 0077
  (`AchievementUnlock.activity_id`, `unique(achievement_definition_id,
  activity_id)`) traegt das exakt wie geplant, ein eigener Gewinner je
  Node.
- `track-<slug>` (sechs Eintraege, `unlock_when: {type: track_passed,
  track: <slug>}`): das bereits vorhandene, aber ebenfalls nie benutzte
  `track_passed`-Kriterium.

Beide Ausloeser laufen bereits ueber den bestehenden Pfad
(`ActivityProgressRecorder::record()` -> `AchievementUnlockEvaluator`),
ausgeloest von `NodeController::submitFlag()` bzw.
`ExamAttemptService::complete()` -- keine neue Vergabe-Logik noetig, nur
die Content-Eintraege.

**`ProfileService::totalPoints()` zaehlt Track-Punkte jetzt direkt aus
`ExamAttempt` (distinct bestandene `track_id`) statt aus `TrackBadge`,**
weil die Badge-Vergabe selbst nicht mehr synchron in `ProfileService`
passiert, sondern deklarativ ueber den Evaluator -- beide Quellen sind
aequivalent (ein `TrackBadge` entstand immer beim ersten bestandenen
Versuch), aber die neue Zaehlung braucht keine Schreiblogik mehr.

**`achievements`/`track_badges` bleiben als Tabellen bestehen, werden
aber nicht mehr beschrieben.** Ein neuer Artisan-Befehl
(`achievements:migrate-pionier`) uebertraegt ihre bestehenden Zeilen
unveraendert (`user_id`, `awarded_at`) nach `achievement_unlocks`, statt
sie aus `node_attempts`/`exam_attempts` neu abzuleiten -- eine Neuableitung
wuerde nach Zeilen-Erstellungsreihenfolge sortieren
(`ActivityBackfillProgress`-Muster), die von der echten
Loese-Chronologie abweichen kann, und faelschlich einen anderen "Pionier"
kueren. Die beiden Alt-Tabellen zu droppen ist bewusst ein spaeterer,
getrennter Schritt (siehe `docs/offene-fragen.md`) -- ein automatisches
`migrate --force` beim Deploy darf ihre Daten nicht loeschen, bevor der
Backfill in jeder echten Umgebung gelaufen und bestaetigt ist.

**Neuer Bug gefunden und behoben:** `achievement_unlocks` trug seit der
urspruenglichen Migration (2026_09_18) eine unbedingte
`unique(user_id, achievement_definition_id)`-Constraint, die nie auf
"nur persoenliche Achievements" eingeschraenkt wurde, als
`scope: global`/`activity_id` spaeter (2026_09_25) dazukam. Ergebnis: ein
Nutzer, der ein global-scoped Achievement auf einer *zweiten* Aktivitaet
gewinnt, scheiterte an dieser Alt-Constraint --
`AchievementService::unlock()` faengt die
`UniqueConstraintViolationException` ab und meldet faelschlich "bereits
freigeschaltet", ohne die zweite Zeile zu schreiben. Kein bestehender Test
deckte das auf, weil `AchievementUnlockEvaluatorTest`s einziger
Global-Scope-Test zwei verschiedene Nutzer auf zwei verschiedenen
Aktivitaeten gewinnen laesst, nie denselben Nutzer zweimal -- "trailblazer"
ist das erste Content-Achievement, das `scope: global` tatsaechlich mit
mehr als einer moeglichen Aktivitaet ausuebt, und hat den Fehler beim
Testschreiben sofort sichtbar gemacht. Migration 2026_09_28 ersetzt die
Alt-Constraint durch einen partiellen Unique-Index
(`WHERE activity_id IS NULL`, von SQLite und Postgres gleichermassen
unterstuetzt), der nur persoenliche Achievements betrifft -- global-scoped
bleiben ausschliesslich durch `unique(achievement_definition_id,
activity_id)` geschuetzt.

## Konsequenzen

- `content/achievements.yml`: sieben neue Eintraege (`trailblazer`,
  `track-fundamente`, `track-services`, `track-bild`,
  `track-troubleshooting`, `track-betrieb`, `track-grundlagen`), sieben
  neue Bild-Assets unter `public/images/achievements/` (1254×1254 PNG,
  Stil der sechs bestehenden Badges).
- `App\Services\ProfileService`: `maybeAwardFirstBlood()` und die
  `TrackBadge`-Vergabe in `recomputeAfterExamAttempt()` entfernt;
  `achievementsFor()` (die gemeinsame Pionier-Lesestelle) entfernt --
  Dashboard und oeffentliches Profil lesen nur noch
  `AchievementService::listForUser()`. `totalPoints()` zaehlt Track-Punkte
  neu aus `ExamAttempt`.
- `ExamAttemptService`: `badge_awarded` wird jetzt aus dem Rueckgabewert
  von `ActivityProgressRecorder::record()` abgeleitet, nach dem Speichern
  des Versuchs (Reihenfolge wichtig: `ExamActivity::result()` und
  `totalPoints()` lesen beide den frisch persistierten Zustand).
- `resources/js/pages/Dashboard.vue`, `Profiles/Show.vue`,
  `resources/views/profiles/pdf.blade.php`: die "Pionier"/"Bestandene
  Tracks"/"First Blood"-Kacheln entfernt, das PDF zeigt jetzt dieselbe
  generische Achievement-Liste wie Dashboard/Profil.
- `app/Console/Commands/AchievementsMigratePionier.php` (neu, Signatur
  `achievements:migrate-pionier`): einmaliger, idempotenter Backfill der
  Alt-Tabellen nach `achievement_unlocks`, manuell auszufuehren (Muster:
  `ActivityBackfillProgress`).
- `database/migrations/2026_09_28_..._fix_achievement_unlocks_personal_scope_uniqueness.php`
  (neu): behebt die oben beschriebene Constraint-Luecke.
- `App\Models\Achievement`/`App\Models\TrackBadge`: keine Verwendung mehr
  im Anwendungscode; ihre Tabellen bleiben bewusst bestehen, bis der
  Backfill ueberall bestaetigt ist (siehe `docs/offene-fragen.md` fuer den
  offenen Folgeschritt "Tabellen droppen").

## Verifikation

- `AchievementsMigratePionierTest`: Backfill uebertraegt den korrekten
  historischen Gewinner (`user_id`, `unlocked_at`) unveraendert,
  idempotent bei erneutem Lauf, scheitert klar ohne geseedete
  Definitionen, ueberspringt sauber first_blood-Zeilen ohne passende
  Activity.
- `ProfileServiceTest`: `totalPoints()` zaehlt jeden bestandenen Track
  genau einmal, auch bei mehreren bestandenen Versuchen.
- `NodeControllerTest`: das Loesen der ersten Node schaltet gleichzeitig
  "first-blood" (persoenlich) und "trailblazer" (global) frei; eine
  zweite, andere Node darf "trailblazer" fuer denselben Nutzer erneut
  vergeben (Regressionstest fuer den oben beschriebenen Constraint-Bug).
- `ExamControllerTest`: `badge_awarded`/Punkte funktionieren unveraendert
  ueber den neuen Ablauf, inklusive des Falls "zweiter bestandener
  Versuch vergibt nichts erneut".
- `PublicProfileControllerTest`: das oeffentliche Profil und sein
  PDF-Export zeigen bestandene Tracks als normale, unlocked Achievements.
- Manuelle Verifikation gegen den echten, laufenden Stack: Backfill auf
  der echten Dev-Datenbank ausgefuehrt (5 vorhandene first_blood-Zeilen,
  0 TrackBadges), Dashboard und oeffentliches Profil zeigen "Trailblazer"
  korrekt mit dem migrierten Datum als freigeschaltet, mit echtem
  Artwork statt Platzhalter.
- Alle 394 Tests, `phpstan analyse` (Level 7), `pint --test`,
  `npm run check`, `vue-tsc --noEmit`, `vitest run` und `npm run build`
  sind gruen.
