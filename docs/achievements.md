# Achievement-System

Generisches, DB-gestütztes Achievement-/Badge-System, das dem bestehenden
Projektstil folgt: eine Registry-Klasse als einzige Quelle der Wahrheit, eine
Service-Schicht mit einer einzigen `unlock()`-Methode statt verstreuter
`if ($slug === ...)`-Blöcke, und synchrone Aufrufe direkt aus Controllern
(das Projekt hat an keiner Stelle Laravel-Events/-Listener, siehe
`AchievementService`-Klassendoc).

## Verhältnis zum älteren "Pionier"-System (first_blood/track_badges)

Vor diesem System gab es ein schmales, separates Achievement-Feature:
`App\Models\Achievement` (`type=first_blood`, global pro Node, ADR 0009)
und `App\Models\TrackBadge` (ein Abzeichen pro Nutzer und bestandenem
Track, ADR 0066), zusammengefasst unter dem Anzeige-Label "Pionier". ADR
0091 (15.09.2026) hat beide Mechaniken vollständig migriert, sobald die
dafür nötigen Bild-Assets vorlagen:

- `first_blood` lebt jetzt als `trailblazer`-Achievement (`scope: global`,
  `unlock_when: {type: activity_completed, activity_type: node}`, kein
  `key` — ein eigener Gewinner je Node).
- `track_badges` leben jetzt als sechs `track-<slug>`-Achievements
  (`unlock_when: {type: track_passed, track: <slug>}`).
- Beide nutzen ausschließlich die unten beschriebene, bereits für ADR 0077
  gebaute Infrastruktur — keine neue Vergabe-Logik, nur die Content-Einträge.
- Ein Artisan-Befehl (`php artisan achievements:migrate-pionier`)
  überträgt historische `achievements`/`track_badges`-Zeilen einmalig,
  unverändert (`user_id`, `awarded_at`) nach `achievement_unlocks`.
- `achievements`/`track_badges` als Tabellen bleiben vorerst bestehen
  (nicht mehr beschrieben), bis der Backfill überall bestätigt gelaufen
  ist — siehe `docs/offene-fragen.md` für den offenen Folgeschritt, sie zu
  droppen. Es gibt keinen `pioneer_achievements`-Prop mehr; Dashboard und
  öffentliches Profil zeigen nur noch die eine, generische
  Achievement-Liste unten.

## Architektur

```
App\Achievements\AchievementRegistry   (statisches PHP-Array, einzige Quelle der Wahrheit)
        │
        ▼ (AchievementSeeder::run(), updateOrCreate per slug)
achievement_definitions                (DB-Tabelle: Metadaten je Achievement)
        │
        ▼ (App\Services\AchievementService::unlock())
achievement_unlocks                    (DB-Tabelle: user_id × achievement_definition_id)
        │
        ▼ (AchievementService::listForUser())
Frontend (Achievement-DTOs, siehe resources/js/types/achievement.ts)
```

- **`App\Achievements\AchievementRegistry`** (`apps/web/app/Achievements/AchievementRegistry.php`):
  das feste Array aller Achievements (slug, name, description, image,
  category, rarity, points, is_hidden, sort_order). Wird sowohl vom Seeder
  als auch von `ContentValidate` gelesen — rein dateibasiert, kein
  DB-Zugriff nötig.
- **`achievement_definitions`**: eine Zeile je Achievement, befüllt über
  `Database\Seeders\AchievementSeeder` (`updateOrCreate(['slug' => ...], ...)`,
  idempotent, kein `insert()`).
- **`achievement_unlocks`**: eine Zeile je (Nutzer, Achievement) bei
  persönlichen (`scope: personal`, Default), eine Zeile je (Achievement,
  Aktivität) bei globalen Achievements (`scope: global`, `activity_id`
  gesetzt — ADR 0077/0091). Zwei sich ergänzende Constraints verhindern
  Duplikate race-sicher: ein partieller Unique-Index `(user_id,
  achievement_definition_id) WHERE activity_id IS NULL` für persönliche,
  `unique(achievement_definition_id, activity_id)` für globale
  Achievements (erst `exists()`-Check, dann `create()` in einem
  `try/catch (UniqueConstraintViolationException)`, siehe
  `AchievementService::unlock()`).
