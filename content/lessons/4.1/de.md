---
title: „Association rejected" — die Verbindung kommt gar nicht erst zustande
teaser: Die häufigste Fehlkonfiguration überhaupt, und die einzige, die sich zeichengenau prüfen lässt.
objectives:
  - Eine abgelehnte Association von einem Fehler im laufenden Transfer unterscheiden
  - Den Ablehnungsgrund im Log dem Called oder dem Calling AE Title zuordnen
  - Beide Konfigurationsseiten zeichengenau abgleichen, statt eine Seite zu raten
---

## Zwei ganz verschiedene Arten von "es geht nicht"

Ein Ticket sagt "die Modalität sendet nicht ins Archiv" — das kann zwei
völlig verschiedene Ursachen haben, und die Unterscheidung entscheidet,
wo du überhaupt suchst:

1. **Die Association kommt nicht zustande.** Der Verbindungsversuch
   selbst scheitert oder wird abgelehnt, bevor ein einziges Bild
   unterwegs ist. Das ist das Thema dieser Lektion.
2. **Die Association steht, aber etwas geht danach schief** — falsche
   Transfer Syntax, ein zu großes Objekt, eine abgelehnte SOP Class. Das
   sind andere Fehlerbilder mit anderen Meldungen, siehe Lektion 4.2 ff.

Der Unterschied zeigt sich schon in der ersten Log-Zeile: Kommt überhaupt
eine Antwort vom Gegenüber, oder scheitert es an der reinen
TCP-Verbindung?

## Die TCP-Ebene: bevor DICOM überhaupt mitredet

Zwei Fehlerbilder passieren, bevor irgendeine DICOM-Verhandlung beginnt —
es geht nur um Host und Port.

```
$ echoscu -v -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242
I: Requesting Association
I: Association Accepted
I: Sending Echo Request: MsgID 1
I: Received Echo Response (Status: 0x0000 - Success)
I: Releasing Association
```
**Was du daran abliest:** Das ist der Erfolgsfall zum Vergleich — vier
Zeilen zwischen Anfrage und Antwort, jede DICOM-Verhandlung lief durch.
Alles, was jetzt folgt, weicht davon ab, bevor die zweite Zeile erreicht
ist.

```
$ echoscu -v -aet MEINE-WS -aec ORTHANC 127.0.0.1 9999
I: Requesting Association
E: Association request failed: unable to connect to remote
E: TCP Initialisation Error: [Errno 111] Connection refused
I: Aborting Association
```
**Was du daran abliest:** "Connection refused" heißt: der Host antwortet,
aber auf diesem Port lauscht kein Dienst — ein DICOM-Server läuft dort
nicht (oder eine Firewall lässt die Anfrage passieren, aber niemand nimmt
ab). Noch keine Rede von AE Titles.

```
$ echoscu -v -aet MEINE-WS -aec ORTHANC 10.255.255.1 4242
I: Requesting Association
E: Association request failed: unable to connect to remote
E: TCP Initialisation Error: [Errno 101] Network is unreachable
I: Aborting Association
```
**Was du daran abliest:** Eine andere Fehlermeldung für ein anderes
Problem — der Host selbst ist im Netz nicht erreichbar. Beide Meldungen
kommen von der Betriebssystem-Ebene (`Errno`), nicht von DICOM.

## Die Association-Ebene: {{term:called-ae-title}} und {{term:calling-ae-title}}

Stimmen Host und Port, verhandelt DICOM als Nächstes die {{term:association}}
selbst — und genau hier liegt die häufigste Fehlkonfiguration überhaupt:
ein AE Title, der auf einer Seite anders geschrieben ist als auf der
anderen.

Zwei Ablehnungsgründe, die DICOM klar benennt:

- **`Called AE Title Not Recognized`** — die Gegenstelle wurde unter
  einem Namen angesprochen, den sie nicht als ihren eigenen erkennt. Das
  Ziel ist falsch eingetragen.
- **`Calling AE Title Not Recognized`** — die Gegenstelle kennt zwar
  ihren eigenen Namen, aber nicht den des Anrufers. Der Absender ist bei
  ihr nicht registriert.

Beide Meldungen klingen ähnlich, zeigen aber auf entgegengesetzte Enden
der Verbindung: *Called* betrifft das Ziel, *Calling* den Absender. Wer
das verwechselt, sucht am falschen Gerät.

<!-- kein-beispiel -->
```
Diese beiden Meldungen lassen sich in der Spielwiese nicht live erzeugen:
Orthanc läuft hier bewusst mit `DicomAlwaysAllowEcho` und den
verwandten Optionen (siehe containers/orthanc/orthanc.json) und nimmt
deshalb jeden Called/Calling AE Title an -- das ist eine bewusste
P7-Entscheidung, damit Lernende nicht an einer Modalitätenliste scheitern,
bevor sie überhaupt etwas ausprobiert haben (ADR 0008). Getestet: selbst
`echoscu -aet BELIEBIGER-NAME -aec FALSCHER-NAME 127.0.0.1 4242` wird
angenommen.

Das genau zu erleben — und zu beheben — ist die Aufgabe der Nodes
"Silent CT" (Called-Seite) und "Wrong Door" (Calling-Seite): siehe Lab
unten.
```

## Im Alltag

Eine kleine Abgleichtabelle hilft mehr als jede Faustregel — beide Seiten
einer Verbindung gehören zeichengenau verglichen, nicht aus dem
Gedächtnis:

| Feld | Steht bei | Muss übereinstimmen mit |
|---|---|---|
| Eigener AE Title der Modalität | Modalität | "Calling AE Title" in der Zulassungsliste des Archivs |
| Ziel-AE-Title, den die Modalität anspricht | Modalität | Eigener AE Title des Archivs |

Zwei Zeilen, zwei Richtungen — und ein einzelnes Zeichen (Bindestrich
statt Unterstrich, wie in den Nodes unten) reicht, damit eine davon nicht
mehr stimmt.

## Stolperfallen

- **"Am Gerät sieht doch alles richtig aus."** Die Konsole zeigt eine
  vollständige, plausible eigene Konfiguration — der Fehler wird erst im
  Vergleich mit der Gegenseite sichtbar, nie durch Anstarren der eigenen
  Einstellungen allein.
- **AE Titles sind keine Hostnamen.** Groß-/Kleinschreibung,
  Bindestrich/Unterstrich — DICOM vergleicht zeichengenau, ohne Toleranz.
- **Ping und ein früherer Erfolg beweisen nichts über den aktuellen
  Zustand.** Jede der drei Ebenen (TCP, Association, danach die
  eigentliche Übertragung) muss einzeln geprüft werden.

## Selbstcheck

1. Ein Sendeauftrag scheitert mit `Connection refused`. Ist das ein
   Problem mit dem AE Title? Warum oder warum nicht?
2. Die Fehlermeldung nennt `Calling AE Title Not Recognized`. Musst du
   das Archiv oder die sendende Modalität korrigieren?
3. Warum lässt sich diese Ablehnung nicht in der Spielwiese, aber in den
   Nodes "Silent CT" und "Wrong Door" nachvollziehen?

*Wissenskarten (spaced repetition) folgen, sobald `meta.yml` ein
strukturiertes `quiz`-Feld mit Antwortschlüssel bekommt — siehe
`docs/content-todo.md`, Abschnitt P3.*

Werkzeuglage geprüft am: 2026-09-13
