# 0058 — P10.49: Lektion 5.3 (Migration) — echter Zwei-Archiv-Test, `sandbox.required` als inertes Feld entlarvt

Status: akzeptiert
Datum: 2026-09-14

## Kontext

Lektion 5.3 lag seit P10.46 (ADR 0055) als Gerüst vor, mit einer
offenen Frage: Trägt der in ADR 0055 vorsichtig vorgeschlagene
Hands-on-Baustein (zwei Orthanc-Instanzen, echter Export/Import,
echte UID-Diffs) tatsächlich, oder bleibt die Lektion konzeptionell?

**Vor dem Schreiben real getestet, nicht angenommen.**

## Der reale Test: zwei isolierte Orthanc-Instanzen

Zwei `:test`-getaggte Orthanc-Container (`dcmlab/orthanc:test`, aus dem
bestehenden `containers/orthanc`-Rezept gebaut, niemals die geteilten
`:latest`-Tags berührt) plus ein `:test`-getaggter Toolbox-Container auf
einem eigenen, isolierten Docker-Netz (`dcmlab-migration-test`) — außerhalb
der Standard-Sandbox-Orchestrierung, da diese pro Sitzung nur ein
einziges Orthanc bereitstellt und eine Migration per Definition Quelle
und Ziel braucht.

**Test 1 — korrekte Migration (Netzwerktransfer, keine UID-Neuvergabe):**
Drei echte, per `datasets/build/generate.py` erzeugte CT-Instanzen
real per `storescu` nach Orthanc A gesendet, per REST
(`GET /instances/{id}/file`) zurückgeholt (simuliert den
Export-Schritt eines Migrationstools), per `storescu` nach Orthanc B
weitergeleitet (Import-Schritt). Ergebnis, real per `dcmdump`-Diff und
`findscu` bestätigt:
- `StudyInstanceUID`, `SeriesInstanceUID`, `SOPInstanceUID`,
  Pixeldaten: identisch, Byte für Byte, auf beiden Archiven.
- Sogar Orthancs eigene interne Instanz-ID (deterministisch aus den
  DICOM-Identifiers abgeleitet) ist auf A und B identisch — ohne jede
  Koordination zwischen den Instanzen.
- Einzige real gemessene Änderung: `FileMetaInformationGroupLength`
  (206→208 Bytes), `ImplementationClassUID`/`ImplementationVersionName`
  wechseln von `PYDICOM 3.0.2` auf `OFFIS_DCMTK_370` — Orthanc schreibt
  beim Export seine eigene Implementierungssignatur, nicht die des
  ursprünglichen Erzeugers.

**Test 2 — kaputte Migration (UID-Neuvergabe):** Dieselbe Studie
erneut an B gesendet, diesmal mit real per `pydicom` frisch generierter
`StudyInstanceUID`/`SeriesInstanceUID`/`SOPInstanceUID` (alles andere
identisch, gleicher `PatientName`/`PatientID`). Reales `findscu`-Ergebnis
auf B danach: **zwei getrennte Studien** für denselben Patienten (3
Instanzen in der korrekten, 1 Instanz in der neu-UID-vergebenen) — kein
Fehlercode, keine Warnung, beide für sich genommen vollständig gültige
DICOM-Objekte.

Ein dritter geplanter Test (Überleben eines Private Tags über den
Roundtrip) wurde nach zwei erfolglosen `dcmodify -i`-Versuchen
(„Corrupted data" bei der Dateneinlement-Einfügung, vermutlich
VR-Mehrdeutigkeit bei unbekannten privaten Tags) **nicht** weiterverfolgt
— das Zeitbudget dafür war unverhältnismäßig zum bereits vorhandenen,
starken Befund aus Test 1/2. Der Private-Tag-Risikopunkt bleibt in der
Lektion als zitierte, aber nicht live verifizierte Aussage.

## Wichtiger Nebenfund: `sandbox.required` ist ein inertes Feld

Vor dem Festlegen von `sandbox.required: false` für diese Lektion
geprüft, was dieses Feld im Code überhaupt bewirkt — **es wird an
keiner Stelle in `apps/web/app` gelesen.** Was tatsächlich steuert, ob
der „Spielwiese starten"-Button erscheint, ist ausschließlich, ob
irgendein in `tools` deklariertes Werkzeug in `content/tools/de.yml`
`needs_sandbox: true` trägt (`LessonController::toolbarData()`,
Zeile ~114). `sandbox.dataset` steuert unabhängig davon nur, ob ein
„Liegt bereit"-Hinweis mit einem bestimmten Datensatz angezeigt wird —
der Toolbox-Container stellt unabhängig davon immer `ct-thorax-60`
bereit.

