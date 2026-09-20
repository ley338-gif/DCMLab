# Content-Schemas — DCM Lab

Verbindliches Format für Lektionen, Nodes, Werkzeuge und Glossar. Metadaten (Titel, Lernziele, Werkzeuge, Voraussetzungen, Schwierigkeit, Punkte, …) liegen immer im Git-Repo und werden bei jedem `content:sync`-Lauf aus den Dateien geschrieben — unabhängig vom Migrationsstatus einer Lektion/Node. Für die eigentliche **Prosa** gilt das nur mit einer wichtigen Ausnahme, siehe die Warnung direkt darunter.

> ### ⚠️ Prosa-Änderungen an bereits migrierten Lektionen/Nodes bleiben wirkungslos
>
> Seit dem Rich-Content-Cutover (ADR 0118) rendert der Lernpfad die eigentliche
> Prosa (Briefing/Erklärung/Stolperfallen/Selbstcheck bzw. Briefing/Hints/
> Write-up) einer Lektion oder Node **aus der DB-Spalte `rich_content`**,
> sobald diese gesetzt ist — `LearnerViewBuilder`/`NodeController::show()`
> lesen den Prosa-Teil von `body` dann überhaupt nicht mehr.
>
> `body` ist dabei aber **keine rein ungenutzte Legacy-Spalte**: `content:sync`
> schreibt sie bei jedem Lauf weiterhin vollständig aus der Datei, und bei
> Lektionen wird der `## Quiz`-Abschnitt (`quiz_raw`) laut ADR 0118 bewusst
> **immer** aus `body` gelesen, auch nach dem Cutover — nur der Prosa-Teil
> davor bzw. danach ist betroffen. Bei Nodes betrifft es `body` vollständig
> (Briefing/Hints/Write-up), da Nodes keinen Quiz-Sonderfall haben.
>
> `content:sync` warnt seit dieser Erkenntnis (`ContentSync.php`), wenn sich
> **der Prosa-Anteil** einer bereits migrierten Ressource ändert (reine
> Metadaten- oder Quiz-Änderungen lösen die Warnung bewusst nicht aus, weil
> sie weiterhin normal wirksam werden) — das ist der einzige Hinweis, es
> gibt keinen Validierungsfehler.
>
> **Vor jeder inhaltlichen Änderung an einer bestehenden Lektion/Node
> prüfen**, ob `rich_content` bereits gesetzt ist (z. B.
> `App\Models\Lesson::where('lesson_id', '3.2')->value('rich_content')`,
> oder `php artisan rich-content:coverage` für den Gesamtüberblick). Ist es
> gesetzt, führt eine reine Datei-Änderung **nicht** zum Ziel — die
> Änderung muss über den Studio-Rich-Content-Editor (oder programmgesteuert
> über dieselben Klassen: `ContentVersioningService::createDraft()` →
> `submitForReview()` → `ContentPublishingService::publish()`, NIEMALS über
> ein direktes Schreiben der Spalte) veröffentlicht werden.
>
> Betrifft aktuell (Stand ADR 0118 + Backfill) den Großteil des Bestands —
> track 7/8 (Interoperabilität/FHIR) und alle nach dem Backfill neu
> angelegten Lektionen/Nodes sind noch datei-basiert (`rich_content` ist
> dort `null`); dort funktioniert der klassische
> `de.md`/`node.yml` → `content:sync`-Weg wie in diesem Dokument beschrieben
> unverändert. `meta.yml`/`node.yml`-Felder wie `title`, `objectives`,
> `requires`, `tools`, `hints[*].cost`, `points`, `difficulty` bleiben
> **immer** datei-/`content:sync`-geführt, unabhängig vom Cutover-Status —
> nur die Prosa selbst wechselt die Quelle.

**Track und Themenfeld:** `Themenfeld` ist die Track-übergreifende Ebene (Abschnitt 13), `Track` bleibt das Kapitel innerhalb eines Themenfelds. Jeder Track referenziert per `themenfeld:`-Feld genau ein Themenfeld aus `themenfelder.yml`. Aktuell gibt es ein einziges Themenfeld (`dicom`), dem alle fünf Tracks angehören — die Ebene existiert bereits im Datenmodell, damit eine spätere Erweiterung auf weitere Themenfelder (siehe `docs/konzept-lernplattform.md` Abschnitt 13, "Mehr als DICOM") kein Schema-Bruch ist, keine neue Migration von Bestandsdaten erfordert.

## Verzeichnisstruktur

