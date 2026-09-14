# Auftrag an den Code-Agenten — DCM Lab, Ausbau zum Lightweight LMS

*Nachfolger von `dcm-lab-agent-prompt.md` (Phase 1). Dieses Dokument ist neuer
und schlägt es bei Widersprüchen. Grundlagen:
`docs/adr/0071-content-speicherort-autorenschicht.md` und
`docs/adr/0072-aktivitaetsvertrag-lightweight-lms.md`.*

---

## 0. Rolle und Ziel

Du arbeitest in einem laufenden, funktionierenden System. Alle Phasen P0 bis
P10 des Vorgängerauftrags sind umgesetzt: Laravel 13 + Inertia + Vue 3,
Node-Engine und Sandbox-Orchestrator in FastAPI, echte Container als
Spielwiese, 41 Lektionsverzeichnisse, 10 Nodes, 5 Track-Prüfungen, Punkte,
Ränge, öffentliche Profile.

Dein Auftrag ist **kein Neubau**. Er macht aus einer Lernplattform mit drei
fest verdrahteten Inhaltsarten ein **Lightweight LMS**: ein Kern mit genau
einem Plugintyp, eine Autorenschicht davor, und drei Stellen aufgeräumt, an
denen sonst Parallelsysteme entstehen.

Fachliches Ziel in einem Satz: *Eine Person ohne Git-Zugang und ohne
Kommandozeile kann eine Lektion mit Quiz schreiben, eine Abschlussprüfung
anlegen, ein Achievement definieren und zuordnen — und ein sechster
Inhaltstyp kostet später ein Modul, keinen Umbau.*

---

## 1. Leitplanken

Diese Sätze entscheiden Zweifelsfälle. Läuft eine technische Entscheidung
gegen einen dieser Sätze, ist die Entscheidung falsch, nicht der Satz.

1. **Der Lernende installiert nichts und richtet nichts ein.** Unverändert aus
   dem Vorgängerauftrag.
