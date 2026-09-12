# Auftrag an den Code-Agenten — DCM Lab, Phase 1 (MVP)

> Dieses Dokument ist dein vollständiger Auftrag. Es ist so geschrieben, dass du
> ohne Rückfragen anfangen kannst. Wo du trotzdem eine Entscheidung brauchst,
> steht in Abschnitt 14, wie du damit umgehst.

---

## 0. Rolle und Ziel

Du baust **DCM Lab**, eine Lernplattform für DICOM und PACS nach dem Vorbild von
HackTheBox: Man lernt, indem man kaputte PACS-Umgebungen repariert.

**Ziel dieser Phase (Phase 1 / MVP):** eine lauffähige, selbst hostbare Webapp mit
Nutzerkonten, in der ein angemeldeter Nutzer

1. Lektionen aus Track 1 (Fundamente) und Track 4 (Troubleshooting) lesen kann,
2. simulierte Übungsszenarien („Nodes") im Browser lösen und einen Flag einreichen kann,
3. neben jeder Lektion eine **echte** Übungsumgebung („Spielwiese") starten kann —
   ein Container-Paar aus Orthanc und den DCMTK-Werkzeugen, bedient über ein Web-Terminal,
4. dafür Punkte, Ränge und Fortschritt bekommt.

Das Ganze läuft mit **einem `docker compose up`** auf einem Server. Der Lernende
installiert **nichts** — ein Browser genügt.

Arbeitstitel und Repo-Name: `dcm-lab`. Die Plattform verwendet in Namen und Slugs
konsequent `dcm`, nie `dicom` (Markenschutz NEMA). Im Fließtext darf DICOM
beschreibend vorkommen.

---

## 1. Die vier Leitplanken — unverhandelbar

Diese Sätze entscheiden Zweifelsfälle. Wenn eine technische Entscheidung gegen
einen dieser Sätze läuft, ist die Entscheidung falsch, nicht der Satz.

1. **Der Lernende installiert nichts und richtet nichts ein.** Kein Docker auf dem
   Client, keine lokale Toolinstallation, kein Download. Alles kommt aus dem Browser.
   Zielgruppe sind Leute ohne lokale Adminrechte.
2. **Nie echte Patientendaten.** Alle Testdaten werden im Repo per Skript synthetisch
   erzeugt (pydicom). Kein Datensatz kommt aus einem realen Haus, auch nicht anonymisiert.
3. **Die Übungsumgebung hat keinen Weg nach draußen.** Kein Internet-Egress aus
   Sandbox-Containern, kein Weg zu anderen Sitzungen, kein Weg ins Host-Netz.
4. **Lernstoff liegt im Git-Repo, nicht in der Datenbank.** Die Datenbank hält
   Fortschritt und Zustand, niemals Lektionstext oder Node-Definitionen. Kein CMS.

Zusatzregel für dich als Agent: **Die Content-Schemas in Abschnitt 4 und 5 sind
verbindlich.** Du implementierst gegen sie. Du änderst sie nicht, um dir die
Implementierung zu erleichtern (siehe Abschnitt 14).

---

## 2. Fachlicher Kontext in fünf Minuten

Damit du die Domäne nicht raten musst:

- **DICOM** ist beides: ein Dateiformat (`.dcm`) und ein Netzwerkprotokoll (DIMSE).
- Eine Verbindung heißt **Association**. Sie wird aufgebaut zwischen einem
  **SCU** (fragt an) und einem **SCP** (antwortet). Das sind Rollen, keine Geräte.
- Adressiert wird über ein Trio: **AE Title**, **Host**, **Port**. Der AE Title des
  Ziels heißt **Called AE Title**, der eigene **Calling AE Title**. AE Titles werden
  **zeichengenau** verglichen — `PACS-ARCHIV` und `PACS_ARCHIV` sind zwei verschiedene Systeme.
- Wichtige Dienste: **C-ECHO** (lebst du?), **C-STORE** (Bild senden),
  **C-FIND** (abfragen), **C-MOVE/C-GET** (abholen), **Modality Worklist**.
- Datenmodell-Hierarchie: **Patient → Study → Series → Instance**, jede Ebene mit
  einer eigenen **UID**.
- Die Standardwerkzeuge sind das **DCMTK** (`echoscu`, `storescu`, `findscu`,
  `dcmdump`, `storescp`, …) und **Orthanc** als freies Archiv.

Das reicht für die Implementierung. Alles Weitere steht im Content.

---

## 3. Zielarchitektur

### 3.1 Stack (festgelegt, nicht zur Diskussion)

| Schicht | Wahl | Grund |
|---|---|---|
| Backend / Web | **Laravel 13** (PHP 8.3+) | Auth, Rollen, Queues, Policies, Mail fertig |
| Frontend | **Vue 3 + Inertia 2 + Vite + Tailwind** | keine getrennte API, kein doppeltes Routing |
| DB | **PostgreSQL 16** | JSONB für Node-Definitionen, Fortschritt, übersetzbare Felder |
| Node-Engine | **Python 3.12 + FastAPI** | DICOM-Semantik ist in Python besser bedienbar; eigenständiger Dienst |
| Sandbox-Orchestrator | **Python 3.12 + FastAPI**, spricht Docker-API | eigener Dienst, einziger mit Docker-Socket |
| Sandbox-Inhalt | **Orthanc** + Toolbox-Container (DCMTK, Python, pydicom, pynetdicom) | die echte Spielwiese |
| Terminal | **xterm.js** über WebSocket | eine Komponente für Node und Spielwiese |
| Queue / Cache | **Redis** | Jobs, Rate Limits, Sandbox-Warteschlange |
| Deployment | **ein `docker-compose.yml`** | App, DB, Redis, Engine, Orchestrator, Reverse Proxy (Caddy) |

Die Zweisprachigkeit PHP/Python ist gewollt und die einzige erlaubte Stelle davon.

### 3.2 Monorepo-Layout

```
dcm-lab/
├── apps/
│   └── web/                      Laravel 13 + Inertia + Vue 3
├── services/
│   ├── engine/                   FastAPI: simulierte Node-Engine
│   └── sandbox/                  FastAPI: Container-Orchestrierung der Spielwiese
├── content/                      DER LERNSTOFF (Abschnitt 4) — Single Source of Truth
├── containers/
│   ├── orthanc/                  Image + Konfiguration der Spielwiese
│   └── toolbox/                  Image mit DCMTK, python, pydicom, pynetdicom, tshark
├── datasets/
│   └── build/                    Skripte, die synthetische Testdaten erzeugen
├── infra/
│   ├── docker-compose.yml
│   ├── docker-compose.dev.yml
│   └── caddy/
├── docs/
│   ├── adr/                      Architecture Decision Records
│   └── *.md                      mitgelieferte Konzeptdokumente
├── Makefile
└── README.md
```

### 3.3 Dienstgrenzen

- **Laravel** kennt Nutzer, Fortschritt, Punkte, Sitzungen und rendert alles Sichtbare.
  Laravel kennt **keine** DICOM-Semantik.
- **Engine** kennt Nodes und beantwortet Befehle. Die Engine kennt **keine** Nutzer —
  sie bekommt eine opake `session_id` und einen `node_slug`.
- **Orchestrator** startet und räumt Container. Er ist der **einzige** Dienst mit
  Zugriff auf den Docker-Socket. Laravel spricht ihn nur über HTTP an.
- Interne Aufrufe laufen über ein gemeinsames Secret (`X-DCMLAB-KEY`) im internen
  Compose-Netz. Keiner der beiden Python-Dienste ist von außen erreichbar.

---

## 4. Content-Format (verbindlich)

Alles unterhalb von `content/` ist Quelltext. Die Plattform liest es ein; solange
das Schema gilt, ist geschriebener Content nie verloren.

```
content/
├── tracks.yml
├── datasets.yml
├── lessons/<id>/meta.yml, de.md            (später en.md)
├── nodes/<slug>/node.yml, de.md, assets/
├── tools/de.yml
└── glossary/de.yml
```

**Trennprinzip:** Alles Technische (Ports, AE Titles, Punkte, Slugs, Flag-Hash) steht
sprachneutral in `*.yml`. Alle Prosa steht in `<locale>.md`. Eine Übersetzung fasst
YAML nie an.

### 4.1 `lessons/<id>/meta.yml`

```yaml
id: "1.5"                      # IMMER String (sonst wird 1.10 zu 1.1)
track: fundamente
order: 5
title_key: lesson.1_5.title
duration_minutes: 8
level: einsteiger              # einsteiger | aufbau | fortgeschritten
objectives_count: 3
requires: ["1.0", "1.1"]
tools: [echoscu, storescp]     # Slugs aus tools/de.yml, HÖCHSTENS VIER
sandbox:
  required: true
  dataset: ct-thorax-60
  note: "Zwei Serien, absichtlich gemischt"
lab:
  node: scu-scp-basics         # Slug aus content/nodes/, oder null
  optional: false
glossary_terms: [scu, scp, ae-title, association]
quiz:
  - id: q1
    type: single               # single | multi | input
    answer: 1                  # Index bzw. exakter String; der Text steht in de.md
tools_checked: "2026-09-12"    # Pflicht, sobald tools nicht leer ist
status: draft                  # draft | review | published
authors: ["ley338"]
updated: "2026-09-12"
```

### 4.2 `lessons/<id>/de.md`

Frontmatter `title`, `teaser`, `objectives[]`. Danach in dieser Reihenfolge:

| Block | Pflicht | Herkunft |
|---|---|---|
| Werkzeugleiste | ja | **gerendert** aus `meta.yml`, nicht geschrieben |
| Aufhänger | ja | Prosa |
| Erklärung | ja | Prosa |
| Beispiele | ja | verteilt im Text (Abschnitt 4.5) |
| Diagramm | optional | ASCII oder Mermaid, kein Bildmaterial |
| „Im Alltag heißt das" | ja | Prosa |
| Stolperfallen | ja | Prosa |
| Lab-Einstieg | wenn Lab | Prosa |
| Selbstcheck | ja | 2–4 Fragen, Antworten eingeklappt |

Fachbegriffe werden beim ersten Vorkommen als `{{term:scu}}` ausgezeichnet und vom
Renderer zu einem Tooltip aus `glossary/de.yml` aufgelöst.

### 4.3 `tools/de.yml`

```yaml
dcmdump:
  name: dcmdump
  suite: dcmtk                  # dcmtk | dcm4che | python | server | extern
  kind: datei                   # datei | netz | server | analyse | skript
  purpose: Kippt den kompletten Inhalt eines DICOM-Objekts als Text aus.   # EINE Zeile, max 90 Zeichen
  example: "dcmdump datei.dcm"
  lesson: "1.0"
  anchor: was-steht-in-dieser-datei
  needs_sandbox: false
```

Nur `purpose` ist übersetzbar. Startbelegung der Registry: siehe mitgeliefertes
`docs/werkzeug-registry.md`.

### 4.4 Die Werkzeugleiste — gerendert, nie geschrieben

Steht ganz oben in jeder Lektion, **vor** dem Aufhänger:

```
┌─ FÜR DIESE LEKTION ──────────────────────────────── 8 Min ─┐
│  WERKZEUGE                                                 │
│  dcmftest   Prüft, ob eine Datei DICOM ist       NEU       │
│             dcmftest datei.dcm                             │
│  dcmdump    Kippt den Inhalt als Text aus                  │
│             dcmdump datei.dcm                              │
│                                                            │
│  LIEGT BEREIT   daten/ct-thorax/ · 60 Dateien, synthetisch │
│  VORHER         1.0 Die Werkzeugkiste                      │
│  DANACH         Lab: Node „First Contact" (easy, 10 Pkt)   │
│  [ Spielwiese starten ]                                    │
└────────────────────────────────────────────────────────────┘
```

| Zeile | Quelle |
|---|---|
| Dauer | `meta.yml: duration_minutes` |
| Werkzeuge, `purpose`, `example` | `meta.yml: tools` → `tools/de.yml` |
| Markierung **NEU** | abgeleitet: kein früherer Eintrag im Track hat diesen Slug |
| LIEGT BEREIT | `sandbox.dataset` + `sandbox.note` aus `datasets.yml` |
| VORHER | `requires` → Titel aus der jeweiligen `de.md` |
| DANACH | `lab.node` → Titel, Schwierigkeit, Punkte aus `node.yml` |
| Schaltfläche | vorhanden, wenn irgendein Werkzeug `needs_sandbox: true` hat |

Einklappbar, beim zweiten Besuch einer Lektion standardmäßig zu.

### 4.5 Die Beispielregel — der Validator prüft sie

Jede Aussage, die man ausprobieren kann, wird im Lektionstext mit einem Beispiel aus
drei Teilen belegt: **Befehl** (vollständig, kopierbar, echte Werte statt Platzhalter),
**Ausgabe** (echt, höchstens gekürzt) und **Leseanleitung**, die wörtlich mit
`**Was du daran abliest:**` beginnt. Der feste Wortlaut macht die Regel maschinell prüfbar.

### 4.6 Referenzwerte — überall identisch

| Rolle | Wert |
|---|---|
| Spielwiese (Archiv) | AE `ORTHANC`, `127.0.0.1`, Port `4242`, Weboberfläche `8042` |
| Eigene Workstation | AE `MEINE-WS` |
| Eigener Empfänger | AE `MEIN-EMPFANG`, Port `11112` |
| Testdaten in der Sandbox | `~/daten/<dataset-slug>/` |
| Beispielpatientin | `MUSTER^ERIKA`, PatientID `4711` |
| Beispielstudie | `CT Thorax nativ`, Serien `Thorax 1.0 B70f` und `Thorax 5.0 B31f` |

Die Spielwiese meldet sich bewusst wie eine lokale Standardinstallation, damit
dieselben Befehle auch später zu Hause noch stimmen. **Nodes** spielen dagegen im
fiktiven Klinikum (`10.20.0.0/24`). Die beiden Welten werden nie vermischt.

### 4.7 `content:validate` — Artisan-Befehl, läuft in CI und im Pre-Commit-Hook

**Struktur**
- jede `meta.yml` hat eine `de.md` und umgekehrt
- `objectives_count` stimmt mit der Anzahl in `de.md`
- jede Hint-ID aus `node.yml` hat einen `### h<n>`-Abschnitt in `de.md`
- jeder `{{term:x}}` existiert im Glossar
- jede ID in `requires` und `related_lessons` existiert
- **kein Flag-Klartext im Repo** (Regex gegen das Flag-Format)

**Beispielregel**
- jede `de.md` enthält mindestens einen Codeblock
- auf jeden Codeblock folgt innerhalb von drei Zeilen `**Was du daran abliest:**`
  — ausgenommen Blöcke, denen `<!-- kein-beispiel -->` vorangestellt ist

**Werkzeuge**
- jeder Slug aus `tools` existiert in `tools/de.yml`; höchstens vier
- **Umkehrprüfung (die wichtigste):** Jedes Wort, das in einem Beispielblock am Anfang
  einer `$`-Zeile steht und in der Registry vorkommt, muss in `meta.yml: tools`
  deklariert sein
- `tools_checked` gesetzt und nicht älter als 12 Monate, sobald `tools` nicht leer ist
- `sandbox.dataset` existiert in `datasets.yml`
- jeder Platzhalter aus `node.yml` kommt in mindestens einem `templates`-Eintrag vor
- `purpose` einzeilig, höchstens 90 Zeichen

Der Befehl gibt Fehler mit Datei und Zeilennummer aus und beendet sich mit Exitcode ≠ 0.

---

## 5. Node-Format und Engine-Regelwerk

### 5.1 `nodes/<slug>/node.yml`

```yaml
slug: silent-ct
difficulty: easy                # easy | medium | hard | insane
points: 10                      # 10 | 25 | 50 | 100
category: netzwerk              # netzwerk | datenmodell | bildgebung | integration | security
skills: [netzwerk]              # höchstens zwei, speist das Skill-Radar
related_lessons: ["1.5", "4.1"]
estimated_minutes: 15

environment:
  engine: simulated             # simulated | container  ← beide Werte müssen vorgesehen sein
  hosts:
    - name: archive
      ip: 10.20.0.10
      services:
        - port: 104
          type: scp
          ae_title: PACS-ARCHIV
          accepts: [verification, ct-image-storage]
    - name: ct-console
      ip: 10.20.0.30
      role: modality-simulator
      config_editable: true     # nur hier darf der Lernende Werte ändern
      config:
        local_ae: CT_RAUM3
        remote_ae: PACS_ARCHIV  # ← der eingebaute Fehler
        remote_host: 10.20.0.10
        remote_port: 104
  tools: [echoscu, storescu, findscu, dcmdump]
  dataset: ct-thorax-3-slices
  templates:
    - label_key: tpl.echo
      command: "echoscu -aet DCMLAB-WS -aec ZIEL_AE 10.20.0.10 104"
  placeholders: [ZIEL_AE, UID_AUS_SCHRITT_1]

flag:
  type: tag_value               # tag_value | hash | exact
  source_tag: "0008,103E"
  hash: "sha256:<beim Build erzeugt>"
  case_sensitive: false

hints:
  - { id: h1, cost: 1 }
  - { id: h2, cost: 2 }
  - { id: h3, cost: 4 }

stuck_timeout_minutes: 20
status: draft
updated: "2026-09-12"
```

`nodes/<slug>/de.md` enthält `## Briefing`, `## Hints` mit `### h1`…`### h3` und
`## Write-up`. **Diese Überschriften sind Schlüssel und dürfen nicht umbenannt werden** —
der Parser findet sie darüber.

### 5.2 Flag-Handling

- Der Flag-Klartext liegt **nie** im Repo. Er steckt in den Testdaten des Datensatzes.
- Ein Build-Schritt (`make content-build`) liest den Wert aus den Assets, bildet
  `sha256(normalisierter Wert)` und schreibt ihn in `node.yml`.
- Normalisierung bei `case_sensitive: false`: trimmen, Mehrfach-Leerzeichen auf eines,
  lowercase. Dieselbe Normalisierung gilt bei der Prüfung der Eingabe.
- Eingaben werden pro Nutzer und Node rate-limitiert (z. B. 10 Versuche/Minute).

### 5.3 Das Regelwerk der simulierten Engine — aus dem Prototyp übernommen

Das ist die eigentliche Ausbeute aus Phase 0. Implementiere es genau so.

**Association-Prüfung, in dieser Reihenfolge** (gilt für jedes Netzwerkwerkzeug):

1. Host unbekannt → `TCP Initialization Error: Connection timed out`
2. Host bekannt, aber nicht der konfigurierte Port → `Connection refused`
3. Called AE ≠ konfiguriertem AE Title → `Rejected Permanent, Source: Service User, Reason: Called AE Title Not Recognized`, **Ablehnungszähler +1**
4. Calling AE nicht in der Liste konfigurierter Nodes → `Calling AE Title Not Recognized`, **Ablehnungszähler +1**
5. sonst akzeptiert, **Zähler für akzeptierte Associations +1**

Die Reihenfolge ist didaktisch, nicht technisch begründet: Sie erzwingt die
Stufenprüfung *Netzwerk → Transport → Identität*. Nicht umsortieren.

**Werkzeugverhalten**

- **Alle DCMTK-Werkzeuge sind bei Erfolg still.** `-v` zeigt die Association-Schritte,
  `echo $?` liefert den Exitcode. Das ist der erste Stolperstein für Einsteiger und
  gehört ausdrücklich dazu.
- **DCMTK-Defaults sind gesetzt:** `-aet ECHOSCU`, `-aec ANY-SCP`. Wer `echoscu 10.20.0.10 104`
  ohne Parameter tippt, bekommt sofort eine Ablehnung — Lernmoment, kein Umgebungsfehler.
- **`findscu`** kennt Study Root (`-S`) und Patient Root (`-P`), `-k` mit Schlüsselwort
  (`PatientID`) **und** mit Tag (`0010,0020`), Wildcards `*` und `?`. Antworten im
  DCMTK-Format inklusive VR, Längenangabe und Keyword.
- **Abfrage auf SERIES-Ebene ohne `StudyInstanceUID`** → `0xa900 Identifier does not match SOP Class`.
  Deshalb muss der Flag in zwei Schritten geholt werden.
- **`storescu` von der Workstation** scheitert an fehlenden Dateien — die Bilder liegen
  auf der Modalität, nicht beim Lernenden.
- **Platzhalter-Abfang:** Wer `StudyInstanceUID=UID_AUS_SCHRITT_1` stehen lässt, bekommt
  nicht „0 Antworten", sondern den Hinweis, dass VR `UI` nur Ziffern und Punkte erlaubt.
- Nicht-DICOM-Befehle im Szenario: `ping`, `ls`, `cat`, `echo $?`, `clear`, `help`.
- Ein unbekannter Befehl gibt `command not found` zurück, nie einen Stacktrace.

**Rückkopplung**

- Die Zähler auf der Archiv-Statusseite reagieren live. Jede abgelehnte Association —
  auch die des Lernenden, auch jeder Wiederholungsversuch der simulierten Modalität —
  erhöht die Ablehnungszahl. *Die Verbindung kommt an und wird abgewiesen* ist eine
  andere Aussage als *es kommt gar nichts an*; dieses Signal ist Lernstoff.
- Der Bestand (Studies / Series / Instances) springt erst nach erfolgreichem C-STORE.

**Punktevergabe**

- Volle Punktzahl minus die `cost` jedes genutzten Hints.
- Write-up vorab ansehen setzt die Node auf 0 Punkte (Fortschritt bleibt erhalten).
- Nach `stuck_timeout_minutes` ohne Fortschritt bietet die Oberfläche Hint 1 **aktiv** an
  — das ist die Anti-Frust-Regel und kein optionales Extra.
- **First Blood:** Bonus für die erste Lösung einer neu veröffentlichten Node.

### 5.4 Oberfläche einer Node

Drei Umgebungen als Tabs — die Trennung ist inhaltlich und bildet ab, **wo** man im
echten Leben Zugriff hat:

| Tab | Was der Lernende kann |
|---|---|
| **Workstation** | volle Shell mit den Werkzeugen aus `environment.tools`, plus Befehlsvorlagen zum Anklicken |
| **Modalität / Konsole** | nur die Felder aus `config` mit `config_editable: true` ändern, Auftrag auslösen, Auftragsprotokoll lesen. **Keine Shell.** |
| **Archiv** | Statusseite: Port, Bestand, Zähler akzeptiert/abgelehnt. Konfiguration gesperrt. |

Links daneben: Briefing, gestaffelte Hints mit sichtbaren Kosten, Flag-Eingabe, Write-up.

**Befehlsvorlagen** schreiben einen vollständigen Befehl in die Eingabezeile, statt ihn
auszuführen. Der Platzhalter in GROSSBUCHSTABEN ist markiert und wird überschrieben.
Vorlagen sind **kein Hint** und kosten **keine Punkte** — sie nehmen die Syntax ab,
nicht das Denken.

**Zentrale Design-Regel:** Werte, die der Lernende selbst herausfinden soll, dürfen in
keiner Oberfläche ablesbar sein. Bei `silent-ct` ist der AE Title des Archivs in der UI
unsichtbar; er steht in einer Datei auf der Workstation (`cat netzplan-radiologie.txt`)
oder ergibt sich aus dem Nachstellen des Fehlers. Wer eine Node baut oder rendert,
prüft das ausdrücklich.

### 5.5 Engine-API (Vorschlag, du darfst verfeinern)

```
POST /v1/sessions                 {node_slug}                  → {session_id, state}
GET  /v1/sessions/{id}/state                                    → Hosts, Zähler, Bestand, Konfig
POST /v1/sessions/{id}/exec       {host, command}               → {stdout, stderr, exit_code, events[]}
POST /v1/sessions/{id}/config     {host, field, value}          → {ok, state}
POST /v1/sessions/{id}/action     {host, action: "send_study"}  → {log[], state}
POST /v1/sessions/{id}/flag       {value}                       → {correct: bool}
DELETE /v1/sessions/{id}
```

Der Sitzungszustand wird in Postgres gehalten (JSONB), nicht im Prozessspeicher der
Engine — die Engine muss neu startbar sein, ohne dass ein Lernender seinen Stand verliert.
Die Engine validiert jeden Befehl gegen `environment.tools`: ein nicht deklariertes
Werkzeug existiert in dieser Node nicht.

---

## 6. Die Spielwiese (echter Container)

Die freie Übungsumgebung neben jeder Lektion. Hier tippt der Lernende, was er will —
das kann eine Simulation nicht bedienen, deshalb ist sie echt.

| | |
|---|---|
| **Was läuft** | ein Orthanc als Archiv, daneben ein Toolbox-Container mit DCMTK, Python, `pydicom`, `pynetdicom` und den synthetischen Testdaten |
| **Zugang** | Web-Terminal (xterm.js über WebSocket) direkt neben dem Lektionstext, angehängt an den Toolbox-Container |
| **Adressierung** | AE `ORTHANC`, `127.0.0.1`, Port `4242`, Weboberfläche `8042` — bewusst wie eine lokale Standardinstallation |
| **Lebensdauer** | 60 Minuten ohne Aktivität, dann wird aufgeräumt. Kein Zustand über Sitzungen hinweg; Neustart ist immer eine Zeile |
| **Isolation** | eigenes Docker-Netz je Sitzung, **kein Egress**, kein Weg zu anderen Sitzungen, harte CPU- und Speichergrenzen, non-root, `no-new-privileges`, read-only Rootfs wo möglich, kein Docker-Socket im Container |
| **Verfügbarkeit** | nur für angemeldete Nutzer, ein Container-Paar je gleichzeitiger Sitzung, bei Lastspitzen **Warteschlange statt unbegrenztem Hochfahren** |
| **Kontingent** | konfigurierbares Limit pro Nutzer und Tag (Minuten), da dies der einzige mit der Nutzerzahl mitwachsende Kostenposten ist |

Der Orthanc-Weboberfläche (`8042`) wird über den Reverse Proxy pro Sitzung ein
authentifizierter Pfad zugewiesen; sie ist nie direkt aus dem Internet erreichbar.

Das Web-Terminal ist **dieselbe Vue-Komponente** für Nodes und Spielwiese. Für den
Lernenden soll kein Unterschied spürbar sein, ob er gegen eine Simulation oder gegen
einen echten Orthanc tippt. Unterschiedlich ist nur das Transport-Backend
(Engine-HTTP/WS vs. Container-Attach).

Testdaten werden ausschließlich vom Skript in `datasets/build/` erzeugt (pydicom), in
`content/datasets.yml` beschrieben und beim Sitzungsstart read-only in den
Toolbox-Container gemountet.

---

## 7. Datenmodell (Kern)

```
users ──< enrollments >── tracks ──< lessons
  │                                    │
  ├──< lesson_progress ────────────────┘
  ├──< quiz_reviews            (Spaced Repetition: karte, faelligkeit, stufe)
  ├──< node_attempts >── nodes ──< node_hints
  │        (status, started_at, flag_submitted_at, hints_used, points, engine_session_id)
  ├──< sandbox_sessions        (container_ids, started_at, last_activity_at, expires_at, status)
  ├──< achievements            (badges, first_blood)
  └──< profiles                (rank, skill_vector jsonb, public_slug, leaderboard_opt_in)
```

Regeln:

- `lessons`, `tracks`, `nodes` sind ein **Index über den Content**, kein Speicher dafür.
  Ein Befehl `content:sync` liest `content/` ein und aktualisiert diesen Index
  (Slug, Titel, Reihenfolge, Punkte, Hash der Quelldatei). Die Prosa bleibt im Dateisystem.
- Alle übersetzbaren Felder liegen als JSONB `{"de": "..."}`, nie als flache Spalte.
- Ränge: Novice → Operator → Administrator → Architect → Standard Bearer.
- Skill-Radar über die fünf Kategorien `netzwerk | datenmodell | bildgebung | integration | security`,
  gespeist aus `skills` der gelösten Nodes. Es zeigt Lücken, nicht nur Punkte.
- Leaderboard ist **optional und abschaltbar**, Standard ist Fortschritt gegen sich selbst.

---

## 8. Mehrsprachigkeit

Deutsch ist die einzige ausgelieferte Sprache. Die Struktur ist trotzdem von Anfang an
mehrsprachig — Nachrüsten ist teuer, Vorbereiten kostet fast nichts.

- Locale `de` als Standard, **Sprachumschalter im Code vorhanden, im UI ausgeblendet**.
- URL-Schema `/de/...` von Anfang an, Root leitet dorthin um.
- Keine hardcodierten UI-Strings ab Tag 1 — alles in `lang/de.json`.
- Datums-, Zahlen- und Zeitformate über die Locale.
- Fallback beim Content: fehlende `<locale>.md` → `de.md`.

**Inhaltliche Grundregel, die auch dich betrifft:** Englische Fachbegriffe werden nie
übersetzt — Association, Called/Calling AE Title, Presentation Context, Transfer Syntax,
Study Instance UID, Storage Commitment, Worklist, SOP Class. **Werkzeugausgaben werden
nie übersetzt.** Übersetzt werden Erklärung, Beispiel, Fehlerbeschreibung, Briefing,
Hint, Write-up und das Feld `purpose`.

---

## 9. Qualitätsanforderungen (gelten in jeder Phase)

- **Tests:** Pest für Laravel (Feature + Unit), pytest für Engine und Orchestrator,
  Playwright für zwei E2E-Pfade (Lektion lesen + Spielwiese starten; Node lösen + Flag).
  Die Engine-Regeln aus 5.3 bekommen eine **Tabellen-getriebene Testsuite**: jede der
  fünf Ablehnungsstufen, die DCMTK-Defaults, die stille Erfolgsausgabe, die
  SERIES-ohne-Study-Abweisung und der Platzhalter-Abfang je ein Testfall.
- **CI (GitHub Actions):** `content:validate`, Lint (Pint, ESLint, ruff), Typen
  (PHPStan Level 6+, mypy), alle Tests, `docker compose build`.
- **Konfiguration** ausschließlich über `.env`; `.env.example` ist vollständig und kommentiert.
- **Seeds:** `make seed` erzeugt einen Demo-Nutzer, importiert den Content und macht
  die Plattform in einem Schritt spielbar.
- **Commits:** Conventional Commits, kleine Einheiten, je Phase mindestens ein Commit,
  nie ein Commit mit rotem Test oder rotem Validator.
- **ADR:** Jede Abweichung von diesem Dokument und jede nicht triviale Entscheidung
  bekommt eine kurze Datei in `docs/adr/NNNN-titel.md` (Kontext, Entscheidung, Folgen).
- **Barrierefreiheit und Bedienung:** Tastaturbedienbarkeit im Terminal und in den
  Formularen, sichtbarer Fokus, ausreichende Kontraste. Die Plattform wird in
  Krankenhaus-IT auf alten Bildschirmen benutzt — keine Effekthascherei, kein Dark-Mode-Zwang.
- **Sicherheit:** CSRF, Rate Limits auf Flag- und Login-Endpunkten, Policies statt
  Rollen-Ifs, keine Secrets im Repo, Docker-Socket nur im Orchestrator, Sandbox ohne Egress.

---

## 10. Arbeitsphasen

Arbeite sie **in dieser Reihenfolge** ab. Jede Phase endet mit grünem Validator,
grünen Tests, einem Commit und einer kurzen Notiz im `README.md`, was jetzt geht.
Beginne eine Phase nicht, bevor die Definition of Done der vorigen erfüllt ist.

### P0 — Gerüst und Betrieb
Monorepo nach 3.2, `docker-compose.yml` mit App, Postgres, Redis, Engine, Orchestrator,
Caddy. Laravel 13 + Inertia + Vue 3 + Tailwind installiert. `Makefile` mit
`up`, `down`, `test`, `lint`, `seed`, `content-validate`, `content-build`. CI-Pipeline.
`README.md` mit einer Anleitung, die auf einem leeren Server funktioniert.
**DoD:** `git clone && cp .env.example .env && make up` liefert eine erreichbare Startseite.

### P1 — Content-Pipeline
Parser für `tracks.yml`, `meta.yml`, `de.md`, `tools/de.yml`, `glossary/de.yml`,
`datasets.yml`. Vollständiger `content:validate` nach 4.7 und `content:sync` nach
Abschnitt 7. Markdown-Renderer mit `{{term:x}}`-Auflösung und Mermaid.
**DoD:** Zwei echte Lektionen (1.1 und 1.5, liegen bei) und die Registry laufen durch
Validator und Sync; ein absichtlich kaputter Testfall je Validierungsregel schlägt fehl.

### P2 — Konten, Locale, Grundgerüst der UI
Registrierung, Login, E-Mail-Bestätigung, Passwort-Reset, Profil. `/de/`-Routing,
`lang/de.json`, Sprachumschalter im Code. Navigation, Tracks-Übersicht, leeres Dashboard.
**DoD:** Ein Nutzer kann sich registrieren, anmelden und die Trackliste sehen; kein
sichtbarer String steht hartkodiert im Quelltext.

### P3 — Lektionen
Lektionsansicht mit **gerenderter Werkzeugleiste** nach 4.4, Glossar-Tooltips,
Selbstcheck, Fortschritt (`lesson_progress`), Quiz-Karten mit Spaced Repetition.
**DoD:** Lektion 1.1 und 1.5 sind vollständig lesbar; die Werkzeugleiste stimmt
automatisch mit `meta.yml` überein; **NEU** wird korrekt abgeleitet; Fortschritt überlebt Logout.

### P4 — Node-Engine
FastAPI-Dienst nach 5.5, Regelwerk nach 5.3 vollständig, Zustand in Postgres,
Flag-Prüfung mit Hash und Normalisierung, Hints mit Kosten, `stuck_timeout`,
`content-build` erzeugt den Flag-Hash aus den Assets.
**DoD:** `silent-ct` ist als `node.yml` + `de.md` + Assets vollständig im Repo und über
die API lösbar; die Tabellen-Testsuite aus Abschnitt 9 ist grün; kein Flag-Klartext im Repo.

### P5 — Node-Oberfläche und Web-Terminal
Drei-Tab-Oberfläche nach 5.4, xterm.js-Komponente, Befehlsvorlagen mit markierten
Platzhaltern, Hint-Panel mit sichtbaren Kosten, Flag-Eingabe, Write-up nach Lösung.
Live-Zähler des Archivs.
**DoD:** `silent-ct` ist im Browser vollständig spielbar, inklusive beider Lösungswege
(Fehler nachstellen / eigene Dokumentation lesen); der AE Title des Archivs ist nirgends
in der UI ablesbar; ein Reload verliert keinen Fortschritt.

### P6 — Die zweite Node als Schema-Test
Node **Wrong Door** (easy): dasselbe Muster wie `silent-ct`, diesmal auf der
Calling-Seite — das Archiv kennt den Calling AE Title der Konsole nicht.
**Sie wird ausschließlich aus dem Schema erzeugt, ohne eine Zeile Sonderlogik.**
**DoD:** Wrong Door ist spielbar; falls dafür Engine-Code nötig war, ist das ein
Schema-Mangel und wird als ADR dokumentiert, nicht als Sonderfall verbaut.

### P7 — Die Spielwiese
Orchestrator-Dienst, Images `containers/orthanc` und `containers/toolbox`,
Datensatz-Erzeugung in `datasets/build/`, Sitzungsverwaltung mit Warteschlange,
Idle-TTL 60 Minuten, Kontingent pro Nutzer und Tag, Terminal-Anbindung über dieselbe
Vue-Komponente, Aufräum-Job.
**DoD:** Aus Lektion 1.5 heraus startet ein Nutzer die Spielwiese und bekommt bei
`echoscu -v -aec ORTHANC 127.0.0.1 4242` eine echte Antwort eines echten Orthanc;
ein Egress-Versuch aus dem Container scheitert nachweislich; nach 60 Minuten Leerlauf
ist das Container-Paar weg.

### P8 — Punkte, Ränge, Profil
Punktekonto, Ränge, Badges, Skill-Radar, öffentliches Profil unter `public_slug`,
PDF-Export des Profils, optionales Leaderboard (Standard aus), First Blood.
**DoD:** Nach dem Lösen von `silent-ct` mit einem Hint stehen 9 Punkte im Konto, das
Radar zeigt `netzwerk`, und das öffentliche Profil ist ohne Login erreichbar und exportierbar.

### P9 — Content-Fläche und Härtung
Gerüstdateien für alle Lektionen aus Track 1 und Track 4 (`status: draft`, Metadaten
vollständig, Text wo vorhanden), mindestens 10 Node-Definitionen, davon die
mitgelieferten vollständig. Betriebsdokumentation: Backup, Update, Logs, Metriken.
Rate Limits, Fehlerseiten, Datenschutz-/Impressums-Platzhalter, Nutzungsbedingungen
mit dem Hinweis, dass die Spielwiese Nutzercode ausführt.
**DoD:** `make up && make seed` auf einem frischen Server ergibt eine benutzbare
Plattform mit Track 1, Track 4, zwei vollständigen Nodes und laufender Spielwiese.

---

## 11. Was du ausdrücklich **nicht** baust

- Kein CMS, kein Editor für Lektionen im Browser. Content wird per Pull Request geändert.
- Keine echte DICOM-Implementierung in der Node-Engine. Nodes sind simuliert;
  `engine: container` ist vorgesehen, aber Phase 3 des Produkts.
- Keine Team-/Organisationskonten, keine Zertifikate, keine Bezahlung.
- Keine Saisons, keine Community-Nodes, keine Write-up-Beiträge anderer Nutzer.
- Kein zweiter, klickbarer Werkzeugmodus neben dem Terminal. Die Befehlsvorlagen sind
  die beschlossene Antwort auf diese Frage; die größere Lösung ist bewusst offen.
- Keine Mobil-App. Responsiv genug fürs Tablet reicht, das Terminal bleibt Desktop-Sache.

---

## 12. Mitgelieferte Unterlagen

Lege diese Dateien unverändert unter `docs/` ab. Sie sind die Begründung hinter den
Festlegungen und im Zweifel die Quelle, aus der du Details nachliest:

| Datei | Inhalt |
|---|---|
| `konzept-lernplattform.md` | Marktlücke, Zielgruppen, didaktisches Modell, vollständiges Curriculum (41 Lektionen), Spielmechanik, Phasenplan |
| `content-schema.md` | verbindliches Content-Format — Langfassung von Abschnitt 4 |
| `werkzeug-registry.md` | Werkzeugleiste und Startbelegung der Registry (21 Werkzeuge mit Slug, Suite, Zweck, Beispiel, Lektion) |
| `node-silent-ct.md` | Briefing, drei Hints und vollständiges Write-up der ersten Node |
| `prototyp-silent-ct.md` | Erkenntnisse aus dem spielbaren Prototyp, Herleitung des Engine-Regelwerks |
| `lektion-1.0/1.1/1.2/1.5` | vier geschriebene Lektionen als Eingangs-Content |

Bei Widersprüchen gilt: **dieses Auftragsdokument schlägt die Unterlagen**, weil es
neuer ist. Melde den Widerspruch trotzdem (Abschnitt 14).

---

## 13. Arbeitsweise

- Lies zuerst alle Unterlagen aus Abschnitt 12, dann fang an.
- Eine Phase nach der anderen. Keine Vorgriffe, keine halb fertigen Nebenbaustellen.
- Vor jedem Commit: `make lint && make content-validate && make test`.
- Halte `README.md` nach jeder Phase aktuell: was läuft, wie man es startet, was fehlt.
- Schreibe Code in Englisch (Bezeichner, Kommentare), **Inhalte und UI-Strings in Deutsch**.
- Wo du Prosa für Lektionen oder Nodes erfinden müsstest: **tu es nicht.** Lege ein
  Gerüst mit `status: draft` an und vermerke die Lücke in `docs/content-todo.md`.
  Erfundener Fachinhalt ist schlimmer als eine leere Datei.
- Erfinde keine Werkzeugausgaben. Wo du eine echte DCMTK- oder Orthanc-Ausgabe brauchst,
  erzeuge sie in der Spielwiese und übernimm sie wörtlich.

---

## 14. Wenn du eine Entscheidung brauchst

Nicht stehenbleiben und nicht raten. In dieser Reihenfolge:

1. Steht es in den Unterlagen aus Abschnitt 12? Dann gilt das.
2. Lässt sich die Entscheidung **umkehrbar** treffen? Dann triff sie, schreib ein ADR
   und mach weiter.
3. Ist sie **nicht umkehrbar** oder berührt sie eine der vier Leitplanken aus
   Abschnitt 1, das Content-Schema oder das Engine-Regelwerk? Dann halte an, sammle
   die Frage in `docs/offene-fragen.md` mit deiner Empfehlung und arbeite an einer
   anderen Stelle derselben Phase weiter.

Bekannte offene Punkte, die du **nicht** selbst entscheidest:

- Betriebskosten und Obergrenzen der Spielwiese (Kontingente sind konfigurierbar zu bauen,
  die Zahlen setzt der Betreiber).
- Lizenzmodell des Repos (Plattform offen vs. Content geschützt) — lass `LICENSE` leer
  und vermerke es.
- Ob es zusätzlich einen klickbaren Werkzeugmodus geben soll (siehe Abschnitt 11).

---

## 15. Abnahmekriterium für Phase 1

Auf einem frischen Server mit Docker:

```
git clone … && cd dcm-lab && cp .env.example .env && make up && make seed
```

Danach kann eine Person, die noch nie mit DICOM zu tun hatte, ohne irgendetwas zu
installieren:

1. ein Konto anlegen und sich anmelden,
2. Lektion 1.5 lesen — mit korrekt erzeugter Werkzeugleiste und Glossar-Tooltips,
3. aus der Lektion heraus die Spielwiese starten und dort gegen einen echten Orthanc
   `echoscu -v -aec ORTHANC 127.0.0.1 4242` laufen lassen,
4. die Node **Silent CT** im Browser lösen, den Flag einreichen und das Write-up sehen,
5. anschließend 10 Punkte, den Rang Novice und ein Skill-Radar mit einer Achse
   `netzwerk` im eigenen Profil finden.

Wenn das durchläuft, ist Phase 1 fertig.