```
content/
├── SCHEMA.md                     ← diese Datei
├── themenfelder.yml              ← Themenfeld-Definitionen (Abschnitt 13)
├── tracks.yml                    ← Track-Definitionen und Reihenfolge, je Track ein Themenfeld
├── achievements.yml              ← Achievement-Definitionen (Abschnitt 12)
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

related_node:                  # vor CMS-8a hiess dieses Feld `lab` --
                                # umbenannt, um nicht mit dem CMS-8-
                                # Lab-Activity-Typ zu kollidieren
  node: scu-scp-basics         # Slug aus content/nodes/, oder null
  optional: false              # true = Lektion gilt auch ohne die Node als erledigt

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
themenfeld: dicom                # Slug aus themenfelder.yml, Default dicom (Abschnitt 13)
skills: [netzwerk]              # fließt ins Skill-Radar des Profils
related_lessons: ["1.5", "4.1"]
estimated_minutes: 15
interaction: terminal           # terminal | scenario, Default terminal (siehe 6j)

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
- `interaction` ist optional (Default `terminal`) — bestehende Nodes brauchen das Feld nicht. `scenario` ersetzt `environment:` durch einen Entscheidungsbaum, siehe 6j.
- `themenfeld` ist optional (Default `dicom`) — bestehende Nodes brauchen das Feld nicht. Steuert die Gruppierung im Labs-Katalog (`/nodes`, Abschnitt 5) und muss auf einen Slug aus `themenfelder.yml` verweisen.

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

### 6d. Abstract-Syntax-(SOP-Class-)Ablehnung beim Senden (ab P10)

Derselbe Presentation Context, der eine Transfer Syntax aushandelt
(Abschnitt 6c), handelt auch einen Abstract Syntax aus — den
vorgeschlagenen SOP Class UID. Für Nodes, in denen ein nicht
unterstützter Objekttyp den Sendeauftrag scheitern lässt (unabhängig
von einer eventuell passenden Transfer Syntax), trägt der Ziel-Dienst
`accepted_sop_classes`, und die Modalitäts-Konfiguration bekommt ein
zusätzliches editierbares Feld `sop_class`:

```yaml
environment:
  hosts:
    - name: archive
      services:
        - port: 104
          ae_title: KLINIK-ARCHIV
          accepts: [verification, ct-image-storage]
          accepted_sop_classes: ["1.2.840.10008.5.1.4.1.1.2"]  # nur klassisches CT Image Storage
    - name: ct-9
      role: modality-simulator
      config_editable: true
      config:
        local_ae: CT-9
        remote_ae: KLINIK-ARCHIV
        remote_host: 10.90.0.10
        remote_port: 104
        sop_class: "1.2.840.10008.5.1.4.1.1.2.1"  # ← der eingebaute Fehler: Enhanced CT
```

Die Sendeaktion prüft `sop_class` vor `transfer_syntax` (beide erst,
nachdem Host, Port und AE-Title bereits akzeptiert wurden — Reihenfolge
aus Abschnitt 5.3 bleibt unverändert): eine Ablehnung meldet den echten
Presentation-Context-Ablehnungsgrund `abstract-syntax-not-supported`
(PS3.8 Table 9-18, Result-Wert 3) statt `transfer-syntaxes-not-supported`
(Wert 4, Abschnitt 6c) — beide sind reale, im Standard definierte
Ablehnungsgründe in derselben Tabelle, aber verschiedene Ursachen. Ein
erfolgreiches C-ECHO (Verification SOP Class, real: `1.2.840.10008.1.1`)
beweist dabei nichts über diese Aushandlung — es ist eine eigene,
unabhängige Presentation-Context-Verhandlung. Die SOP-Class-UIDs sind
reale, aus PS3.6 Annex A stammende Werte (siehe
`services/engine/tests/test_abstract_syntax.py`). Nodes ohne
`accepted_sop_classes` verhalten sich unverändert. Details und
Begründung: ADR 0019.

### 6e. Abstract-Syntax-Ablehnung pro Objekt beim direkten `storescu` (ab P10)

Abschnitt 6d prüft `accepted_sop_classes` beim Sendeauftrag einer
Modalitäts-Simulation (ein einzelnes `sop_class`-Feld für den ganzen
Auftrag). Für Nodes, in denen stattdessen einzelne Objekte per
`storescu <peer> <port> <datei>` von einer Shell aus gesendet werden
(Abschnitt 6b), trägt jedes Objekt in `objects` zusätzlich ein eigenes
`sop_class`-Feld — dieselbe `accepted_sop_classes`-Liste des
Ziel-Diensts gilt dann pro Objekt, nicht für den ganzen Ordner:

```yaml
environment:
  hosts:
    - name: archive
      services:
        - port: 104
          ae_title: KLINIK-ARCHIV
          accepts: [verification, ct-image-storage]
          accepted_sop_classes: ["1.2.840.10008.5.1.4.1.1.2"]  # nur CT Image Storage
  objects:
    - filename: "schicht-01.dcm"
      bytes: 524288
      sop_class: "1.2.840.10008.5.1.4.1.1.2"       # kommt an
    - filename: "screenshot.dcm"
      bytes: 262144
      sop_class: "1.2.840.10008.5.1.4.1.1.7"        # wird abgelehnt
```

Ein gemischter Ordner kann so **teilweise** ankommen — genau das
Fehlerbild "Teiltransfer": einzelne `storescu`-Aufrufe scheitern mit
demselben Ablehnungsgrund wie in Abschnitt 6d
(`abstract-syntax-not-supported`, PS3.8 Table 9-18, Result-Wert 3),
ohne dass die bereits angekommenen Objekte betroffen sind. `dcmdump
<datei>` zeigt dabei die reale SOP Class UID eines Objekts
(Tag `(0008,0016) SOPClassUID`), sofern es ein `sop_class`-Feld trägt —
so kann die Ursache schon vor dem Sendeversuch nachgewiesen werden.
Objekte ohne `sop_class` und Nodes ohne `accepted_sop_classes`
verhalten sich unverändert. Details und Begründung: ADR 0020.

### 6f. Modality Worklist (ab P10)

Modality Worklist ist ein eigenes Query/Retrieve Information Model
(PS3.4 Annex K, SOP Class Modality Worklist Information Model - FIND,
real: `1.2.840.10008.5.1.4.31`) — kein `QueryRetrieveLevel` wie
STUDY/SERIES (Abschnitt 6a), sondern eine flache Liste geplanter
Verfahren (Scheduled Procedure Steps). Ein Archiv-Host trägt dafür
`worklist` statt `records`:

```yaml
environment:
  hosts:
    - name: archive
      services:
        - port: 104
          ae_title: RIS-BROKER
          accepts: [verification, modality-worklist-find]
      worklist:
        - patient_id: "20261"
          patient_name: "MEYER^HANS"
          accession_number: "A20261"
          scheduled_station_ae_title: "CT5-RAUM3"
          scheduled_procedure_step_start_date: "20260913"
          modality: "CT"
