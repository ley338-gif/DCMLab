# 0092 — Autoren-Panel: Einstiegspunkt, Review-Queue, Nutzerverwaltung

## Status

Angenommen, 15.09.2026.

## Kontext

Author und Reviewer sind seit W3 (ADR 0071) echte Rollen mit echten
Policies (`ActivityPolicy`), und seit W6 gibt es vier Editoren
(Lektion/Quiz/Pruefung/Achievement). Es gab aber **keine einzige Seite**,
von der aus diese Rollen ihre Werkzeuge finden konnten -- jeder Editor ist
nur ueber eine URL mit fester Ressourcen-ID erreichbar
(`/de/author/lessons/{lesson}/edit`), nirgends im UI verlinkt. Ein
Betreiber, der einem Nutzer die Reviewer-Rolle gibt, landet beim Versuch,
irgendetwas davon zu finden, in lauter 404ern.

Ebenso fehlten zwei Dinge, die "wie in einem normalen LMS" fuer diese
Rollen erwartbar sind:

- Eine **uebergreifende Sicht auf alles, was zur Pruefung eingereicht
  wurde** -- `content_versions` ist zwar bereits generisch ueber alle
  Aktivitaetstypen (ADR 0071, W3), aber jeder Editor liest bisher nur die
  eigene, einzelne Aktivitaet aus. Ein Reviewer musste jede URL einzeln
  kennen.
- Eine **Nutzerverwaltung im UI** -- Rollen vergeben und Autor:innen
  einzelnen Aktivitaeten zuordnen (`activity_authors`, ADR 0071) ging bis
  jetzt nur direkt in der Datenbank.

Eine vierte, groessere Anforderung -- neue Lektionen/Nodes/Pruefungen
komplett von Grund auf im UI anlegen (nicht nur bearbeiten) -- ist bewusst
**nicht** Teil dieser Entscheidung: es gibt dafuer noch keinerlei
Infrastruktur (kein Weg, eine neue `Activity`-Zeile samt
`content/`-Dateigeruest ohne Kommandozeile zu erzeugen), und das Design
dafuer (Id-Vergabe, Mindestfelder je Typ, Dateigeruest) ist eine eigene,
groessere Entscheidung. Einzige Ausnahme: **Achievements**, weil deren
"neu anlegen" bereits seit ADR 0083 funktioniert (ein Slug, der noch nicht
in `achievements.yml` steht, startet den bestehenden Editor mit leeren
Feldern) -- das Panel verlinkt das nur, baut nichts Neues.

## Entscheidung

**Drei neue Seiten unter `/de/author`, kein neuer Rollen-Typ.** Es gibt
weiterhin nur `learner`/`author`/`reviewer` (kein "Admin") -- Reviewer
bleibt die hoechste bestehende Stufe und uebernimmt Nutzerverwaltung,
analog dazu, dass `ActivityPolicy::publish()` schon jede Aktivitaet einem
Reviewer erlaubt.

1. **`/de/author` (Panel-Hub)**: zeigt die eigenen zugewiesenen
   Aktivitaeten (Author), die Review-Queue-Anzahl und den Zugang zur
   Nutzerverwaltung (Reviewer), sowie eine "Neue Achievement anlegen"-Karte
   (Slug eingeben, oeffnet den bestehenden Achievement-Editor). Listet
   bewusst **nicht** jeden bestehenden Inhalt durch -- das waere eine
   zweite, staendig nachzuziehende Quelle fuer "was gibt es", waehrend die
   jeweilige Lernansicht das schon zeigt.
2. **`/de/author/review-queue` (Reviewer-only)**: `ContentVersion::where
   ('status', 'review')`, dedupliziert auf die juengste Version je
   Aktivitaet (`unique('activity_id')` nach `latest()`), mit einem
   typspezifischen Sprung zum jeweiligen Editor
   (`ReviewQueueController::editUrl()`) und dem bestehenden, generischen
   `ContentVersionController::publish()` als einzigem Freigabeweg -- kein
   zweiter.
3. **`/de/author/users` (Reviewer-only)**: Rollen per Dropdown aendern
   (`AuthorUserController::updateRole()`) und Autor:innen einzelnen
   Aktivitaeten zuordnen/entziehen ueber `activity_authors`
   (`assignActivity()`/`removeActivity()`, `syncWithoutDetaching()` fuer
   Idempotenz).

