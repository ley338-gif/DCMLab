# Content-Schemas — DCM Lab

Verbindliches Format für Lektionen, Nodes, Werkzeuge und Glossar. Alles liegt im Git-Repo, nicht in der Datenbank. Die Plattform liest diese Dateien ein — solange sich das Schema nicht ändert, ist geschriebener Content nie verloren.

## Verzeichnisstruktur

```
content/
├── SCHEMA.md                     ← diese Datei
├── tracks.yml                    ← Track-Definitionen und Reihenfolge
├── datasets.yml                  ← Testdatensätze, die in der Spielwiese liegen
├── lessons/
│   └── 1.5/
│       ├── meta.yml              ← sprachneutrale Metadaten
│       ├── de.md                 ← Lektionstext Deutsch
│       └── en.md                 ← später
├── nodes/
│   └── silent-ct/
│       ├── node.yml              ← sprachneutrale Definition (Technik, Flag, Punkte)
│       ├── de.md                 ← Briefing, Hints, Write-up Deutsch
│       └── assets/               ← vorbereitete DICOM-Testdaten
├── tools/
│   └── de.yml                    ← Werkzeugverzeichnis (siehe werkzeug-registry.md)
└── glossary/
    └── de.yml                    ← Fachbegriffe mit Kurzerklärung (Tooltips)
```

**Grundprinzip der Trennung:** Alles, was Technik ist (Ports, AE Titles, Flag-Hash, Punkte, Dateien, Slugs), steht sprachneutral in `meta.yml` / `node.yml`. Alles, was Prosa ist, steht in `<locale>.md`. Eine Übersetzung fasst die YAML-Dateien nie an.

---

## 0. Die Beispielregel — gilt für alles

**Jede Aussage, die man ausprobieren kann, wird mit einem Beispiel belegt.** Nicht als Zugabe am Ende, sondern direkt an der Stelle, an der die Aussage fällt.

Das ist die wichtigste Regel dieses Schemas, weil sie den Unterschied zwischen Lesestoff und Handlungsfähigkeit ausmacht. Ein Satz wie „mit `dcmdump` siehst du den Inhalt einer Datei" erzeugt Nicken. Ein Befehl mit echter Ausgabe erzeugt einen Versuch.

### Ein Beispiel besteht immer aus drei Teilen

```markdown
$ dcmdump +P PatientName +P Modality ct-thorax-0001.dcm      ← 1. der Befehl
(0008,0060) CS [CT]                    #  2, 1 Modality      ← 2. die Ausgabe
(0010,0010) PN [MUSTER^ERIKA]          # 12, 1 PatientName

**Was du daran abliest:** Tags lassen sich über ihren Namen   ← 3. die Leseanleitung
ansprechen, nicht nur über (gggg,eeee). Die Ausgabe kommt in
Tag-Reihenfolge, nicht in deiner Abfragereihenfolge.
```

1. **Der Befehl** — vollständig und kopierbar. Keine Platzhalter, wo ein echter Wert stehen kann. `127.0.0.1 4242` statt `<host> <port>`.
2. **Die Ausgabe** — echt. Gekürzt, wo die Kürzung nicht die Aussage trägt; nie erfunden, nie beschönigt.
3. **Die Leseanleitung** — beginnt wörtlich mit `**Was du daran abliest:**`. Ein bis drei Sätze, die sagen, was man aus der Ausgabe schließt. Nicht, was der Befehl tut — das steht darüber.

Der feste Wortlaut der Leseanleitung ist kein Stilzwang, sondern macht die Regel prüfbar (Abschnitt 8).

### Weitere Regeln dazu