```

Ausgelöst wird die Abfrage über das reale `findscu`-Flag `-W` (statt
`-S`/`-P`) — matcht dann per Wildcard (dieselbe `dicom_wildcard_match`
aus Abschnitt 6a) gegen `worklist` statt `records`. Eine leere Antwort
ist dabei technisch immer eine **gültige** Antwort, kein Fehler — genau
das macht das typische Fehlerbild "Worklist leer" aus: ein plausibler,
aber falscher Query-Key (häufig `ScheduledStationAETitle`, real
Tag `(0040,0001)`) liefert `Number of Matches: 0`, ohne dass irgendwas
technisch schiefgeht. Die vereinfacht flachen Felder stehen real in der
Scheduled Procedure Step Sequence `(0040,0100)` — dieselbe Vereinfachung
wie bei STUDY/SERIES (Abschnitt 6a), keine echte Sequenz-Verschachtelung.
Nodes ohne `worklist` verhalten sich unverändert; `-S`/`-P` fragen
weiterhin ausschließlich `records` ab, nie `worklist`. Details und
Begründung: ADR 0021.

### 6g. `dcmdump` auf Objekt-Metadaten (ab P10)

Für Nodes, in denen reines Inspizieren lokaler Objekte die Aufgabe ist
(kein Senden, kein C-FIND) — etwa ein Vergleich derselben Serie in
verschiedenen Transfer Syntaxen — kann `dcmdump <datei>` (Abschnitt 6b:
`environment.objects`) zusätzliche Felder pro Objekt anzeigen:

```yaml
objects:
  - filename: "schicht-01.dcm"
    bytes: 52224
    transfer_syntax: "1.2.840.10008.1.2.4.50"   # File Meta (0002,0010)
    sop_class: "1.2.840.10008.5.1.4.1.1.2"      # Dataset (0008,0016)
    lossy_image_compression: "01"                # Dataset (0028,2110)
```

Jedes vorhandene Feld erzeugt eine eigene, real getaggte `dcmdump`-Zeile;
fehlende Felder werden nicht ausgegeben — genau wie bei einer echten
Datei, der ein optionales oder (bei verlustfreier Kompression bzw.
fehlender Kennzeichnung) nicht vorhandenes Element fehlt. `objects` ohne
eines dieser Felder verhalten sich unverändert (weiterhin der
Fallback-Fehler `"keine lokale Datei in dieser Simulation"`). Details
und Begründung: ADR 0022.

### 6h. `dcmftest` und weitere `dcmdump`-Objektfelder (ab P10)

`dcmftest <datei>` (Abschnitt 6b: `environment.objects`) beantwortet
die reale erste Frage vor jedem `dcmdump`: Ist die Datei überhaupt
gültiges DICOM-Format? Ein bekanntes Objekt liefert `yes: <datei>`
(Exit-Code 0), ein unbekannter Dateiname `no: <datei>` (Exit-Code 1) —
in dieser Simulation gilt jedes deklarierte Objekt als gültiges
DICOM-Format, ganz gleich, welchen Dateinamen es trägt (wie bei einer
echten Datei ohne `.dcm`-Endung).

`_exec_dcmdump` ist außerdem um drei weitere reale Objektfelder
erweitert — `modality` (0008,0060), `study_description` (0008,1030),
`acquisition_date` (0008,0022), `slice_thickness` (0018,0050) und
`convolution_kernel` (0018,1210) —, alle in echter aufsteigender
Tag-Reihenfolge ausgegeben (siehe `DCMDUMP_FIELD_ORDER` in
`services/engine/app/rules.py`). Für Nodes, in denen ein gesuchter Wert
per Tag-**Name**, nicht per Tag-**Nummer**, gefunden werden soll (kein
Query-Key vorgegeben), reicht ein voller `dcmdump <datei>` — der
Lernende sucht den Wert im vollständigen Dump, statt ihn per `+P
<Tag>` gezielt anzufragen (diese Simulation kennt `+P` nicht). Details
und Begründung: ADR 0023.

### 6i. `dcmdump`-Hierarchiefelder (ab P10)

Für Nodes, in denen mehrere Objekte per Zählschleife ausgewertet werden
sollen (wie viele Patienten/Studies/Series stecken in einem Ordner,
Abschnitt 6b), trägt jedes Objekt zusätzlich `patient_id`, `study_uid`
und/oder `series_uid` — dieselben realen Tags, die `app/find.py` schon
für C-FIND-Matching nutzt (`PatientID` 0010,0020, `StudyInstanceUID`
0020,000D, `SeriesInstanceUID` 0020,000E), hier aber ohne jede
Association oder C-FIND: reines lokales `dcmdump` pro Datei. Der
Lernende zählt selbst, wie viele unterschiedliche Werte pro Ebene
vorkommen — dieselbe Technik wie die reale Zählschleife
(`for f in *.dcm; do dcmdump +P StudyInstanceUID "$f"; done | sort |
uniq -c`) aus Lektion 1.2, nur ohne Shell-Loop-Unterstützung in der
Simulation (jede Datei wird einzeln gedumpt). Details und Begründung:
ADR 0024.

### 6j. `scenario`-Nodes (`interaction: scenario`, ab P10)

Für Themenfelder ohne Kommandozeile (Abschnitt 13, PoC "Datenschutz im
Klinikbetrieb") ersetzt ein Entscheidungsbaum die gesamte `environment:`
und wird von einem eigenen Dienst (`services/scenario-engine`, nicht
`services/engine`) ausgewertet:

```yaml
interaction: scenario
themenfeld: datenschutz         # steuert die Gruppierung im Labs-Katalog

