# 0086 — `status`-Feld im Bestand auf `published` normalisiert

## Status

Angenommen, 15.09.2026. Loest die offene Frage "`status: fertig` im
Bestand normalisieren" aus `docs/offene-fragen.md` (offen seit ADR
0075, W3).

## Kontext

`docs/content-schema.md` dokumentiert genau drei gueltige Werte fuer
`status` in `meta.yml`/`node.yml`: `draft | review | published`. Der
reale Bestand trug stattdessen `status: fertig` (25 Dateien, ein
nirgends dokumentierter Wert) oder `status: draft` (34 Dateien). ADR
0075 hatte das beim Umsetzen von W3 festgestellt, aber bewusst nicht
angefasst, weil unklar war, ob die 34 `draft`-Faelle tatsaechlich
unfertig sind oder nur nie umgestellt wurden -- die Empfehlung war,
das "in einem eigenen, review-baren Commit" zu klaeren.

**Vor der Aenderung wurde geprueft, ob `status` ueberhaupt eine
Wirkung auf die Lernenden-Sichtbarkeit hat:**
`ContentVersioningService::isPublished()` liest ausschliesslich
`content_versions.status` (die Versionierungs-Pipeline aus W3), nicht
das rohe `meta.yml`/`node.yml`-Feld -- und liefert `true` fuer jede
Aktivitaet ohne Versionshistorie, was heute auf den gesamten
Bestand zutrifft. `TrackController`/`resources/js/pages/Tracks/Show.vue`
reichen `lesson.status` nur als Anzeige-Prop durch, ohne sie je in
einer Bedingung auszuwerten. **Diese Aenderung hat damit keine
Lernenden-sichtbare Wirkung** -- eine reine Werteaenderung, wie in
der urspruenglichen Empfehlung erwartet.

## Entscheidung

**Alle 59 betroffenen Dateien pruefen, dann auf `published` setzen --
keine blieb `draft`.** Fuer die 34 `draft`-Faelle wurde jede Kategorie
einzeln anhand von drei unabhaengigen Belegen geprueft, nicht geraten:

- **Track 1 (1.0–1.8, 9 Lektionen):** Zeilenzahl 185–382 je `de.md`,
  keine "Gerüst"-Marker. Bestaetigt reales, komplettes
  `docs/content-schema.md`-Kapitel "10. Nachzuziehen" (Werkzeugbeispiele
  fuer 1.1/1.5 fehlten laut Dokument noch) ist selbst veraltet: beide
  Lektionen enthalten die dort als fehlend beschriebenen
  `dcmftest`/`dcmdump`- bzw. `echoscu`/`storescp`-Beispiele bereits, und
  `meta.yml: tools` listet sie.
- **Track 4, sieben verbleibende Lektionen (4.1–4.6, 4.10):**
  `docs/content-todo.md` behauptet noch "Kein Gerüst enthält
  Fachprosa" -- tatsaechlich hat jede dieser Dateien 131–178 Zeilen
  echten Fliesstext (Beispiele, "Im Alltag", "Stolperfallen",
  "Selbstcheck"), keine "Gerüst"/"noch zu schreiben"-Marker. Auch
  dieser Teil von `content-todo.md` ist veraltet (siehe unten).
- **Lektion 6.1 (1 Datei):** 81 Zeilen echter Text, keine Marker.
- **17 Node-Definitionen:** `docs/content-todo.md` Abschnitt P9 listet
  selbst sieben urspruenglich als Geruest angelegte Nodes
  (`first-contact`, `zwei-ebenen-tiefer`, `wo-steht-das`, `zwillinge`,
  `halbe-sache`, `mitgehoert`, `verbindung-ohne-bild`) inzwischen als
  "✅ ... fertig ... nicht mehr blockiert"; sechs weitere neuere Nodes
  (`c-find-mismatch`, `oversized-image`, `syntax-negotiation-fails`,
  `patient-merge-discovery`, `worklist-query-empty`, `teiltransfer`)
  als "✅ vollständig und live verifiziert"; `silent-ct`/`wrong-door`/
  `neue-node` waren laut README ohnehin bereits spielbar;
  `anruf-am-empfang` (Datenschutz-Szenario, `interaction: scenario`)
  hat einen vollstaendigen Entscheidungsbaum ohne jeden Marker.

**Nebenbefund: `README.md` und `docs/content-todo.md` sind an mehreren
Stellen veraltet**, unabhaengig vom `status`-Feld -- z. B. behauptet
`README.md` "Track 2, 3, 5 sind im Konzept geplant, aber nicht
angelegt" und "10 Node-Definitionen", obwohl beides laengst ueberholt
ist (Tracks 2/3/5 existieren vollstaendig, es gibt 17 Nodes). Das ist
eine **eigene Doku-Pflege-Aufgabe**, absichtlich nicht Teil dieser
Aenderung (die nur das `status`-Feld normalisiert) -- als eigener
Punkt in `docs/offene-fragen.md` festgehalten.

## Konsequenzen

- `content:validate` bleibt frei von Verstoessen (42 Lektionen, 17
  Nodes, 5 Pruefungen).
- Sobald `status` kuenftig tatsaechlich fuer eine Sichtbarkeits-Gate
  ausgewertet wird (die naechste offene Frage, die diese Aenderung
  nicht loest), spiegelt der Bestand ab jetzt seinen echten Zustand
  wider, statt eine Migration von 59 Dateien vor sich herzuschieben.

## Verifikation

- `CONTENT_PATH=../../content php artisan content:validate`: keine
  Verstoesse, vor und nach der Aenderung.
- Volle Pest-Suite (364 Tests) unveraendert gruen -- kein Test liest
  `status` aus echtem Content, alle `status`-Assertions verwenden
  eigene synthetische Fixtures oder das unabhaengige
  `content_versions`/`tracks.status`-Feld.
- Code-Lektuere von `ContentVersioningService::isPublished()` und der
  Track-/Lesson-Anzeige (`TrackController`, `Tracks/Show.vue`) bestaetigt:
  keine Lernenden-sichtbare Verhaltensaenderung.
