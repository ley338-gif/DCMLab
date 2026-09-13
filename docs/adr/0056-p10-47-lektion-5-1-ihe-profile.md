# 0056 — P10.47: Lektion 5.1 (IHE-Profile) — Scaffold-Annahme widerlegt, echter Hands-on-Anteil gefunden

Status: akzeptiert
Datum: 2026-09-14

## Kontext

Lektion 5.1 lag seit P10.46 (ADR 0055) als Gerüst vor, mit der
Annahme: „Kein reales Hands-on-Beispiel geplant" — IHE-Profile seien
Integrationsebene, nicht mit der vorhandenen Toolbox nachstellbar.

**Diese Annahme wurde vor dem Schreiben gezielt geprüft, nicht
übernommen.** Die Prüfung ergab das Gegenteil: Scheduled Workflow
(SWF.b) besteht laut offizieller IHE-Spezifikation exakt aus den
DICOM-Diensten, die diese Plattform in Lektion 2.5 (Modality Worklist)
und 2.6/4.8 (MPPS) bereits real zeigt — SWF.b fügt kein neues
technisches Beispiel hinzu, sondern nur die Reihenfolge und die
Transaktionsnummern, unter denen Hersteller Konformität ausschreiben.

## Recherche: echte IHE-Transaktionsnummern, nicht aus dem Gedächtnis

Vor dem Schreiben per `WebSearch`/`WebFetch` + `pdftotext` das
offizielle „IHE Radiology Technical Framework Supplement — Scheduled
Workflow.b" (Rev. 1.7, 2019-08-09) direkt abgerufen und Tabelle 34.1-1 /
Abbildung 34.1-1 als Text extrahiert (das PDF war für den
LLM-gestützten `WebFetch`-Renderer nicht direkt lesbar, `pdftotext
-layout` auf die lokal gespeicherte Datei lieferte den Klartext).
Daraus real bestätigt:

| Transaktion | Nummer |
|---|---|
| Query Modality Worklist | RAD-5 |
| Modality Images Stored | RAD-8 |
| Modality Procedure Step In Progress | RAD-6 |
| Modality Procedure Step Completed | RAD-7 |

Für PIR: der reale Mechanismus (HL7-`A40`-Merge-Nachricht vom
ADT-System) per `WebSearch` bestätigt (IHE-Wiki). Für XDS-I: die realen
Akteursnamen (Imaging Document Source/Consumer, Document
Repository/Registry) und der reale Fund, dass das geteilte Manifest
technisch ein Key Object Selection Document ist, ebenfalls per
`WebSearch` bestätigt — keine dieser Angaben stammt aus dem
Modelltraining ohne Beleg.

## Entscheidung — echter Hands-on-Anteil statt reiner Konzeptlektion

**`content/lessons/5.1/de.md`**: vollständig neu geschrieben.
- Eine Tabelle mit den vier realen RAD-TF-Transaktionsnummern,
  explizit verknüpft mit den Lektionen, die sie bereits zeigen (2.5,
  2.6, 4.8).
- Eine **live in der Spielwiese ausgeführte, zusammenhängende
  Sequenz** aller vier Transaktionen gegen dieselbe fiktive Patientin
  (`MUSTER^ERIKA`, dieselbe Identität in `datasets.yml` und
  `worklists.yml`): RAD-5 (`findscu -W`, identisch zu Lektion 2.5),
  RAD-8 (`storescu`), RAD-6/RAD-7 (MPPS `N-CREATE`/`N-SET`, identischer
  Client/Server-Aufbau wie Lektion 4.8, `/opt/tools/mppsscp.py`), und
  eine abschließende `findscu -S`-Kontrolle, die zeigt, dass das
  gespeicherte Bild und die MPPS-Meldung sich auf denselben, vorher
  abgefragten Patienten beziehen.
- PIR erklärt über den bereits real verifizierten Fund aus Lektion 4.6
  ({{term:coercion}}, `0xB000`) als DICOM-seitige Konsequenz desselben
  Problems, das PIR auf Prozessebene beschreibt — kein neues Beispiel
  nötig, echter Querverweis.
- XDS-I erklärt über den bereits real gebauten Fund aus Lektion 3.5
  (Key Object Selection Document) als dieselbe SOP-Klasse, die das
  XDS-I-Manifest technisch trägt — ebenfalls kein neues Beispiel nötig,
  echter Querverweis.

**`content/lessons/5.1/meta.yml`**: `sandbox.required` von `false` auf
`true` korrigiert, `dataset: ct-thorax-60` ergänzt, `tools` von `[]`
auf `[findscu, storescu, pynetdicom]` gesetzt (alle drei bereits
registriert), `glossary_terms: [scheduled-workflow,
patient-information-reconciliation, xds-i]`, `duration_minutes` von 10
auf 15 (vier reale Werkzeugbeispiele statt der ursprünglich
angenommenen reinen Prosa), `status: draft` → `fertig`.