2. **Nie echte Patientendaten.** Unverändert.
3. **Die Übungsumgebung hat keinen Weg nach draußen.** Unverändert.
4. **Zu jeder Frage gibt es genau ein System.** Ein Speicherort für Lernstoff,
   ein Plugintyp, eine Validierung, ein Fortschrittsmodell, ein Kartenstapel
   (`quiz_reviews`), ein Abzeichen-System, ein Renderpfad. Entsteht bei deiner
   Umsetzung eine zweite Variante davon, ist die Umsetzung falsch. Dieser
   Punkt **ersetzt** die alte Leitplanke 4 („Lernstoff liegt im Git-Repo, kein
   CMS") und die erste Zeile von Abschnitt 11 des Vorgängerauftrags.
5. **Der Lernstoff lebt in der Datenbank, `content/` wird erzeugt.** Eine
   Richtung, immer. Erzeugte Dateien werden nie von Hand bearbeitet und nie
   zurückgelesen.
6. **Lightweight heißt: im Zweifel weglassen.** Siehe Abschnitt 2.

Zusatzregel: **Das Content-Schema in `docs/content-schema.md` bleibt
verbindlich.** Du änderst es nicht, um dir den Editor zu erleichtern. Die
Module erzeugen dieses Format, nicht ein bequemeres.

---

## 2. Was „Lightweight LMS" bedeutet

Die Muster stammen aus Moodle (Aktivitätsmodule, Plugintyp `mod`, Fähigkeiten
über `supports()`) und Open edX (XBlock mit `student_view`, `studio_view`,
`author_view`, `has_score`). Übernommen wird das Muster, nicht der Umfang.

**Drin:**
- Ein Plugintyp: die Aktivität.
- Abschluss plus optionale Punktzahl als gemeinsames Ergebnis.
- Drei Rollen mit Policies.
- Voraussetzungen zwischen Aktivitäten.
- Eine wiederverwendbare Fragenbank.
- Erinnerungen an fällige Wiederholungen.

**Draußen, und zwar bewusst:**
- Kein Notenbuch mit Gewichtungen, Kategorien oder Notenskalen.
- Keine Capability-Matrix, keine Rollenvererbung über Kontexte.
- Keine Kursformate, keine Foren, kein Chat, kein Kalender.
- Keine Fristen, keine Kohorten, keine Organisationskonten.
- Keine weiteren Plugintypen. Kein Blocktyp, kein Themetyp, kein Berichtstyp.

Wenn dir während der Arbeit auffällt, dass ein Feature mit „Moodle hat das
auch" zu rechtfertigen wäre: Das ist kein Argument. Schreib es in
`docs/offene-fragen.md` und mach weiter.

---

## 3. Ausgangslage — lies das, bevor du etwas anfasst

Wiederzuverwenden, **nicht** nachzubauen:

| Bereich | Wo | Zustand |
|---|---|---|
| Content lesen | `app/Content/ContentRepository.php` | vollständig, nur Leseseite |
| Content prüfen | `app/Console/Commands/ContentValidate.php` | 930 Zeilen Regelwerk, als Command gefangen |
| Content indizieren | `app/Console/Commands/ContentSync.php` | Themenfelder, Tracks, Lektionen, Nodes |
| Flag-Hashes | `app/Console/Commands/ContentBuild.php` | erzeugt `node.yml: flag.hash` |
| Quiz | `QuizContent`, `QuizSchedulerService`, `quiz_reviews` | vollständig, SM-2 |
| Prüfung | `ExamContent`, `ExamAttemptService`, `exam_attempts`, `track_badges` | vollständig, abdeckungsbalancierte Ziehung |
| Rendern | `MarkdownRenderer`, `HeadingSlug` | erzeugt auch die Anker für `review` |
| Achievements | `achievements.yml`, `AchievementRegistry`, `AchievementService` | Definition vollständig, Vergabe teils hartkodiert |
| Dienstgrenzen | `EngineClientContract`, `SandboxClientContract` | seit P10.68 entkoppelt |

Fehlt vollständig:

- **Jede Form von Autorisierung.** Kein `Gate::`, keine Policy, kein
  Rollenfeld. Nur `auth` und `verified`.
- **Jeder Schreibweg in `content/`** außer `content:build`.
- **Auswertung von `status` und `authors`.** Beide stehen im Schema,
  gefiltert wird nirgends. Entwürfe sind heute für jeden sichtbar.
- **Durchsetzung von `requires`.** Steht im Schema, wird nie geprüft.
- **Jede Erinnerung.** `quiz_reviews.due_at` wird berechnet, niemand erfährt
  davon.

Altlasten, die du auflösen sollst:

- **Drei Inhaltsarten als Sonderfälle:** `lessons`, `nodes`, Prüfungen nur als
  Dateien, dazu drei getrennte Fortschrittstabellen. Gegenstand von W0.
- **Drei Abzeichen-Mechaniken:** `achievements` (global pro Node),
  `track_badges`, `achievement_definitions`/`achievement_unlocks`. Lies ADR
  0070 vor W4.
- **Zwei hartkodierte Achievement-Vergaben:** `first-blood` in
  `NodeController::unlockNodeAchievements()`, `sandbox-starter` in
  `SandboxController::create()`.

**Wichtig für jede Speicherort-Entscheidung:**
`services/engine/app/content.py` und `services/sandbox/app/datasets_yaml.py`
lesen `content/` direkt vom Dateisystem über `CONTENT_PATH`, mit `lru_cache`.
Deshalb bleiben die erzeugten Dateien bestehen. Du baust für diese Dienste
keine API und änderst ihre Content-Zugriffe nicht. Du sorgst nur dafür, dass
ihr Cache nach einer Veröffentlichung ungültig wird.

---

## 4. Zielarchitektur

```
Editor (Browser)  ── authorView je Modul
   └─> content_drafts + content_versions      (DB, draft|review|published)
          └─ Freigabe ─> ContentValidator      (Kernregeln + Modulregeln)
                 └─> ContentWriter ─> content/** (erzeugt, Kopf-Marker)
                        ├─> content:sync
                        ├─> services/engine
                        └─> services/sandbox

Laufzeit:
   Aktivität (lesson|quiz|exam|node|sandbox)
      ├─ learnerView  ─> Inertia-Props
      └─ ActivityResult (abgeschlossen, Punktzahl, Skills)
             └─> activity_progress ─> Punkte, Rang, Skill-Radar, Achievements
```

---

## 5. Arbeitsphasen

Jede Phase endet mit grünem Validator, grünen Tests, einem Commit, einem ADR
bei jeder nicht trivialen Entscheidung und einer Notiz im `README.md`.
Beginne keine Phase, bevor die vorige fertig ist.

### W0 — Der Aktivitätsvertrag

Das Fundament. Ein Interface mit den sechs Fähigkeiten aus ADR 0072
(Lernendenansicht, Autorenansicht, Validierung, Serialisierung,
Deserialisierung, Ergebnis) plus einer Merkmalsdeklaration nach dem Vorbild
von Moodles `supports()`.

Datenmodell:

- `activities` (Typ, Slug, Track, Reihenfolge, Status, Autoren, Titel,
  Teaser, typspezifische Nutzdaten als JSONB, Quell-Hash).
- `activity_progress` (Nutzer, Aktivität, Abschluss, Punktzahl,
  Höchstpunktzahl, Zeitpunkt) als **einzige** Quelle für Punkte, Rang,
  Skill-Radar und Achievement-Auslöser.
- Versuchszustand bleibt typspezifisch: `node_attempts` behält
  `engine_session_id` und `hints_used`, `exam_attempts` behält gezogene
  Fragen, Position und Antworten. Nur der Ergebnisteil wandert.

Stelle `lesson`, `quiz`, `exam`, `node` und `sandbox` hinter den Vertrag. Die
Spielwiese wird dabei ein eigener Typ ohne Bewertung und ist danach überall
platzierbar, nicht nur unter einer Lektion.

`ProfileService` und `AchievementService` rechnen danach ausschließlich gegen
`activity_progress`.

**DoD:** Ein künstlicher sechster Aktivitätstyp lässt sich in einem Test
registrieren, anzeigen, abschließen und bewerten, ohne dass `ProfileService`,
`AchievementService`, das Dashboard oder die Punkterechnung angefasst wurden.
Punkte, Rang, Skill-Radar und öffentliches Profil sind für einen
Bestandsnutzer nach der Migration unverändert. `silent-ct` bleibt spielbar.

### W1 — `ContentValidator` als Service

Das Regelwissen aus `ContentValidate` wandert in einen Service, der eine
normalisierte Struktur prüft und `ContentIssue[]` zurückgibt. Kernregeln
liegen im Service, typspezifische Regeln steuert das jeweilige Modul bei. Das
Artisan-Command wird ein dünner Aufruf, CI bleibt unverändert.

`ContentIssue` bekommt zusätzlich einen optionalen Feldpfad für den Editor;
die Zeilennummer über `LineFinder` bleibt für den Dateiweg erhalten.

**DoD:** `php artisan content:validate` verhält sich wie vorher, nachgewiesen
an den bestehenden `ContentValidateTest`-Fällen. Der Service ist ohne
Dateisystem gegen ein Array aufrufbar und liefert dieselben Befunde.

### W2 — `ContentWriter` und `ContentImporter`

Beide nutzen Serialisierung und Deserialisierung aus W0.

Writer: schreibt eine freigegebene Version nach `content/`. Validierung ist
Vorbedingung. Atomar schreiben, dann `content:sync`, dann Cache-Invalidierung
bei Engine und Orchestrator. Jede erzeugte Datei bekommt einen Kopfkommentar,
der sie als erzeugt ausweist. Ein Pre-Commit-Hook und eine CI-Prüfung weisen
Handänderungen an `content/` ab. `ContentBuild` wird ebenfalls ein Service und
läuft im Schreibweg mit.

Importer: liest den heutigen Bestand einmalig als Startversion ein. Danach nur
noch als ausdrücklicher Admin-Vorgang mit Vollüberschreibung und Warnung, nie
automatisch, nie als Synchronisierung.

**DoD:** Import gefolgt von sofortigem Export lässt alle 41 Lektionen, 10
Nodes, 5 Prüfungen und die Achievements inhaltlich unverändert; ein Diff gegen
den Git-Stand zeigt nur die Kopf-Marker.

### W3 — Versionierung, Freigabe, Rollen, Besitz

- `content_versions`: unveränderliche Snapshots, Status
  `draft | review | published`, Diff, Rollback. Ersetzt die Git-Historie.
- `authors` wird eine echte Beziehung auf `users`.
- Rollen: Lernender, Autor, Reviewer. **Policies statt Rollen-Ifs.**
- `status` wird erstmals wirksam: Entwürfe sind für Lernende unsichtbar.

**DoD:** Ein Autor kann fremden Content nicht bearbeiten. Ein Entwurf
erscheint in keiner Lernendenansicht. Eine veröffentlichte Version lässt sich
zurücksetzen, und `content/` folgt dem.

### W4 — Abzeichen vereinheitlichen und deklarativ auslösen

Zuerst ADR 0070 einlösen: eine Mechanik statt drei, mit Datenmigration. Die
Unterscheidung zwischen global vergebenen und persönlichen Abzeichen bleibt
fachlich und wird ein Feld, keine zweite Tabelle.

Dann das Kriterium in die Definition selbst, ausgewertet gegen
`activity_progress` aus W0:

```yaml
- slug: fundamente-durch
  name: Fundamente durch
  unlock_when:
    type: track_passed      # activity_completed | track_passed | count | first_solve
    track: fundamente
```

Die beiden hartkodierten Vergaben werden darauf umgestellt und verschwinden
aus den Controllern.

**DoD:** Ein Achievement, das nur als Dateneintrag entsteht, wird beim
Abschluss der zugeordneten Aktivität vergeben, ohne eine Zeile
Controller-Code. Bestandsabzeichen sind nach der Migration unverändert
sichtbar, im Dashboard, im Profil und im PDF-Export.

### W5 — Fragenbank und Voraussetzungen

**Fragenbank:** Eine Frage wird ein Objekt mit Typ, Antwort, Skill-Tags und
Rückverweis. Lektionsquiz und Prüfung ziehen aus derselben Bank.
`quiz_reviews` bleibt der einzige Kartenstapel, das ist heute schon so und
bleibt so.

**Voraussetzungen:** `requires` wird durchgesetzt. Eine Aktivität mit offenen
Voraussetzungen wird angezeigt, aber gesperrt, mit Hinweis worauf sie wartet.
Keine harte Verbergung, das widerspricht dem Quereinstieg aus
`content-schema.md` Abschnitt 2.

**DoD:** Dieselbe Frage lässt sich in einem Lektionsquiz und im Prüfungspool
verwenden, ohne sie zweimal zu pflegen. Die Prüfungsziehung bleibt
abdeckungsbalanciert.

### W6 — Die Editoren

Jeder Editor ist die `authorView` seines Moduls, mit Live-Vorschau über
`MarkdownRenderer` und Live-Befunden aus dem `ContentValidator`. In dieser
Reihenfolge:

1. **Quiz.** Frage, Optionen, richtige Antwort in einem Formular. Die
   Aufteilung auf Prosa und 0-basierten Index macht der Writer, nie der Autor.
   Größter Einzelgewinn, deshalb zuerst.
2. **Lektion.** Prosa und Metadaten, Werkzeugauswahl gegen `tools/de.yml`,
   Glossarbegriffe gegen das Glossar, Beispielregel live geprüft.
3. **Prüfung.** Pool aus der Fragenbank, Typmischung und Abdeckung als
   Fortschrittsanzeige statt als Fehlermeldung hinterher, `review`-Anker als
   Dropdown aus den Überschriften der Ziellektion.
4. **Achievement.** Felder aus `achievements.yml`, Bild-Upload,
   Auslösekriterium aus W4 als Auswahl.

**DoD je Editor:** Der Typ lässt sich vollständig ohne Kommandozeile anlegen,
prüfen, einreichen, freigeben und danach benutzen.

### W7 — Erinnerungen

Der Wiederholungsplaner rechnet `due_at` aus, und niemand erfährt davon. Eine
Mail in einstellbarem Abstand mit der Zahl fälliger Karten und einem Link auf
`/de/review`, abschaltbar im Profil, Standard an. Keine Push-Infrastruktur,
keine Kampagnen, kein Digest-Baukasten.

**DoD:** Ein Nutzer mit fälligen Karten erhält genau eine Mail pro Intervall,
ein Nutzer ohne fällige Karten keine, und die Abschaltung wirkt sofort.

---

## 6. Was du ausdrücklich nicht baust

- Alles aus Abschnitt 2 unter „Draußen".
- **Keine LTI-Anbindung und kein cmi5.** Der Ergebnisteil des
  Aktivitätsvertrags ist so zu schneiden, dass beides später ohne
  Kernänderung möglich ist. Mehr nicht.
- **Kein SCORM.**
- **Keinen Editor für Node-Engine-Definitionen** (`environment`, `hosts`,
  `rules`). Nodes bleiben Autorenarbeit über den Importweg.
- **Keine Git-Anbindung.** Kein Commit, kein Push aus der Anwendung.
- **Keine zweite Validierung im Vue-Formular.** Befunde kommen vom Server.
- **Keine Übersetzungsoberfläche.** Mehrsprachigkeit bleibt vorbereitet.

---

## 7. Qualitätsanforderungen

Abschnitt 9 des Vorgängerauftrags gilt unverändert: Pest und pytest, CI mit
Validator, Lint, PHPStan und mypy, Conventional Commits, ein ADR je nicht
trivialer Entscheidung, Policies statt Rollen-Ifs, Tastaturbedienbarkeit und
Kontraste.

Zusätzlich:

- **Die Testdatenbank-Sperren aus ADR 0069 bleiben unangetastet.** Writer und
  Importer sind die ersten Werkzeuge, die im Normalbetrieb schreiben, und
  bekommen dieselbe Härte.
- **W0 und W4 enthalten Datenmigrationen.** Beide sind rückwärts lauffähig und
  werden gegen eine Kopie mit echtem Bestand geprüft, nicht nur gegen
  Fixtures.
- **Zwei neue E2E-Pfade:** Autor legt Lektion mit Quiz an, Reviewer gibt frei,
  Lernender liest und beantwortet eine Karte. Und: Lernender schließt eine
  Aktivität ab und erhält ein datengetriebenes Achievement.

---

## 8. Entscheidungen, die du nicht selbst triffst

Sammle sie in `docs/offene-fragen.md` mit deiner Empfehlung und arbeite an
anderer Stelle derselben Phase weiter:

- **Prüfung je Track oder freier.** `exams/<track>/` erzwingt heute genau eine
  Prüfung pro Track. Zu entscheiden vor W6.3.
- **Herkunft von `authors` beim Import.** Der Bestand trägt Freitext
  („ley338"). Ob das auf ein Konto auflöst oder als Historie stehenbleibt,
  entscheidet der Betreiber, vor W2.
- **Engine-Modul je Themenfeld.** Vorbereitet über `EngineClientContract`,
  nicht Teil dieses Auftrags.
- Lizenzmodell, Betriebskosten und Kontingente der Spielwiese: unverändert
  offen.

---

## 9. Abnahmekriterium

Auf einem frischen Server mit Docker, nach `make up && make seed`:

1. Ein Konto mit der Rolle Autor sieht einen Autorenbereich.
2. Dieser Autor legt eine Lektion mit drei Quizfragen an, ohne Kommandozeile
   und ohne Git. Verstöße gegen die Beispielregel und die
   Werkzeug-Umkehrprüfung erscheinen beim Schreiben am Feld.
3. Ein Reviewer gibt frei, die Lektion erscheint für Lernende,
   `content/lessons/<id>/` existiert als erzeugte Datei und ist mit
   `make content-validate` grün.
4. Der Autor legt ein Achievement an, bindet es an diese Lektion, und ein
   Lernender erhält es beim Abschluss.
5. Eine Frage aus der Bank wird zusätzlich in den Prüfungspool aufgenommen;
   die Ziehung bleibt abdeckungsbalanciert und die Antwort landet in
   `quiz_reviews`.
6. Die Spielwiese lässt sich aus einer Lektion **und** aus einer anderen
   Aktivität heraus starten.
7. Ein Nutzer mit fälligen Wiederholungskarten bekommt eine Erinnerung.
8. `silent-ct` ist unverändert spielbar; Punkte, Ränge und das öffentliche
   Profil verhalten sich wie vorher.

Wenn das durchläuft, ist der Ausbau zum Lightweight LMS fertig.
