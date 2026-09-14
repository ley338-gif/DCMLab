# 0061 — P10.52: Lektion 5.6 (Security) — live reproduzierte AE-Title-Lücke, aktuelle 2026-Studie zitiert

Status: akzeptiert
Datum: 2026-09-14

## Kontext

Lektion 5.6 lag seit P10.46 (ADR 0055) als Gerüst vor, mit zwei offenen
Fragen: (1) Ist ein `findscu`/`storescu`-Angriff ohne vorherige
Autorisierung gegen die eigene Sandbox ein legitimer, kleiner
Hands-on-Baustein? (2) Existieren reale, verifizierbare Quellen für
dokumentierte DICOM-Sicherheitsbefunde? Beide vor dem Schreiben
geprüft, nicht angenommen.

## Einordnung des Hands-on-Bausteins

Der Baustein ist **kein neuer Angriff**, sondern die explizite
Benennung eines bereits an mehreren Stellen dieses Projekts real
dokumentierten Verhaltens: Orthanc akzeptiert in dieser Spielwiese per
Konfiguration (`DicomAlwaysAllowEcho`/`-Store`/`-Find`/etc.,
`containers/orthanc/orthanc.json`) jeden Called/Calling-AE-Title —
bereits real verifiziert in ADR 0008 und seither in praktisch jeder
Lektion implizit genutzt (`-aec ORTHANC` funktioniert nur, weil
niemand geprüft wird). Diese Lektion macht daraus explizit das
eigentliche Lernziel, statt es weiter stillschweigend vorauszusetzen —
kein Verstoß gegen die Projekt-Leitplanke zu Angriffstools, da nichts
Neues gebaut oder ausgeführt wird, das nicht ohnehin schon Teil jeder
Lektion war.

## Recherche: eine aktuelle, reale Studie statt erfundener Vorfälle

Per `WebSearch` gefunden und per `pdftotext` aus dem Original-PDF
extrahiert: „Measuring Healthcare Data Leaks and Security Flaws at
Internet Scale" (Brüggemann, Schmidt, Dölzer, Brockhoff, Ising,
Saatjohann, Schinzel; Fraunhofer SIT, FH Münster, ATHENE; arXiv
2607.04965v2, CC-BY 4.0, Juli 2026 — **aktuell**, nicht die oft
zitierte, inzwischen sieben Jahre alte Greenbone-2019-Studie, die in
einer ersten Recherche ebenfalls gefunden, aber zugunsten der
aktuelleren Quelle verworfen wurde). Reale, aus dem Papertext zitierte
Zahlen:

- **1.903** erfolgreich aufgebaute DICOM-Associations mit genau der
  Methode, die diese Lektion live nachstellt (Calling-AE-Title
  `PYNETDICOM`, Called-AE-Title `ANY-SCP`).
- **93,54 %** dieser Fälle erlaubten Patientendatenzugriff ohne
  weitere Zugriffskontrolle → **1.780** real gefundene, verwundbare
  DICOM-Dienste.
- Gegenprobe im selben Paper: **3.355** von **3.777** gescheiterten
  Verbindungsversuchen scheiterten konkret an einer AE-Title-Prüfung —
  ehrlich mit übernommen, da es zeigt, dass korrekt konfigurierte
  Systeme diesen Angriff tatsächlich abwehren.
- Wörtlich zitiert (kurzer, durch CC-BY-4.0-Lizenz gedeckter Auszug):
  „the DICOM standard does not require vendors [or] operators to use
  [security measures]. In practice, this means that DICOM systems can
  be operated without any security measures in place."

## Entscheidung — echter Hands-on-Anteil: dieselbe Methode wie die zitierte Studie

**`content/lessons/5.6/de.md`**: vollständig neu geschrieben.
- Live in der echten Spielwiese: `echoscu -aet PYNETDICOM -aec
  ANY-SCP` — frei erfundene Titel, Association trotzdem akzeptiert.
- Anschließend `findscu` mit denselben frei erfundenen Titeln — liefert
  real einen echten (fiktiven) Patientennamen zurück, exakt dieselbe
  Methode wie im zitierten Paper.
- Die Paper-Zahlen direkt danach als Beleg, dass dies kein
  Sandbox-Artefakt, sondern ein real millionenfach beobachtetes Muster
  ist.
- Netzsegmentierung als Standardkompensation erklärt, mit der
  ehrlichen Einschränkung, dass sie das AE-Title-Problem nicht löst,
  sondern nur den Kreis möglicher Angreifer verkleinert.
- DICOM TLS (Supplement 51) als die eigentlich vorhandene, aber selten
  eingesetzte Lösung — mit echtem Querverweis auf Lektion 4.9s bereits
  real verifizierten TLS-`storescp`-Befund, keine neue Infrastruktur
  gebaut.

**`content/lessons/5.6/meta.yml`**: `sandbox.required` von `false` auf
`true` korrigiert (Dataset `ct-thorax-60`), `tools` von `[]` auf
`[echoscu, findscu, storescu]` gesetzt, `glossary_terms:
[netzsegmentierung]`, `duration_minutes` von 10 auf 15, `status: draft`
→ `fertig`.

**`content/glossary/de.yml`**: neuer Begriff `netzsegmentierung`.

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Isolierter Compose-Stack (`docker compose -p p10-52`, eigene Ports
  15439/16386/18096).
- Test-Nutzer angelegt, echte Anmeldung, echte Sitzung über Lektion 2.1
  gestartet, realer Sitzungscontainer gefunden.
- `echoscu -aet PYNETDICOM -aec ANY-SCP` real ausgeführt — Association
  Accepted, Echo Response Success, mit frei erfundenen Titeln.
- `findscu` mit denselben Titeln real ausgeführt — liefert real
  `PatientName [MUSTER^ERIKA]` zurück, ohne jede Anmeldung.
- `content:validate`: 0 Verstöße (41 Lektionen, 16 Nodes, 23 Werkzeuge,
  52 Glossarbegriffe).
- Lektion im Browser gegen den echten Stack aufgerufen — rendert
  vollständig fehlerfrei.
- Vor dem Entfernen des eigenen Sitzungscontainers geprüft (Standard-
  praxis seit ADR 0056–0060): nur die eigene Sitzungs-UUID war unter
  `dcmlab-sandbox-*` vorhanden, Valkey-Quota-Schlüssel weiterhin vom
  Vortag. Alle Docker-Ressourcen danach vollständig entfernt;
  `dcmlab/toolbox:latest`/`dcmlab/orthanc:latest` unangetastet.
- `datasets/build`/`services/sandbox`-Regressionssuites nicht erneut
  ausgeführt — diese Slice ändert keinen Python-/PHP-Code.

## Nicht Teil dieser Slice

- Kein Node-Stub für Lektion 5.6 (`lab.node` bleibt `null`).
- Kein live gebautes TLS-Szenario — bereits real in Lektion 4.9
  vorhanden, hier nur referenziert.
- Keine Entscheidung über die offene Hands-on-Frage für 5.7 (siehe ADR
  0055) — wird erst bei dieser Lektion geprüft.
