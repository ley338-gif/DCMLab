# 0098 — Administrator-Rolle, Policies vereinheitlicht (CMS-3a)

## Status

Angenommen, 15.09.2026.

## Kontext

ADR 0094 legt Phase CMS-3 ("Studio Foundation") fest:
`administrator`-Rolle, `/studio`, `StudioLayout`, gemeinsame
Resource-Komponenten, Policies vereinheitlichen. Der CMS-0-Audit fand
zwei konkrete Baustellen: es gibt keine vierte Rolle (`UserRole` kennt
nur `Learner`/`Author`/`Reviewer`, `role` ist eine reine `string`-Spalte
ohne DB-Constraint -- eine Erweiterung braucht keine Migration), und
`AuthorPanelController::index()` prueft die Rolle inline
(`abort_if($user->role === UserRole::Learner, 403)`) statt ueber eine
Policy, im Unterschied zu jedem anderen Controller im Autoren-Bereich.

Wie CMS-2 ist auch CMS-3 zu gross fuer eine Aenderung: `/studio` und die
Studio-Komponentenbibliothek (`StudioLayout`, `ResourceTable`,
`StatusBadge`, ...) sind ein eigener, groesserer Frontend-Umbau und
folgen separat (**CMS-3b**). Diese ADR deckt den risikoarmen
Backend-Teil ab (**CMS-3a**): die Rolle selbst und die
Policy-Vereinheitlichung.

## Entscheidung

`UserRole` bekommt einen vierten Fall, `Administrator`. Bewusst
**additiv zu Reviewer, nicht ersetzend**: jede Policy, die heute
`$user->role === UserRole::Reviewer` prueft (`UserPolicy`,
`ActivityPolicy`, `SandboxTemplatePolicy`), prueft jetzt
`in_array($user->role, [Reviewer, Administrator], true)`. Grund: es
existiert noch kein einziges Administrator-Konto in einer echten Umgebung
-- eine Verengung auf "nur Administrator darf X" haette den heute
einzigen Betreiber (Reviewer) sofort von Nutzerverwaltung, Freigaben und
Sandbox-Vorlagen ausgesperrt. Der Studio-Auftrag (Abschnitt 18) beschreibt
zwar eine sauberere Trennung (Reviewer nur Review/Publish, Administrator
Nutzer/Vorlagen/Einstellungen) -- diese Verengung ist eine bewusst spaeter
zu treffende, riskantere Entscheidung, kein CMS-3a-Umfang (siehe
`docs/offene-fragen.md`).

`AuthorUserController::updateRole()`s Validierung akzeptiert jetzt
zusaetzlich `administrator`.

`AuthorPanelController::index()`s `abort_if(...)` wird durch
`Gate::authorize('studio.access')` ersetzt -- derselbe Regelinhalt
(jede Rolle ausser Learner), aber als benannter Gate statt Rollen-If im
Controller (`AppServiceProvider::configureGates()`). Ein benannter Gate
statt einer Modell-Policy, weil es keine einzelne Eloquent-Ressource
gibt, die "der Autoren-/Studio-Bereich als Ganzes" waere -- analog zu
`ReviewQueueController`, das dafuer bereits `UserPolicy::viewAny`
wiederverwendet, statt eine neue Policy-Klasse fuer eine
Nicht-Modell-Ressource zu erzwingen.

## Konsequenzen

- Keine Migration: `users.role` ist eine reine `string`-Spalte ohne
  DB-Constraint (bestaetigt in `add_role_to_users_table.php`).
- `resources/js/types/auth.ts`, `Author/Users.vue`s Rollen-Dropdown
  kennen jetzt `administrator`.
- **Noch offen (separat, nicht Teil dieser ADR)**: `/studio`-Praefix,
  `StudioLayout.vue`, gemeinsame Resource-Komponenten (CMS-3b);
  Entscheidung, ob/wann Reviewer-Faehigkeiten tatsaechlich exklusiv auf
  Administrator verengt werden, sobald echte Administrator-Konten
  provisioniert sind (Betreiberentscheidung, `docs/offene-fragen.md`).

## Verifikation

- Alle 437 Tests (10 neu: `UserPolicyTest` neu, je ein Administrator-Fall
  in `ActivityPolicyTest`/`SandboxTemplatePolicyTest`/
  `AuthorPanelControllerTest`/`AuthorUserControllerTest`), PHPStan
  Level 7 und `pint --test` sind gruen.
- `vue-tsc --noEmit`: keine neuen Fehler (der eine bestehende,
  unabhaengige `Result.vue`-Fehler bleibt unveraendert). `npm run build`
  erfolgreich.
