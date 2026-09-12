---
title: Wrong Door
scenario_title: Der zweite Scanner meldet sich nicht
---

## Briefing

Vor zwei Wochen ist ein zweites MR-Gerät in Betrieb gegangen. Seitdem
kommen die Aufnahmen aus diesem Raum nicht im Archiv an — beim ersten
MR-Gerät im Haus funktioniert alles wie gewohnt.

Beide Geräte hängen am selben Archiv, am selben Switch, im selben Subnetz.
Der einzige Unterschied ist das Gerät selbst.

**Deine Umgebung:**

| System | Adresse | Was du darfst |
|---|---|---|
| Deine Workstation | 10.20.0.50 | Shell mit `echoscu`, `storescu`, `findscu`, `dcmdump`, `ping` — plus Befehlsvorlagen |
| Archiv | 10.20.0.10 | Statusseite ansehen; Konfiguration gesperrt (Herstellerzugang) |
| Zweites MR-Gerät | 10.20.0.31 | Netzwerkkonfiguration ansehen und ändern, Studie senden |

Auf der Gerätekonsole gibt es keine Shell — nur das Konfigurationsmenü und
den Sende-Knopf.

**Deine Aufgabe:** Bring die Aufnahme des zweiten MR-Geräts ins Archiv.

**Der Flag** ist die Series Description der Serie, die im Archiv ankommt —
genau wie bei der Node zuvor, in zwei Schritten über `findscu` auszulesen.

Vorkenntnisse: Lektion 1.5, Node „Silent CT". Rechne mit 15 Minuten.

---

## Hints

### h1

Silent CT hatte den Fehler auf der Called-Seite — beim Namen des Archivs.
Hier ist das Archiv nicht das Problem: Ein `C-ECHO` von der Workstation mit
deiner eigenen, bekannten Kennung gegen das Archiv funktioniert einwandfrei.

Stell stattdessen genau die Verbindung nach, die das zweite MR-Gerät
aufbaut — mit seiner eigenen Kennung als `-aet`.

### h2

Die Ablehnung nennt diesmal einen anderen Grund als bei Silent CT:
*Calling AE Title Not Recognized*.

Das heißt: Nicht das Archiv wird unter falschem Namen angesprochen —
sondern der *Anrufer* stellt sich unter einem Namen vor, den das Archiv
nicht in seiner Liste registrierter Geräte findet.

### h3

Das Archiv kennt `MR_2` (Unterstrich). Die Konsole des zweiten Geräts ist
mit `MR-2` (Bindestrich) konfiguriert.

Trag in der Konsole die korrekte Kennung `MR_2` ein und sende erneut.

---

## Write-up

### Der Weg

**1. Die Gegenprobe: Ist das Archiv schuld?**

```
$ echoscu -aet DCMLAB-WS -aec PACS-ARCHIV 10.20.0.10 104
$ echo $?
0
```

Erfolgreich, und still — genau wie bei jedem anderen funktionierenden
Gerät. Das Archiv selbst hat also kein Problem mit eingehenden
Verbindungen. Der Fehler muss auf der Seite des neuen Geräts liegen.

**2. Den Fehler nachstellen**

Die Konfiguration des zweiten MR-Geräts steht in seinem Menü:

```
Zweites MR-Gerät — Netzwerkkonfiguration
  Eigener AE Title : MR-2
  Ziel-AE Title    : PACS-ARCHIV
  Ziel-Host        : 10.20.0.10
  Ziel-Port        : 104
```

Dieselbe Verbindung von der Workstation aus, mit genau diesen Werten:

```
$ echoscu -aet MR-2 -aec PACS-ARCHIV 10.20.0.10 104
F: Association Rejected:
F:   Result: Rejected Permanent, Source: Service User
F:   Reason: Calling AE Title Not Recognized
```

Ein anderer Grund als bei Silent CT — und diesmal geht es nicht um das
Ziel, sondern um den Absender.

**3. Vergleichen**

```
$ cat geraeteliste-radiologie.txt
AE TITLE        Geraet
--------------  -----------------------------------
...
MR_1            MR-Geraet 1
MR_2            MR-Geraet 2 (Neuzugang)
...
```

Registriert ist `MR_2`, mit Unterstrich. Die Konsole sendet als `MR-2`, mit
Bindestrich — für DICOM zwei vollständig unterschiedliche Namen.

**4. Korrigieren und senden**

Eigenen AE Title in der Konsole auf `MR_2` ändern, dann den Sendeauftrag
erneut auslösen. Auf der Archiv-Statusseite springt der Bestand
anschließend von 0 auf 1 Study / 1 Series / 1 Instance.

**5. Flag holen**

Wie bei Silent CT in zwei Schritten: zuerst die Study über `PatientID`
finden, dann mit der zurückgegebenen `StudyInstanceUID` die Serie abfragen.
Die Series Description aus der zweiten Antwort ist dein Flag.

### Warum das im echten Leben passiert

Neue Geräte werden oft nach einem Namensschema in Betrieb genommen (`MR_1`,
`MR_2`, ...), aber die tatsächliche Eintragung im Archiv und die
Konfiguration am Gerät stammen aus zwei getrennten Arbeitsschritten von
zwei unterschiedlichen Personen. Ein Bindestrich statt Unterstrich fällt
dabei am Bildschirm kaum auf.

### Was du mitnimmst

1. **Dieselbe Fehlermeldungs-Familie, ein anderer Grund.** *Called* und
   *Calling AE Title Not Recognized* klingen ähnlich, zeigen aber auf
   entgegengesetzte Enden der Verbindung — wer das verwechselt, sucht am
   falschen Gerät.
2. **Erst die Gegenprobe, dann der Nachbau.** Eine funktionierende
   Verbindung mit bekannten Werten schließt das Archiv als Fehlerquelle
   aus, bevor du beim eigentlichen Verdächtigen suchst.
3. **Namenslisten veralten leise.** Ein Gerät kann korrekt aussehen und
   trotzdem nicht in der Liste stehen, die tatsächlich zählt.

### Verwandte Inhalte

- Lektion 1.5 — SCU und SCP, Called und Calling AE Title
- Node **Silent CT** (easy) — dasselbe Muster auf der Called-Seite
