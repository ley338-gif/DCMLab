# 0077 — W4: Deklarative Achievements

## Status

Angenommen, 15.09.2026. Arbeitsphase W4 aus `dcm-lab-lms-agent-prompt.md`,
im Anschluss an ADR 0073 (W0), die ContentValidator-Extraktion (W1), ADR
0074 (W2), ADR 0075 (W3) und ADR 0076 (activity_progress-Schreibziel).

## Kontext

W4 verlangt zwei Dinge: "Zuerst ADR 0070 einlösen: eine Mechanik statt
drei, mit Datenmigration", dann ein deklaratives Auslösekriterium in
`achievements.yml`, ausgewertet gegen `activity_progress`, das die beiden
hartkodierten Vergaben (`first-blood` in `NodeController`, `sandbox-starter`
in `SandboxController`) ablöst.

ADR 0070 hatte eine Schema-Vereinheitlichung von `achievements`
(global-pro-Node) und `track_badges` (persönlich-pro-Track) explizit
geprüft und verworfen — nicht aus Bequemlichkeit, sondern weil beide
Tabellen UI-seitig bewusst **ohne** Badge-Bildsprache leben ("Pionier",
schlichte Liste mit Name/Datum) und getrennt von der bild-/rarity-basierten
`achievement_definitions`/`achievement_unlocks`-Kartengalerie ("Achievements")
gehalten werden. Eine echte Migration ihrer Daten in die Karten-Tabelle
würde für jede migrierte Zeile ein Bild-Asset brauchen, das es nie gab und
das ein Code-Agent nicht sinnvoll selbst gestalten kann (public/images/
achievements/ enthält exakt sechs Bilder für exakt sechs bestehende
Definitionen).

## Entscheidungen

**1. "Eine Mechanik statt drei" wird auf Ebene des Auslöse-Mechanismus
eingelöst, nicht durch physische Datenmigration der Pionier-Tabellen.**
`achievement_definitions`/`achievement_unlocks` bekommen `scope`
(personal | global) und `unlock_when` als das EINE deklarative
Vergabesystem, das ADR 0070 nicht kannte. `achievements` (global first_blood)
und `track_badges` bleiben als eigenständiges, bild-freies "Pionier"-Lesemodell
bestehen, exakt wie ADR 0070 es begründet hat — diese Begründung ist durch
W4 nicht entkräftet worden, nur die Prämisse "keine Datenmigration lohnt
sich" ist für den NEUEN Anwendungsfall (deklarative Vergabe fuer
Karten-Achievements) überholt. Festgehalten als eigener Punkt in
`docs/offene-fragen.md`: eine echte Migration der Pionier-Daten bleibt
möglich, sobald jemand die fehlende Bildsprache dafür entworfen hat — das
ist eine Design-, keine Code-Entscheidung.

**2. Schema-Design ohne partielle Indizes.** `activity_id` (nullable) auf
`achievement_unlocks` plus `unique(achievement_definition_id, activity_id)`
reicht aus, weil SQL NULL nie als gleich zu NULL behandelt: persönliche
Achievements (jede Aktivität kriegt ihre eigene Definition, z. B. eine je
Track — siehe `unlock_when`-Beispiel im Auftrag) setzen `activity_id` nie
und bleiben unberührt von diesem Constraint; global-scoped Achievements
setzen ihn immer und werden dadurch race-sicher auf einen Gewinner je
Aktivität begrenzt, unabhängig vom Nutzer. Kein treibergebundenes SQL, kein
Kommentar-pflichtiger Sonderfall, den ADR 0070 zu Recht ablehnte.

**3. `sandbox-starter` bleibt hartkodiert.** Sein Auslöser ist "Spielwiese
gestartet", kein Abschluss — `activity_progress`/`unlock_when` modellieren
ausschließlich Abschlüsse (ADR 0072: "Abschluss, Punktzahl, ..."). Ein
`unlock_when: {type: activity_started}` würde eine neue Kategorie von
Ereignis erfinden, die `ActivitySupports`/`ActivityResult` heute nicht
kennt — das ist eine Erweiterung des Aktivitätsvertrags selbst, nicht
dieser Phase.

**4. `node.yml`s `achievements:`-Feld wird abgelöst und aus dem Bestand
entfernt.** Die vier real genutzten Zuordnungen (`echo-heard` →
`silent-ct`, `store-and-forward` → `teiltransfer`, `association-accepted`
→ `verbindung-ohne-bild`, `worklist-whisperer` → `worklist-query-empty`)
wandern als `unlock_when: {type: activity_completed, activity_type: node,
key: ...}` nach `achievements.yml` — dieselben sechs Bild-Assets, nur die
Vergabe-Logik wird deklarativ statt in `NodeController::
unlockNodeAchievements()` (jetzt entfernt). `content:validate` prüft
`unlock_when.type` gegen die bekannten Werte und `key`/`track` gegen echte
Nodes/Tracks.

## Konsequenzen

**Was W4 liefert:** `App\Achievements\AchievementUnlockEvaluator`, aufgerufen
von `ActivityProgressRecorder` direkt nach jedem `activity_progress`-Schreiben;
`AchievementService::unlock()` unterstützt `scope: global` über einen
optionalen `Activity`-Parameter; `NodeController::unlockNodeAchievements()`
ist vollständig entfernt, `NodeController::submitFlag()` kennt keine
Achievement-Slugs mehr. Ein Achievement, das nur als `unlock_when`-Eintrag
in `achievements.yml` entsteht, wird beim Abschluss der zugeordneten
Aktivität automatisch vergeben.

**Was W4 nicht liefert:** eine physische Migration von `achievements`/
`track_badges` in `achievement_unlocks` (Entscheidung 1); ein deklaratives
Kriterium für `sandbox-starter` (Entscheidung 3). Beide sind bewusste,
begründete Deferrals, keine übersehenen Lücken.

## Verifikation

- `AchievementUnlockEvaluatorTest` beweist alle drei Kriterientypen sowie
  die Race-Sicherheit von `scope: global` über zwei Nutzer und zwei
  Aktivitäten hinweg.
- `NodeControllerTest::test_correct_flag_unlocks_the_personal_first_blood_achievement_once`
  beweist den Fluss end-to-end über den echten HTTP-Endpunkt, inklusive
  eines zweiten, eigens deklarierten Achievements für einen zweiten Node.
- `php artisan content:validate` gegen den echten Bestand: 0 Verstöße
  (42 Lektionen, 17 Nodes, 23 Werkzeuge, 52 Glossarbegriffe, 5 Prüfungen).
- `php artisan content:build` gegen den echten Bestand bleibt idempotent
  (0 neue Hashes) — die `achievements:`-Entfernung berührt die Flag-Hash-
  Erzeugung nicht.
- Alle 288 Tests, `phpstan analyse` (Level 7), `pint --test` und
  `npm run check` sind grün.
