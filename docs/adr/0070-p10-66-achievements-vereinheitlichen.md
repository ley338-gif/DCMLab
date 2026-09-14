# 0070 — P10.66: Achievements vereinheitlichen

## Status

Angenommen.

## Kontext

Seit P10.60 (ADR 0066) und P10.65 (ADR 0068) gibt es zwei Abzeichen-Tabellen
mit bewusst unterschiedlicher Semantik:

- `achievements` (`unique(node_id, type)`, aktuell nur `type = "first_blood"`):
  kein Abzeichen pro Nutzer, sondern pro Node — "wer hat diese Node global
  als Erster gelöst" (ADR 0009). Vergeben in
  `ProfileService::maybeAwardFirstBlood()`.
- `track_badges` (`unique(user_id, track_id)`): ein Abzeichen pro Nutzer —
  jeder, der einen Track besteht, bekommt sein eigenes. Vergeben in
  `ProfileService::recomputeAfterExamAttempt()`.

Der Auftrag verlangt zuerst eine Bestandsaufnahme, keine Implementierung.
Empirisch geprüft (Lesen von `ProfileService`, `DashboardController`,
`PublicProfileController`, `Dashboard.vue`, `Profiles/Show.vue`,
`profiles/pdf.blade.php`):

- **Schreibseite: keine Dopplung.** `maybeAwardFirstBlood()` und
  `recomputeAfterExamAttempt()` sind unterschiedliche Methoden mit
  unterschiedlichen Auslösern (Node-Solve vs. Exam-Abschluss) und
  unterschiedlicher Race-Behandlung (`UniqueConstraintViolationException`
  vs. `firstOrCreate()`). Es gibt hier nichts zu vereinheitlichen — die
  beiden Vergabewege sind schon heute sauber getrennt, weil die Ereignisse,
  die sie auslösen, verschieden sind.
- **Leseseite: echte Dopplung, aber nicht wo der Auftrag sie vermutet.**
  Drei fast identische Anfrage-Bausteine beantworten dieselbe Frage
  ("welche Abzeichen hat dieser Nutzer"):
  - `DashboardController::index()` (Zeile 68–77 vor dieser Änderung):
    `Achievement::where('user_id', ...)->with('node')->get()->map(...)`
    — zeigt **nur** First-Bloods in der Achievements-Karte.
  - `PublicProfileController::profileData()` (Zeile 44–55 vor dieser
    Änderung): dieselbe Achievement-Query **plus** eine strukturell
    identische `TrackBadge::where('user_id', ...)->with('track')->get()->map(...)`
    — beide als zwei parallele, getrennte Arrays (`first_bloods`,
    `track_badges`).
  - Die eigentliche Lücke: das Dashboard zeigt bestandene Tracks zwar als
    Badge in der Track-Fortschrittszeile (`track.exam.passed`, aus
    `ExamAttemptService::statusForTracks()`, ADR 0068), aber **nicht** in
    der "Achievements"-Karte — `achievementLabels` in `Dashboard.vue` kennt
    nur `first_blood`. Das ist keine Dopplung, sondern eine Inkonsistenz,
    die aus der fehlenden gemeinsamen Quelle folgt.
- `Profiles/Show.vue` und `profiles/pdf.blade.php` rendern beide zwei
  strukturell fast identische Karten/Tabellen ("Bestandene Tracks" und
  "First Blood") aus den zwei getrennten Props — auch hier keine
  Schema-Frage, sondern zwei Konsumenten derselben unverbundenen Queries.

## Entscheidung — gemeinsames Lesemodell statt Schema-Migration

**Keine Migration in eine gemeinsame Tabelle.** Geprüft und verworfen:

- Die beiden Unique-Constraints sind nicht zwei Spielarten derselben Regel,
  sondern zwei verschiedene Antworten auf zwei verschiedene Fragen (global
  vs. pro Nutzer). Ein gemeinsames `type`-Feld bräuchte einen dritten,
  bedingten Constraint-Fall — praktisch zwei partielle/gefilterte Unique-
  Indizes auf `type` in einer Tabelle (`WHERE type = 'first_blood'` →
  `unique(node_id)`, `WHERE type = 'track_passed'` → `unique(user_id,
  track_id)`). Postgres (Produktion) und SQLite (Testsuite, siehe
  `phpunit.xml`) unterstützen partielle Indizes zwar beide, aber das ersetzt
  zwei selbsterklärende Ein-Zeilen-Constraints durch einen, der nur mit
  Kommentar verständlich ist — mehr Komplexität für dieselbe Aussagekraft.
