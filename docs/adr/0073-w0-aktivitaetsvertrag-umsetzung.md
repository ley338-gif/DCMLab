# 0073 — W0 Aktivitätsvertrag: Umsetzungsentscheidungen

## Status

Angenommen, 15.09.2026. Konkretisiert ADR 0072 für Arbeitsphase W0 aus
`dcm-lab-lms-agent-prompt.md`.

## Kontext

ADR 0072 beschreibt das Zielbild (ein Plugintyp, sechs Fähigkeiten,
`activities` + `activity_progress`), aber nicht die Reihenfolge, in der ein
laufendes System dorthin migriert. Beim Umsetzen von W0 sind drei Fragen
aufgetaucht, die ADR 0072 offen lässt und die vor dem nächsten Schritt
(dual-write in W-Zwischenschritt, dann Lesecutover in ProfileService/
AchievementService) festgehalten werden müssen, damit spätere Phasen auf
derselben Grundannahme aufbauen.

## Entscheidungen

**1. `activities` ist vorerst ein Verzeichnis, keine Ablösung von `lessons`/
`nodes`.** ADR 0072 sagt unter Konsequenzen: "`lessons`, `nodes` und die
Prüfungsdefinitionen werden zu `activities` mit `type`." Wörtlich umgesetzt
hieße das, beide Tabellen fallen zu lassen — das berührt jede
Route-Model-Bindung (`getRouteKeyName()`), über zwanzig Controller/Services
und den gesamten bestehenden Testbestand gleichzeitig, in einem einzigen
Schritt. Stattdessen bleibt `activities` in W0 ein zusätzlicher, von
`content:sync` mitgepflegter Index (`type` + `key` als fachlicher Schlüssel),
während `Lesson`/`Node`/`Track` unverändert die eigentlichen Fachdaten tragen.
Der W0-DoD (sechster Typ ohne Anfassen von ProfileService/AchievementService/
Dashboard, unveränderte Werte für Bestandsnutzer) ist damit vollständig
erfüllt, ohne das Migrationsrisiko einer physischen Tabellenzusammenführung
einzugehen. Die physische Konsolidierung bleibt eine offene Folgearbeit,
siehe unten.

**2. Quiz und Spielwiese bekommen keinen eigenen `activities`-Eintrag.** Beide
hängen heute an genau einer Lektion (`meta.yml`s `quiz:`-Block bzw.
`lessons.sandbox`) und sind keine eigenständig sortier- oder platzierbare
Einheit. `QuizActivity`/`SandboxActivity` implementieren den vollen
`ActivityContract`, werden aber direkt aus einer `Lesson` konstruiert
(`new QuizActivity($lesson, ...)`), nicht über `ActivityRegistry::resolve()`.
Nur `lesson`, `node` und `exam` sind registry-indiziert. Wird die Spielwiese
später (siehe ADR 0072, "bewusst offen gelassen") tatsächlich frei
platzierbar, braucht sie eine eigene Content-Struktur — erst dann bekommt sie
einen echten `activities`-Eintrag.

**3. `validate()`, `serialize()`, `deserialize()` sind in W0 bewusst dünn.**
`validate()` liefert für alle Typen `[]`: das 1022-Zeilen-Regelwerk aus
`ContentValidate` wandert laut ADR 0071 in W1 in einen wiederverwendbaren
Service, den diese Methode dann aufruft. Eine eigene Prüfung vorher wäre
genau die "zweite, schwächere Prüfung", die ADR 0071 verbietet. `serialize()`
gibt für Lektion/Node/Prüfung die heute unverändert auf der Platte stehenden
Dateien zurück (Identität, keine Generierung) — `ContentWriter` in W2 macht
daraus echte Erzeugung. `deserialize()` normalisiert den Ist-Zustand bereits
in die flache Form, die ein künftiger `content_drafts`-Datensatz (W3) haben
wird. Für Quiz/Spielwiese liefert `serialize()` eine leere Liste (kein
eigenes Dateiziel).

## Konsequenzen

**Was W0 liefert:** `App\Activities\ActivityContract` mit allen sechs
Fähigkeiten aus ADR 0072, `ActivitySupports`/`ActivityResult` als
Werttypen, `ActivityRegistry` als Auflösungsschicht (Vorbild:
`EngineClientResolver`), die Tabellen `activities` (Verzeichnis) und
`activity_progress` (noch ungenutzt, Ziel-Schema für die nächste Phase),
konkrete Verträge für alle fünf heutigen Typen, und `content:sync` pflegt
`activities` automatisch mit.

**Was W0 nicht liefert (und warum das kein Rückschritt ist):**
`ProfileService`/`AchievementService` lesen weiterhin aus `node_attempts`/
`track_badges`/`lesson_progress` — der Lesecutover auf `activity_progress`
als einzige Quelle ist ein eigener, noch zu planender Schritt mit
Datenmigration für Bestandsnutzer (die "Ergebnis unverändert"-Prüfung aus dem
W0-DoD ist dafür bereits als Test vorbereitet, siehe
`ActivityRegistryTest::test_a_sixth_activity_type_can_be_registered_displayed_completed_and_scored`,
die zeigt, dass `activity_progress` allein für Anzeige/Abschluss/Bewertung
ausreicht). Die physische Zusammenführung von `lessons`/`nodes`/
Prüfungsdefinitionen in `activities` (Entscheidung 1) ist ebenfalls
zurückgestellt.

## Verifikation

- `ActivityRegistryTest` beweist den W0-DoD: ein nur im Test existierender
  sechster Typ lässt sich registrieren, anzeigen, abschließen und bewerten,
  ohne `ProfileService`, `AchievementService` oder einen Controller
  anzufassen.
- `content:sync` bleibt idempotent und erzeugt zusätzlich zu Tracks/
  Lektionen/Nodes einen `activities`-Eintrag je Lektion, Node und
  Track-Prüfung (`ContentSyncTest`).
- Alle 242 bestehenden und neuen Tests, `phpstan analyse` (Level 7) und
  `pint --test` sind grün; kein bestehender Controller, Service oder
  Content-Pfad wurde verändert.
