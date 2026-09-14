# 0062 — P10.53: Lektion 5.7 (Monitoring) — drei reale REST-Endpunkte tragen

Status: akzeptiert
Datum: 2026-09-14

## Kontext

Lektion 5.7 lag seit P10.46 (ADR 0055) als Gerüst vor, mit einem als zu
prüfen markierten Hands-on-Kandidaten: Orthancs REST-Endpunkte
(`/statistics`, `/changes`) real abfragen, mit der offenen Frage, ob
die Sandbox ohne echte Produktionslast aussagekräftige Werte liefert.
Vor dem Schreiben real getestet, nicht angenommen.

## Drei reale Endpunkte statt einem

Live in der Spielwiese geprüft: `/statistics` liefert echte, mit der
Sitzung mitwachsende Kapazitätswerte (0 → 60 Instanzen/50.760 Bytes
nach dem Senden des Datensatzes). `/system` liefert echte Versions-
und Konfigurationsdaten (Orthanc 1.13.0, eingebettete DCMTK-/
OpenSSL-Versionen, Thread-/Job-Limits) — verbindet sich direkt mit
Lektion 5.6s real zitiertem Studienfund zu Software-Clustern bei
veralteten DICOM-Servern. `/jobs` liefert nach einem real ausgelösten
asynchronen Anonymisierungsjob (Lektion 5.4, 60 Instanzen) echte
Laufzeit- und Erfolgskennzahlen (`EffectiveRuntime`, `Progress`,
`State`, `FailedInstancesCount`) — die Sandbox-Größe ist für diese
Kennzahlen kein Problem, da der Mechanismus (nicht die absolute Zahl)
das Lernziel ist.

**Ergebnis der Prüfung:** Die ursprüngliche Sorge aus ADR 0055 ("keine
echte Produktionslast, daher evtl. nicht aussagekräftig") trifft nicht
zu — alle drei Endpunkte liefern echte, korrekt reagierende Werte, die
das jeweilige Lernziel unabhängig von der absoluten Sitzungsgröße
tragen.

## Entscheidung — drei Kennzahlen-Kategorien, alle real belegt

**`content/lessons/5.7/de.md`**: vollständig neu geschrieben.
- **Kapazität**: `/statistics` vorher/nachher, echte Zählerwerte.
- **Konfiguration/Version**: `/system`, mit explizitem Querverweis auf
  Lektion 5.6s Studienfund zu Software-Clustern.
- **Durchsatz/Fehlerrate**: `/jobs`, echte leere Liste als gültiger
  Ruhezustand (Parallele zu Lektion 2.5s leerer `findscu`-Antwort),
  dann ein real ausgelöster Anonymisierungsjob mit echten
  Erfolgskennzahlen.
- Klare Abgrenzung zu Lektion 5.5: alle drei Endpunkte sind
  technisches Monitoring, keiner sagt etwas über Zugriffsverhalten
  (wer hat wann ein Bild geöffnet) — dieselbe Lücke, die 5.5 bereits
  real demonstriert hat, hier nicht erneut behauptet, sondern nur
  referenziert.

**`content/lessons/5.7/meta.yml`**: `sandbox.required` von `false` auf
`true` korrigiert (Dataset `ct-thorax-60`), `tools` von `[]` auf
`[curl, storescu]` gesetzt, `duration_minutes` von 10 auf 15, `status:
draft` → `fertig`. Kein neuer Glossarbegriff — „Monitoring" ist kein
DICOM-Fachbegriff, der eine eigene Glossarkarte rechtfertigt (dasselbe
Muster wie Lektion 5.3).

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Isolierter Compose-Stack (`docker compose -p p10-53`, eigene Ports
  15440/16387/18097).
- Test-Nutzer angelegt, echte Anmeldung, echte Sitzung über Lektion 2.1
  gestartet, realer Sitzungscontainer gefunden.
- `/statistics` real vor und nach `storescu` abgefragt: 0 → 60
  Instanzen, 0 → 50.760 Bytes.
- `/system` real abgefragt: Version, Bibliotheksversionen,
  Performance-Konfiguration bestätigt.
- `/jobs` real vor (leer) und nach einem real via `POST
  .../anonymize` ausgelösten Job abgefragt: echte
  `EffectiveRuntime`/`Progress`/`State`/`FailedInstancesCount`-Werte
  erfasst.
- `content:validate`: 0 Verstöße (41 Lektionen, 16 Nodes, 23
  Werkzeuge, 52 Glossarbegriffe).
- Lektion im Browser gegen den echten Stack aufgerufen — rendert
  vollständig fehlerfrei.
- Vor dem Entfernen des eigenen Sitzungscontainers geprüft (Standard-
  praxis seit ADR 0056–0061): nur die eigene Sitzungs-UUID war unter
  `dcmlab-sandbox-*` vorhanden, Valkey-Quota-Schlüssel weiterhin vom
  Vortag. Alle Docker-Ressourcen danach vollständig entfernt;
  `dcmlab/toolbox:latest`/`dcmlab/orthanc:latest` unangetastet.
- `datasets/build`/`services/sandbox`-Regressionssuites nicht erneut
  ausgeführt — diese Slice ändert keinen Python-/PHP-Code.

## Nicht Teil dieser Slice

- Kein Node-Stub für Lektion 5.7 (`lab.node` bleibt `null`).
- Keine dauerhafte Monitoring-Infrastruktur (Dashboards, Alerting) für
  die Plattform selbst gebaut — Gegenstand der Lektion ist, welche
  Kennzahlen real existieren, nicht deren Betrieb.
- Damit ist die letzte in ADR 0055 als „zu prüfen" markierte
  Hands-on-Frage für Track 5 geklärt — nur Lektion 5.8 (Beschaffung,
  bewusst als reine Synthese der übrigen sieben Lektionen geplant)
  steht noch aus.
