# 0031 — P10.21: Lektion 4.8 — MPPS-Gegenstelle und echtes Storage Commitment

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Lektion 4.8 ("Bilder da, aber Befund geht nicht raus") lag seit P9 als
Gerüst vor. Ihr eigener "Was zum Schreiben noch fehlt"-Hinweis nannte
den erwarteten Aufwand: "MPPS und Storage Commitment gibt es in der
Spielwiese bisher nicht ... entweder ein `pynetdicom`-Gegenstück in die
Spielwiese aufnehmen oder die Lektion nach hinten schieben."

**Fund vor dem Schreiben (analog zu ADR 0030, Worklist):** Orthanc
(`orthancteam/orthanc`) beherrscht **Storage Commitment nativ** —
keine Plugin-Datei nötig, kein neuer Container. Die REST-Route
`POST /modalities/{id}/storage-commitment` löst ein echtes
`N-ACTION`/`N-EVENT-REPORT`-Gespräch aus; `GET
/storage-commitment/{id}` liefert das Ergebnis (verzögert, wie in
echten Häusern). Verifiziert mit einem manuellen Testaufbau: Orthanc
als eigene Gegenstelle (`modalities/self`,
`"AllowStorageCommitment": true`) eingetragen, ein echtes Objekt
gespeichert, Commitment angefragt — `Status: "Success"` mit den
korrekten SOP-UIDs. Eine zweite Anfrage für eine nie gespeicherte SOP
Instance UID lieferte eine echte Ablehnung
(`FailureReason 274`, PS3.4 J.3.4).

**MPPS dagegen unterstützt Orthanc nicht** — ein `pynetdicom`-Testclient
konnte keine Assoziation mit dem MPPS-SOP-Class-Kontext aufbauen
(`assoc.is_established == False`), bestätigt durch eine Prüfung des
Orthanc-Images (keine `mpps`-bezogene Plugin-Datei vorhanden, anders
als beim Worklists-Plugin in ADR 0030). Für MPPS wurde deshalb — genau
wie im ursprünglichen Gliederungsvorschlag der Lektion vorgesehen — ein
eigener, echter `pynetdicom`-SCP geschrieben und in die Toolbox
aufgenommen.

## Entscheidung — Storage Commitment nativ über Orthanc, MPPS über
einen eigenen echten SCP

**`containers/toolbox/scripts/mppsscp.py`** (neu): ein minimaler, aber
echter MPPS-SCP (`pynetdicom`, Handler für `EVT_N_CREATE` und
`EVT_N_SET`, jeweils mit Status `0x0000` und Logausgabe der
empfangenen `PerformedProcedureStepStatus`/`PatientName`-Werte). Läuft
als eigener Prozess, den ein Lernender selbst im Terminal startet
(`python3 /opt/tools/mppsscp.py --port 11112 --ae-title MPPS-SCP &`) —
kein Orchestrator-Wiring nötig, anders als bei der Worklist (ADR 0030),
weil MPPS kein bei Sitzungsstart vorbereitetes Datenobjekt braucht,
sondern eine Gegenstelle, die während der Sitzung mitläuft.

**`containers/toolbox/Dockerfile`**: `curl` ergänzt (für die
Storage-Commitment-REST-Aufrufe gegen Orthancs HTTP-API, über die
gemeinsame Netzwerk-Namespace unter `127.0.0.1:8042` erreichbar) und
`COPY containers/toolbox/scripts/mppsscp.py /opt/tools/mppsscp.py`.

Kein Orthanc-Konfigurationsänderung nötig — `modalities/self` wird vom
Lernenden selbst per `curl -X PUT` eingetragen, real und im Terminal
nachvollziehbar, statt vorkonfiguriert (bewusste Entscheidung: der
Registrierungsschritt selbst ist Teil dessen, was die Lektion
vermittelt — "der Rückweg braucht eigene Konfiguration").

**`content/lessons/4.8/de.md`**: vollständig neu geschrieben, mit
echten Beispielen — MPPS `N-CREATE`/`N-SET` gegen den echten SCP
(inklusive Logausgabe der Gegenstelle und einem `tshark`-Mitschnitt der
Assoziation), Storage Commitment über Orthancs REST-API (Erfolg für
ein echtes, gespeichertes Objekt; echte Ablehnung mit
`FailureReason 274` für eine nie gespeicherte SOP Instance UID). Kein
`<!-- kein-beispiel -->`-Block nötig.

