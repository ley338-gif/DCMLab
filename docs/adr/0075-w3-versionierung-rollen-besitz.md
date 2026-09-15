# 0075 — W3 Versionierung, Rollen, Besitz: Umsetzungsentscheidungen

## Status

Angenommen, 15.09.2026. Konkretisiert ADR 0071 fuer Arbeitsphase W3 aus
`dcm-lab-lms-agent-prompt.md`, im Anschluss an ADR 0073 (W0), die
ContentValidator-Extraktion (W1) und ADR 0074 (W2).

## Kontext

W3 verlangt drei Dinge: `content_versions` als unveraenderliche
Snapshots mit Rollback, `authors` als echte Beziehung auf `users`, und
Rollen/Policies statt Rollen-Ifs, mit dem Ziel, dass `status` "erstmals
wirksam" wird -- Entwuerfe sollen fuer Lernende unsichtbar sein. Beim
Umsetzen sind zwei Befunde aufgetaucht, die vor dem naechsten Schritt
festgehalten werden muessen.

## Entscheidungen

**1. Versionierung und ContentWriter bleiben getrennt.** `content_versions`
speichert `payload` in der Form, die `ActivityContract::deserialize()`
liefert. Ein `publish()` oder `rollback()` dieses Service schreibt jedoch
NICHT automatisch nach `content/`: `serialize()` liest heute noch immer den
Ist-Zustand der Datei, nicht den gespeicherten `payload` (ADR 0073). Ein
Rollback wuerde also `content_versions` korrekt zuruecksetzen, aber
`content/` bliebe unveraendert -- ein stiller Widerspruch zwischen DB und
Datei, schlimmer als gar keine Automatisierung. `ContentVersioningService`
ist deshalb reine Buchfuehrung (draft/review/published-Uebergaenge,
`is_current`-Verwaltung, Rollback auf die vorherige veroeffentlichte
Version); das tatsaechliche "content/ folgt dem" aus dem W3-DoD wird erst
eingeloest, sobald ein Editor (W6) `payload` in einer Form befuellt, aus der
`serialize()` wirklich rendern kann. Bis dahin ist der Weg vorbereitet
(`ContentWriter` ist bereits agnostisch gegenueber der Herkunft der
Dateiinhalte, siehe ADR 0074), aber nicht verdrahtet.

**2. Sichtbarkeit fuer Lernende wird NICHT an das bestehende
`status`-Feld von `lessons`/`nodes` gekoppelt.** Eine Bestandsaufnahme des
echten Contents zeigt: 34 Lektionen/Nodes tragen `status: draft`, 25 tragen
`status: fertig` -- eine Zeichenkette, die im dokumentierten Schema
(`draft | review | published`, `docs/content-schema.md`) gar nicht vorkommt.
**Kein einziger realer Datensatz traegt `status: published`.** Wuerde die
Lernendenansicht jetzt nach `status === 'published'` filtern, verschwaende
der komplette heutige Lernstoff aus der Anwendung -- eine Regression, die
jeder Verifikationszeile in ADR 0071/0072/0073 widerspricht
("`silent-ct` bleibt spielbar", "Punkte ... unveraendert"). Stattdessen gilt:
eine Aktivitaet ohne jede Zeile in `content_versions` zaehlt als sichtbar
(`ContentVersioningService::isPublished()` liefert `true`) -- das betrifft
heute jede reale Aktivitaet, da `content_versions` leer ist, solange kein
Editor (W6) hineinschreibt. Die Regel greift also erst fuer echte Entwuerfe,
die ein Editor tatsaechlich anlegt, nie fuer Bestandscontent. Das
`fertig`/`draft`-Wirrwarr im echten Content ist als eigene Frage in
`docs/offene-fragen.md` festgehalten -- eine Normalisierung auf das
dokumentierte Schema ist eine redaktionelle Entscheidung des Betreibers,
keine, die ein Code-Agent automatisiert durchziehen sollte.

## Konsequenzen

**Was W3 liefert:** `users.role` (`learner` | `author` | `reviewer`,
Default `learner`), `App\Policies\ActivityPolicy` (`update`: Reviewer immer,
Autor nur fuer eigene Aktivitaeten via `activity_authors`; `publish`: nur
Reviewer), die Pivot-Tabelle `activity_authors` als echte Autoren-Beziehung
neben der bestehenden Freitextspalte, `content_versions` mit
`ContentVersioningService` (Lebenszyklus + Rollback + `isPublished()`).

**Was W3 nicht liefert:** die tatsaechliche Verdrahtung von `isPublished()`
in `NodeController::index/show`, `LessonController::show`,
`TrackController` o. ae. -- das waere heute wirkungslos (siehe Entscheidung
2), aber es lohnt sich, den Kontrollfluss dafuer erst zu bauen, wenn er
etwas zu filtern hat. Ebenso unveraendert: der Bestandsimport von `authors`
in `activity_authors` (echte Konten fuer den Freitext-Bestand) -- abhaengig
von der in `docs/offene-fragen.md` offenen Frage, wie Freitext auf Konten
abgebildet wird.

## Verifikation

- `ActivityPolicyTest` beweist den W3-DoD: ein Reviewer darf jede
  Aktivitaet bearbeiten, ein Autor nur eigene, ein Lernender keine.
- `ContentVersioningServiceTest` beweist den vollen Lebenszyklus
  (draft -> review -> published), dass ein neues Publish die vorherige
  aktuelle Version ablöst (`is_current`), dass Rollback die payload der
  vorherigen Version wiederherstellt, und dass eine Aktivitaet ohne
  Versionshistorie als sichtbar gilt.
- Alle 272 Tests, `phpstan analyse` (Level 7), `pint --test` und
  `npm run check` sind gruen; kein bestehender Controller wurde veraendert,
  die Sichtbarkeit von Bestandscontent ist unveraendert.