scenario:
  start: anruf                  # Schluessel aus steps, an dem eine Sitzung beginnt
  steps:
    anruf:
      prompt: >
        Das Telefon klingelt. Eine Anruferin sagt, sie sei die Tochter
        eines Patienten und moechte die gestrigen Befunde erfahren.
      options:
        - id: sofort_auskunft
          label: Befunde direkt am Telefon mitteilen.
          next: fehler
        - id: identitaet_pruefen
          label: Nach einer Schweigepflichtentbindung fragen.
          next: erfolg
    fehler:
      terminal: true
      outcome: wrong             # correct | wrong
      prompt: Verstoss gegen die Schweigepflicht.
    erfolg:
      terminal: true
      outcome: correct
      reveal: schweigepflicht-gewahrt   # Klartext, von content:build gehasht
      prompt: "Richtig: ohne Entbindung keine Auskunft am Telefon."
```

Jeder Schritt ist entweder ein Entscheidungspunkt (`options`, jede mit
`id`/`label`/`next`) oder ein Terminalschritt (`terminal: true`,
`outcome`). `flag.source_tag: scenario` weist `content:build` an, den
Klartext aus `reveal` des `outcome: correct`-Terminalschritts zu hashen,
statt aus `datasets.yml` — eine Szenario-Node hat kein
`environment.dataset`. `templates`/`placeholders`/`tools` entfallen
ebenso; die Node-Oberfläche zeigt statt der Terminal-Tabs eine
Dialogfrage mit Antwortoptionen. Details und Begründung: ADR 0071.

### 6k. PACS-Routing — Object & Routing Foundation (ab Phase A)

Ein Host kann eine simulierte, sendende DICOM-Identität und eigene Routen
bekommen — eine Route gehört immer dem Host, der sie ausführt, nie einem
globalen Schlüssel:

```yaml
environment:
  hosts:
    - name: pacs
      ip: 10.20.0.10
      dicom:
        calling_ae: RAD-PACS       # Pflicht, sobald der Host routes hat (kein Fallback)
      services:
        - id: pacs-store            # nur Pflicht, wenn eine Route diesen Service referenziert
          port: 104
          ae_title: RAD-ARCHIV
          accepted_sop_classes: ["1.2.840.10008.5.1.4.1.1.2"]
      routes:
        - id: CT-TO-DOSE
          enabled: true
          destination:
            host: dose-scp          # muss ein Host in diesem environment sein
            service: dose-store     # muss eine services[].id dieses Hosts sein
          match:
            modality: CT             # Kurzform, siehe unten

    - name: dose-scp
      ip: 10.20.0.30
      services:
        - id: dose-store
          port: 104
          ae_title: DOSE-SCP
```

`match` erlaubt entweder die gezeigte Kurzform oder die kanonische Form mit
`all`/`any`:

```yaml
match:
  all:                              # UND-Verknüpfung
    - field: modality
      op: equals
      value: CT
    - field: sop_class
      op: in
      values: ["1.2.840.10008.5.1.4.1.1.2", "1.2.840.10008.5.1.4.1.1.2.1"]