- **Der Fehlerfall gehört dazu.** Zu jedem Werkzeug und jedem Dienst steht mindestens ein Beispiel, in dem etwas *nicht* klappt. Die Fehlermeldung ist der Lernstoff, die Erfolgsausgabe ist nur die Bestätigung.
- **Beispiele laufen in der Spielwiese**, nie gegen ein Produktivsystem. Die Spielwiese meldet sich bewusst wie eine lokale Standardinstallation: AE Title `ORTHANC`, Host `127.0.0.1`, Port `4242`, Weboberfläche auf `8042`. Damit stimmen dieselben Befehle auch dann noch, wenn sich später jemand freiwillig etwas Eigenes hinstellt. Eigene AE Titles in Beispielen heißen `MEINE-WS` bzw. `MEIN-EMPFANG`.
- **Mindestens ein Beispiel pro Lektion.** In Werkzeug-, Service- und Troubleshooting-Lektionen: mindestens eines pro Werkzeug bzw. pro Dienst.
- **Windows nicht vergessen**, wo es abweicht — `%ERRORLEVEL%` statt `$?`, PowerShell statt Shell-Schleife. Kurz, als Zusatz, nicht als zweite Spur.
- **Ausgaben altern.** Versionsnummern stehen nur dort, wo sie eine Aussage tragen. Jede Lektion mit Werkzeugbezug trägt am Ende eine Zeile *„Werkzeuglage geprüft am …"*.

### Gilt auch für Nodes

Im Write-up einer Node ist jeder Schritt ein Beispiel nach derselben Regel: Befehl, Ausgabe, was man daraus schließt. Ein Write-up, das den Lösungsweg nur beschreibt, ist unvollständig.

---

## 1. Die Werkzeugleiste — erzeugt, nicht geschrieben

Jede Lektion beginnt mit einem Kasten, der drei Fragen beantwortet, bevor der Text anfängt: Womit arbeite ich hier? Liegt bereit, was ich brauche? Hätte ich vorher etwas lesen sollen?

**Der Autor schreibt diesen Kasten nicht.** Er legt in `meta.yml` Slugs an, und die Plattform rendert daraus. Grund: Von Hand geschrieben wären das 41 Stellen, die auseinanderlaufen, sobald sich ein Befehl oder ein Pfad ändert — erzeugt ist es eine.

Format, Felder, Gestaltungsregeln und das Werkzeugverzeichnis stehen in **`werkzeug-registry.md`**. Für dieses Schema zählt nur: Die Leiste ist Pflichtblock Nummer eins, und sie entsteht aus `tools` und `sandbox` in der `meta.yml`.

Eine Regel daraus gehört hierher, weil sie den Zuschnitt von Lektionen bestimmt: **höchstens vier Werkzeuge je Lektion.** Wer mehr braucht, hat eine Lektion gebaut, die zwei Lektionen sein sollte.

---

## 2. Lektion — `meta.yml`

```yaml
id: "1.5"                      # String, nicht Float (sonst wird 1.10 zu 1.1)
track: fundamente              # Slug aus tracks.yml
order: 5                       # Position innerhalb des Tracks
title_key: lesson.1_5.title    # Titel liegt in der Sprachdatei, nicht hier
duration_minutes: 8            # Lesezeit ohne Lab
level: einsteiger              # einsteiger | aufbau | fortgeschritten
objectives_count: 3            # Anzahl Lernziele (für Validierung gegen de.md)

requires: ["1.0", "1.1"]       # Lektions-IDs, die vorher gelesen sein sollten
                               # leer = jederzeit lesbar

tools:                         # Slugs aus content/tools/de.yml, höchstens vier
  - echoscu                    # speisen die Werkzeugleiste
  - storescp

sandbox:
  required: true               # Spielwiese nötig, um die Beispiele zu fahren
  dataset: ct-thorax-60        # Slug aus datasets.yml
  note: "Zwei Serien, absichtlich gemischt"   # optional, eine Zeile

lab:
  node: scu-scp-basics         # Slug aus content/nodes/, oder null
  optional: false              # true = Lektion gilt auch ohne Lab als erledigt

glossary_terms:                # Begriffe, die hier zum ersten Mal fallen
  - scu
  - scp
  - ae-title
  - association

quiz:                          # Wissenskarten für Spaced Repetition
  - id: q1
    type: single               # single | multi | input
    answer: 1                  # Index bzw. exakter String; Text steht in de.md
  - id: q2
    type: multi
    answer: [0, 2]

tools_checked: "2026-09-12"    # Pflicht, sobald tools nicht leer ist

status: draft                  # draft | review | published
authors: ["ley338"]
updated: "2026-09-12"
```