- Eine Migration bräuchte zusätzlich eine Daten-Migration bestehender
  `achievements`- und `track_badges`-Zeilen in die neue Tabelle — ein
  Risiko an Produktionsdaten, das keinem der beobachteten Schmerzpunkte
  abhilft: die Dopplung sitzt nachweislich auf der Leseseite, nicht im
  Schema.
- Die Vergabe-Semantik (`maybeAwardFirstBlood()`,
  `recomputeAfterExamAttempt()`) bliebe ohnehin unverändert bestehen, weil
  sie schon heute nicht dupliziert ist — eine Schema-Migration würde also
  nur Risiko hinzufügen, ohne eine Codezeile Vergabe-Logik einzusparen.

**Stattdessen: eine neue Methode `ProfileService::achievementsFor(User
$user): array`.** Sie führt beide Quellen zu einer nach `awarded_at`
sortierten, typisierten Liste zusammen (`kind: 'first_blood'|'track_passed'`
plus die jeweils passenden Anzeigefelder) und ersetzt die drei getrennten
Anfrage-Bausteine:

- `DashboardController::index()` nutzt sie jetzt für die
  Achievements-Karte — dadurch zeigt das Dashboard ab sofort auch
  bestandene Tracks als Achievement-Eintrag, nicht mehr nur First-Bloods
  (behebt die oben beschriebene Inkonsistenz, ohne dass dafür eine neue
  Datenquelle nötig war).
- `PublicProfileController::profileData()` nutzt dieselbe Methode und
  spaltet das Ergebnis nach `kind` in die bestehenden Props `first_bloods`
  / `track_badges` auf — die Prop-Struktur für `Profiles/Show.vue` und
  `profiles/pdf.blade.php` bleibt unverändert, beide Templates brauchten
  keine Anpassung.

Damit gibt es nur noch eine Stelle, die die Frage "welche Abzeichen hat
dieser Nutzer" beantwortet; beide Tabellen, ihre Unique-Constraints und
ihre getrennte Vergabe-Logik bleiben unangetastet.

## Konsequenzen

- Keine neue Migration, kein Datenverlust-Risiko, keine Downtime-Frage.
- `App\Models\Achievement` und `App\Models\TrackBadge` bleiben bestehen,
  werden aber nur noch von `ProfileService` direkt abgefragt — beide
  Controller kennen die Modelle nicht mehr.
- `Dashboard.vue`: `PioneerAchievementEntry`-Typ und
  `pioneerAchievementLabels` erweitert um `track_passed`; neuer lang-Key
  `"Abschlussprüfung bestanden"`.
- `Profiles/Show.vue`, `profiles/pdf.blade.php`, deren Feature-Tests: keine
  Änderung nötig (Prop-Vertrag identisch).
- Erweiterbarkeit für einen möglichen künftigen Typ (z. B. "alle fünf
  Tracks bestanden", von ADR 0068 als mögliche Zukunft genannt): die neue
  Methode ist bereits `kind`-diskriminiert und würde einen dritten `kind`
  ohne Strukturänderung aufnehmen — bewusst **kein** dritter Typ, keine
  neue UI in dieser Slice, nur ein Design, das keine weitere Migration
  erzwingt, falls das später kommt.

## Nachtrag — Verhältnis zum generischen Achievement-System

Nach dieser Entscheidung kam ein zweites, unabhängiges Achievement-System
hinzu (`App\Achievements\AchievementRegistry`, `achievement_definitions`/
`achievement_unlocks`, siehe `docs/achievements.md`) mit sechs neuen,
persönlichen Badges (First Blood, Association Accepted, Echo Heard, Store
& Forward, Worklist Whisperer, Sandbox Starter). Um den neuen, persönlichen
`first-blood`-Slug nicht mit dem hier beschriebenen, globalen
`first_blood`-Achievement zu verwechseln, heißt die hier vereinheitlichte
Karte im UI seither **"Pionier"** statt "Achievements" (`Dashboard.vue`s
`pioneerAchievementLabels`, `Profiles/Show.vue`s Card-Titel) und bekam eine
eigene, generische "Achievements"-Sektion daneben. Diese ADR und ihre
Entscheidungen (gemeinsames Lesemodell, keine Schema-Migration) bleiben
für das "Pionier"-System unverändert gültig.

## Verifikation

- `ProfileServiceTest`: neuer Test für `achievementsFor()` — First-Blood
  und Track-Badge desselben Nutzers erscheinen beide, absteigend nach
  `awarded_at` sortiert, mit korrektem `kind`.
- `DashboardTest`: erweitert um den Nachweis, dass ein `TrackBadge` in der
  Pionier-Liste des Dashboards auftaucht.
- `PublicProfileControllerTest`, `ExamControllerTest`: unverändert grün —
  Prop-Verträge nicht angefasst.
- `make lint && make content-validate && make test`.