- **`App\Services\AchievementService`**: die einzige öffentliche API.
  - `unlock(User $user, string $slug, array $metadata = []): AchievementUnlockResult`
    — idempotent, sicher mehrfach aufrufbar. `AchievementUnlockResult::status`
    ist eines von `AchievementUnlockStatus::{NotFound,AlreadyUnlocked,NewlyUnlocked}`.
  - `listForUser(User $user): Collection` — alle Definitionen (sortiert nach
    `sort_order`) mit `unlocked`/`unlocked_at` für diesen Nutzer, fertig fürs
    Frontend (Bildpfad bereits zu `/images/achievements/{image}` aufgelöst).

## Wo Achievements freigeschaltet werden

Seit ADR 0077 (P10.74) deklarativ statt über Controller-Code: jede
Aktivität, die abschließt, läuft durch
`App\Activities\ActivityProgressRecorder` (schreibt `activity_progress`)
und direkt danach durch `App\Achievements\AchievementUnlockEvaluator`, der
alle `achievement_definitions` mit einem `unlock_when`-Kriterium
(`docs/content-schema.md` Abschnitt 12) dagegen prüft und passende über
`AchievementService::unlock()` freischaltet. Kein Event/Listener-System
(das Projekt hat nirgends eines) — ein direkter Methodenaufruf am Ende der
Schreibkette.

- **`NodeController::submitFlag()`**: nach korrektem Flag ruft
  `ActivityProgressRecorder::record('node', $node->slug, $user)` den
  Evaluator auf. `first-blood` (`unlock_when: {type: first_solve,
  activity_type: node}`) und die vier node-gebundenen Achievements
  (`unlock_when: {type: activity_completed, activity_type: node, key:
  ...}`) entstehen daraus automatisch, ohne dass `NodeController` die
  Slugs kennt.
  - Response bekommt weiterhin `unlocked_achievements: [...]` (der
    Rückgabewert von `record()`) für die Unlock-Notification im Frontend.
- **`SandboxController::create()`**: `sandbox-starter` bleibt hartkodiert.
  Der Auslöser ("Spielwiese gestartet") ist kein Abschluss und passt
  deshalb nicht in `activity_progress`/`unlock_when` — siehe ADR 0077 für
  die Begründung.

## Achievement an eine Aktivität koppeln (`achievements.yml`)

Das frühere `node.yml`-Feld `achievements:` ist abgelöst. Ein Achievement,
das an ein bestimmtes Lernziel gebunden ist (z. B. C-ECHO, C-STORE, MWL),
deklariert stattdessen sein eigenes Auslösekriterium direkt in
`achievements.yml`:

```yaml
unlock_when:
  type: activity_completed
  activity_type: node
  key: silent-ct
```

`content:validate` prüft `unlock_when.type` gegen die bekannten Werte und
`key`/`track` gegen echte Nodes/Tracks (`docs/content-schema.md` Abschnitt
12 für alle Typen). Aktuelle Zuordnung:

| Achievement | Node | Warum |
|---|---|---|
| `association-accepted` | `verbindung-ohne-bild` | Szenario ist wörtlich "Association wird angenommen, Bild kommt nicht durch" |
| `echo-heard` | `silent-ct` | Der Lösungsweg beginnt laut Hint 1 explizit mit einem C-ECHO-Test |
| `store-and-forward` | `teiltransfer` | Ziel der Node ist ein vollständiger, erfolgreicher C-STORE-Transfer |
| `worklist-whisperer` | `worklist-query-empty` | MWL-/C-FIND-Node |

## Neues Achievement hinzufügen

1. PNG nach `apps/web/public/images/achievements/<slug>.png` ablegen
   (quadratisch, wird per `aspect-ratio: 1/1; object-fit: contain`
   dargestellt — keine Verzerrung nötig).
