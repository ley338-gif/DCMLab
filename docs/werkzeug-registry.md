# Werkzeug-Registry und Werkzeugleiste

*Ein Verzeichnis für alle Werkzeuge des Curriculums. Jede Lektion nennt nur noch Slugs — der Kasten am Kopf der Lektion rendert sich daraus.*

---

## 1. Warum erzeugt und nicht geschrieben

Lektion 1.0 stellt die Werkzeuge vor. Jede folgende Lektion braucht am Kopf trotzdem die Antwort auf drei Fragen, sonst steht der Lernende wieder still:

- **Womit arbeite ich hier?**
- **Liegt das, was ich brauche, schon bereit?**
- **Hätte ich vorher etwas lesen sollen?**

Diese Angaben von Hand in jede Lektion zu schreiben, hieße: 41 Stellen, die auseinanderlaufen, sobald sich ein Werkzeug, ein Befehl oder ein Dateipfad ändert. Erzeugt aus einem Verzeichnis laufen sie an genau einer Stelle auseinander — und `content:validate` merkt es.

Damit ändert sich auch die Rolle von 1.0: Sie ist nicht die Lektion, die man einmal liest und abhakt, sondern **das Nachschlagewerk, auf das jeder Kasten zurückverweist**.

---

## 2. `content/tools/de.yml`

Ein Eintrag je Werkzeug. `name`, `suite`, `example` und `kind` sind in allen Sprachdateien identisch — übersetzt wird nur `purpose`. Das ist dieselbe Regel wie beim Glossar.

```yaml
dcmdump:
  name: dcmdump
  suite: dcmtk                    # dcmtk | dcm4che | python | server | extern
  kind: datei                     # datei | netz | server | analyse | skript
  purpose: Kippt den kompletten Inhalt eines DICOM-Objekts als Text aus.
  example: "dcmdump datei.dcm"
  lesson: "1.0"                   # wo es ausführlich vorgestellt wird
  anchor: was-steht-in-dieser-datei
  needs_sandbox: false            # true = braucht die laufende Spielwiese
```

**Regeln:**

- `purpose` ist **eine** Zeile. Wer zwei braucht, hat das Werkzeug nicht verstanden oder beschreibt zwei Werkzeuge.
- `example` ist der kürzeste sinnvolle Aufruf, nicht der vollständigste. Der vollständige steht in der Lektion.
- `lesson` + `anchor` zeigen auf die Stelle, an der das Werkzeug erklärt wird — meistens 1.0, bei Spezialwerkzeugen die Fachlektion.
- Ein Werkzeug, das in keiner Lektion erklärt wird, gehört nicht in die Registry.

---

## 3. Die Werkzeugleiste

Steht **vor** dem Aufhänger, ganz oben in der Lektion. Sie wird gerendert, nicht geschrieben — der Autor legt in `meta.yml` nur Slugs an.

```
┌─ FÜR DIESE LEKTION ──────────────────────────────── 8 Min ─┐
│                                                            │
│  WERKZEUGE                                                 │
│  dcmftest   Prüft, ob eine Datei DICOM ist       NEU       │
│             dcmftest datei.dcm                             │
│  dcmdump    Kippt den Inhalt als Text aus                  │
│             dcmdump datei.dcm                              │
│                                                            │
│  LIEGT BEREIT   daten/ct-thorax/  ·  60 Dateien, synthetisch│
│  VORHER         1.0 Die Werkzeugkiste                      │
│  DANACH         Lab: Node „First Contact" (easy, 10 Pkt)   │
│                                                            │
│  [ Spielwiese starten ]                                    │
└────────────────────────────────────────────────────────────┘
```

**Was woher kommt:**

