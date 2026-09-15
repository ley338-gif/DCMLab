# 0097 — SandboxActivity als echte Activity, SandboxSession (CMS-2b)

## Status

Angenommen, 15.09.2026.

## Kontext

ADR 0096 (CMS-2a) fuehrte den `SandboxTemplate`-Katalog und
`RuntimeProviderContract`/`RuntimeProviderRegistry` ein, liess aber zwei
Punkte aus ADR 0094s CMS-2-Liste offen: `SandboxActivity` hatte weiterhin
keine eigene `activities`-Zeile und war nicht bei `ActivityRegistry`
registriert (anders als Lesson/Node/Exam/Achievement); `state()`/`exec()`/
`destroy()` lösten den Runtime-Provider weiterhin fest auf `"docker"` auf,
mangels einer Moeglichkeit, eine `sandbox_id` auf ihre urspruengliche
Vorlage zurueckzufuehren (dokumentiert in `docs/offene-fragen.md`).

## Entscheidung

**SandboxActivity als echte Activity.** `ContentSync::syncLessons()`
legt jetzt zusaetzlich einen `activities`-Eintrag (`type=sandbox,
key=<lesson_id>`) an -- nur fuer Lektionen, die tatsaechlich
`sandbox.dataset` gesetzt haben (keine 1:1-Entsprechung wie bei Lesson/
Node/Exam). `ActivityRegistry` registriert `ActivityType::Sandbox` analog
zu den anderen Typen (`AppServiceProvider`). `SandboxActivity` bleibt
weiterhin `versionable: false` (ADR 0095) -- die eigentliche Konfiguration
haengt unveraendert am `content_versions`-Datensatz der Lektion; diese
Aenderung gibt ihr nur eine Identitaet fuer Autorenzuordnung
(`activity_authors`) und kuenftige Platzierung (ADR 0072/CMS-6). Entfernt
eine spaetere Bearbeitung die Spielwiese aus einer Lektion, bleibt ihr
`activities`-Eintrag bestehen (`content:sync` loescht nie, bekanntes,
bereits dokumentiertes Verhalten).

**`sandbox_sessions`-Tabelle.** Eine durable Aufzeichnung jeder
Spielwiesen-Sitzung (`user_id, activity_id, sandbox_template_id,
runtime_provider, runtime_instance_id, status, started_at,
last_activity_at, expires_at, finished_at, result`) -- der eigentliche
Laufzeitzustand bleibt in `services/sandbox` (Redis/Docker), diese Tabelle
ist Historie/Audit, keine zweite Wahrheit. `activity_id` ist nullable: eine
Sitzung darf auch entstehen, wenn `content:sync` fuer die Lektion noch
keinen `type=sandbox`-Eintrag angelegt hat.

`SandboxController::create()` schreibt jetzt eine Sitzungszeile nach
erfolgreicher Erzeugung (nicht bei `error`/Kontingent-Ueberschreitung).
`state()`/`exec()`/`destroy()` schlagen `runtime_provider` ueber
`runtime_instance_id` nach (`runtimeProviderFor()`), mit `"docker"` nur
noch als Fallback fuer unbekannte/altere `sandbox_id`s. `exec()`
aktualisiert `last_activity_at`, `destroy()` setzt `status=destroyed` und
`finished_at`.

## Konsequenzen

- `ActivityRegistry::registeredTypes()` liefert jetzt `lesson, node, exam,
  achievement, sandbox` (vorher ohne `sandbox`).
- Neue Migration `sandbox_sessions` (FKs `nullOnDelete`/`cascadeOnDelete`
  je nach Feld, siehe Migrationskommentar).
- Kein Verhalten in `services/sandbox`/`docker_ops.py` geaendert --
  weiterhin die alleinige Quelle fuer tatsaechlichen Container-Zustand.
- Das in ADR 0096 dokumentierte Offene ("state/exec/destroy loesen fest
  auf docker auf") ist damit geschlossen, siehe `docs/offene-fragen.md`.
- Weiterhin nicht Teil dieser ADR: `RuntimeManager` (schrittweise
  Ablösung von `SandboxController` selbst) -- laut
  `docs/studio-architecture-plan.md` erst relevant, sobald ein zweiter
  Runtime-Provider tatsaechlich ansteht; `SandboxController` ist bereits
  duenn genug, dass eine vorzeitige `RuntimeManager`-Zwischenschicht ohne
  zweiten Provider nur zusaetzliche Indirektion waere.

## Verifikation

- Alle 429 Tests (7 neu/aktualisiert: `ContentSyncTest` — Sandbox-Activity
  nur bei vorhandener Spielwiese, `ActivityRegistryTest`,
  `SandboxActivityTest` — Registry-Roundtrip, vier neue
  `SandboxControllerTest`-Faelle fuer Sitzungserzeugung/-abschluss/
  Provider-Aufloesung), PHPStan Level 7 und `pint --test` sind gruen.
- Keine Vue-/Frontend-Aenderung.
