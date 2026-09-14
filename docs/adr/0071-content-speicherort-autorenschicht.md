# 0071 — Autorenschicht: Speicherort von Lernstoff

## Status

Angenommen, 15.09.2026. Ersetzt Leitplanke 4 aus `dcm-lab-agent-prompt.md`
Abschnitt 1 und die erste Zeile von Abschnitt 11 desselben Dokuments.

## Kontext

Das Ziel hat sich erweitert: Die Plattform soll ein vollwertiges LMS werden.
Konkret verlangt sind drei Dinge, die es heute nicht gibt:

1. Lektionen müssen von anderen Personen geschrieben werden können.
2. Erfolge müssen angelegt und Lerninhalten zugeordnet werden können.
3. Quizze und Abschlussprüfungen müssen angelegt werden können.

Alle drei scheitern an derselben Stelle. `content/` ist ein reiner Lesepfad:
`ContentRepository` liest das Dateisystem, `content:sync` schreibt einen Index
in die Datenbank, `content:validate` prüft, `content:build` erzeugt
Flag-Hashes. Es existiert kein Weg, über den die Anwendung Lernstoff erzeugt.
Für einen Autor mit Git-Zugang ist das kein Mangel, für jeden anderen ist es
die vollständige Hürde.

Die Fachlogik dagegen ist vorhanden und tragfähig:

- Quiz: `quiz:` in `meta.yml` plus `## Quiz`-Block in `de.md`, geparst von
  `QuizContent`, terminiert über `QuizSchedulerService` in `quiz_reviews`.
- Prüfung: `exams/<track>/{exam.yml,de.md}`, `ExamContent`,
  `ExamAttemptService` mit abdeckungsbalancierter Ziehung, `exam_attempts`,
  `track_badges`. Prüfungsantworten fließen in dieselbe `quiz_reviews`-Tabelle
  wie die Lektionskarten, kein zweiter Kartenstapel.
- Achievements: `content/achievements.yml` → `AchievementRegistry` →
  `AchievementSeeder` → `achievement_definitions`; Vergabe über
  `AchievementService::unlock()` nach `achievement_unlocks`.
- Themenfeld-Ebene, `EngineClientContract` und `SandboxClientContract` seit
  P10.68 vorhanden.

Es fehlt also keine Mechanik, sondern der Schreibweg und das Werkzeug davor.

Zwei Befunde haben die Entscheidung geprägt:

- **`ContentValidate` ist ein Artisan-Command mit 930 Zeilen Regelwissen**
  (Beispielregel, Werkzeug-Umkehrprüfung, Anker-Auflösung, Typmischung im
  Prüfungspool, Pflichtfelder in `achievements.yml`). Diese Regeln sind der
  eigentliche Qualitätswert des Projekts. Jeder Weg, der sie nicht
  wiederverwendet, erzeugt zwangsläufig eine zweite, schwächere Prüfung im
  Editor-Formular.
- **Die Python-Dienste lesen `content/` direkt vom Dateisystem.**
  `services/engine/app/content.py` und `services/sandbox/app/datasets_yaml.py`
  lesen über `CONTENT_PATH` mit `lru_cache`. Sie hängen genauso am Dateiformat
  wie Laravel. Ein Umzug des Lernstoffs in die Datenbank betrifft damit nicht
  nur `apps/web`, sondern beide Dienste.

## Entscheidung

**Die Datenbank wird die Autoren-Wahrheit. `content/` wird ein erzeugtes
Artefakt, kein Speicherort.**

Der Fluss läuft in genau eine Richtung:

```
Editor (Browser)
   └─> content_drafts / content_versions   (DB, versioniert, Status draft|review|published)
          └─ Freigabe ─> ContentValidator  (derselbe Regelsatz wie CI)
                 └─> ContentWriter ─> content/**   (erzeugte Dateien)
                        ├─> content:sync            (Laravel-Index)
                        ├─> services/engine         (liest node.yml wie bisher)
                        └─> services/sandbox        (liest datasets.yml wie bisher)
```

Dateien unter `content/` werden erzeugt, nie von Hand bearbeitet und nie
zurückgelesen. Das ist dieselbe Beziehung, die `content:build` heute schon zu
`node.yml` hat: etwas erzeugt eine Datei, die danach die einzige gelesene
Quelle ist.

**Der Grundsatz, der jeden Zweifelsfall entscheidet: es gibt zu jeder Frage
genau ein System.** Ein Speicherort, eine Validierung, ein Kartenstapel
(`quiz_reviews`), ein Abzeichen-System, ein Renderpfad (`MarkdownRenderer` +
`HeadingSlug`), ein Auswertungsdienst (`EngineClientContract`). Entsteht bei
einer Umsetzung eine zweite Variante davon, ist die Umsetzung falsch.

### Verworfene Alternativen

**A — Dateien bleiben die Wahrheit, die Anwendung schreibt sie per Git.**
Der Editor hält eine Arbeitskopie, committet als Autor, Review läuft als Pull
Request. Verworfen, weil die Anwendung damit zum Git-Client mit
Schreibzugriff, Konfliktbehandlung, Branch-Verwaltung und Deploy-Zyklus wird.
Das ist genau die Art Betriebskomplexität, an der das Nachbarprojekt HNR im
Alltag gescheitert ist. Ein externer Dozent soll ein Formular ausfüllen, nicht
auf einen Merge warten.