**`content/glossary/de.yml`**: drei neue Begriffe (`scheduled-workflow`,
`patient-information-reconciliation`, `xds-i`), jeweils mit `see_also`
auf bereits bestehende Begriffe (`worklist`, `mpps`, `coercion`,
`key-object-selection`) verknüpft.

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Isolierter Compose-Stack (`docker compose -p p10-47`, eigene Ports
  15434/16381/18091).
- Test-Nutzer angelegt, echte Anmeldung über die Web-Oberfläche, echte
  Sitzung über Lektion 2.1 („Spielwiese starten") gestartet, realer
  Sitzungscontainer per `docker ps --filter name=dcmlab-sandbox-`
  gefunden.
- Alle vier Transaktionen real in diesem Sitzungscontainer ausgeführt:
  RAD-5 (`findscu -W`) liefert exakt den erwarteten
  `MUSTER^ERIKA`/`CT01`-Auftrag; RAD-8 (`storescu instance-0005.dcm`)
  liefert `0x0000 (Success)`; RAD-6/RAD-7 (`N-CREATE`/`N-SET` gegen den
  echten `mppsscp.py`) liefern beide Status `0`, im SCP-Log real
  protokolliert; abschließende `findscu -S`-Kontrolle bestätigt
  `NumberOfStudyRelatedInstances 2` für `MUSTER^ERIKA` (zwei während
  dieser Sitzung real gesendete Bilder).
- `content:validate`: 0 Verstöße (41 Lektionen, 16 Nodes, 23 Werkzeuge,
  47 Glossarbegriffe).
- Lektion 5.1 im Browser gegen den echten Stack aufgerufen — rendert
  vollständig fehlerfrei, inklusive aller drei neuen
  `{{term:...}}`-Verweise.
- `datasets/build`/`services/sandbox`-Regressionssuites nicht erneut
  ausgeführt — diese Slice ändert keinen Python-/PHP-Code.
- Alle Docker-Ressourcen dieser Slice (Compose-Stack, echter
  Sitzungscontainer, projekt-eigene `p10-47-*`-Images) nach Abschluss
  entfernt; `dcmlab/toolbox:latest`/`dcmlab/orthanc:latest` vor und nach
  der Slice per `docker images --filter` geprüft — unangetastet.

## Nebenfund dieser Slice (kein Content-Bezug, sicherheitsrelevant)

Beim Aufräumen wurden zusätzlich zum eigenen Sitzungscontainer dieser
Slice **neun weitere, bereits verwaiste** `dcmlab-sandbox-<uuid>`-
Container samt zugehöriger `-data`/`-worklists`-Volumes gefunden und
entfernt — offensichtlich aus früheren Slices dieser Roadmap-Sitzung,
die nie über die reguläre „Spielwiese beenden"-Aktion sauber
abgebaut wurden. **Vor dem Entfernen wurde geprüft, ob eine dieser
Sitzungen zur echten, laufenden Live-Umgebung des Nutzers
(`infra-*`-Projekt) gehört** — das war nicht der Fall: `infra-sandbox-1`
zeigte in den letzten 30 Minuten keine Aktivität, der einzige
Valkey-Quota-Schlüssel (`sandbox:quota:3:2026-09-13`) stammt vom
Vortag, und keiner der entfernten Container-UUIDs entsprach der
zuletzt in `infra-sandbox-1`s Logs sichtbaren Sitzung. Die
`infra-*`-Container und die geteilten `dcmlab/*:latest`-Images blieben
zu jedem Zeitpunkt unangetastet. Dennoch: **künftige Slices sollten
verwaiste `dcmlab-sandbox-*`-Ressourcen vor dem Entfernen grundsätzlich
gegen die Live-Aktivität von `infra-sandbox-1` prüfen** (Log-Zeitfenster
und Valkey-Quota-Schlüssel wie hier), nicht ungeprüft per Namensfilter
löschen — dieselbe Vorsicht wie beim Docker-Image-Nebenfund aus ADR
0048.

## Nicht Teil dieser Slice

- Kein Node-Stub für Lektion 5.1 (`lab.node` bleibt `null`) — SWF/PIR/
  XDS-I sind IHE-Prozessprofile, kein Engine-Feature-Kandidat.
- Keine Entscheidung über 5.4/5.7s offene Hands-on-Fragen (siehe ADR
  0055) — wird erst bei diesen Lektionen geprüft.