**Konsequenz für diese und künftige Track-5-Lektionen:** Die
Design-Prämisse aus ADR 0055 ("wir setzen `sandbox.required: false`,
damit kein Sandbox-Button erscheint") war auf einer ungeprüften
Annahme über die Wirkung dieses Felds aufgebaut. Für 5.3 im Ergebnis
unkritisch — `storescu` ist ein Werkzeug mit `needs_sandbox: true`, der
Button erscheint also ohnehin (was hier sogar sinnvoll ist: Lernende
können `storescu`/`dcmdump` im Standard-Sandbox selbst ausprobieren,
auch wenn die volle Zwei-Archiv-Migration dort nicht nachstellbar ist).
`sandbox.required` bleibt dennoch als **dokumentierendes** Feld
sinnvoll (hält fest: „das *vollständige* Beispiel dieser Lektion
braucht mehr als die Standard-Sandbox"), hat aber keine
Validierungs- oder UI-Wirkung. Für Lektionen mit ausschließlich
`needs_sandbox: false`-Werkzeugen (wie 5.1/5.2 vor ihrer Korrektur)
erscheint der Button entsprechend nicht — unabhängig vom eigenen
`sandbox.required`-Wert.

## Entscheidung — echter Hands-on-Anteil, außerhalb der Standard-Sandbox

**`content/lessons/5.3/de.md`**: vollständig neu geschrieben, mit
transparenter Offenlegung, dass die Befunde in einer eigens
aufgesetzten Zwei-Archiv-Umgebung erfasst wurden (nicht in der
Standard-Sandbox reproduzierbar) — beide Testtranskripte als
`<!-- kein-beispiel -->`-Blöcke (Abschnitt 13: real, aber nicht in der
Standard-Sandbox nachstellbar), mit Erklärung direkt im Block statt
einer nachfolgenden „Was du daran abliest"-Zeile, demselben Muster wie
bereits in Lektion 1.8/4.1/4.2 für strukturell nicht reproduzierbare,
aber real recherchierte/verifizierte Sachverhalte etabliert.

**`content/lessons/5.3/meta.yml`**: `tools` von `[]` auf `[storescu,
dcmdump]` gesetzt (beide bereits registriert), `sandbox.required`
bleibt `false` (dokumentiert ehrlich: das *vollständige* Beispiel
braucht zwei Archive), `duration_minutes` von 10 auf 12, `status:
draft` → `fertig`. Kein neuer Glossarbegriff — „Migration" selbst ist
kein DICOM-Fachbegriff, der eine Glossarkarte rechtfertigt.

## Manuell verifiziert

- Isolierter Compose-Stack (`docker compose -p p10-49`, eigene Ports
  15436/16383/18093) für `content:validate`/`content:sync` und
  Browser-Rendering-Check — getrennt vom Zwei-Archiv-Test selbst (der
  läuft komplett außerhalb der Standard-Orchestrierung).
- `content:validate`: zunächst 1 Verstoß (`tools_checked` fehlte trotz
  nicht-leerer `tools`), sofort behoben — danach 0 Verstöße (41
  Lektionen, 48 Glossarbegriffe).
- Lektion im Browser gegen den echten Stack aufgerufen — rendert
  vollständig fehlerfrei, „Spielwiese starten"-Button erscheint wie
  oben erklärt (wegen `storescu`s `needs_sandbox: true`).
- Zwei-Archiv-Test: alle Docker-Ressourcen (`orthanc-a-test`,
  `orthanc-b-test`, `toolbox-migration-test`, Netz
  `dcmlab-migration-test`, Images `dcmlab/orthanc:test`/
  `dcmlab/toolbox:test`) nach Abschluss vollständig entfernt.
  `dcmlab/toolbox:latest`/`dcmlab/orthanc:latest` vor und nach jeder
  Phase per `docker images --filter` geprüft — unangetastet. Vor dem
  Entfernen des Standard-Stacks zusätzlich geprüft (Standardpraxis seit
  ADR 0056/0057), dass keine `dcmlab-sandbox-*`-Container liefen und
  der Valkey-Quota-Schlüssel weiterhin vom Vortag stammt.
- `datasets/build`/`services/sandbox`-Regressionssuites nicht erneut
  ausgeführt — diese Slice ändert keinen Python-/PHP-Code.

## Nicht Teil dieser Slice

- Kein Node-Stub für Lektion 5.3 (`lab.node` bleibt `null`).
- Keine Korrektur der bereits gemergten Lektionen 5.1/5.2 bezüglich
  des `sandbox.required`-Nebenfunds — beide sind durch andere
  Werkzeuge (`findscu`/`storescu`/`pynetdicom`, alle mit
  `needs_sandbox: true`) ohnehin korrekt mit sichtbarem Sandbox-Button
  versehen, der Nebenfund ändert dort nichts am beobachtbaren Verhalten.
- Keine dauerhafte Zwei-Archiv-Infrastruktur für die Standard-Sandbox
  gebaut — das wäre eine Engineering-Aufgabe eigenen Umfangs, nicht
  Teil einer einzelnen Lektions-Slice.
- Private-Tag-Überleben nicht live verifiziert (siehe oben) — bleibt
  als zitierte, nicht selbst getestete Aussage in der Lektion markiert.
