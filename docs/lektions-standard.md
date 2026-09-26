# Lektions-Standard

Was eine Lektion leisten muss, bevor sie veröffentlicht wird: das Niveau, das die
Überarbeitungen von **4.1** (#178, Association-Rejection) und **3.8** (#177, RDSR)
gesetzt haben. Es gilt für alle, die eine Lektion schreiben: Betreiber, Agent oder
externe Autoren.

Dieses Dokument **ergänzt** `docs/content-schema.md` und wiederholt es nicht. Format,
Beispielregel (Abschnitt 0), Lektionsstruktur mit Aufhänger, Stolperfallen und
Selbstcheck (Abschnitt 3), Referenzwerte (Abschnitt 8) und Validierung (Abschnitt 9)
stehen dort. Hier steht, was 4.1 und 3.8 darüber hinaus leisten.

**Herkunft.** Jede Regel nennt in Klammern ihre Quelle: eine Stelle in
`content/lessons/4.1/de.md` bzw. `content/lessons/3.8/de.md` oder einen Review-Befund
aus den Commit-Nachrichten von #177/#178 (`git log --format=%B 70c7d35 bb4f890`, die
Review-Runden stehen dort als Folge-Commits). „R1“ bis „R3“ bezeichnen Review-Runde 1
bis 3.

> 3.8 ist Stand 2026-09-25 noch `draft`. 4.1 ist `published`.

---

## 1. Diagnose statt Definition

- **Einstieg über ein Ticket oder Symptom, nicht über einen Begriff.** Der erste
  Abschnitt zitiert, was im Betrieb tatsächlich gemeldet wird, samt dem, was
  fehlt. (4.1 „Ein Ticket, das mehr als eine Ursache haben kann“: „CT_RAUM3 sendet seit
  heute Morgen nicht mehr ins Archiv“, kein Fehlercode; 3.8 „Die Untersuchung ist im
  PACS, aber im Dose-System fehlt sie“)
- **Das Symptom wird in eine Kette eingeordnet.** Jede Stufe hat ihr eigenes Werkzeug
  und ihre eigene Fehlermeldung, und die Lektion sagt, welche Stufen *sie*
  behandelt. (4.1 „Netzweg → TCP → Association → Presentation Context → DIMSE“ mit
  Lektionszuordnung je Stufe; 3.8 „Sechs Stationen, sechs mögliche Fehler“)
- **Die Kette ist mit den Nachbarlektionen konsistent.** Eine Lektion erfindet für
  dasselbe Gebiet kein eigenes, abweichendes Modell. (#178: „konsistent mit Lektion
  4.10s eigener Kettendarstellung“)
- **Ausdrückliche Abgrenzung** gegen die Nachbarlektion, mit der Grenze als prüfbarem
  Kriterium. (4.1 „Abgrenzung zu Lektion 4.2“: „endet dort, wo die Association
  tatsächlich zustande kommt“)
- **Keine Grundlagen wiederholen, die eine `requires`-Lektion schon erklärt.**
  Höchstens ein Satz als Anker mit Verweis. (#178, Befund: 4.1 wiederholte großteils
  1.5/1.6; 4.1 zeigt den Erfolgsfall nur „als Vergleichspunkt“; 3.8 „Aus Lektion 3.7
  kennst du bereits den Grundsatz …“)
- **Den eigenen Zuständigkeitsbereich benennen.** Die Lektion sagt ausdrücklich, was
  sie nicht beurteilt. (3.8: „Keine medizinphysikalische Dosisbewertung, keine
  Grenzwertentscheidung“)

## 2. Beweiskraft präzise formulieren

- **Zu jedem Symptom und jedem Test gehört: Was beweist es, was beweist es nicht?**
  Ein positiver Test gilt nur für genau die Parameter, mit denen er lief. (4.1: ein
  erfolgreicher C-ECHO beweist „nur, dass genau dieser Absender mit genau diesen Werten
  akzeptiert wurde“; 3.8: „ein erfolgreicher C-STORE beweist nur die Annahme, nicht die
  Auswertung“)
