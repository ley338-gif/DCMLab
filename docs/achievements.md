# Achievement-System

Generisches, DB-gestütztes Achievement-/Badge-System, das dem bestehenden
Projektstil folgt: eine Registry-Klasse als einzige Quelle der Wahrheit, eine
Service-Schicht mit einer einzigen `unlock()`-Methode statt verstreuter
`if ($slug === ...)`-Blöcke, und synchrone Aufrufe direkt aus Controllern
(das Projekt hat an keiner Stelle Laravel-Events/-Listener, siehe
`AchievementService`-Klassendoc).

## Verhältnis zum älteren "First Blood"-Achievement

Bevor dieses System existierte, gab es bereits ein schmales
Achievement-Feature: `App\Models\Achievement` + `achievements`-Tabelle +
`ProfileService::maybeAwardFirstBlood()`, dokumentiert in
[ADR 0009](adr/0009-p8-punkte-raenge-profil-entscheidungen.md). Es vergibt
`type=first_blood` **einmal pro Node, global** — an den ersten Nutzer, der
eine neu veröffentlichte Node löst (`unique(node_id, type)`), nicht an jeden
Nutzer für seine eigene erste Lösung.

Das neue Achievement-System hat einen eigenen `first-blood`-Slug mit einer
anderen, persönlichen Bedeutung ("dein erstes gelöstes Lab/Node" — jeder
Nutzer kann ihn bekommen). Um die Namenskollision aufzulösen, ohne die
bestehenden Daten anzufassen:

- Die alte Tabelle, das alte Model und `ProfileService::maybeAwardFirstBlood()`
  bleiben **unverändert** bestehen — keine Migration, keine verlorene
  Historie, ADR 0009 bleibt gültig.
- Nur das Anzeige-Label wurde von "First Blood" zu **"Pionier"** geändert
  (`Dashboard.vue`s `pioneerAchievementLabels`, `Profiles/Show.vue`s
  Card-Titel). Backend-seitig heißen die zugehörigen Props bewusst
  `pioneer_achievements`, um Verwechslungen mit `achievements` (das neue
  System) zu vermeiden.
- Der neue, persönliche `first-blood`-Slug lebt vollständig in den unten
  beschriebenen neuen Tabellen.

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
- **`achievement_unlocks`**: eine Zeile je (Nutzer, Achievement), sobald
  freigeschaltet. `unique(user_id, achievement_definition_id)` verhindert
  Duplikate, auch bei gleichzeitigen Requests (derselbe
  Race-sicher-Ansatz wie `ProfileService::maybeAwardFirstBlood()`: erst
  `exists()`-Check, dann `create()` in einem `try/catch
  (UniqueConstraintViolationException)`).
- **`App\Services\AchievementService`**: die einzige öffentliche API.
  - `unlock(User $user, string $slug, array $metadata = []): AchievementUnlockResult`
    — idempotent, sicher mehrfach aufrufbar. `AchievementUnlockResult::status`
    ist eines von `AchievementUnlockStatus::{NotFound,AlreadyUnlocked,NewlyUnlocked}`.
  - `listForUser(User $user): Collection` — alle Definitionen (sortiert nach
    `sort_order`) mit `unlocked`/`unlocked_at` für diesen Nutzer, fertig fürs
    Frontend (Bildpfad bereits zu `/images/achievements/{image}` aufgelöst).

## Wo Achievements freigeschaltet werden

Keine Event/Listener-Architektur (das Projekt hat nirgends welche) — direkte
Service-Aufrufe aus Controllern:

- **`NodeController::submitFlag()`**: nach korrektem Flag,
  - `first-blood`, wenn dies der erste jemals von diesem Nutzer gelöste
    Node/Lab ist (`NodeAttempt::where('status','solved')->count() === 1`),
  - alle Slugs aus dem optionalen `achievements:`-Feld des gelösten
    `node.yml` (siehe unten).
  - Response bekommt `unlocked_achievements: [...]` (nur tatsächlich neu
    freigeschaltete) für die Unlock-Notification im Frontend.
- **`SandboxController::create()`**: `sandbox-starter`, aber nur wenn die
  Spielwiese wirklich erzeugt wurde (Orchestrator-Antwort ohne `error`-Feld),
  nicht beim bloßen Öffnen der Lektion.

## Achievement an einen Node koppeln (`node.yml`)

Die simulierte Engine kennt kein separates "C-ECHO erfolgreich"-Ereignis —
Lösen einer Node heißt "korrekte Flag eingereicht", nicht "Protokoll X
erfolgreich ausgeführt". Für Achievements, die an ein bestimmtes Lernziel
gebunden sind (z. B. C-ECHO, C-STORE, MWL), deklariert `node.yml` deshalb
optional:

```yaml
achievements: [echo-heard]
```

`content:validate` prüft, dass jeder genannte Slug in der Registry existiert.
Aktuelle Zuordnung:

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
2. Eintrag in `App\Achievements\AchievementRegistry::all()` ergänzen (slug,
   name, description, image, category, rarity, points, is_hidden,
   sort_order).
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
  (`AchievementService::listForUser()`), unabhängig von der bestehenden
  "Pionier"-Liste.

## Tests

- `Tests\Unit\Services\AchievementServiceTest`: Kernverhalten von `unlock()`
  (neu, idempotent, unbekannter Slug, Metadaten, Race-Sicherheit) und
  `listForUser()`.
- `Tests\Feature\NodeControllerTest`: `first-blood` beim ersten gelösten
  Node, kein zweites Mal beim zweiten; node-gebundene Achievements über
  `achievements:` in `node.yml`.
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