**`content/lessons/4.8/meta.yml`**: `status: draft` → `fertig`,
Kommentar zum fehlenden Node um den Hinweis auf die neuen
Spielwiese-Fähigkeiten ergänzt, `tools_checked`/`updated` auf
`2026-09-13`. `lab.node` bleibt `null` — kein passender Node-Stub
vorhanden (unverändert gegenüber dem Ausgangszustand).

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Storage Commitment isoliert getestet (Orthanc + echtes gespeichertes
  Objekt): `modalities/self` mit `AllowStorageCommitment: true`
  registriert, Commitment angefragt — `Status: "Success"` mit
  korrekten SOP-UIDs.
- Dieselbe Anfrage für eine real generierte, aber nie gespeicherte SOP
  Instance UID: `Status: "Failure"`, `FailureReason: 274` — echte
  Ablehnung, kein simulierter Fehlertext.
- MPPS isoliert getestet: `mppsscp.py` lokal gestartet,
  `pynetdicom`-Client sendet `N-CREATE` (`IN PROGRESS`) und `N-SET`
  (`COMPLETED`) — beide mit Status `0x0000` beantwortet, SCP-Log zeigt
  beide empfangenen Werte korrekt.
- `tshark`-Mitschnitt der MPPS-Assoziation: `A-ASSOCIATE`,
  `N-CREATE-RQ`/`-RSP`, `N-SET-RQ`/`-RSP`, `A-RELEASE` — vollständig
  korrekt dissektiert.
- `datasets/build` und `services/sandbox`: pytest/ruff/mypy erneut
  ausgeführt (unverändert von dieser Slice betroffen) — alle grün,
  keine Regression.
- **Echter End-to-End-Test über die Produkt-Oberfläche:** eingeloggt,
  Lektion 4.8 geöffnet (rendert vollständig, kein "Gerüst"-Hinweis
  mehr), "Spielwiese starten" geklickt — echtes, vom Orchestrator
  erzeugtes Container-Paar.
- Im echten Sitzungscontainer: reales Objekt gespeichert, UIDs per
  `dcmdump` extrahiert, Storage Commitment über `curl` gegen Orthancs
  echte REST-API angefragt und bestätigt (`Success`), MPPS-SCP
  gestartet und über einen echten `pynetdicom`-Client bedient — alle
  Ausgaben stammen aus diesem einen, echten Sitzungscontainer, nicht
  aus dem isolierten Vortest.
- "Spielwiese beenden" über die echte Oberfläche geklickt — bestätigt
  per `docker ps -a`, dass beide Container vollständig entfernt wurden.
- `content:validate`: 19 vorbestehende Verstöße, keine neuen.
- Alle Docker-Ressourcen dieser Slice (Compose-Stack, geteilte
  Test-Images, Sitzungscontainer/-Netz) nach Abschluss vollständig
  entfernt.

## Nicht gebaut / offen (siehe `docs/content-todo.md`)

- Kein Node-Stub für Lektion 4.8 (`lab.node` bleibt `null`) — die
  Statuskette (MPPS + Storage Commitment) eignet sich grundsätzlich für
  einen künftigen Node, wurde aber in der ursprünglichen Roadmap nicht
  benannt und ist damit eine offene, spätere Entscheidung.
- Abgrenzung zu Lektionen 2.6/2.7 (im ursprünglichen Gerüst genannt):
  diese Lektionen existieren noch nicht (Track 2 ist laut
  `docs/content-todo.md` noch nicht begonnen) — die Abgrenzung ist
  damit gegenstandslos, bis Track 2 geschrieben wird.
- `mppsscp.py` hat keine eigene Testabdeckung (pytest/mypy) — konsistent
  mit dem bestehenden Muster, dass Toolbox-interne Skripte
  (z. B. `datasets/build/generate.py`s Aufruf durch `docker_ops.py`)
  über echte Integrationsverifikation abgesichert werden, nicht über
  isolierte Unit-Tests einer eigenständigen Datei ohne CI-Anbindung.
