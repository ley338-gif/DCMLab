# 0099 — DCMLab Studio: Grundgerüst und erste Ressourcenseite (CMS-3b)

## Status

Angenommen, 15.09.2026.

## Kontext

ADR 0098 (CMS-3a) fuehrte die `administrator`-Rolle und
`Gate::authorize('studio.access')` ein, liess den eigentlichen
`/studio`-Bereich aber offen. `docs/studio-architecture-plan.md`
Abschnitt 7 verlangt ein `StudioLayout.vue` mit gemeinsamer
Komponentenbibliothek (ResourceHeader, ResourceTable, StatusBadge,
SaveButton, PublishMenu, DangerZone, RevisionHistory, SortableList,
ArchiveDialog, DeleteDialog, EmptyState) -- diese ADR baut bewusst nur
das, was die ERSTE reale Studio-Seite tatsaechlich braucht, nicht die
volle Liste auf Vorrat (kein Code fuer hypothetische spaetere
Anforderungen): `StudioLayout`, eine Sidebar-Navigation und eine einzige,
neue Ressourcenseite. Weitere der genannten Komponenten entstehen erst,
wenn eine zweite Studio-Seite dieselbe Form tatsaechlich braucht.

Als erste Ressource wurde `sandbox_templates` (ADR 0096) gewaehlt: die
Tabelle existiert seit CMS-2, hatte aber **ueberhaupt keine
Oberflaeche** -- nur der einmalige `SandboxTemplateSeeder`. Damit ist
sie die einzige Ressource, die in Studio entsteht, ohne irgendetwas am
bestehenden Autoren-Panel zu verdraengen oder zu duplizieren
(Track/Lesson/Node haben dort bereits ihre etablierten, funktionierenden
Pfade -- deren Migration ist CMS-4/5/8, nicht Teil dieser ADR).

## Entscheidung

**Layout.** `StudioLayout.vue` komponiert dieselben generischen
Bausteine wie das bestehende `AppLayout.vue`
(`AppShell`/`AppContent`/`AppSidebarHeader`/`Toaster`), nur mit einer
neuen `StudioSidebar.vue` statt `AppSidebar.vue` -- keine Neuerfindung
der Shell-Mechanik. `app.ts`s Layout-Switch bekommt einen Fall fuer
`name.startsWith('Studio/')`. `StudioSidebar.vue` verlinkt zurueck auf
das Autoren-Panel (`/de/author`), damit beide Bereiche gegenseitig
auffindbar bleiben, solange die Migration ressourcenweise laeuft
(Studio-Architekturplan Abschnitt 7/8).

**Routen.** `de/studio` (Dashboard) und `de/studio/sandbox-templates`
(Liste, Anlegen, Bearbeiten/Freigeben) -- additiv neben `de/author/*`,
keine bestehende Route veraendert oder entfernt.

**Rechte.** `StudioController::index()` prueft `studio.access` (ADR
0098, wie AuthorPanelController). `StudioSandboxTemplateController`
nutzt die bereits bestehende `SandboxTemplatePolicy` (ADR 0096/0098)
unveraendert: `viewAny` fuer jede Nicht-Lernende-Rolle, `manage`
(Anlegen/Bearbeiten/Freigeben) fuer Reviewer/Administrator.

**Sichtbarkeit.** Ein "Studio"-Navigationspunkt erscheint in
`GlobalHeader.vue` und `AppSidebar.vue` unter derselben Bedingung wie
"Autoren-Panel" (`user.role !== 'learner'`) -- sonst waere `/studio` fuer
niemanden auffindbar gewesen.

## Konsequenzen

- Neue Dateien: `StudioController.php`, `StudioSandboxTemplateController.php`,
  `StudioLayout.vue`, `StudioSidebar.vue`, `Studio/Dashboard.vue`,
  `Studio/SandboxTemplates.vue`.
- Keine Migration, keine Aenderung an bestehenden `/author/*`-Routen
  oder -Seiten.
- **Bewusst nicht Teil dieser ADR**: `ResourceTable`/`SortableList`/
  `RevisionHistory`/`ArchiveDialog`/`DeleteDialog`/`DangerZone` als
  eigenstaendige, wiederverwendbare Komponenten -- die
  Sandbox-Vorlagen-Seite baut ihre Liste/Formulare direkt (wie
  `Author/Users.vue` es fuer die Nutzerverwaltung schon tut), weil es
  bisher nur einen einzigen Konsumenten gibt. Eine Verallgemeinerung
  folgt, sobald eine zweite Studio-Ressourcenseite dieselbe Form
  tatsaechlich braucht (docs/studio-architecture-plan.md Abschnitt 7).
- `SandboxController::create()`s Auswahl "erste freigegebene Vorlage"
  (ADR 0096) bleibt unveraendert -- mit dieser Seite koennen jetzt
  mehrere Vorlagen angelegt/freigegeben werden, was diese Vereinfachung
  sichtbarer macht, aber technisch unveraendert harmlos ist, solange
  nur ein Runtime-Provider ("docker") registriert ist.

## Verifikation

- Alle 451 Tests (14 neu: `StudioControllerTest`,
  `StudioSandboxTemplateControllerTest`), PHPStan Level 7 und
  `pint --test` sind gruen.
- `vue-tsc --noEmit`: keine neuen Fehler. `npm run check`
  (Formatierung + Lint) und `npm run build` erfolgreich.