2. Eintrag in `content/achievements.yml` ergänzen (slug, name, description,
   image, category, rarity, points, is_hidden, sort_order, optional
   `unlock_when`/`scope` — siehe `docs/content-schema.md` Abschnitt 12).
3. `php artisan db:seed --class=AchievementSeeder` ausführen (idempotent,
   auch Teil von `DatabaseSeeder`).
4. Unlock-Kriterium ergänzen: entweder ein neuer `unlock()`-Aufruf an der
   passenden Stelle (Controller/Service), oder — falls es sich an ein
   Lernziel einer bestimmten Node koppeln lässt — `achievements: [slug]` in
   deren `node.yml` setzen, ganz ohne PHP-Code zu ändern.
5. Tests ergänzen (siehe unten).

## Frontend

- `resources/js/types/achievement.ts`: `Achievement`-Interface, exakt das,
  was `AchievementService::toArray()` liefert.
- `resources/js/components/achievements/AchievementBadge.vue`: die einzige
  Badge-Darstellung (keine Komponente pro Achievement). Locked-Zustand über
  CSS-Filter auf demselben Bild (`grayscale(1) brightness(.45) contrast(.8)`,
  `opacity:.45`) plus kleines Lock-Icon — kein zweites Asset nötig.
- `resources/js/components/achievements/AchievementCard.vue`: größere
  Darstellung fürs Profil-Grid.
- `resources/js/components/achievements/AchievementUnlockToast.vue` +
  `resources/js/lib/achievementToast.ts`: die dezente
  "Achievement freigeschaltet"-Notification, über das im Projekt bereits
  vorhandene `vue-sonner` (`toast.custom(...)`). Wird von `Nodes/Show.vue`
  (nach `submitFlag`) und `SandboxPanel.vue` (nach `start()`) aufgerufen,
  sobald die jeweilige JSON-Antwort ein nicht-leeres `unlocked_achievements`
  enthält. `GlobalLayout.vue` und `LessonLayout.vue` mounten dafür `<Toaster />`.
- Dashboard (`Dashboard.vue`) und öffentliches Profil (`Profiles/Show.vue`)
  zeigen je eine Sektion mit dem vollständigen Achievement-Grid
  (`AchievementService::listForUser()`) — die einzige Achievement-Anzeige
  seit der Pionier-Migration (ADR 0091).

## Tests

- `Tests\Unit\Services\AchievementServiceTest`: Kernverhalten von `unlock()`
  (neu, idempotent, unbekannter Slug, Metadaten, Race-Sicherheit) und
  `listForUser()`.
- `Tests\Feature\NodeControllerTest`: `first-blood` beim ersten gelösten
  Node, kein zweites Mal beim zweiten; node-gebundene Achievements über
  `unlock_when` in `achievements.yml`.
- `Tests\Unit\Achievements\AchievementUnlockEvaluatorTest`: alle
  `unlock_when`-Kriterientypen, inklusive `scope: global`
  (Race-Sicherheit über zwei Nutzer hinweg).
- `Tests\Feature\SandboxControllerTest`: `sandbox-starter` nur bei
  wirklich erzeugter Sandbox, nicht bei Kontingent-Fehler.
- `Tests\Feature\DashboardTest` / `PublicProfileControllerTest`: die
  `achievements`-Props sind vollständig und korrekt, das öffentliche Profil
  leakt nie das interne `metadata`-Feld eines Unlocks.
- `Tests\Feature\Content\ContentValidateTest`: `achievements:` in `node.yml`
  muss auf bekannte Slugs verweisen.
- Frontend (Vitest + `@vue/test-utils`, `apps/web/vitest.config.ts`,
  `npm run test`): `AchievementBadge.spec.ts` (unlocked/locked, Bild,
  Alt-Text, Größen), `Dashboard.spec.ts` (Achievements-Sektion mit 0 und
  mehreren Achievements).
