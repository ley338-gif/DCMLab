# Offene Fragen

Entscheidungen aus dem Ausbau zum Lightweight LMS (`dcm-lab-lms-agent-prompt.md`),
die der Betreiber trifft, nicht der Code-Agent. Format: Frage, Kontext,
Empfehlung. Erledigte Punkte werden hier durchgestrichen, nicht geloescht.

## Cache-Invalidierung bei Engine/Sandbox nach einer Veroeffentlichung (W2)

**Frage:** Sollen `services/engine`, `services/scenario-engine` und
`services/sandbox` einen Endpunkt bekommen, den `ContentWriter` nach jedem
Schreiben aufruft, um ihren `lru_cache`-Inhalt sofort zu invalidieren?

**Kontext:** `ContentWriter` (ADR 0071/0074) haengt hinter
`CacheInvalidatorContract` aktuell `NullCacheInvalidator` (No-op) ein. Die
drei Python-Dienste cachen `content/` pro Prozess und lesen es erst beim
naechsten Neustart neu ein -- eine frisch veroeffentlichte Lektion/Node
erscheint dort also erst nach einem Neustart/Deploy des jeweiligen Dienstes,
nicht sofort.

**Empfehlung:** Einen einfachen `POST /internal/cache/clear`-Endpunkt in
allen drei Diensten ergaenzen (loescht den `lru_cache`), abgesichert mit
demselben `X-DCMLAB-KEY`-Header wie die bestehende Engine-Anbindung.
`HttpCacheInvalidator implements CacheInvalidatorContract` ruft alle drei
parallel auf und loggt, statt zu werfen, wenn einer nicht erreichbar ist --
eine kurzzeitig veraltete Engine ist kein Grund, eine Veroeffentlichung
abzubrechen. Aufwand: klein, aber dienstuebergreifend (drei FastAPI-Apps),
deshalb bewusst nicht im selben Schritt wie `ContentWriter` erledigt.

## Pruefung je Track oder freier (vor W6.3)

**Frage:** Bleibt `exams/<track>/` bei genau einer Pruefung pro Track, oder
sollen modul-/trackuebergreifende Pruefungen moeglich werden?

**Kontext:** Der heutige Aufbau (ein `exam.yml` je Track-Slug) ist im
Aktivitaetsvertrag als `ExamActivity` 1:1 auf `Track` abgebildet. Eine
Erweiterung auf mehrere Pruefungen pro Track oder trackuebergreifende
Pruefungen ist nach dem Pruefungs-Editor (W6.3) teurer als davor.

**Empfehlung:** Bei genau einer Pruefung pro Track bleiben, solange kein
konkreter fachlicher Bedarf fuer mehrere vorliegt -- Konzept-Abschnitt 4
(selbstgesteuertes Lernen) nennt keinen.

## Herkunft von `authors` beim Import (vor W2/W3)

**Frage:** Loest der Bestandscontent-Freitext in `authors` (z. B. `"ley338"`)
beim einmaligen Import auf ein echtes Nutzerkonto auf, oder bleibt er als
Historie stehen?

**Kontext:** ADR 0071 macht `authors` zu einer echten Beziehung auf `users`
(W3). Der heutige Bestand traegt dort Freitext ohne Kontobezug.

**Empfehlung:** Freitext als Historie in einem zusaetzlichen Feld
(`legacy_authors` o. ae.) belassen und `authors` beim Import leer lassen,
statt zu raten, welches Konto gemeint ist -- eine falsche automatische
Zuordnung waere schlechter als keine.

## Engine-Modul je Themenfeld (nicht Teil dieses Auftrags)

**Frage:** Wann bekommt ein Themenfeld ausserhalb DICOM (z. B. das
Datenschutz-PoC-Modul aus P10.69) einen eigenen `services/*-engine`-Dienst?

**Kontext:** `EngineClientContract`/`EngineClientResolver` sind seit P10.68
dafuer vorbereitet (siehe `docs/adr/0071-...md` Abschnitt "Offene Punkte").

**Empfehlung:** Erst nach W0-W7, wenn der Aktivitaetsvertrag und die
Autorenschicht stehen -- ein zweites Engine-Modul jetzt zu bauen wuerde
gegen einen sich noch aendernden Vertrag entwickeln.

## Lizenzmodell, Betriebskosten und Kontingente der Spielwiese

**Frage:** Unveraendert offen, siehe `dcm-lab-agent-prompt.md` und
`dcm-lab-lms-agent-prompt.md` Abschnitt 8. Kein neuer Befund in diesem
Ausbau.
