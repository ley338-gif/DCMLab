# 0090 — W6.3: Fragenpool-Verwaltung im Prüfungs-Editor

## Status

Angenommen, 15.09.2026. Schließt die in ADR 0082 bewusst offen gelassene
Lücke ("Bewusst nicht Teil von v1: Fragen einzeln hinzufügen, bearbeiten
oder entfernen"), umgesetzt im Zuge von `docs/offene-fragen.md`.

## Kontext

ADR 0082 hat den Prüfungs-Editor auf Einstellungen plus eine reine
Lese-Pool-Übersicht beschränkt, weil der Fragenpool eine statistische
Einheit ist (`ContentValidator::checkExamStructure()`: Typ-Anteile
35–40/20–25/20–25/10–15 %, ≥ 25 % `difficulty: 3`, ≥ 4 Fragen je Lektion,
≥ 4 `cross`-Fragen) und nicht wie Lektions-Quizkarten einzeln bearbeitbar
ist, ohne die Statistik des *gesamten* Pools zu berühren. Damit blieb ein
neuer Prüfungspool weiterhin Kommandozeilen-Sache -- die einzige
verbliebene Lücke zum in Abschnitt 5 geforderten "Prüfungstyp vollständig
ohne Kommandozeile anlegbar".

## Entscheidung

**Der gesamte Fragenpool wird bei jeder Änderung als Einheit ersetzt,
nicht Frage für Frage:** `ExamQuestionGenerator` (analog zum
Zeilenersatz-Muster von `LessonMetaGenerator`/`ExamMetaGenerator`)
regeneriert bei einem `questions`-Entwurf den kompletten `questions:`-Block
in `exam.yml` und den kompletten Körper von `de.md` (alle `### fNN — ...`-
Abschnitte) neu aus der eingereichten Liste -- nie einzelne Karten. Das
ist dieselbe Erkenntnis wie in ADR 0082: die Poolstatistik ist eine
Eigenschaft der ganzen Liste, also ist die ganze Liste die Ersetzungs-
einheit. `ref`-Fragen (ADR 0079/W5) bekommen dabei weiterhin keinen
eigenen `### fNN`-Abschnitt in de.md.

**Die Live-Pool-Übersicht rechnet ab jetzt immer gegen den Entwurf, nicht
gegen den gespeicherten Bestand:** `ExamEditorController::coverage()`
nimmt die Fragenliste als Parameter und wird sowohl beim Laden (mit der
aktuellen `pending_version` oder dem Ist-Zustand) als auch bei jedem
`/validate`-Aufruf mit der gerade im Formular gehaltenen Liste aufgerufen.
Jede Änderung im Editor (Frage hinzufügen/bearbeiten/entfernen) löst sofort
eine `/validate`-Anfrage aus, die sowohl `issues` als auch eine frisch
berechnete `coverage` zurückgibt -- das ist die im Agent-Prompt geforderte
"Fortschrittsanzeige statt Fehlermeldung hinterher", jetzt tatsächlich
*während* der Bearbeitung statt nur für den gespeicherten Zustand.

**Frage-IDs müssen dem Muster `fNN` folgen** (`ContentValidator` prüft das
jetzt explizit). Grund: `ExamContent::splitIntoBlocks()` erkennt einen
neuen Abschnitt nur an der Überschrift `### fNN — ...`; eine
nicht-konforme ID (z. B. `f9a` oder ein frei getippter Name) lässt ihre
Überschrift beim Parsen unerkannt durchrutschen und ihren Inhalt
stillschweigend mit dem *vorherigen* Abschnitt verschmelzen -- sichtbar nur
als verwirrende Folgefehler ("Frage X braucht genau eine Erklärung-Zeile
(2 gefunden)" beim Vorgänger, "Frage Y hat keinen Abschnitt" bei der neuen
Frage selbst). Das wurde beim Live-Test dieser Funktion tatsächlich so
beobachtet (Testfrage-ID `f99smoketest`) und mit dieser Validierung durch
eine klare, sofortige Fehlermeldung ersetzt, statt es dem Publish-Schritt
zu überlassen.

**Ref-Fragen im UI müssen zur ursprünglichen Frage typkompatibel bleiben,**
wenn sie eine bestehende Frage ersetzen: der Editor erlaubt es, eine
eigene Frage per Checkbox in eine `ref`-Frage umzuwandeln, aber der
referenzierte Lektions-Quiztyp fließt direkt in die Typmischung ein --
eine Umwandlung, die den Typ ändert, kann dieselben Bandbreiten verletzen
wie das Hinzufügen einer neuen Frage. Das ist kein Sonderfall im Code,
sondern folgt automatisch aus derselben Entwurf-gegen-`coverage()`-Prüfung.

## Konsequenzen

- `apps/web/app/Content/ExamQuestionGenerator.php` (neu): regeneriert
  `exam.yml`s `questions:`-Block und `de.md`s Fragenkörper als Einheit aus
  einer PHP-Liste von Fragen (eigene Felder oder `ref`).
- `ExamActivity::serialize($draft)`: bei einem `questions`-Schlüssel im
  Entwurf ersetzt `ExamQuestionGenerator` den gesamten Pool; ohne
  `questions`-Schlüssel bleibt das Verhalten aus ADR 0082 unverändert
  (nur Einstellungen).
- `ExamEditorController`: `catalog()` liefert Lektionen, ihre Überschriften
  (für das Anker-Dropdown, `HeadingSlug`), die Lektions-Quizfragen (für
  `ref`) und die verfügbaren Tags; `currentQuestions()` löst den
  Ist-Zustand author-sichtbar auf (inklusive Antwort/Erklärung, anders als
  die Lernpfad-Ansicht `ExamContent::parseQuestions()`); `coverage()` nimmt
  jetzt die Fragenliste als Parameter statt sie immer aus dem Ist-Zustand
  zu lesen.
- `resources/js/pages/Author/ExamEditor.vue`: vollständige Fragenpool-
  Verwaltung (Liste, Hinzufügen/Bearbeiten/Entfernen, `ref`-Umschaltung mit
  Lektion/Frage-Dropdown, typabhängiges Antwortfeld, mehrere Review-Anker),
  mit sofortiger `/validate`-Rückmeldung nach jeder Änderung.
- `ContentValidator::checkExamStructure()`: neue Prüfung, dass jede
  Fragen-ID dem Muster `^f\d+$` folgt (verhindert die oben beschriebene
  stille Abschnitts-Verschmelzung).
- **DoD-Lücke aus ADR 0082 geschlossen:** ein neuer Prüfungspool ist jetzt
  vollständig über den Editor pflegbar, ohne Kommandozeile.

## Verifikation

- `ExamQuestionGeneratorTest`: Rundtrip gegen den echten 40-Fragen-Pool
  von `fundamente` ist verlustfrei (No-Op-Regenerierung ergibt exakt den
  ursprünglichen `questions:`-Block und Fragenkörper); einzelne
  Felder (Typ, Antwort, Erklärung, `ref`, mehrere Review-Ziele) werden
  korrekt neu geschrieben.
- `ExamActivityTest`: `serialize($draft)` mit `questions` ersetzt den
  gesamten Pool; `validate($draft)` prüft den regenerierten Pool (nicht
  den gespeicherten).
- `ExamEditorControllerTest`: `catalog`/`questions` im Ist-Zustand;
  `/validate` liefert Coverage für den *eingereichten* Entwurf, nicht den
  gespeicherten Pool; voller Lebenszyklus für das Bearbeiten einer
  bestehenden Frage; voller Lebenszyklus für das Ersetzen einer eigenen
  Frage durch eine `ref`-Frage (mit typkompatiblem Ziel, siehe oben).
- Manueller Live-Test gegen den echten, laufenden Stack (`docker compose`)
  und den echten 40-Fragen-Pool von `fundamente`: Hinzufügen einer Frage
  mit gültiger ID zeigt sofort die erwarteten (und einzig erwarteten)
  Quoten-Warnungen in der "Befunde"-Liste, ohne die vorhandenen 40 Fragen
  zu beschädigen. Dabei wurden zwei reale Fehler gefunden und behoben, die
  keine der bestehenden Pest-Tests abgedeckt hätten: (1) ein UI-Bug, bei
  dem "Frage hinzufügen" keinen sichtbaren Editier-Bereich öffnete, weil
  der Bearbeitungsindex auf eine Position *hinter* dem Ende des Arrays
  gesetzt wurde, ohne vorher einen Platzhalter einzufügen (das `v-for`
  der Editierzeile hatte dadurch nichts zu rendern); (2) die oben
  beschriebene ID-Format-Lücke im Validator. Beides untermauert, warum
  UI-Änderungen laut Projektrichtlinie vor Abschluss im Browser gegen
  echten Bestand getestet werden müssen, nicht nur gegen Pest-Fixtures.
- Alle 390 Tests, `phpstan analyse` (Level 7), `pint --test`,
  `npm run check`, `vue-tsc --noEmit` und `npm run build` sind grün.
