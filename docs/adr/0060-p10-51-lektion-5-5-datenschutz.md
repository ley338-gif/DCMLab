# 0060 — P10.51: Lektion 5.5 (Datenschutz/Zugriffsprotokollierung) — Orthancs `/changes` real geprüft

Status: akzeptiert
Datum: 2026-09-14

## Kontext

Lektion 5.5 lag seit P10.46 (ADR 0055) als Gerüst vor, mit zwei offenen
Fragen: (1) Unterstützt Orthanc tatsächlich ATNA/RFC-3881-Audit-Logs
(evtl. per Plugin)? (2) Sind die zitierten Rechtsgrundlagen
(Röntgenverordnung/StrlSchG-Nachfolge, DSGVO Art. 9/17) aktuell und
korrekt? Beide vor dem Schreiben real geprüft, nicht angenommen.

## Recherche 1: Orthanc hat kein eingebautes Zugriffsprotokoll

Per `WebSearch` (Orthanc-Nutzerforum, offizielles Orthanc Book)
bestätigt: Orthanc protokolliert Web-Zugriffe nicht vollständig; die
Projektdokumentation selbst empfiehlt für echte Zugriffsprotokollierung
einen vorgeschalteten Reverse-Proxy (nginx/Apache). Kein
ATNA-/RFC-3881-Plugin gefunden.

**Was Orthanc tatsächlich bietet, real live getestet:** der
REST-Endpunkt `/changes` — ein echtes, zeitgestempeltes, fortlaufend
nummeriertes **Änderungsprotokoll** (`NewInstance`/`NewSeries`/
`NewStudy`/`NewPatient`, mit `Date`/`Seq`). Ein realer Test in der
Spielwiese zeigte den entscheidenden, vorher nicht angenommenen
Unterschied: Nach `storescu` (Schreiben) wuchs `/changes` real um vier
Einträge (Seq 1–4); nach einem anschließenden `curl .../file`-Download
(Lesen) desselben Objekts blieb `/changes` unverändert bei Seq 4 — **ein
Lesezugriff hinterlässt in Orthancs eigenem Protokoll keine Spur.**
Zusätzlich real bestätigt: kein Feld für Nutzeridentität in
`/changes`, konsistent mit `AuthenticationEnabled: false` in
`containers/orthanc/orthanc.json` — ohne Authentifizierung gibt es
ohnehin keine Identität, die protokolliert werden könnte.

## Recherche 2: reale, aktuelle Rechtsgrundlagen

- **§ 127 StrlSchV** (Strahlenschutzverordnung, seit 31.12.2018
  Nachfolgeregelung der Röntgenverordnung, RöV-Werte inhaltlich
  weitgehend übernommen) — real per `WebSearch` bestätigt:
  Röntgenuntersuchungen 10 Jahre, Röntgenbehandlungen 30 Jahre,
  Minderjährige bis Vollendung des 28. Lebensjahres.
- **Art. 17 Abs. 3 DSGVO** — real bestätigt: gesetzliche
  Aufbewahrungspflicht ist ein ausdrücklich genannter Grund, eine
  Löschung zu verweigern; Verarbeitung während dieser Zeit ist auf den
  Aufbewahrungszweck beschränkt.

Beide Zitate stammen aus der Recherche dieser Slice, nicht aus dem
Modelltraining ohne Beleg.

## Entscheidung — echter Hands-on-Anteil: Änderungs- vs. Zugriffsprotokoll

**`content/lessons/5.5/de.md`**: vollständig neu geschrieben.
- Realer `/changes`-Auszug nach einem echten `storescu` (vier Einträge,
  echte IDs/Zeitstempel/Sequenznummern aus der Verifikation).
- **Live-Gegenprobe:** derselbe Endpunkt nach einem echten
  Datei-Download — unverändert. Der zentrale, real demonstrierte
  Befund der Lektion: Änderungsprotokoll ≠ Zugriffsprotokoll.
- IHE ATNA als reale, standardisierte Antwort auf genau diese Lücke
  benannt (Node Authentication + strukturierte Audit-Nachrichten nach
  RFC 3881) — konzeptionell, da Orthanc es nicht implementiert und ein
  Nachbau unverhältnismäßig wäre.
- Reale Aufbewahrungsfrist (§ 127 StrlSchV) und reale Auflösung des
  Spannungsfelds Löschanspruch/Aufbewahrungspflicht (Art. 17 Abs. 3
  DSGVO).

**`content/lessons/5.5/meta.yml`**: `sandbox.required` von `false` auf
`true` korrigiert (Dataset `ct-thorax-60`), `tools` von `[]` auf
`[curl, storescu]` gesetzt, `glossary_terms: [atna]`,
`duration_minutes` von 10 auf 15, `status: draft` → `fertig`.

**`content/glossary/de.yml`**: neuer Begriff `atna`, verknüpft mit
`conformance-statement`.

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Isolierter Compose-Stack (`docker compose -p p10-51`, eigene Ports
  15438/16385/18095).
- Test-Nutzer angelegt, echte Anmeldung, echte Sitzung über Lektion 2.1
  gestartet, realer Sitzungscontainer gefunden.
- `storescu` real ausgeführt, `curl .../changes` zeigt real vier neue
  Einträge; anschließender `curl .../file`-Download real ausgeführt,
  `/changes?last` zeigt real denselben, unveränderten letzten Eintrag.
- `content:validate`: 0 Verstöße (41 Lektionen, 16 Nodes, 23 Werkzeuge,
  51 Glossarbegriffe).
- Lektion im Browser gegen den echten Stack aufgerufen — rendert
  vollständig fehlerfrei.
- Vor dem Entfernen des eigenen Sitzungscontainers geprüft (Standard-
  praxis seit ADR 0056–0059): nur die eigene Sitzungs-UUID war unter
  `dcmlab-sandbox-*` vorhanden, Valkey-Quota-Schlüssel weiterhin vom
  Vortag. Alle Docker-Ressourcen danach vollständig entfernt;
  `dcmlab/toolbox:latest`/`dcmlab/orthanc:latest` unangetastet.
- `datasets/build`/`services/sandbox`-Regressionssuites nicht erneut
  ausgeführt — diese Slice ändert keinen Python-/PHP-Code.

## Nicht Teil dieser Slice

- Kein Node-Stub für Lektion 5.5 (`lab.node` bleibt `null`).
- Kein live gebautes ATNA-Audit-Repository — bleibt konzeptionell,
  Orthanc unterstützt es nicht nativ.
- Keine Entscheidung über die offene Hands-on-Frage für 5.6/5.7 (siehe
  ADR 0055) — wird erst bei diesen Lektionen geprüft.