**Neue `UserPolicy`** (`viewAny`/`update`, beide `role === Reviewer`) statt
Ad-hoc-Rollen-Ifs in den Controllern -- folgt demselben "Policies statt
Rollen-Ifs"-Prinzip wie `ActivityPolicy`.

**Nav-Link in beiden bestehenden Shells** (`GlobalHeader.vue` fuer
Dashboard/Tracks/etc., `AppSidebar.vue` fuer die Autoren-Editoren selbst
und Settings-Seiten -- die Layout-Wahl in `app.ts` ist eine feste
Seiten-Namens-Liste, kein automatischer Fallback), sichtbar nur wenn
`auth.user.role !== 'learner'`.

**Entdeckter, unabhaengiger Befund (nicht Teil dieser Aenderung):** beim
Testen der Review-Queue gegen den echten Stack scheiterte "Freigeben" mit
einem 500er, weil `content/` in `infra/docker-compose.yml` fuer `app`
`:ro` gemountet ist -- betrifft jeden Editor gleichermassen, nicht nur das
neue Panel. Als offene Frage festgehalten (`docs/offene-fragen.md`), nicht
hier mitgeloest, da eine Entscheidung ueber den Deploy-/Dev-Mount-Pfad
noetig ist.

## Konsequenzen

- `app/Policies/UserPolicy.php` (neu).
- `app/Http/Controllers/AuthorPanelController.php`,
  `ReviewQueueController.php`, `AuthorUserController.php` (neu).
- `resources/js/pages/Author/{Panel,ReviewQueue,Users}.vue` (neu).
- `resources/js/components/GlobalHeader.vue`,
  `resources/js/components/AppSidebar.vue`: bedingter "Autoren-Panel"-Link.
- `resources/js/types/auth.ts`: `User.role` jetzt explizit typisiert statt
  nur ueber die `[key: string]: unknown`-Indexsignatur erreichbar.
- Routen `author.index`, `author.review-queue.index`, `author.users.*`
  unter derselben `auth`/`verified`-Middleware-Gruppe wie die bestehenden
  Autoren-Routen; Autorisierung bleibt Controller-seitig (Policy), nicht
  Route-Middleware, demselben Muster folgend.
- **Bewusst nicht Teil dieser Aenderung:** neue Inhalte (Lektion/Node/
  Pruefung) von Grund auf im UI anlegen -- fehlende Infrastruktur, eigene
  groessere Entscheidung; der read-only `content/`-Mount im lokalen
  Dev-Setup, der jedes "Freigeben" verhindert -- als offene Frage
  festgehalten.

## Verifikation

- `AuthorPanelControllerTest`: Gast wird umgeleitet, Lernende:r bekommt
  403, Author sieht nur die eigenen zugewiesenen Aktivitaeten ohne
  Review-Queue-Zahl, Reviewer sieht die Review-Queue-Zahl.
- `ReviewQueueControllerTest`: Lernende:r/Author bekommen 403; leere Queue
  ohne Eintraege; Eintraege ueber Lektion und Achievement hinweg mit
  korrektem Editor-Link (Achievement nutzt `payload.slug`, nicht den
  Aktivitaetsschluessel `catalog`); nur die juengste Version je Aktivitaet
  erscheint; ein nie eingereichter Entwurf (`status: draft`) erscheint
  nicht.
- `AuthorUserControllerTest`: Lernende:r/Author bekommen 403 auf jede
  Nutzerverwaltungs-Route; Reviewer sieht alle Nutzer mit Rolle und
  zugewiesenen Aktivitaeten; Rollenwechsel funktioniert und wird
  validiert (unbekannte Rolle abgelehnt); Zuweisen/Entfernen einer
  Aktivitaet funktioniert, doppeltes Zuweisen dupliziert die Pivot-Zeile
  nicht.
- Manueller Test gegen den echten, laufenden Stack: Rollenwechsel im UI
  live nachvollzogen (Dropdown-Aenderung schaltet sofort die
  "Inhalte zuweisen"-Aktion frei), Aktivitaets-Zuweisung mit allen 65
  echten Aktivitaeten aus dem realen Bestand im Dropdown bestueckt,
  Review-Queue zeigt eine echte eingereichte Version korrekt an -- dabei
  den oben beschriebenen, unabhaengigen read-only-Mount-Befund gefunden.
- Alle 412 Tests, `phpstan analyse` (Level 7), `pint --test`,
  `npm run check`, `vue-tsc --noEmit`, `vitest run`, `npm run build` und
  `content:validate` sind gruen.