```

Die Kurzform (`match: {modality: CT}`) ist ein Authoring-Vertrag für
**genau eine** Gleichheitsbedingung — sie normalisiert intern zu `all: [{field, op: equals, value}]`,
aber mehrere Felder in Kurzform (`{modality: CT, sop_class: ...}`) sind
**kein** gültiges implizites `all` mehr, sondern ein Validierungsfehler; für
mehrere Bedingungen ist die kanonische `all`/`any`-Form Pflicht. Kurzform und
`all`/`any` dürfen nicht gemischt werden.

Erlaubte `field`-Werte (Whitelist, Tippfehler-Schutz): `modality`,
`sop_class`, `study_description`, `series_description` — keine rohe
Tag-Syntax, kein `source_ae`. Erlaubte `op`-Werte: `equals`/`not_equals`
(brauchen `value`, kein zusätzliches `values`), `in` (braucht `values`,
kein zusätzliches `value`), `exists` (weder `value` noch `values`) — jede
Kombination mit dem jeweils falschen Schlüssel ist ein Validierungsfehler,
kein still ignoriertes Extra-Feld. `routes[].enabled` ist optional
(Default `true`), muss aber, wenn gesetzt, ein echtes YAML-Boolean sein
(`true`/`false`) — ein String wie `enabled: "false"` ist ein
Validierungsfehler statt eines stillschweigend wahren Werts.
`content:validate` prüft `destination.host`/`destination.service`,
eindeutige `services[].id`/Route-`id`, das erlaubte `field`/`op`-Vokabular,
den `enabled`-Typ und das Pflichtfeld `dicom.calling_ae` rein strukturell
— **nicht**, ob eine Route fachlich sinnvoll ist (`match: {modality: CT}`
bleibt gültig, selbst wenn genau das die eingebaute Root Cause eines Nodes
ist).

**Ab Phase A** (Object & Routing Foundation) führen `storescu` und der
Sendeauftrag einer Modalitäts-Simulation zusätzlich zum unveränderten
`bestand`-Zähler ein sitzungslokales Objekt- und Presence-Modell
(`state["objects"]`/`state["stored_objects"]`, primärer Schlüssel
`object_id`, nicht Dateiname oder SOP Instance UID).

**Ab Phase B** (Jobs, Events & Automatic Routing) wertet ein erfolgreicher
Store `routes:` des Ziel-Hosts automatisch aus: eine matchende, noch nicht
verarbeitete Route erzeugt einen deterministischen Routing-Job
(`state["jobs"]`, `j-001`, `j-002`, …), der synchron über dieselbe
Association-/SOP-Class-/Transfer-Syntax-Verhandlung wie ein lernenden-
initiierter Store läuft (`check_association()`, Wiederverwendung, kein
zweiter Verhandlungsweg) und bei Erfolg Presence am Zielhost setzt — mit
demselben `object_id`, kein Klon. Ein kuratiertes, append-only Event-Log
(`state["events"]`: `store.completed`, `route.evaluated`, `job.created`,
`job.sent`/`job.failed`) macht den Ablauf nachvollziehbar. `route_history`
verhindert, dass dieselbe `(host, route_id, object_id)`-Kombination
mehrfach automatisch verarbeitet wird (auch nach einem fehlgeschlagenen
Job — Phase B hat keinen Retry); `max_routing_depth` (Engine-Konstante,
Phase 1 = `1`) begrenzt automatische Forwards auf genau einen Hop nach dem
Initial Ingest, unabhängig von Fan-out (mehrere gleichzeitig matchende
Routen erzeugen Geschwister-Jobs auf derselben Tiefe). Details und
Begründung: ADR 0120.

**Ab Phase C** (Read-only PACS CLI) kann eine Node `pacs` in `environment.tools`
freigeben (nodeweit, wie jedes andere Werkzeug — nicht hostlokal) —
derselbe `tool not in node.tools`-Mechanismus, keine neue Sonderprüfung.
`pacs` liest ausschließlich bereits
vorhandenen Phase-A-/Phase-B-Zustand (nie `bestand`) und ist komplett
lesend, mit genau einer Ausnahme, die selbst side-effect-free ist:

```text
pacs objects                        # RuntimeObjects + Presence
pacs object show <object-id>
pacs routes                         # alle Routes aller Hosts
pacs route show <route-id>
pacs route test <route-id> <object-id>
pacs jobs                           # persistierter Job-State
pacs job show <job-id>
pacs events                         # Event-Log in Append-Reihenfolge
```

`pacs route test` mutiert **keinen** Zustand — kein Job, kein Event, keine
`route_history`-Änderung, kein `last_progress_at`-Touch — und verwendet für
die Auswertung exakt denselben Matcher (`evaluate_route()`) wie die
automatische Routenausführung, keinen zweiten. Bestehende Nodes ohne
`tools: [pacs]` sind unverändert — auch `help` zeigt `pacs` nur, wenn es
freigegeben ist. Details und Begründung: ADR 0120.

### 6l. Hands-on-Solve-Contract — `solve.requires` (ab Phase D.1)

**Generisch, nicht PACS-spezifisch.** Ein Hands-on-Node (`interaction:
terminal`) kann optional unter `solve:` eine Liste von Bedingungen über
den Session-State deklarieren, die zusätzlich zum ohnehin weiterhin
erforderlichen Flag-Wert erfüllt sein müssen, bevor der Node lösbar ist:

```yaml
solve:
  requires:
    - type: object_exists
      as: rdsr                       # Alias fuer spaetere Bedingungen
      where:
        sop_class: "1.2.840.10008.5.1.4.1.1.88.67"
        modality: SR

    - type: presence
      object: rdsr                   # Referenz auf den obigen Alias
      host: pacs
      present: true

    - type: presence
      object: rdsr
      host: dose-scp
      present: false

    - type: job_not_exists
      where:
        route_id: PACS-TO-DOSE
        object: rdsr

    - type: job_exists
      where:
        route_id: PACS-TO-DOSE
        status: sent

    - type: event_exists
      where:
        type: route.evaluated
        route_id: PACS-TO-DOSE
        object: rdsr
        matched: false
        field: modality
        operator: equals
        expected: CT
        actual: SR