**Regeln:**

- `id` immer als String in Anführungszeichen
- Titel und jeder sichtbare Text stehen **nie** in `meta.yml` — nur Schlüssel und Slugs
- `requires` beschreibt fachliche Abhängigkeit, nicht Reihenfolge im Menü. Eine Lektion ohne `requires` darf quer eingestiegen werden — Voraussetzung für spätere Umsortierung.
- `tools` ist gleichzeitig Inhaltsverzeichnis und Prüfgrundlage: Was hier nicht steht, darf in den Beispielen nicht vorkommen (Abschnitt 8).

## 3. Lektion — `de.md`

```markdown
---
title: SCU und SCP — Rollen, nicht Geräte
teaser: Warum dasselbe CT je nach Situation beides ist.
objectives:
  - Die Rolle vom Gerät trennen
  - Called und Calling AE Title korrekt zuordnen
  - Ein C-ECHO in beide Richtungen erklären
---

## Der Moment, in dem es klickt
...
```

**Struktur jeder Lektion — in dieser Reihenfolge:**

| Block | Pflicht | Zweck |
|---|---|---|
| **Werkzeugleiste** | **ja, erzeugt** | Werkzeuge, bereitliegende Daten, Voraussetzungen — aus `meta.yml`, nicht geschrieben (Abschnitt 1) |
| Aufhänger | ja | Eine reale Situation, kein Definitionssatz |
| Erklärung | ja | Der eigentliche Stoff, 5–8 Min. |
| **Beispiele** | **ja** | Befehl, Ausgabe, Leseanleitung — verteilt im Text, nicht gesammelt am Ende (Abschnitt 0) |
| Diagramm | wenn es hilft | ASCII oder Mermaid, kein Bildmaterial |
| „Im Alltag heißt das" | ja | Übertragung auf Klinikbetrieb; endet mit einer Befehlstabelle, wo es passt |
| Stolperfallen | ja | Kasten mit häufigen Missverständnissen |
| Lab-Einstieg | wenn Lab | Überleitung, keine Lösung |
| Selbstcheck | ja | 2–4 Fragen, Antworten eingeklappt |

**Schreibregeln:**