| Zeile | Quelle |
|---|---|
| Dauer oben rechts | `meta.yml: duration_minutes` |
| Werkzeuge, `purpose`, `example` | `meta.yml: tools` → Slugs → `tools/de.yml` |
| Markierung **NEU** | abgeleitet: kein früherer Eintrag im Track hat diesen Slug |
| LIEGT BEREIT | `meta.yml: sandbox.dataset` + `sandbox.note` |
| VORHER | `meta.yml: requires` → Titel aus der jeweiligen `de.md` |
| DANACH | `meta.yml: lab.node` → Titel, Schwierigkeit, Punkte aus `node.yml` |
| Schaltfläche | vorhanden, wenn irgendein Werkzeug `needs_sandbox: true` hat |

**Gestaltungsregeln:**

- Höchstens **vier** Werkzeuge. Wer mehr braucht, hat eine Lektion gebaut, die zwei Lektionen sein sollte. Das ist die eigentliche Leistung des Kastens: Er macht Überladung sichtbar, bevor jemand sie liest.
- Die Leiste ist einklappbar und beim zweiten Besuch einer Lektion standardmäßig zu. Sie ist eine Startrampe, kein Pflichtstoff.
- Kein Fließtext. Wer hier erklärt, schreibt die Lektion doppelt.
- **NEU** ist die einzige Auszeichnung. Sie sagt dem Wiederkehrer, wo er hinschauen muss, und dem Einsteiger, dass er hier gleich etwas Neues bekommt.

---

## 4. Ergänzung in `meta.yml`

```yaml
tools:
  - dcmftest
  - dcmdump

sandbox:
  required: true                  # Spielwiese nötig, um die Beispiele zu fahren
  dataset: ct-thorax-60           # Slug aus content/datasets.yml
  note: "Zwei Serien, absichtlich gemischt"   # optional, eine Zeile
```

Nichts davon ist sichtbarer Text — nur Schlüssel und Slugs. Die Trennung aus dem Content-Schema bleibt unangetastet.

---

## 5. Die Startbelegung

Das ist der Stand, mit dem das Curriculum startet. Werkzeuge kommen dazu, wenn eine Lektion sie braucht — nicht auf Vorrat.

### Dateien ansehen und verändern

| Slug | Suite | Wofür | Beispiel | Erklärt in |
|---|---|---|---|---|
| `dcmftest` | dcmtk | Prüft, ob eine Datei überhaupt DICOM ist | `dcmftest datei.dcm` | 1.1 |
| `dcmdump` | dcmtk | Kippt den kompletten Inhalt als Text aus | `dcmdump datei.dcm` | 1.0 |
| `dcm2json` | dcmtk | Gibt denselben Inhalt als JSON aus, zum Weiterverarbeiten | `dcm2json datei.dcm` | 1.3 |
| `dcmconv` | dcmtk | Schreibt ein Objekt in einer anderen Transfer Syntax neu | `dcmconv +te datei.dcm neu.dcm` | 1.7 |
| `dcmcjpeg` | dcmtk | Komprimiert ein Objekt | `dcmcjpeg datei.dcm klein.dcm` | 1.7 |
| `dcmdjpeg` | dcmtk | Packt ein komprimiertes Objekt wieder aus | `dcmdjpeg klein.dcm gross.dcm` | 1.7 |
| `dcmodify` | dcmtk | Ändert einzelne Tags in einer vorhandenen Datei | `dcmodify -m PatientID=4711 datei.dcm` | 4.6 |
| `img2dcm` | dcmtk | Macht aus einem gewöhnlichen Bild ein DICOM-Objekt | `img2dcm bild.jpg neu.dcm` | 3.2 |

### Über das Netz reden

