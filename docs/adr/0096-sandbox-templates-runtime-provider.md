# 0096 — SandboxTemplate-Katalog und RuntimeProviderContract (CMS-2a)

## Status

Angenommen, 15.09.2026.

## Kontext

ADR 0094 legt Phase CMS-2 ("Sandbox Architecture") fest: ein
Drei-Ebenen-Modell (`SandboxTemplate`/`SandboxActivity`/`SandboxSession`)
hinter einer `RuntimeProviderContract`-Abstraktion, ohne die bereits
starken Container-Sicherheitsmassnahmen in `services/sandbox` neu zu
bauen (siehe `docs/studio-architecture-plan.md` Abschnitt 1.3/6). Der
CMS-0-Audit fand: `SandboxController` liest `lessons.sandbox.dataset`
direkt und ruft `SandboxClientContract` unmittelbar auf -- keine
Vorlagen-Ebene, keine eigene `activities`-Zeile fuer Sandbox, keine
Trennung zwischen "welche Laufzeitumgebung" und "welcher Datensatz".

Die vollstaendige CMS-2-Liste (SandboxTemplate, SandboxActivity als
echte Activity, SandboxSession, RuntimeProviderContract,
RuntimeProviderRegistry, RuntimeManager) ist zu gross fuer eine einzelne,
gut pruefbare Aenderung. Diese ADR deckt den ersten, risikoarmen Teil ab
(**CMS-2a**): den Vorlagenkatalog und die Runtime-Provider-Abstraktion.
Der zweite Teil (**CMS-2b**: `SandboxActivity` mit eigener
`activities`-Zeile, `SandboxSession` als durable Sitzungsaufzeichnung,
`RuntimeManager`) folgt separat.

## Entscheidung

- Neue Tabelle `sandbox_templates` (`slug, name, description,
  runtime_provider, status(draft|published), created_by, updated_by`)
  -- der Katalog freigegebener Laufzeitumgebungen. Bewusst schlank: keine
  Spalten fuer Ressourcenlimits/Netzwerkpolitik, solange
  `services/sandbox` diese nicht tatsaechlich pro Vorlage variieren kann
  (heute exakt ein Containerpaar-Typ, siehe `docker_ops.py`). Genau eine
  Zeile wird geseedet (`dicom-basic-tools`), die die heutige, einzige
  Laufzeitumgebung repraesentiert.
- `SandboxTemplatePolicy`: `manage()` (anlegen/freigeben) nur fuer
  `Reviewer` -- Platzhalter fuer `administrator`, bis ADR 0094/CMS-3 die
  vierte Rolle einfuehrt, analog zu `UserPolicy`s bestehendem Muster.
  `viewAny()` fuer jeden Autor/Reviewer.
- `RuntimeProviderContract` (create/state/exec/delete) mit
  `DockerRuntimeProvider` als einziger Implementierung -- reine
  Weiterleitung an das bestehende `SandboxClientContract`, keine
  Verhaltensaenderung am eigentlichen Docker-Kontakt.
  `RuntimeProviderRegistry::for(string $runtimeProvider)` loest per
  `match` auf, nach demselben Muster wie `EngineClientResolver`.
- `SandboxController::create()` prueft jetzt zusaetzlich, dass eine
  freigegebene `SandboxTemplate` existiert, und ruft ueber die Registry
  statt direkt `SandboxClientContract` auf. `state()`/`exec()`/`destroy()`
  loesen (noch) fest `"docker"` auf -- ohne `SandboxSession`-Datensatz
  gibt es noch keine Zuordnung "welcher Provider hat diese sandbox_id
  erzeugt". Das ist eine bekannte, dokumentierte Zwischenluecke, keine
  versehentliche.

## Konsequenzen

- `SandboxControllerTest` seedet jetzt eine freigegebene Vorlage in
  `setUp()`; ein neuer Test deckt die Ablehnung ohne freigegebene
  Vorlage ab.
- **Noch offen (CMS-2b, separat)**: `SandboxActivity` bekommt weiterhin
  keine eigene `activities`-Zeile und ist weiterhin nicht bei
  `ActivityRegistry` registriert -- die in
  `docs/studio-architecture-plan.md` Abschnitt 2.2 dokumentierte
  Lesson-Kopplung besteht fort. `SandboxSession` (durable
  Sitzungsaufzeichnung) existiert noch nicht; `state`/`exec`/`destroy`
  loesen weiterhin hart auf `"docker"` auf, statt provider-agnostisch zu
  sein. Diese Luecke wird in `docs/offene-fragen.md` nachverfolgt.
- Keine Aenderung an `services/sandbox`/`docker_ops.py` -- alle
  bestehenden Sicherheitsmassnahmen (Resource-Limits, Netzwerk-Isolation,
  Kontingente) bleiben unangetastet.

## Verifikation

- Alle 422 Tests (8 neu: `RuntimeProviderRegistryTest`,
  `SandboxTemplatePolicyTest`, ein neuer `SandboxControllerTest`-Fall),
  PHPStan Level 7 und `pint --test` sind gruen.
- Keine Vue-/Frontend-Aenderung.
