# 0093 — Tracks-Übersicht und Breadcrumb nach Themenfeld gruppiert

## Status

Angenommen, 15.09.2026.

## Kontext

`themenfelder.yml`/`Themenfeld` (Abschnitt 13) existieren seit der
Datenschutz-Erweiterung mit zwei echten Werten (`dicom`, `datenschutz`),
und `Nodes/Index.vue`/`NodeController` gruppieren Labs bereits danach
(Themenfeld-Überschrift, dann Kategorie). `/de/tracks` (Startseite) und
die Breadcrumb auf einer einzelnen Trackseite kannten das Feld bisher
nicht -- eine flache Liste bzw. "Tracks > Fundamente" ohne die
Themenfeld-Ebene dazwischen, obwohl Track und Node exakt dieselbe
Datenstruktur (`themenfeld_id`) tragen.

## Entscheidung

`TrackController::index()`/`show()` bekommen denselben `themenfeldSlug()`/
`themenfeldOrder()`-Fallback wie `NodeController` (Fallback `"dicom"`,
weil das `themenfelder.yml`s tatsächlicher `order: 1`-Wert ist) --
bewusst dieselbe kleine Duplikation wie zwischen den beiden Controllern
akzeptiert, statt eine gemeinsame Abstraktion für zwei Zeilen Fallback-
Logik einzuziehen. `Tracks/Index.vue` gruppiert jetzt einstufig nach
Themenfeld (keine zweite Ebene wie bei Nodes -- Tracks haben keine
`category`), `Tracks/Show.vue`s Breadcrumb wird um eine mittlere Stufe
erweitert: "Tracks > `<Themenfeld>` > `<Track>`". Die mittlere Stufe
verlinkt auf `home()` (dieselbe Tracks-Übersicht) -- es gibt keine
eigene Themenfeld-Seite zum Verlinken, und keine wird für diese Änderung
neu gebaut.

## Konsequenzen

- `TrackController.php`: `index()` liefert `themenfeld` je Track und
  sortiert zuerst nach Themenfeld-`order`, dann Track-`order`; `show()`
  liefert `track.themenfeld` für die Breadcrumb.
- `Tracks/Index.vue`: Track-Karten stehen jetzt unter einer
  Themenfeld-Überschrift (`trans('themenfeld.<slug>.title')`), sonst
  optisch unverändert.
- `Tracks/Show.vue`: Breadcrumb dreistufig statt zweistufig.

## Verifikation

- `TrackControllerTest`: ein Track ohne `themenfeld_id` fällt auf
  `"dicom"` zurück; die Trackübersicht sortiert und gruppiert korrekt
  nach Themenfeld-`order` zuerst, Track-`order` danach, über zwei
  Themenfelder hinweg.
- Manuell gegen den echten Stack: `/de` zeigt "DICOM und PACS" (5 Tracks)
  und "Datenschutz im Klinikbetrieb" (1 Track) als eigene Abschnitte;
  `/de/tracks/fundamente` und `/de/tracks/grundlagen` zeigen die
  jeweils korrekte dreistufige Breadcrumb.
- Alle 414 Tests, `phpstan analyse` (Level 7), `pint --test`,
  `npm run check`, `vue-tsc --noEmit` und `npm run build` sind grün.