- **Eine Meldung besagt nur, was sie tatsächlich aussagt, keine naheliegende
  Ursache.** (4.1 R2/R3: „Connection refused“ heißt aktive Zurückweisung, nicht
  zwingend „kein Dienst“ und nicht zwingend „der Zielhost hat geantwortet“; 4.1:
  „Der Ablehnungsgrund grenzt nur ein, *welcher* der beiden Namen betroffen ist —
  nicht, *wessen* Konfigurationseintrag … abweicht“)
- **Fehlerbilder, die sich ähnlich anfühlen, als nicht austauschbar benennen.**
  (#178: Timeout, Network unreachable, Host unreachable und Connection refused
  „explizit als nicht austauschbar benannt“; 3.8: „kein Job“ ≠ `failed`)
- **Das Fehlen eines Belegs ist selbst ein Befund und wird so gedeutet.** (3.8: kein
  Job-Eintrag heißt „Die Route hat das RDSR nie ausgewählt“, nicht „Transfer
  fehlgeschlagen“)
- **Keine unbelegten Superlative und keine ungeprüften Allaussagen.** Also nicht
  „häufigste Fehlkonfiguration überhaupt“, „jeder andere SR“, „liefert ein RDSR nie“.
  (#178 Befund; 3.8 R3: „jeder andere Structured Report“ wurde zu „viele andere
  SR-Dokumenttypen“; 3.8 R2: Effective-Dose-Passage ohne „nie“)
- **Nicht Geprüftes als nicht geprüft kennzeichnen**, statt es mit Beweisrhetorik zu
  formulieren. (4.1 R2: „Host unreachable“ steht als „typischerweise … hier nicht live
  geprüft“)

## 3. Normbezug geprüft

- **Jede PS3.x-Aussage trägt eine konkrete Fundstelle** (Teil plus Abschnitt,
  Tabelle oder Annex) und ist gegen den **aktuellen** Standardtext geprüft, nicht aus
  dem Gedächtnis. Nach `content-schema.md` Abschnitt 3 gilt dabei: erst verständlich
  erklären, dann die Fundstelle. (Zitat aus 4.1, live geprüft laut #178: „PS3.8
  Tabelle 9-21 für A-ASSOCIATE-RJ, Tabelle 9-26 für A-ABORT“; 3.8: „PS3.4 Annex B.5“,
  TID 10011/10012/10013 nach PS3.16)
- **Kennt der Standard mehrere Fälle, wird keiner weggekürzt.** (4.1 R1: Source einer
  A-ASSOCIATE-RJ ist service-user *oder* service-provider (ACSE) *oder*
  service-provider (Presentation), nicht „immer die Gegenstelle“; 4.1 R1: ein Abort
  kann während *oder* nach der Verhandlung auftreten)
- **Protokollebenen nicht vermischen.** (4.1 R1: TCP-Fehler erzeugen keine
  A-ASSOCIATE-RJ-PDU; 3.8 R1: `Modality = SR` unterscheidet kein RDSR, maßgeblich ist
  die SOPClassUID)
- **Eine Beispielinstanz nicht auf die Klasse verallgemeinern.** (3.8 R2: „Wurzel
  jedes RDSR“ wurde zu „Wurzel dieses klassischen CT-RDSR“; Irradiation Event ≠
  Serie)

## 4. Beispiele live erzeugt

- **Ausgaben stammen aus einem echten, isolierten Nachbau der Spielwiese** (Orthanc +
  Toolbox wie ADR 0008) und werden wörtlich übernommen. (#178: „Alle drei
  TCP-Beispiele frisch … live erzeugt und wörtlich übernommen“)