**B — Alles in die Datenbank, auch zur Laufzeit.**
Verworfen wegen des zweiten Befundes oben: `services/engine` und
`services/sandbox` müssten eine API gegen Laravel bekommen oder ihre
Content-Zugriffe umbauen. Das ist der teuerste Posten am gesamten Vorhaben und
kauft nichts, was die erzeugten Dateien nicht auch liefern.

**C — Autoren schreiben in die Datenbank, Bestandscontent bleibt in Git.**
Verworfen ohne Prüfung. Zwei Quellen, zwei Validierungen, zwei Renderpfade.
Genau das Parallelsystem, das dieser ADR verhindern soll.

## Konsequenzen

**Was unverändert bleibt:** `services/engine`, `services/sandbox`, die
Container, `ContentRepository` (Leseseite), `content:sync`, `QuizContent`,
`ExamContent`, `AnswerGrader`, `QuizSchedulerService`, `ExamAttemptService`,
`quiz_reviews`, `exam_attempts`, `MarkdownRenderer`, `HeadingSlug`. Das
Content-Schema aus `docs/content-schema.md` bleibt wörtlich gültig, es wird
künftig nur erzeugt statt getippt.

**Was neu entsteht:** eine Autorenschicht (Entwürfe, Versionen, Freigabe), ein
Rollen- und Besitzmodell, vier Editoren.

**Was umgebaut wird:**

- `ContentValidate` wird ein Service, aufgerufen von CI und Editor. Das
  Artisan-Command bleibt als dünner Aufruf bestehen, damit CI unverändert
  läuft.
- `status` (`draft | review | published`) und `authors` existieren bereits im
  Schema und werden heute nirgends ausgewertet. Sie werden erstmals wirksam:
  `status` steuert Sichtbarkeit, `authors` löst auf echte Nutzer auf.
- Die drei Abzeichen-Mechaniken (`achievements` global pro Node,
  `track_badges` pro Nutzer und Track, `achievement_definitions` /
  `achievement_unlocks`) werden zusammengeführt. ADR 0070 hat das bewusst
  vertagt und im UI mit „Pionier" gegen „Achievements" umschifft. Vor dem
  Achievement-Editor ist das einzulösen, sonst schreibt ein Autor gegen drei
  Systeme.
- Achievements bekommen ein deklaratives Auslösekriterium. Heute sind zwei
  Vergaben hartkodiert (`first-blood` in `NodeController`, `sandbox-starter`
  in `SandboxController`), und Lektionen wie Prüfungen vergeben gar nichts.

**Was wegfällt:** Git als Content-Speicher, Pull Requests als Review-Weg,
`authors` als Freitextliste.

**Schutz der Einbahnstraße.** Der wahrscheinlichste Weg, wie dieser Entwurf
kaputtgeht, ist der Betreiber selbst, der eine Kleinigkeit schnell in der
Datei korrigiert. Drei Maßnahmen dagegen:

1. Jede erzeugte Datei trägt einen Kopfkommentar, der sie als erzeugt
   ausweist und auf den Editor verweist.
2. Ein Pre-Commit-Hook und eine CI-Prüfung weisen Handänderungen an
   `content/` ab.
3. Der Importer bleibt erhalten, aber ausschließlich als ausdrücklicher
   Admin-Vorgang mit Vollüberschreibung der Entwurfsdaten und deutlicher
   Warnung. Nie automatisch, nie als Synchronisierung.

**Risiko, das bestehen bleibt:** Ist der Editor langsamer zu bedienen als eine
Datei im Texteditor, wird er umgangen. Das ist kein Architekturproblem,
sondern eine Anforderung an die Umsetzung, und es ist das einzige Kriterium,
an dem dieser Umbau im Alltag scheitern kann.

## Offene Punkte, die vor dem Editor zu entscheiden sind

- **Prüfung je Track oder freier.** `exams/<track>/` erzwingt heute genau eine
  Prüfung pro Track. Modul- oder trackübergreifende Prüfungen wären eine
  Schema-Erweiterung, die nach dem Editor teuer wird.
- **Engine-Modul je Themenfeld.** `services/engine` wertet DICOM-Befehle aus.
  Ein Themenfeld außerhalb DICOM braucht eine andere Auswertung. Konzept
  Abschnitt 13 sieht dafür ein Modul je Themenfeld vor, der Vertrag dafür ist
  seit P10.68 mit `EngineClientContract` vorbereitet, aber nicht ausgeführt.

## Verifikation

- Eine Person ohne Git-Zugang und ohne Kommandozeile legt eine Lektion mit
  Quiz an, reicht sie zur Prüfung ein, ein Reviewer gibt sie frei, und die
  Lektion erscheint für Lernende, ohne dass jemand etwas manuell ausführt.
- `make content-validate` ist grün gegen die erzeugten Dateien, und der
  Editor hat dieselben Meldungen vorher am Feld angezeigt.
- `silent-ct` bleibt spielbar, Prüfung und Wiederholungskarten verhalten sich
  unverändert.
- Ein Achievement, das in der Oberfläche angelegt und an eine Lektion
  gebunden wurde, wird beim Abschluss dieser Lektion vergeben, ohne dass dafür
  eine Zeile Controller-Code entstanden ist.