| Slug | Suite | Wofür | Beispiel | Erklärt in |
|---|---|---|---|---|
| `echoscu` | dcmtk | Fragt eine Gegenstelle, ob sie antwortet | `echoscu -v -aec ORTHANC 127.0.0.1 4242` | 1.0 |
| `storescu` | dcmtk | Schickt Objekte an ein Archiv | `storescu -aec ORTHANC 127.0.0.1 4242 datei.dcm` | 2.2 |
| `storescp` | dcmtk | Nimmt selbst Objekte entgegen | `storescp -od ./eingang 11112` | 2.2 |
| `dcmsend` | dcmtk | Wie `storescu`, robuster bei vielen Dateien | `dcmsend 127.0.0.1 4242 ordner/ --scan-directories` | 2.2 |
| `dcmrecv` | dcmtk | Wie `storescp`, moderner Nachfolger | `dcmrecv 11112 -od ./eingang` | 2.2 |
| `findscu` | dcmtk | Fragt ab, was ein Archiv kennt | `findscu -S -k QueryRetrieveLevel=STUDY …` | 2.3 |
| `movescu` | dcmtk | Fordert Objekte an, Auslieferung an eine dritte Stelle | `movescu -S -aem ZIEL -k StudyInstanceUID=… …` | 2.4 |
| `getscu` | dcmtk | Fordert Objekte an, Auslieferung an einen selbst | `getscu -S -k StudyInstanceUID=… …` | 2.4 |
| `wlmscpfs` | dcmtk | Stellt eine Modality Worklist aus Dateien bereit | `wlmscpfs -dfp ./wl 1234` | 2.5 |

### Umgebung und Analyse

| Slug | Suite | Wofür | Beispiel | Erklärt in |
|---|---|---|---|---|
| `orthanc` | server | Das Archiv der Spielwiese, mit Weboberfläche | *läuft bereits* | 1.0 |
| `tshark` | extern | Zeigt, was tatsächlich über die Leitung geht | `tshark -i any -Y dicom` | 4.10 |
| `pydicom` | python | Liest und schreibt DICOM-Objekte im Skript | `dcmread("datei.dcm")` | 1.0 |
| `pynetdicom` | python | Baut DICOM-Verbindungen im Skript | `AE().associate(host, port)` | 2.2 |

**Nicht in der Registry, bewusst:** Viewer. Ein Viewer ist kein Prüfwerkzeug, und die Lektionen sollen nicht suggerieren, dass man mit dem Auge etwas verifiziert. Viewer kommen in 1.0 als Kategorie vor und in Track 3 dort, wo Darstellung tatsächlich das Thema ist.

---

## 6. Prüfungen für `content:validate`

Zusätzlich zu den Regeln aus `content-schema.md`:

- jeder Slug aus `meta.yml: tools` existiert in `tools/de.yml`
- **Umkehrprüfung:** Jedes Wort, das in einem Beispielblock am Anfang einer `$`-Zeile steht und in der Registry vorkommt, muss in `meta.yml: tools` deklariert sein. Damit kann keine Lektion ein Werkzeug benutzen, das sie nie vorgestellt hat.
- `lesson` + `anchor` jedes Registry-Eintrags zeigen auf eine existierende Lektion und eine existierende Überschrift
- höchstens vier Einträge in `meta.yml: tools`
- `sandbox.dataset` existiert in `datasets.yml`
- `purpose` ist einzeilig und höchstens 90 Zeichen

Die Umkehrprüfung ist die wichtigste davon. Sie ist der Grund, warum man sich beim Schreiben auf den Kasten verlassen kann, statt ihn nachzupflegen.

---

## 7. Was als Nächstes daraus folgt

1. `content-schema.md` ist entsprechend ergänzt: Werkzeugleiste als erster Pflichtblock, `tools` und `sandbox` in `meta.yml`, die Prüfungen aus Abschnitt 6.
2. 1.1 und 1.5 bekommen beim Nachziehen der Beispielregel gleich ihre Werkzeugleiste — bei 1.1 ist `dcmftest` der fehlende Baustein, mit dem sich der `DICM`-Nachweis endlich als Befehl statt als Beschreibung führen lässt.
3. `datasets.yml` fehlt noch. Es hält, welche Testdatensätze in der Spielwiese liegen — bisher nur `ct-thorax-60`. Kann warten, bis die zweite Lektion einen zweiten Datensatz braucht.