- **Nicht Reproduzierbares erscheint nicht als Ausgabe.** Es wird als
  `<!-- kein-beispiel -->` markiert, der Grund steht dabei, samt Prüfdatum und
  Verweis auf eine Node, in der man es erlebt. (4.1 „Called und Calling“: Orthanc
  akzeptiert per `DicomAlwaysAllow*` jeden AE Title, „erneut … geprüft
  (2026-09-21)“, verwiesen an Silent CT/Wrong Door)
- **Aussagen, die nur für den Testaufbau gelten, als solche kennzeichnen.** (4.1 R2/R3:
  „Anfrage verlässt den Host gar nicht erst“ gilt „nur für den konkret nachgebauten,
  egress-losen Docker-Testaufbau“)
- **Synthetische Objekte ehrlich benennen.** Kein „echt“ oder „valide“, wenn nur das
  Dateiformat geprüft ist. Was das Prüfwerkzeug tatsächlich beweist, steht dabei. (3.8
  R2: „synthetisches, strukturell reduziertes Lehrbeispiel im DICOM-Part-10-Format“,
  `dcmftest` prüft nur Meta-Header/Part-10, keine IOD-/TID-Konformität)
- **Zahlen im Text stimmen mit den eigenen Tabellen und Diagrammen überein.** (#177
  Befund: „vier Stationen“ im Text, fünf Tabellenzeilen)

## 5. Ein Diagnosewerkzeug in der Lektion

Wo das Thema es hergibt, enthält die Lektion mindestens eines der folgenden
Werkzeuge. Es fasst die Beweiskraft-Aussagen aus Abschnitt 2 zusammen und führt
nichts Neues ein.

- **Diagnosematrix** mit den Spalten *Beobachtung | Bewiesen | Nicht bewiesen |
  Nächster Schritt*. (4.1 „Kleine Diagnosematrix“; 3.8 *Beobachtung | Belegt | Belegt
  nicht*)
- **Soll-Ist-Vergleich** als nummerierte Schritte: Soll klären, beide Seiten gegen das
  Soll vergleichen, eine Variable pro Versuch ändern, die Korrektur am Ort des Solls
  dokumentieren. (4.1 „Soll und Ist beider Seiten vergleichen“)
- **Stationenliste** mit je einem Beleg pro Station und einer Aussage, was ein grüner
  Schritt über die folgenden *nicht* sagt. (3.8 „Sechs Stationen“ plus Mermaid-Kette)
- **Maßnahme mit Nachprüfung**, nicht nur Ursache. Dazu gehört, warum die naheliegende
  Maßnahme falsch wäre. (3.8 „Saubere betriebliche Diagnose und Maßnahme“: „einfach um
  `SR` erweitern“ ist keine saubere Lösung; nach der Änderung Job, Transfer und
  Empfang verifizieren)

## 6. Lektionsquiz ist Pflicht

Format und Validierung regelt `content-schema.md` Abschnitt 2 (`quiz:`-Block,
`**qN — …**` in `de.md`). Zusätzlich gilt:

- **4–5 Fragen.** (4.1 und 3.8: je 5)
- **Nur die Typen `single`, `multi`, `input`.** Das Lektionsquiz kennt kein
  `truefalse` (`ContentValidator`: „erlaubt: single, multi, input“). Richtig/Falsch
  wird als `single` mit zwei Optionen umgesetzt. (4.1 q3; #178)
- **Mindestens eine `multi`- und eine `input`-Frage.** (4.1 q4/q5. 3.8 hat bisher
  *keine* `input`-Frage und muss nachziehen, siehe Lektions-Backlog.)
- **Distraktoren aus echten Fehlannahmen**, am besten aus den Stolperfallen der
  Lektion. (4.1 q1: Option „Der Host ist im Netz nicht erreichbar“, dazu die
  Stolperfalle „Connection refused heißt …“)
- **Keine vagen „wahrscheinlichste Erklärung“-Fragen.** Jede richtige Antwort folgt
  aus einer Aussage der Lektion. Mindestens eine Frage lässt eine Ausgabe oder einen
  Befund lesen. (#177: „5 evidenzbasierte Fragen statt der alten, zu vagen …“, eine
  Frage testet den `dcmdump`-Ausschnitt)
- **Richtige Optionen sind so präzise wie der Fließtext**, zeit- und wertegebunden, wo
  nötig. Nach jeder Umformulierung wird der Antwortindex neu geprüft. (4.1 R2/R3: q4
  „Zum Zeitpunkt dieses Gegenchecks …“, Index `[0,2,3]` „nach Prüfung unverändert“)
- **Das Quiz wird einmal interaktiv gegen die echte Lektion durchgespielt.** (#178:
  „interaktiv im Browser … verifiziert“)

## 7. Konsistenz mitprüfen

- **Prüfungsfragen des Tracks**, die auf die Lektion zeigen: Inhalt *und*
  `review.anchor`. Beide werden mitkorrigiert, wenn die Lektion eine Aussage
  präzisiert oder eine Überschrift umbenennt. (#178: f01/f23 inhaltlich korrigiert,
  Antwortindizes unverändert; #177: Anker f36/f37 nachgezogen, f35/f38 geprüft)
- **`meta.yml` gegen den neuen Inhalt prüfen:**
  - `objectives_count` (#178: 3→4)
  - `duration_minutes` (#178: 12→18)
  - `tools` gegen die tatsächlich verwendeten Werkzeuge
  - `related_node` inklusive `optional` (#177: auf `gefiltert` gesetzt, weil das die
    aktive Hands-on-Anwendung ist)
  - `requires`
  - `glossary_terms` (#177: neuer Begriff `radiation-dose-structured-report` im
    Glossar)
  - `tools_checked`

  Was unverändert bleibt, wird trotzdem ausdrücklich als geprüft vermerkt. (#178:
  „Audit bestätigt sie als weiterhin zutreffend“)

## 8. Umfang ist ein Richtwert, kein Ziel

4.1 hat rund 2.500 Wörter, 3.8 rund 3.600 (jeweils `de.md` inklusive Quiz). Die Länge
entsteht aus Diagnosetiefe, also aus Kette, Beweiskraft, Diagnosewerkzeug und
Normbezug, nicht aus Wiederholung. Eine kürzere Lektion, die alle Regeln erfüllt, ist
fertig. Eine längere, die Grundlagen wiederholt, ist es nicht. (#178:
Grundlagenwiederholung war *der* Befund, nicht die Länge)

## 9. Ablauf seit ADR 0122

1. **Entwerfen.** Eine Datei oder ein Branch gilt nur als Arbeitsstand.
2. **Als Entwurf in Studio anlegen**, im Lesson- und Quiz-Editor oder aus der Datei
   mit `php artisan content:draft <X.Y> --from=<de.md> --author=<nutzer>`.
   Letzteres legt nur einen Entwurf an und veröffentlicht nie.
3. **Review durch eine zweite Rolle.** Reviewer und Autor sind verschiedene Personen.
   Die Checkliste unten wird abgehakt.
4. **In Studio veröffentlichen.**
5. **`make content-export`** ausführen (`docs/betrieb.md`, „Content-Änderungen ins Repo
   bringen“).
6. **PR `content-export: Lektion X.Y …`** einreichen, mit der abgehakten Checkliste in
   der Beschreibung.

Handänderungen an `content/` sind für bestehende Lektionen nicht der vorgesehene Weg.
Seit ADR 0122 überschreibt `content:sync` den Studio-Stand nicht mehr, eine
Dateiänderung würde also schlicht nicht wirken. (ADR 0122; der Befund zu 1.7, 2.3 und
4.5 dort zeigt, wie Datei-Edits für Lernende unsichtbar blieben)

---

## Review-Checkliste

Der Reviewer hakt sie vor der Freigabe ab. Der Autor übernimmt sie mit seinen eigenen
Antworten in die PR-Beschreibung bzw. den Studio-Review-Kommentar. Jeder Punkt ist
Ja/Nein; ein „Nein“ braucht eine Begründung.

- [ ] Einstieg über ein Ticket oder Symptom, keine Definition
- [ ] Das Symptom ist in eine Kette oder Stationenliste eingeordnet, die zu den
      Nachbarlektionen passt
- [ ] Abgrenzung zur Nachbarlektion mit prüfbarer Grenze vorhanden
- [ ] Keine Grundlagen aus `requires`-Lektionen wiederholt (höchstens ein Anker mit
      Verweis)
- [ ] Zu jedem Test und jeder Meldung steht „beweist / beweist nicht“
- [ ] Keine unbelegten Superlative oder ungeprüften Allaussagen
- [ ] Nicht Geprüftes ist als nicht geprüft gekennzeichnet
- [ ] Jede PS3.x-Aussage hat eine Fundstelle und ist gegen den aktuellen Standardtext
      geprüft
- [ ] Mehrere Fälle im Standard sind nicht auf einen verkürzt
- [ ] Alle Ausgaben sind live erzeugt und wörtlich übernommen; Nicht Reproduzierbares
      ist als `<!-- kein-beispiel -->` markiert, mit Grund, Datum und Node-Verweis
- [ ] Aussagen, die nur für den Testaufbau gelten, und synthetische Objekte sind als
      solche benannt
- [ ] Zahlen im Text stimmen mit Tabellen und Diagrammen überein
- [ ] Ein Diagnosewerkzeug ist vorhanden (Matrix, Soll-Ist oder Stationen), wo das
      Thema es hergibt
- [ ] Quiz: 4–5 Fragen, mindestens je eine `multi` und `input`, kein `truefalse`
- [ ] Quiz: Distraktoren aus echten Fehlannahmen, Antwortindizes nach der letzten
      Umformulierung geprüft, einmal interaktiv durchgespielt
- [ ] Prüfungsfragen des Tracks (Inhalt und `review.anchor`) mitgeprüft
- [ ] `meta.yml` geprüft: `objectives_count`, `duration_minutes`, `tools`,
      `related_node`, `requires`, `glossary_terms`, `tools_checked`
- [ ] Veröffentlicht über Studio (Reviewer ≠ Autor), danach `make content-export` und
      PR `content-export:`

## Automatisierbar später

Nur aufgelistet, nicht gebaut. Kandidaten für `content:validate` bzw.
`ContentValidator`, jeweils sinnvoll nur für `status: published`:

| Checklistenpunkt | Mögliche Prüfung |
|---|---|
| Quiz-Pflicht | `quiz:` nicht leer |
| 4–5 Fragen | Anzahl der `quiz`-Einträge ∈ [4, 5] |
| `multi` und `input` vorhanden | je mindestens ein Eintrag dieses Typs |
| Diagnosewerkzeug | Warnung, falls weder eine Tabelle mit einer Spalte „Nicht bewiesen“/„Belegt nicht“ noch eine nummerierte Stationenliste vorkommt (Heuristik, nur Hinweis) |
| `kein-beispiel` mit Grund | auf den Marker folgt ein Block oder Satz, nicht nur der Fence (Heuristik) |
| unbelegte Superlative | Hinweis bei „häufigste … überhaupt“, „immer“, „nie“, „jeder“ in Behauptungssätzen (nur Hinweis, viele Fehlalarme) |
| Normfundstelle | Hinweis bei „PS3.“ ohne folgende Tabellen-, Abschnitts- oder Annex-Angabe |

Schon heute geprüft werden `objectives_count` (`checkLessonStructure`) und die
Prüfungsanker. Nicht automatisierbar bleiben die inhaltlichen Kernpunkte:
Beweiskraft, Normtreue, Live-Herkunft der Ausgaben, Abgrenzung. Dafür gibt es das
Review.
