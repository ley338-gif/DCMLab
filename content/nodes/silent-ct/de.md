---
title: Silent CT
scenario_title: Das CT in Raum 3 ist still
---

## Briefing

Montagmorgen, 7:15 Uhr. Die erste Untersuchung im CT-Raum 3 ist durch, die MTRA ruft an: Im PACS kommt nichts an. Am Gerät sieht alles normal aus, die Bilder sind da, der Sendeauftrag läuft angeblich durch.

Am Wochenende war Wartung. Die Firma hat am Archiv ein Update eingespielt und anschließend die Netzwerkkonfiguration aus einem Backup zurückgeschrieben. Der Techniker der Modalität sagt, am CT sei nichts verändert worden — und er hat recht.

**Deine Umgebung:**

| System | Adresse | Was du darfst |
|---|---|---|
| Deine Workstation | 10.20.0.50 | Shell mit `echoscu`, `storescu`, `findscu`, `dcmdump`, `ping` — plus Befehlsvorlagen |
| Archiv | 10.20.0.10 | Statusseite ansehen; Konfiguration gesperrt (Herstellerzugang) |
| CT-Konsole Raum 3 | 10.20.0.30 | Netzwerkkonfiguration ansehen und ändern, Studie senden |

Auf der CT-Konsole gibt es keine Shell — nur das Konfigurationsmenü und den Sende-Knopf. Genau wie im echten Leben.

**Deine Aufgabe:** Bring die Thorax-Studie ins Archiv.

**Der Flag** ist die Series Description der Serie, die im Archiv ankommt. Auslesen kannst du sie, sobald sie dort liegt — in zwei Schritten, weil eine Abfrage auf Serien-Ebene die Study benennen muss:

```
# 1. Study der Patientin finden
findscu -S -k QueryRetrieveLevel=STUDY \
        -k PatientID=<PatientID> -k StudyInstanceUID \
        -aet DCMLAB-WS -aec <ArchivAE> 10.20.0.10 104

# 2. Serien dieser Study auflisten
findscu -S -k QueryRetrieveLevel=SERIES \
        -k StudyInstanceUID=<aus Schritt 1> \
        -k SeriesInstanceUID -k SeriesDescription \
        -aet DCMLAB-WS -aec <ArchivAE> 10.20.0.10 104
```

**Was du daran abliest:** Zwei Abfragen, weil eine Abfrage auf
Serien-Ebene die übergeordnete Study eindeutig benennen muss —
Schritt 1 liefert die StudyInstanceUID, die Schritt 2 dann braucht.

**Erster Schritt, falls du noch nie mit Kommandozeilenwerkzeugen gearbeitet hast:** Unter dem Terminal liegen fertige Befehlsvorlagen zum Anklicken. Der markierte Platzhalter wird überschrieben, dann Enter.

Vorkenntnisse: Lektion 1.5. Rechne mit 15 Minuten.

---

## Hints

### h1

Fang nicht bei der Firewall an, sondern beim einfachsten Test: einem C-ECHO von deiner Workstation zum Archiv — und danach mit genau den Werten, die die CT-Konsole benutzt.

Wenn eine Verbindung abgelehnt wird, sagt DICOM ziemlich genau, warum. Lies die Fehlermeldung Wort für Wort, nicht nur die erste Zeile.

### h2

Die Ablehnung nennt einen Grund: *Called AE Title Not Recognized*.

Das heißt: Das Archiv wurde unter einem Namen angesprochen, den es nicht als seinen eigenen erkennt. Es geht also nicht um Netzwerk, nicht um Ports, nicht um Rechte — es geht um einen Namen.

Schau dir an, welchen Ziel-AE-Title die CT-Konsole verwendet, und vergleiche ihn zeichengenau mit dem, unter dem das Archiv tatsächlich läuft. Deine eigene Dokumentation liegt auf der Workstation.

### h3

Das Archiv läuft unter `PACS-ARCHIV`, mit Bindestrich.

Die CT-Konsole sendet an `PACS_ARCHIV`, mit Unterstrich.

Trag in der Konsole den korrekten Ziel-AE-Title ein und sende erneut.

---

## Write-up

### Der Weg

**1. Erreichbarkeit prüfen — und dabei nichts beweisen**

```
$ ping 10.20.0.10
64 bytes from 10.20.0.10: icmp_seq=1 ttl=64 time=0.31 ms
```

**Was du daran abliest:** Der Host antwortet. Das sagt über DICOM noch gar nichts aus, schließt aber Kabel und Routing aus.

**2. C-ECHO von der Workstation**

```
$ echoscu -aet DCMLAB-WS -aec PACS-ARCHIV 10.20.0.10 104
$ echo $?
0
```

**Was du daran abliest:** Erfolgreich. Das Archiv lebt, lauscht auf 104 und nimmt Verbindungen an. Der Fehler liegt also nicht am Archiv als solchem.

**3. Den Fehler nachstellen**

Jetzt der entscheidende Schritt: dieselbe Verbindung mit den Werten, die die CT-Konsole benutzt. Die stehen in ihrem Konfigurationsmenü:

```
CT-Konsole Raum 3 — Netzwerkkonfiguration
  Eigener AE Title : CT_RAUM3
  Ziel-AE Title    : PACS_ARCHIV
  Ziel-Host        : 10.20.0.10
  Ziel-Port        : 104
```

**Was du daran abliest:** Genau diese Werte — nicht die eigenen der
Workstation — muss der nächste Testaufruf verwenden, um den Fehler der
CT-Konsole nachzustellen. Damit von der Workstation aus:

