# 0088 — Bild-Upload im Achievement-Editor

## Status

Angenommen, 15.09.2026. Loest die offene Frage "Bild-Upload im
Achievement-Editor" (`docs/offene-fragen.md`, ADR 0083, W6.4).

## Kontext

ADR 0083 hielt `image` bewusst als Freitextfeld -- ein Upload brauche
einen eigenen, sicherheitsgeprueften Endpunkt ausserhalb der
`content_versions`-Transaktion, weil Achievement-Bilder unter
`public/images/achievements/` liegen, nicht unter `content/`, und
`ContentWriter` ausschliesslich Text unter `content/` schreibt.

## Entscheidung

**`AchievementEditorController::uploadImage()`** (neuer Endpunkt
`POST author/achievements/{slug}/edit/image`, `throttle:10,1`) schreibt
SOFORT und direkt nach `public/images/achievements/`, unabhaengig vom
Entwurfs-/Freigabe-Kreislauf -- exakt wie schon das Freitextfeld es
erlaubte (ein Achievement-Bild war nie Teil des versionierten Review/
Rollback-Wegs, siehe ADR 0083). Der Upload aendert daran nichts, er
ersetzt nur die Handeingabe des Dateinamens.

**Sicherheitsmassnahmen:**

- `Gate::authorize('update', $activity)` -- derselbe Autorenschutz wie
  jeder andere Editor-Endpunkt.
- **Der Dateiname wird immer aus dem URL-Slug erzeugt
  (`Str::slug($slug).'.'.$file->extension()`), nie aus dem vom Client
  gesendeten Originalnamen.** Verhindert jede Form von Pfad-Traversal
  unabhaengig davon, was der Upload mitschickt -- der Slug selbst kann
  laut Laravels Standard-Routing ohnehin kein `/` enthalten.
  `UploadedFile::extension()` leitet die Endung aus dem tatsaechlich
  erkannten MIME-Typ ab (Symfonys `guessExtension()`), nicht aus dem
  Client-Dateinamen.
- `'image' => 'required|image|mimes:png,jpg,jpeg,webp|max:512'` --
  Laravels `image`-Regel prueft echten Bildinhalt (nicht nur die
  Endung), `mimes` grenzt auf drei Webformate ein, `max:512` (KB)
  begrenzt die Groesse einer Badge-Grafik grosszuegig, aber endlich.
- Ein erneuter Upload fuer denselben Slug ueberschreibt die bestehende
  Datei -- gewuenscht (Bild austauschen), kein Aufraeumen alter
  Dateien mit anderer Endung (kleine, hinnehmbare Ungenauigkeit bei
  einem Formatwechsel).

**Frontend:** `resources/js/lib/api.ts` bekommt `postFormData()`
(derselbe XSRF-Header-Umgang wie `postJson()`, aber ohne
`Content-Type`, damit der Browser selbst die Multipart-Boundary
setzt). `AchievementEditor.vue` haengt ein Datei-Eingabefeld neben das
bestehende Freitextfeld -- ein erfolgreicher Upload fuellt `fields.image`
automatisch mit dem zurueckgegebenen Dateinamen, das Freitextfeld
bleibt fuer bereits vorhandene Dateien nutzbar.

## Konsequenzen

- `image` ist jetzt entweder per Upload oder weiterhin per Freitext
  pflegbar -- der Achievement-Typ ist damit "vollstaendig ohne
  Kommandozeile anlegbar" (Abnahmekriterium aus Abschnitt 5), die
  DoD-Einschraenkung aus ADR 0083 entfaellt.

## Verifikation

- `AchievementEditorControllerTest`: eine Lernperson darf nicht
  hochladen (403); ein gueltiges Bild wird unter dem erwarteten
  Dateinamen gespeichert und existiert danach wirklich auf der
  Platte; ein Nicht-Bild (`.pdf`) und ein zu grosses Bild werden
  abgelehnt (422); ein absichtlich boesartiger Client-Dateiname
  (`../../evil.png`) hat keinen Einfluss auf den tatsaechlich
  geschriebenen Dateinamen -- der bleibt immer `<slug>.<echte-endung>`.
- Alle 369 Tests, `phpstan analyse` (Level 7), `pint --test`,
  `npm run check` und `npm run build` sind gruen.