```

**Warum**: ein Flag-Wert allein beweist nur, dass der Lernende den
richtigen Wert *kennt* — nicht, dass der zugrunde liegende Vorgang
tatsächlich stattgefunden hat. Ohne diesen Vertrag konnte ein technisch
eindeutiger, aus der Umgebung ableitbarer Flag-Wert (z. B. eine Objekt-ID
oder, wie im ersten Hands-on-Node `gefiltert`, eine Routing-Regel-ID) durch
bloßes Lesen der statischen Node-Definition erraten werden, ganz ohne den
Incident zu reproduzieren. `solve.requires` schließt diese Lücke, ohne
vorzuschreiben, mit welchen Befehlen der Lernende dorthin gelangt:

```text
Runtime-State  = Beweis, dass der praktische Vorgang stattgefunden hat
Flag/Antwort   = Beweis, dass der Lernende die Ursache erkannt hat
runtime prerequisites erfüllt AND Flag korrekt -> gelöst
```

**Bedingungstypen** (Whitelist, keine beliebigen Ausdrücke — kein `eval`,
kein JSONPath/JMESPath, keine Python-/PHP-Ausdrücke aus YAML):

- `object_exists` — **genau ein** Eintrag in `state["objects"]` muss alle
  Felder in `where` erfüllen (erlaubte Felder: `filename`,
  `sop_instance_uid`, `study_uid`, `series_uid`, `sop_class`, `modality`,
  `transfer_syntax`, `study_description`, `series_description`,
  `origin_host`). `as` bindet dieses eine Objekt an einen Alias-Namen, den
  spätere Bedingungen über `object: <alias>` referenzieren — Objekt-
  Korrelation ist dadurch eingebaut: verschiedene Bedingungen können sich
  nicht versehentlich auf verschiedene Objekte beziehen. **Kein Treffer
  und mehr als ein Treffer sind beide nicht erfüllt** — bei mehreren
  Kandidaten wäre unklar, welcher gemeint ist, und das Ergebnis darf
  niemals von der zufälligen Reihenfolge in `state["objects"]` abhängen
  (die Engine bindet nie einfach "das erste passende Objekt").
- `presence` — das per Alias referenzierte Objekt ist (`present: true`)
  oder ist nicht (`present: false`) in `state["stored_objects"][host]`.
- `job_exists` / `job_not_exists` — mindestens ein bzw. kein Eintrag in
  `state["jobs"]` erfüllt `where` (erlaubte Felder: `route_id`, `status`,
  `object` als Alias-Referenz).
- `event_exists` — mindestens ein Eintrag in `state["events"]` erfüllt
  `where` (erlaubte Felder: `type`, `route_id`, `object` als
  Alias-Referenz, `job_id`, `host`, `matched`, `field`, `operator`,
  `expected`, `actual`, `reason`).

**Auswertung ist rein lesend.** `solve.prerequisites_met()` mutiert nie
`state` — kein Event, kein Zähler, kein `last_progress_at`-Touch, kein
Protokollieren, welche `pacs`-Befehle der Lernende benutzt hat. Der
Diagnoseweg bleibt vollständig frei: nur der am Ende tatsächlich
entstandene Zustand zählt, nicht die Befehlshistorie.

**Kein automatisches Lösen.** `solve.requires` allein löst nie einen Node
— `rules.check_flag()` prüft weiterhin zuerst den Flag-Hash und danach
zusätzlich `solve.prerequisites_met()`; nur beides zusammen setzt
`state["solved"] = True`. Ein korrekter Flag-Wert bei nicht erfüllten
Prerequisites verhält sich nach außen wie ein falscher Flag (die
bestehende API kennt nur `{"correct": bool}` — eine feinere
Rückmeldung "Antwort korrekt, Versuch noch nicht vollständig" ist ein
dokumentierter UX-Follow-up, kein Teil von Phase D.1).

**Bestehende Nodes bleiben unverändert.** Fehlt der `solve:`-Schlüssel
komplett (der Normalfall für jeden heute bestehenden Node), gilt die
Bedingung automatisch als erfüllt — exakt das Verhalten vor Phase D.1.

**Fail-closed statt fail-open.** Ist `solve:` dagegen vorhanden, aber
strukturell ungültig — falscher Typ (`solve: foo`), fehlendes oder leeres
`requires` (`solve: {}`, `solve: {requires: []}`), ein Tippfehler wie
`require` statt `requires`, ein unbekanntes zusätzliches Feld neben
`requires`, oder ein `requires`-Eintrag, der selbst kein Objekt ist (z. B.
ein bloßer String) — gilt die Bedingung als **nicht erfüllt**, niemals
automatisch als erfüllt und niemals ein Absturz. Ein Autor, der glaubt, ein
Gate definiert zu haben, darf nie stillschweigend das alte
Flag-only-Verhalten zurückbekommen; diese Prüfung greift in der Engine
selbst dann, wenn invalider Content den Validator umgeht.
`content:validate` prüft `solve.requires` rein strukturell
(bekannte Typen, Pflichtfelder, referenzierte Hosts/Routes existieren,
Alias-Namen eindeutig, keine unbekannten Top-Level- oder `where`-Felder in
`solve` selbst oder in einer Bedingung) — **nie**, ob eine Bedingung
fachlich sinnvoll ist, genau wie bei `environment.hosts[].routes[].match`
(Abschnitt 6k).

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
- jeder Track referenziert ein existierendes Themenfeld aus `themenfelder.yml`
- jeder Eintrag in `achievements.yml` hat alle Pflichtfelder, jeder Slug ist eindeutig

**PACS-Routing** (Abschnitt 6k, ab Phase A)

- `route.destination.host` existiert in `environment.hosts`, `route.destination.service` ist eine `services[].id` **dieses** Ziel-Hosts
- ein Host mit `routes` hat `dicom.calling_ae`
- Route-`id` eindeutig je Node, `services[].id` eindeutig je Host
- `match`-`field` aus der erlaubten Whitelist, `op` aus `equals|not_equals|in|exists`, `op: in` mit nicht-leerer `values`-Liste ohne zusätzliches `value`, `equals`/`not_equals` mit `value` ohne zusätzliches `values`, `exists` ohne `value`/`values`
- `match`-Kurzform erlaubt genau ein Feld (nicht mehrere, nicht gemischt mit `all`/`any`) — geprüft wird ausschließlich die kanonische Form nach Normalisierung, Kurzform und Langform teilen sich einen Prüfpfad; **nicht** geprüft wird, ob eine Route fachlich sinnvoll ist
- `routes[].enabled`, wenn gesetzt, muss ein echtes Boolean sein (kein `"false"`, `0`/`1` o. ä.)

**Hands-on-Solve-Contract** (Abschnitt 6l, ab Phase D.1, generisch — nicht PACS-spezifisch)

- `solve`, wenn vorhanden, muss ein Objekt mit ausschließlich dem Feld `requires` sein (fail-closed: ein Tippfehler wie `require` oder ein falscher Typ wie `solve: foo` ist ein Validierungsfehler, nicht "kein Solve Contract")
- `solve.requires` ist eine nicht-leere Liste
- jede Bedingung hat ein `type` aus `object_exists|presence|job_exists|job_not_exists|event_exists`, keine unbekannten Top-Level- oder `where`-Felder
- `object_exists` hat ein eindeutiges `as` (Alias-Name), jeder spätere `object: <alias>` referenziert einen zuvor deklarierten Alias
- `presence.host` existiert in `environment.hosts`, `presence.present` ist ein echtes Boolean
- ein referenziertes `route_id` existiert unter `environment.hosts[].routes[].id`, ein referenzierter `status` ist `queued|sent|failed`, ein referenzierter Event-`type` ist einer der von der Engine tatsächlich erzeugten Typen, `event_exists`-`matched` ist ein echtes Boolean
- **nicht** geprüft wird, ob eine Bedingung fachlich zum Incident passt

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

**Track-Prüfung** (Abschnitt 11)

- zu jeder `id` aus `exam.yml` gibt es einen `### f<nn>`-Abschnitt in `de.md` und umgekehrt, keine doppelten IDs
- genau eine `**Erklärung:**`-Zeile je Frage
- `type` ist `single`, `multi`, `truefalse` oder `input`; `answer` passt zum Typ und liegt im gültigen (0-basierten) Indexbereich
- `review` ist Pflicht; jede `lesson`-ID existiert im Track, jeder `anchor` löst auf eine echte Überschrift der Ziel-`de.md` auf (Slug über `HeadingSlug`, dieselbe Klasse, die `MarkdownRenderer` für die Heading-IDs benutzt)
- pro Lektion mindestens `min_per_lesson` Poolfragen (optionales Feld in `exam.yml`, Default 4 — für Tracks, in denen praktisch jede Frage `cross` ist, z. B. Troubleshooting, darf es bewusst niedriger gesetzt werden), mindestens vier `cross`-Fragen, `draw` ≤ Poolgröße
- Typmischung 35–40 % `single`, 20–25 % `multi`, 20–25 % `truefalse`, 10–15 % `input`; mindestens ein Viertel der Poolfragen mit `difficulty: 3`
- jeder Slug aus `tags` existiert in `skills.yml`, höchstens zwei je Frage
- `pass_percent` zwischen 50 und 100