```
$ echoscu -aet CT_RAUM3 -aec PACS_ARCHIV 10.20.0.10 104
F: Association Rejected:
F:   Result: Rejected Permanent, Source: Service User
F:   Reason: Called AE Title Not Recognized
```

**Was du daran abliest:** Da ist er. Und die Meldung sagt wörtlich, was los ist: Der Name, unter dem das Archiv angesprochen wurde, ist ihm fremd.

**4. Vergleichen**

Den richtigen Namen findest du in der eigenen Dokumentation:

```
$ cat netzplan-radiologie.txt
AE TITLE        IP            PORT   System
--------------  ------------  -----  -----------------------------------
PACS-ARCHIV     10.20.0.10    104    Zentralarchiv (Herstellerwartung)
...

Archiv läuft als  : PACS-ARCHIV     ← Bindestrich
CT sendet an      : PACS_ARCHIV     ← Unterstrich
```

**Was du daran abliest:** Ein Zeichen. AE Titles werden zeichengenau verglichen — für DICOM sind das zwei völlig verschiedene Namen.

**5. Korrigieren und senden**

Ziel-AE-Title in der CT-Konsole auf `PACS-ARCHIV` ändern, dann den Sendeauftrag erneut auslösen:

```
07:15:03  Sendeauftrag #2 – CT Thorax nativ (3 Objekte)
07:15:04  Verbindungsaufbau 10.20.0.10:104 …
07:15:05  Association akzeptiert (Max PDU 16372)
07:15:06  Bild 1/3 gesendet – Status Success
07:15:07  Bild 2/3 gesendet – Status Success
07:15:08  Bild 3/3 gesendet – Status Success
07:15:09  Auftrag abgeschlossen – 3 von 3 Objekten übertragen
```

**Was du daran abliest:** Auf der Archiv-Statusseite springt gleichzeitig der Bestand von 0 auf 1 Study / 1 Series / 3 Instances.

**6. Flag holen**

```
$ findscu -S -k QueryRetrieveLevel=STUDY \
          -k PatientID=4711 -k StudyInstanceUID \
          -aet DCMLAB-WS -aec PACS-ARCHIV 10.20.0.10 104

$ findscu -S -k QueryRetrieveLevel=SERIES \
          -k StudyInstanceUID=1.2.276.0.7230010... \
          -k SeriesInstanceUID -k SeriesDescription \
          -aet DCMLAB-WS -aec PACS-ARCHIV 10.20.0.10 104
```

**Was du daran abliest:** Die Series Description aus der Antwort ist dein Flag.

Warum zwei Schritte? Eine Abfrage auf Serien-Ebene muss die übergeordnete Ebene eindeutig benennen — im Study Root ist das die StudyInstanceUID. Die PatientID allein genügt dafür nicht, und ein strenges Archiv weist eine solche Abfrage zurück. Mehr dazu in Lektion 2.3.

### Warum das im echten Leben passiert

Diese Node ist kein konstruiertes Rätsel. Das Muster ist einer der häufigsten Ausfälle nach Wartungsarbeiten:

- Bei einem Update wird eine Konfiguration aus einem älteren Backup zurückgeschrieben — mit der damaligen Schreibweise.
- Ein AE Title wird händisch abgetippt, Unterstrich und Bindestrich verwechseln sich.
- Zwei Firmen pflegen zwei Seiten derselben Verbindung und stimmen die Schreibweise nie ab.
- Ein System schreibt AE Titles intern in Großbuchstaben um, das andere nicht.

Das Tückische: **Am Gerät sieht alles richtig aus.** Die Konsole zeigt eine vollständige, plausible Konfiguration. Der Fehler ist nur im Vergleich zweier Systeme sichtbar — und genau deshalb ist das Nachstellen von der Workstation aus der Kniff, der dich hier in Minuten statt in Stunden zum Ziel bringt.

### Was du mitnimmst

1. **Wenn DICOM dir einen Grund nennt, ist das ein Geschenk.** Die meiste verlorene Zeit entsteht dadurch, dass nur die erste Zeile gelesen wird. Rechne im Alltag aber damit, dass viele Systeme nur `no reason given` liefern oder die Verbindung kommentarlos zumachen — dann ist das Log der Gegenstelle deine einzige Quelle.
2. **Stell den Fehler dort nach, wo du Werkzeuge hast.** Du musst nicht an der Konsole sitzen — du musst nur ihre Werte kennen und dich genauso melden. Genau das rettet dich, wenn die Gegenseite dir ihre Konfiguration nicht zeigt.
3. **Ping beweist nichts, C-ECHO beweist wenig, ein erfolgreiches C-STORE beweist etwas.** Jede Stufe prüft mehr als die vorige.
4. **AE Titles sind zeichengenau.** Keine Toleranz, kein Trimmen, keine Groß-/Kleinschreibungs-Gnade. Bei jeder neuen Verbindung: kopieren, nicht abtippen.
5. **Gepflegte eigene Dokumentation schlägt jeden Herstellerzugang.** Der Netzplan auf deiner Workstation hat hier mehr geholfen als das Archiv selbst.

### Verwandte Inhalte

- Lektion 1.5 — SCU und SCP, Called und Calling AE Title
- Lektion 1.6 — AE Title, Host, Port: eine Verbindung einrichten
- Lektion 4.1 — „Association rejected" systematisch
- Node **Wrong Door** (easy) — dasselbe Muster, diesmal auf der Calling-Seite
