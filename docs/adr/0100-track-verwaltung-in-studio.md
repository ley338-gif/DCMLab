# 0100 — Track-Verwaltung in Studio (CMS-4)

## Status

Angenommen, 15.09.2026.

## Kontext

ADR 0094 legt Phase CMS-4 ("Track Management") fest: komplettes
Track-CRUD (create/edit/reorder/duplicate/archive/restore/delete/publish)
in Studio. Der CMS-0-Audit hatte bereits festgehalten: Track ist der
einzige DB-gefuehrte Inhaltstyp ohne echten Textspeicher --
`title_key` verweist nur auf einen Schluessel in `lang/de.json`
(`resources/js/lib/trans.ts` loest ihn client-seitig auf). Ein
Studio-Formular kann diese Datei nicht mitschreiben; ein neu angelegter
Track braucht deshalb einen echten, in der DB gespeicherten Titel --
genau das Muster, das Lesson/Node/Exam/Achievement bereits mit ihrer
`title`/`teaser`-JSON-Spalte haben.

Frueher in diesem Ausbau hatte der Betreiber bereits entschieden, fuer
Track/Lesson vorerst nur "Archivieren", kein "Loeschen" zu bauen (harter
Loeschpfad wuerde ueber `lessons.track_id` kaskadierend echten
Lernfortschritt mitreissen). Diese ADR setzt genau das um.

## Entscheidung

**`title`/`teaser` additiv.** Neue, nullable `title`/`teaser`-JSON-
Spalten auf `tracks` (Migration
`2026_09_29_000001_add_title_and_teaser_to_tracks_table.php`). Ein per
`content:sync` verwalteter Track hat sie weiterhin nicht gesetzt;
`TrackController` faellt clientseitig auf `title_key` zurueck
(`track.title?.de ?? trans(track.title_key)`), Vue-seitig in
`Tracks/Index.vue`/`Tracks/Show.vue` -- **keine bestehende Anzeige
aendert sich**, solange niemand einen Track in Studio bearbeitet.
`title_key` bleibt aus historischen Gruenden `NOT NULL`; ein in Studio
angelegter Track bekommt einen synthetischen, nie gelesenen Wert
(`"track.{$slug}.title"`) -- eine `doctrine/dbal`-Abhaengigkeit fuer
`nullable()->change()` allein dafuer einzufuehren, waere unverhaeltnismaessig.

**`StudioTrackController`** (`/studio/tracks`): `index` (Liste, jede
Nicht-Lernende-Rolle), `store`/`update` (Reviewer/Administrator, neue
`TrackPolicy::manage()` -- Track ist nicht wie eine Activity an einzelne
Autor:innen zuweisbar, deshalb keine feinere Autor-Berechtigung),
`publish`/`unpublish`/`archive`/`restore` als eigene, explizite
Zustandsuebergaenge statt eines generischen `status`-Felds in `update`
-- macht die erlaubten Uebergaenge im Code sichtbar, nicht nur in der
Oberflaeche.

**Kein Loeschen.** Wie bereits entschieden: nur `archive`/`restore`.
`TrackController::index()` (Lernende) blendet `status = archived` jetzt
aus (`where('status', '!=', 'archived')`); `show()` liefert fuer einen
archivierten Track `404` (anders als `draft`, das ueber Direktlink
weiter erreichbar bleibt -- eine bereits bestehende, hier nicht
angetastete Eigenheit).

## Konsequenzen

- Neue Dateien: `StudioTrackController.php`, `TrackPolicy.php`,
  `Studio/Tracks.vue`; "Tracks" ergaenzt in `StudioSidebar.vue` und im
  Studio-Dashboard.
- **Bewusst nicht Teil dieser ADR**: Reorder/Duplizieren per Drag & Drop,
  Lessons-innerhalb-eines-Tracks-Verwaltung (CMS-6, Lesson Composer) --
  `order` ist ueber das Bearbeitungsformular manuell setzbar, aber es
  gibt noch keine Sortierliste; Duplizieren ist noch nicht gebaut.
- `content:sync` bleibt vollstaendig unangetastet -- ein Datei-verwalteter
  Track wird nie durch diese Aenderung ueberschrieben oder umgestellt.

## Verifikation

- Alle 467 Tests (16 neu: `TrackPolicyTest`, `StudioTrackControllerTest`,
  je ein Fall in `TrackControllerTest`/`StudioControllerTest` fuer den
  `title`-Vorrang bzw. den archivierten Zustand), PHPStan Level 7 und
  `pint --test` sind gruen.
- `vue-tsc --noEmit`: keine neuen Fehler. `npm run check` und
  `npm run build` erfolgreich.