---

## 10. Nachzuziehen (erledigt)

Die Beispielregel und die Werkzeugleiste sind nach Lektion 1.1 und 1.5
entstanden und erfüllten die Abschnitte 0 und 1 anfangs noch nicht
(Werkzeugleiste fehlte in beiden). Beides ist inzwischen nachgezogen:
1.1 nennt `dcmftest`/`dcmdump` als echte Befehle mit Ausgabe (Nachweis,
dass eine Datei DICOM ist), 1.5 führt C-ECHO mit `echoscu`/`storescp`
in beide Richtungen als zwei laufende Beispiele vor. Beide stehen seit
der `status`-Normalisierung (ADR 0086) wie alle Lektionen auf
`status: published`.

## 11. Track-Prüfung — `exams/<track>/{exam.yml,de.md}`

Jeder Track bekommt am Ende eine gewertete Abschlussprüfung. Die drei
Wissenskarten je Lektion (`quiz:` in Abschnitt 2) bleiben davon unberührt
— sie sind die Sofortrückmeldung beim Lesen, die Prüfung ist der
zusätzliche, gewertete Pool über den ganzen Track.

### Ablage

```
content/
├── skills.yml                     # kontrolliertes Vokabular fuer tags
└── exams/
    └── fundamente/
        ├── exam.yml                # Typen, Antworten, Rückverweise, Ziehung
        └── de.md                   # Fragetexte, Optionen, Erklärungen
```

Trennprinzip wie überall: Technik in YAML, Prosa in Markdown.

### `exam.yml`

```yaml
track: fundamente            # Slug aus tracks.yml
title_key: exam.fundamente.title
pass_percent: 80
draw: 24                     # so viele Fragen werden aus dem Pool gezogen
duration_minutes: 25         # Richtwert für die Anzeige, keine Uhr
shuffle: true

questions:
  - id: f01
    type: single              # single | multi | truefalse | input
    answer: 1                 # 0-basiert bei single/multi, Boolean bei truefalse
    lesson: "1.5"              # Lektions-ID des Tracks, oder "cross"
    review:                   # Pflicht, ein Objekt oder eine Liste davon
      lesson: "1.5"
      anchor: "rollen-nicht-geraete"
    difficulty: 1              # 1 = Wiedergabe, 2 = Anwendung, 3 = Übertragung
    tags: [netzwerk]           # max. zwei, aus skills.yml
```

`id`s sind unveränderlich — daran hängt der Wiederholungsfortschritt in
`quiz_reviews` (dieselbe Tabelle wie bei den Lektionskarten, kein zweiter
Kartenstapel). Eine geänderte Frage bekommt eine neue ID, die alte entfällt.

**Fragenbank (ADR 0071/0079) — eine Frage wiederverwenden, statt sie
zweimal zu pflegen:** ein Pool-Eintrag kann `ref` statt `type`/`answer`
tragen und damit eine Frage referenzieren, die schon im `quiz:`-Block einer
Lektion (Abschnitt 2) gepflegt wird:

```yaml
  - id: f09
    ref: { lesson: "1.2", question: "q1" }   # statt type/answer
    lesson: "1.2"
    review: { lesson: "1.2", anchor: "vier-ebenen" }
    difficulty: 1
    tags: [datenmodell]
```

`type`, `answer`, Fragetext und Optionen kommen dann ausschließlich aus dem
`quiz:`-Eintrag und dem `**q1 — ...**`-Block der referenzierten Lektion —
`content:validate` prüft, dass `ref.lesson` und `ref.question` existieren.
Ein `ref`-Eintrag braucht **keinen** eigenen `### id — ...`-Abschnitt in
`de.md`; legt man trotzdem einen an (z. B. für eine prüfungsspezifische
Erklärung), gilt dafür weiterhin die `**Erklärung:**`-Pflichtzeile.
`review`, `difficulty` und `tags` bleiben wie bei jedem anderen Eintrag
Sache des Pools, nicht der referenzierten Lektion.

### `de.md`

```markdown
### f01 — Ein CT schickt Bilder an das Archiv. Welche Rolle hat das CT?

1. Storage SCP
2. Storage SCU
3. Das hängt vom Archiv ab
4. Beides gleichzeitig

**Erklärung:** Wer den Dienst anfragt, ist SCU — hier also das CT.

### f14 — `Association Accepted` im Log beweist, dass die Bilder übertragen wurden.

**Richtig / Falsch**

**Erklärung:** Falsch. Angenommen wurde die Verhandlung, nicht die Übertragung.
```