- Duzen. Kein „der Anwender", sondern „du".
- Kein Standard-Zitat als Erklärung. Erst verständlich machen, dann optional auf den Standard verlinken.
- Fachbegriff beim ersten Vorkommen mit `{{term:scu}}` auszeichnen → wird zum Tooltip aus dem Glossar.
- Maximal ein Konzept pro Absatz.
- Jedes Beispiel kommt aus dem Krankenhausbetrieb, nicht aus der Lehrbuchwelt.
- **Keine Behauptung ohne Beleg.** Wo etwas überprüfbar ist, steht der Befehl dabei, mit dem man es überprüft (Abschnitt 0).
- Fragen als Zwischenüberschriften sind gut („Was steht in dieser Datei?"), aber eine Frage ohne darauffolgendes Beispiel ist unvollständig.
- **Den Inhalt der Werkzeugleiste nicht wiederholen.** Wer im Fließtext nochmal aufzählt, was oben steht, schreibt die Lektion doppelt.

## 4. Fachbegriffe — die Übersetzungsregel

**Englische Fachbegriffe werden nicht übersetzt.** Weder ins Deutsche noch später in eine andere Sprache.

| Bleibt immer englisch | Wird übersetzt |
|---|---|
| Association, Called/Calling AE Title, Presentation Context, Transfer Syntax, Abstract Syntax, Storage Commitment, Worklist, Study/Series/Instance, SOP Class | Erklärung, Beispiel, Fehlerbeschreibung, Briefing, Hint, Write-up |

Grund: Logs, Konfigurationsmasken und Conformance Statements sind englisch. Wer „Verbindungsaufbau-Kontext" lernt, findet in keinem Log etwas wieder.

Dasselbe gilt für Befehle und Ausgaben: **Werkzeugausgaben werden nie übersetzt.** Was auf dem Bildschirm steht, steht auch in der Lektion — die Leseanleitung darunter ist der übersetzte Teil. In der Werkzeug-Registry ist `purpose` das einzige übersetzbare Feld.

## 5. Glossar — `glossary/de.yml`

```yaml
scu:
  term: SCU                       # nie übersetzt
  expansion: Service Class User    # nie übersetzt
  short: Die Seite, die eine Anfrage stellt — wer anruft.
  see_also: [scp, association]
  lesson: "1.5"                   # wo es erklärt wird
```

`term` und `expansion` sind in allen Sprachdateien identisch. Übersetzt wird nur `short`.

---

## 6. Node — `node.yml`

```yaml
slug: silent-ct
difficulty: easy                # easy | medium | hard | insane
points: 10                      # 10 | 25 | 50 | 100
category: netzwerk              # netzwerk | datenmodell | bildgebung | integration | security
skills: [netzwerk]              # fließt ins Skill-Radar des Profils
related_lessons: ["1.5", "4.1"]
estimated_minutes: 15

# ---- Umgebung (simuliert oder echter Container) ----
environment:
  engine: simulated             # simulated | container
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
      config_editable: true     # der Lernende darf hier Werte ändern
      config:
        local_ae: CT_RAUM3
        remote_ae: PACS_ARCHIV  # ← der eingebaute Fehler
        remote_host: 10.20.0.10
        remote_port: 104

  tools: [echoscu, storescu, findscu, dcmdump]   # Slugs aus tools/de.yml
  dataset: ct-thorax-3-slices

  # Fertige Befehle, die der Lernende in die Eingabezeile klicken kann.
  # Platzhalter in GROSSBUCHSTABEN sind der Teil, der die Lernaufgabe ist.
  templates:
    - label_key: tpl.ping
      command: "ping 10.20.0.10"
    - label_key: tpl.echo
      command: "echoscu -aet DCMLAB-WS -aec ZIEL_AE 10.20.0.10 104"
  placeholders: [ZIEL_AE, UID_AUS_SCHRITT_1]

# ---- Flag ----
flag:
  type: tag_value               # tag_value | hash | exact
  source_tag: "0008,103E"       # SeriesDescription der ankommenden Serie
  hash: "sha256:<wird beim Build erzeugt>"
  case_sensitive: false
  # Der Wert selbst steht NICHT im Repo. Er steckt in den Testdaten
  # und wird beim Build gehasht.

# ---- Hints (Text in de.md, hier nur Kosten) ----
hints:
  - id: h1
    cost: 1
  - id: h2
    cost: 2
  - id: h3
    cost: 4

stuck_timeout_minutes: 20       # danach bietet die Plattform h1 aktiv an

status: draft
updated: "2026-09-12"
```

**Regeln:**

- Der Flag-Klartext liegt **nie** im Repo, nur sein Hash.
- `config_editable` markiert, woran der Lernende schrauben darf. Alles andere ist Umgebung.
- `skills` speist das Radar-Chart — sparsam vergeben, maximal zwei je Node.
- `templates` sind kein Hint und kosten keine Punkte. Ein Platzhalter darf nie die Lösung vorwegnehmen — im Gegenteil: Er markiert genau die Stelle, an der die Node ihre Frage stellt.
- Auch Nodes nennen ihre Werkzeuge als Slugs. Die Node-Oberfläche zeigt sie in derselben Leiste wie die Lektionen.

### 6a. Archiv-Hosts mit vorhandenen Records (ab P10)

Bis P9 kannte ein Archiv-Host nur einen einzigen Bestand, der erst durch eine
Sendeaktion entsteht (Silent CT, Wrong Door, Neue Node). Für Nodes, in denen
das Archiv von Anfang an etwas enthält (z. B. eine C-FIND-Suche, die an der
Patient ID scheitert), trägt der Archiv-Host stattdessen `records`:

```yaml
- name: archive
  ip: 10.50.0.10
  services: [...]
  records:
    - patient_id: "MEYER, HANS"
      patient_name: "MEYER^HANS"
      study_uid: "1.2.276.0.7230010.3.1.4.<eindeutig>"
      study_description: "MR Kopf nativ"
      study_date: "20260310"
      series:
        - series_uid: "1.2.276.0.7230010.3.1.3.<eindeutig>"
          series_description: "T2 TSE tra"
```

`findscu` matcht dann echt gegen diese Records (DICOM-Wildcards `*`/`?`,
sonst zeichengenau — siehe `services/engine/app/find.py`) statt nur zu
prüfen, ob vorher gesendet wurde. Ein Host hat entweder `records` **oder**
den alten Sende-Mechanismus, nie beides. Details und Begründung: ADR 0011.

### 6b. Objekte mit echter Größe und Größenlimits (ab P10)

Für Nodes, in denen ein `storescu` direkt von einer Shell aus versucht
wird (statt über das Konfigurationsfeld einer Modalitäts-Simulation) und
die Größe eines Objekts über Erfolg oder Ablehnung entscheidet, trägt die
Umgebung `objects`, und der Ziel-Dienst trägt `max_object_bytes`:

```yaml
environment:
  hosts:
    - name: archive
      services:
        - port: 104
          ae_title: KLINIK-ARCHIV
          accepts: [ct-image-storage]
          max_object_bytes: 50000000   # 50 MB
  objects:
    - filename: "klein.dcm"
      bytes: 524288      # 512 x 512 x 16 Bit, eine klassische Schicht
    - filename: "gross.dcm"
      bytes: 78643200    # 150 Frames desselben Formats, ein Enhanced-Volumen
```

`ls` zeigt die Dateigröße real an (wie `ls -la` vor jedem Sendeversuch),
`storescu <peer> <port> <datei>` prüft die Größe gegen `max_object_bytes`
des passenden Diensts und lehnt zu große Objekte mit dem echten DIMSE-
Statuscode `0xA7xx` ("Refused: Out of Resources", PS3.7 Annex C) ab, ohne
den Bestand des Archivs zu verändern. Nodes ohne `objects` verhalten sich
unverändert (Abschnitt 5.3: Bilder liegen auf der Modalität, nicht auf der
Workstation). Details und Begründung: ADR 0012.

### 6c. Transfer-Syntax-Aushandlung beim Senden (ab P10)

Für Nodes, in denen eine falsche Transfer Syntax den Sendeauftrag
scheitern lässt (Host/Port/AE-Title können dabei bereits alle stimmen),
trägt der Ziel-Dienst `accepted_transfer_syntaxes`, und die
Modalitäts-Konfiguration bekommt ein zusätzliches editierbares Feld
`transfer_syntax`:

```yaml
environment:
  hosts:
    - name: archive
      services:
        - port: 104
          ae_title: KLINIK-ARCHIV
          accepts: [ct-image-storage]
          accepted_transfer_syntaxes: ["1.2.840.10008.1.2"]  # nur Implicit VR LE
    - name: ct-3
      role: modality-simulator
      config_editable: true
      config:
        local_ae: CT-3
        remote_ae: KLINIK-ARCHIV
        remote_host: 10.70.0.10
        remote_port: 104
        transfer_syntax: "1.2.840.10008.1.2.4.91"  # ← der eingebaute Fehler
```

Die Sendeaktion prüft `transfer_syntax` erst, nachdem Host, Port und
AE-Title bereits akzeptiert wurden (Reihenfolge aus Abschnitt 5.3 bleibt
unverändert) — eine Ablehnung meldet den echten Presentation-Context-
Ablehnungsgrund `transfer-syntaxes-not-supported` (PS3.8 Table 9-18,
Result-Wert 4), nicht einen Verbindungsfehler. Die Transfer-Syntax-UIDs
sind reale, aus PS3.5 Annex A stammende Werte (siehe
`services/engine/tests/test_transfer_syntax.py`). Nodes ohne
`accepted_transfer_syntaxes` verhalten sich unverändert. Details und
Begründung: ADR 0013.

## 7. Node — `de.md`

```markdown
---
title: Silent CT
scenario_title: Das CT in Raum 3 ist still
---

## Briefing
...

## Hints
### h1
...

## Write-up
(nach Lösung sichtbar)
```

Abschnittsüberschriften sind Schlüssel und dürfen nicht umbenannt werden — `## Briefing`, `## Hints`, `### h1`, `## Write-up`. Der Parser findet sie darüber.

Für das Write-up gilt die Beispielregel aus Abschnitt 0: jeder Schritt mit Befehl, Ausgabe und Leseanleitung.

---

## 8. Referenzwerte für Beispiele

Damit Beispiele über alle Lektionen hinweg zusammenpassen und ein Lernender sie ohne Umrechnen kopieren kann:

| Rolle | Wert |
|---|---|
| Spielwiese (Archiv) | AE `ORTHANC`, `127.0.0.1`, Port `4242`, Web `8042` |
| Eigene Workstation | AE `MEINE-WS` |
| Eigener Empfänger | AE `MEIN-EMPFANG`, Port `11112` |
| Testdaten | `~/daten/<dataset-slug>/` |
| Beispielpatientin | `MUSTER^ERIKA`, PatientID `4711` |
| Beispielstudie | `CT Thorax nativ`, Serien `Thorax 1.0 B70f` und `Thorax 5.0 B31f` |

In Nodes gilt stattdessen das Netz der jeweiligen Szenario-Umgebung (`10.20.0.0/24` bei *Silent CT*). Die beiden Welten werden nicht vermischt: Lektionen üben in der Spielwiese, Nodes spielen im fiktiven Klinikum.

---

## 9. Validierung

Ein `content:validate`-Befehl prüft vor jedem Commit:

**Struktur**

- jede `meta.yml` hat eine `de.md` und umgekehrt
- `objectives_count` stimmt mit der Anzahl in `de.md` überein
- jede Hint-ID aus `node.yml` hat einen `### h<n>`-Abschnitt
- jeder `{{term:x}}` existiert im Glossar
- jede `related_lessons`- und `requires`-ID existiert
- kein Flag-Klartext im Repo (Regex gegen das Flag-Format)

**Beispielregel**

- jede `de.md` enthält mindestens einen Codeblock
- auf jeden Codeblock folgt innerhalb von drei Zeilen eine Leseanleitung (`**Was du daran abliest:**`) — ausgenommen Diagramme und reine Struktur-Blöcke, die per `<!-- kein-beispiel -->` davor markiert werden

**Werkzeuge**

- jeder Slug aus `tools` existiert in `tools/de.yml`
- höchstens vier Slugs in `tools`
- **Umkehrprüfung:** Jedes Werkzeug, das am Anfang einer `$`-Zeile in einem Beispiel vorkommt und in der Registry steht, ist in `tools` deklariert
- `tools_checked` ist gesetzt und nicht älter als 12 Monate, sobald `tools` nicht leer ist
- `sandbox.dataset` existiert in `datasets.yml`
- jeder Platzhalter aus `node.yml` kommt in mindestens einem `templates`-Eintrag vor

Die Umkehrprüfung ist die wichtigste davon: Sie verhindert die Klasse „Lektion benutzt ein Werkzeug, das sie nie vorgestellt hat" — und sie ist der Grund, warum man sich beim Schreiben auf die Werkzeugleiste verlassen kann, statt sie nachzupflegen.

---

## 10. Nachzuziehen

Die Beispielregel und die Werkzeugleiste sind nach Lektion 1.1 und 1.5 entstanden. Beide sind inhaltlich gut, erfüllen die Abschnitte 0 und 1 aber noch nicht:

| Datei | Was fehlt |
|---|---|
| `lektion-1.1-was-dicom-ist.md` | Werkzeugleiste (`dcmftest`, `dcmdump`). Der Nachweis, dass eine Datei DICOM ist, als echter Befehl mit Ausgabe — `dcmftest` ist genau das Werkzeug, das dafür bisher fehlte. |
| `lektion-1.5-scu-und-scp.md` | Werkzeugleiste (`echoscu`, `storescp`). C-ECHO in beide Richtungen als zwei laufende Beispiele statt als Beschreibung. |

Bis das nachgezogen ist, stehen beide auf `status: draft`.
