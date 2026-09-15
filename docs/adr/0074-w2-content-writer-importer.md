# 0074 — W2 ContentWriter/ContentImporter: Umsetzungsentscheidungen

## Status

Angenommen, 15.09.2026. Konkretisiert ADR 0071/0072 fuer Arbeitsphase W2 aus
`dcm-lab-lms-agent-prompt.md`, im Anschluss an ADR 0073 (W0) und die
ContentValidator-Extraktion (W1).

## Kontext

ADR 0071 beschreibt den Schreibweg als
`Freigabe -> ContentValidator -> ContentWriter -> content/**`, wobei
"Freigabe" eine `content_versions`-Zeile mit Status `published` voraussetzt.
Diese Tabelle entsteht erst in W3. W2 baut ContentWriter und ContentImporter
deshalb zwangslaeufig gegen die einzige Quelle, die es bis W3 gibt: die
`serialize()`/`deserialize()`-Methoden aus dem Aktivitaetsvertrag (W0), die
heute den unveraenderten Ist-Zustand widerspiegeln (ADR 0073). Drei
Entscheidungen waren dabei noetig.

## Entscheidungen

**1. `ContentWriter` wird jetzt gebaut und getestet, aber nicht gegen die
echte `content/` ausgefuehrt.** Ein Testlauf gegen den realen Bestand haette
bei den heutigen Passthrough-Serialisierern (ADR 0073) den einzigen
sichtbaren Effekt, allen ~50 Lektions-/Node-/Pruefungsdateien einen
Kopf-Marker voranzustellen -- eine mechanische, aber ueber hundert Dateien
verteilte Aenderung am realen Lernstoff. Das ist kein Schritt, der in einem
automatisierten Agentenlauf ohne Review entstehen sollte. Der W2-DoD
("Import gefolgt von sofortigem Export laesst den Bestand unveraendert")
wird stattdessen durch `ContentWriterTest` bewiesen: ein Test-Aktivitaetstyp
mit kontrolliertem `serialize()`-Ergebnis durchlaeuft Schreiben, erneutes
Schreiben (Idempotenz) und Marker-Pruefung gegen einen temporaeren
Content-Ordner. Das tatsaechliche Anwenden auf `content/` ist eine bewusste
Folgeentscheidung des Betreibers, sobald ein Editor (W6) echte Entwuerfe
erzeugt, die es wert sind, geschrieben zu werden.

**2. `ContentBuild` wird nach demselben Muster wie `ContentValidate` (W1) in
einen Service (`ContentBuilder`) extrahiert, mit identischem
Fehlerverhalten.** Das Artisan-Command bleibt ein duenner Aufruf.
`ContentWriter` ruft `ContentBuilder::build()` nach jedem Schreiben auf, wie
ADR 0071 es fuer den Schreibweg vorsieht ("ContentBuild wird ebenfalls ein
Service und laeuft im Schreibweg mit"). Die urspruenglichen drei
Fehlerpfade des Commands (fehlender `source_tag`: uebersprungen mit Hinweis;
nicht aufloesbarer Resolver/Datensatz: harter Abbruch mit Exception; fehlende
`flag.hash`-Zeile: uebersprungen mit Hinweis) bleiben bitgenau erhalten,
nachgewiesen an allen neun bestehenden `ContentBuildTest`-Faellen ohne
Anpassung.

**3. Cache-Invalidierung bleibt ein No-op (`NullCacheInvalidator`).** Echte
Invalidierung braucht neue Endpunkte in drei separaten FastAPI-Diensten
(`services/engine`, `services/scenario-engine`, `services/sandbox`) --
eine dienstuebergreifende Aenderung, die eine eigene Entscheidung und einen
eigenen Test-/Deploy-Zyklus verdient. `CacheInvalidatorContract` (nach dem
Vorbild von `EngineClientContract`) macht die Erweiterung an genau einer
Stelle moeglich, ohne `ContentWriter` anzufassen. Festgehalten in
`docs/offene-fragen.md` mit Empfehlung.

## Konsequenzen

**Was W2 liefert:** `App\Content\ContentWriter` (Validierung als
Vorbedingung, atomares Schreiben ueber Temp-Datei + Move, Kopf-Marker ueber
`GeneratedFileMarker`, `content:sync` und `ContentBuilder` im Anschluss),
`App\Content\ContentImporter` (Lesepfad fuer einen kuenftigen
Bestandsimport), `App\Content\ContentBuilder` als Service, `GeneratedFileMarker`
mit idempotenter Markierung fuer YAML- und Markdown-Dateien,
`CacheInvalidatorContract` mit No-op-Standardimplementierung.

**Was W2 nicht liefert:** einen Pre-Commit-Hook und eine CI-Pruefung gegen
Handaenderungen an markierten Dateien (ADR 0071, "Schutz der Einbahnstraße").
Da bisher keine reale Datei unter `content/` den Marker traegt (Punkt 1
oben), haette eine solche Pruefung heute nichts zu pruefen -- sie wird
sinnvoll, sobald W6 tatsaechlich Dateien ueber den Editor erzeugt, und
kommt an der Stelle nach.

## Verifikation

- `ContentWriterTest` beweist: Schreiben mit offenen `ContentIssue`s findet
  nicht statt; jede erzeugte Datei traegt den Marker; wiederholtes Schreiben
  verdoppelt den Marker nicht und hinterlaesst keine Temp-Dateien; Export
  nach Import unterscheidet sich nur um den Marker.
- `GeneratedFileMarkerTest` beweist Idempotenz fuer YAML- (Kommentarzeile)
  und Markdown-Dateien (HTML-Kommentar direkt nach der Frontmatter).
- Alle neun bestehenden `ContentBuildTest`-Faelle bleiben unveraendert
  gruen gegen den refaktorierten `ContentBuilder`.
- Alle 259 Tests, `phpstan analyse` (Level 7) und `pint --test` sind gruen;
  `content/` selbst wurde durch diese Aenderung nicht angefasst.