Überschriftenformat exakt `### f<nn> — <Fragetext>` — eine echte
Markdown-Überschrift der Ebene 3, bewusst anders als die
`**qN —**`-Inline-Karten der Lektions-Quiz (Abschnitt 2), weil eine
Prüfungsfrage eine echte, per Anker verlinkbare Überschrift braucht. Bei
`multi` steht `*(Mehrfachauswahl)*` hinter der Frage, bei `input`
`*(Freitext)*`, bei `truefalse` die Zeile `**Richtig / Falsch**` statt
einer Optionsliste. Jede Frage endet mit einer Zeile, die exakt mit
`**Erklärung:**` beginnt — sie sagt, warum richtig richtig ist und warum
die naheliegendste falsche Option falsch ist. Der Rückverweis steht nie in
der `de.md`, er wird aus `review` gerendert und verlinkt auf die
Ziel-Lektion samt Anker (`HeadingSlug` erzeugt denselben Slug, den
`MarkdownRenderer` als `id` auf die Überschrift setzt).

### Pool und Ziehung

Der Pool ist deutlich größer als die Prüfung (Ziel: mindestens 40 Fragen,
davon `draw` gezogen) — sonst bekäme man bei einer Wiederholung dieselben
Fragen. Die Ziehung ist abdeckungsbalanciert: mindestens zwei Fragen je
Lektion, vier bis sechs `cross`-Fragen, danach zufällige Auffüllung.
Distraktoren kommen ausschließlich aus dem Stolperfallen-Block oder einer
Symptomtabelle der jeweiligen Lektion — erfundene Distraktoren prüfen
nichts. Jede Frage ist im Lektionstext beantwortet; fehlt ein Beleg, wird
die Lektion um den fehlenden Absatz ergänzt, statt die Frage zu streichen.

---

## 12. Achievements — `achievements.yml`

Flache Liste, sprachneutral (keine `<locale>.md`-Gegenstelle — Achievement-Texte sind kurz genug, um direkt in der YAML zu stehen). `AchievementSeeder` schreibt diese Liste nach `achievement_definitions`.

```yaml
- slug: echo-heard                 # unveränderlich, Primärschlüssel in achievement_definitions
  name: Echo Heard
  description: Führe dein erstes erfolgreiches C-ECHO durch.
  image: echo-heard.png            # Dateiname, kein Pfad
  category: dicom                  # freies Vokabular, aktuell: labs | dicom | platform
  rarity: common                   # freies Vokabular, aktuell: common | uncommon — kein DB-Constraint
  points: 0
  is_hidden: false
  sort_order: 30
  unlock_when:                     # optional, siehe unten
    type: activity_completed
    activity_type: node
    key: silent-ct
```

Die neun Basisfelder sind Pflicht (`rarity` darf `null` sein, der Schlüssel muss aber existieren). `category` und `rarity` sind bewusst offene Strings statt eines Enums — bei mehreren Themenfeldern bekommt jedes seine eigenen `category`-Werte, ohne dass diese Datei oder ihr Schema sich ändern muss.

**`unlock_when` (ADR 0071/0077, P10.74) — deklaratives Auslösekriterium,
optional.** Fehlt es, bleibt die Vergabe hartkodiert (heute nur noch
`sandbox-starter` in `SandboxController`, weil "Spielwiese gestartet" kein
Abschluss ist). Ist es gesetzt, wertet
`App\Achievements\AchievementUnlockEvaluator` es automatisch aus, sobald
die referenzierte Aktivität abgeschlossen wird — ohne dass dafür
Controller-Code nötig ist. Bekannte `type`-Werte:

- `activity_completed` (`activity_type`, optional `key`): die gerade
  abgeschlossene Aktivität passt auf Typ/Schlüssel. Ohne `key` passt jede
  Aktivität dieses Typs.
- `track_passed` (`track`): die Abschlussprüfung des angegebenen Tracks
  wurde bestanden.
- `first_solve` (optional `activity_type`): der erste jemals
  abgeschlossene Datensatz dieses Nutzers in `activity_progress`.

`scope` (optional, Default `personal`) unterscheidet persönliche Abzeichen
(unique je Nutzer) von `global` (unique je Aktivität, unabhängig davon,
welcher Nutzer zuerst gewinnt — für "wer war der Erste"-Mechaniken).
`content:validate` prüft `unlock_when.type` gegen die bekannten Werte und,
je nach Typ, `track`/`key` gegen echte Tracks/Nodes.

Das frühere `node.yml`-Feld `achievements:` (Abschnitt 6) ist damit
abgelöst und wird nicht mehr verwendet — die vier Nodes, die es nutzten,
sind jetzt stattdessen über `unlock_when` in `achievements.yml` verdrahtet.

## 13. Themenfeld — `themenfelder.yml`

Die Track-übergreifende Ebene aus `docs/konzept-lernplattform.md` Abschnitt 13. Flache Liste, ein Eintrag pro Themenfeld:

```yaml
- slug: dicom                      # Primärschlüssel, von tracks.yml referenziert
  order: 1
  title_key: themenfeld.dicom.title
  status: published
```

Jeder Eintrag in `tracks.yml` bekommt ein `themenfeld:`-Feld mit dem Slug hier. `content:sync` löst das zu `tracks.themenfeld_id` auf; ein unbekannter Slug wird übersprungen und gewarnt (wie bei `lessons.track`). Aktuell existiert genau ein Themenfeld (`dicom`); die Ebene ist bewusst schon angelegt, damit eine spätere Erweiterung keine Migration von Bestandsdaten erfordert (siehe Abschnitt 13 im Konzeptdokument für die Kosten-Abwägung).
