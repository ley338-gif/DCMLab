# 0083 — W6.4: Achievement-Editor

## Status

Angenommen, 15.09.2026. Vierter und letzter Editor aus Arbeitsphase W6
(`dcm-lab-lms-agent-prompt.md` Abschnitt 5), im Anschluss an ADR 0082.

## Kontext

Der Agent-Prompt beschreibt den Achievement-Editor so: "Felder aus
`achievements.yml`, Bild-Upload, Auslösekriterium aus W4 als Auswahl."
Zwei strukturelle Besonderheiten unterscheiden diesen Editor von den
ersten drei:

1. **Ein Achievement ist keine `ActivityContract`-Instanz im ueblichen
   Sinn.** Lektion, Quiz, Pruefung, Node und Spielwiese sind von
   Lernenden abschliessbare Einheiten (`ActivitySupports::
   tracksCompletion`); ein Achievement ist deklaratives Metadatum, das
   `AchievementUnlockEvaluator` gegen `activity_progress` auswertet
   (ADR 0077, W4). Es gibt daher auch keine feste Menge editierbarer
   "Instanzen" wie Lektionen oder Tracks -- `achievements.yml` ist eine
   flache Liste ohne per-Eintrag-Modell.
2. **`image` verweist auf eine Datei unter `public/images/achievements/`,
   ausserhalb von `content/`.** Das ist kein Tippfehler im Bestand,
   sondern die bestehende Konvention (`AchievementService::learnerView()`
   baut `"/images/achievements/{$definition->image}"`) -- Bilder sind nie
   Teil der versionierten Prosa unter `content/` gewesen.

## Entscheidung

**Achievement wird ein sechster, bewusst untypischer Aktivitaetstyp.**
ADR 0072 sieht das ausdruecklich als Erweiterungsweg vor ("ein sechster
Typ registriert sich einfach zusaetzlich bei der ActivityRegistry ... ohne
dass dieses Enum angefasst werden muss", nachgewiesen durch
`ActivityRegistryTest::test_a_sixth_activity_type_can_be_registered_...`).
**`AchievementCatalogActivity`** repraesentiert nicht ein einzelnes
Achievement, sondern die GESAMTE Datei `achievements.yml` als eine
einzige Instanz (`key() === 'catalog'`, `ActivitySupports(authorable:
true, tracksCompletion: false, isGraded: false, ...)`). Welcher einzelne
Eintrag bearbeitet wird, steht im Entwurf selbst (`$draft['slug']`), nicht
in der Aktivitaets-Identitaet -- es gibt nur eine Aktivitaetszeile
(`type=achievement, key=catalog`), die `ContentSync` jetzt zusaetzlich zu
Lektionen/Nodes/Pruefungen anlegt (`ContentSync::
syncAchievementCatalogActivity()`).

**`AchievementCatalogGenerator` (neu)** ist strukturell anders als
`LessonQuizGenerator`/`ExamMetaGenerator`: `achievements.yml` hat keinen
uebergeordneten Schluessel wie `quiz:`, sondern ist eine flache
Top-Level-Liste (`- slug: ...`). Ein Block reicht von seiner
`- slug:`-Zeile bis (ausschliesslich) zur naechsten oder zum Dateiende.
`regenerateEntry()` ersetzt den Block des genannten Slugs chirurgisch
oder haengt einen neuen ans Ende an, wenn der Slug noch nicht existiert --
jeder andere Eintrag (inklusive Kommentare vor dem ersten Eintrag) bleibt
Zeile fuer Zeile erhalten.

**`AchievementCatalogActivity::validate($draft)` filtert nicht nach dem
bearbeiteten Slug**, sondern gibt alle Befunde aus `achievements.yml`
zurueck -- anders als bei Lektion/Pruefung mit je eigener Datei ist
`achievements.yml` eine von allen Eintraegen gemeinsam genutzte Datei; ein
Slug-Filter waere eine zweite, schwaechere Pruefung mit dem Risiko, echte
Kollisionsbefunde (z. B. doppelte Slugs) zu verstecken.

**Bewusst nicht Teil dieses Editors: ein echter Bild-Upload.** `image`
bleibt ein Freitextfeld (Dateiname unter `public/images/achievements/`).
Ein Datei-Upload braucht einen eigenen, sicherheitsgeprueften Endpunkt
(Mime-/Groessenpruefung) ausserhalb der `content_versions`-Transaktion --
Bilder sind, anders als Prosa, nie Teil des versionierten
Review/Freigabe-Kreislaufs gewesen, und `ContentWriter` schreibt
ausschliesslich unter `content/`, nicht unter `public/`. Siehe
`docs/offene-fragen.md`.

## Konsequenzen

- `resources/js/pages/Author/AchievementEditor.vue`: Formular fuer ein
  einzelnes Achievement (Name, Beschreibung, Bilddatei-Freitext,
  Kategorie, Seltenheit, Geltungsbereich, Punkte, Sortierung, versteckt)
  plus ein Auslösekriterium-Auswahlfeld (`activity_completed` /
  `first_solve` / `track_passed`, mit den jeweils passenden
  Unterfeldern) -- damit ist "Auslösekriterium aus W4 als Auswahl"
  vollstaendig umgesetzt.
- Route-Gruppe `de/author/achievements/{slug}/edit` (auth+verified, kein
  Model-Binding -- ein Slug ist kein Eloquent-Model). Ein Slug, der noch
  nicht existiert, startet das Formular mit Standardwerten (`is_new:
  true`) und legt beim Speichern einen neuen Eintrag an.
- Submit/Publish laufen ueber die bestehende, generische
  `quiz-versions`-Gruppe (`ContentVersionController`, ADR 0081).
- **DoD-Einschraenkung** (wie schon bei W6.3, ADR 0082): der Typ ist noch
  nicht *vollstaendig* ohne Kommandozeile anlegbar, solange das Bild
  vorher manuell unter `public/images/achievements/` abgelegt werden
  muss. Bewusst und dokumentiert, keine uebersehene Anforderung.

## Verifikation

- `AchievementCatalogGeneratorTest`: ein benannter Slug wird ersetzt, ein
  unbekannter Slug wird angehaengt, jeder andere Eintrag und Kommentare
  vor dem ersten Eintrag bleiben erhalten, `unlock_when`/`rarity` fehlen
  im Ergebnis, wenn sie im Entwurf leer sind, Rundtrip gegen echten
  Bestand (`content/achievements.yml`) bleibt gueltig und geparst
  identisch.
- `AchievementCatalogActivityTest`: `supports()` erklaert den Typ
  autorenfaehig ohne Abschlusszustand; `result()` ist immer `null`;
  `serialize($draft)`/`validate($draft)` folgen demselben Muster wie
  `LessonActivity`/`ExamActivity`.
- `ActivityRegistryTest::test_the_built_in_content_backed_types_are_registered`
  erweitert um `'achievement'` -- ein echter, kein kuenstlicher sechster
  Typ.
- `AchievementEditorControllerTest`: eine Lernperson darf den Editor
  nicht oeffnen (403); ein noch unbekannter Slug startet mit
  Standardwerten; der volle Kreislauf (pruefen → Entwurf → einreichen →
  freigeben) schreibt tatsaechlich nach `achievements.yml`, und ein
  zweites, unbeteiligtes Achievement im selben Bestand bleibt danach
  unveraendert erhalten.
- Alle 356 Tests, `phpstan analyse` (Level 7), `pint --test`,
  `npm run check` und `npm run build` sind gruen.
