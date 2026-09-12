---
title: Neue Node
scenario_title: Der neue MR-Scanner kennt sein eigenes Archiv nicht
---

## Briefing

Raum 2 hat einen neuen MR-Scanner. Die Firma hat ihn heute Morgen
angeschlossen und wieder abgezogen — "läuft, Rest ist eure Netzwerkkonfiguration".
Am Gerät steht noch die Werkseinstellung: irgendein Test-Archiv des
Herstellers, nicht euer Klinikarchiv.

Deine Umgebung:

| System | Adresse | Was du darfst |
|---|---|---|
| Deine Workstation | 10.40.0.50 | Shell mit echoscu, storescu, findscu, dcmdump — plus Befehlsvorlagen |
| Archiv | 10.40.0.10 | Statusseite ansehen; Konfiguration gesperrt (Herstellerzugang) |
| MR-Scanner Raum 2 | 10.40.0.40 | Netzwerkkonfiguration ansehen und ändern, Studie senden |

Auf dem MR-Scanner gibt es keine Shell — nur das Konfigurationsmenü und den
Sende-Knopf.

Deine Aufgabe: Trag die drei Werte ein, die den Scanner zum Klinikarchiv
finden lassen — AE Title, Host und Port, in dieser Reihenfolge, denn jede
Korrektur deckt erst den nächsten Fehler auf. Danach: Studie senden, Flag
auslesen.

Der Flag ist die Series Description der Serie, die im Archiv ankommt —
auslesen wie gewohnt in zwei Schritten (Study, dann Serie):

```
# 1. Study der Patientin finden
findscu -S -k QueryRetrieveLevel=STUDY \
        -k PatientID=<PatientID> -k StudyInstanceUID \
        -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.40.0.10 104

# 2. Serien dieser Study auflisten
findscu -S -k QueryRetrieveLevel=SERIES \
        -k StudyInstanceUID=<aus Schritt 1> \
        -k SeriesInstanceUID -k SeriesDescription \
        -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.40.0.10 104
```
**Was du daran abliest:** Der zweite Aufruf braucht die `StudyInstanceUID`
aus dem ersten — eine Serien-Abfrage ohne benannte Study weist ein striktes
Archiv zurück.

Vorkenntnisse: Lektion 1.5. Rechne mit 20 Minuten.

## Hints

### h1

Öffne die Netzwerkkonfiguration des MR-Scanners. Alle drei Felder —
Ziel-AE-Title, Ziel-Host, Ziel-Port — stehen noch auf Werkseinstellung.
Vergleiche sie mit `netzplan-klinik.txt` auf deiner Workstation.

### h2

Korrigiere die drei Felder nacheinander, nicht alle auf einmal, und löse
nach jeder Änderung erneut die Sendeaktion aus. Die Fehlermeldung ändert
sich mit jeder Korrektur — das sagt dir, welches Feld als Nächstes dran ist:
zuerst der Host, dann der Port, zuletzt der AE Title.

### h3

Die korrekten Werte stehen in `netzplan-klinik.txt`: AE Title
`KLINIK-ARCHIV`, Host `10.40.0.10`, Port `104`.

## Write-up

### Der Weg

1. Netzwerkkonfiguration des MR-Scanners öffnen

Alle drei Felder zeigen Werkseinstellungen des Herstellers — ein fremdes
Test-Archiv, keine Verbindung zu diesem Klinikum.

2. Sendeaktion mit den Werkseinstellungen auslösen

```
07:00:01  Sendeauftrag – Verbindungsaufbau 192.168.1.1:11112 …
07:00:01  TCP Initialization Error: Connection timed out
```
**Was du daran abliest:** Die Fehlermeldung fällt schon vor jeder
DICOM-Verhandlung — der Host ist im Netz des Klinikums schlicht nicht
erreichbar. Das ist Stufe 1 der Association-Prüfung.

3. Host korrigieren (`netzplan-klinik.txt`: `10.40.0.10`), erneut senden

```
07:02:14  Sendeauftrag – Verbindungsaufbau 10.40.0.10:11112 …
07:02:14  Connection refused
```
**Was du daran abliest:** Der Host antwortet jetzt, aber auf Port 11112
lauscht dort nichts — das Archiv nimmt DICOM-Verbindungen nur auf Port 104
an. Stufe 2.

4. Port korrigieren (`104`), erneut senden

```
07:03:40  Sendeauftrag – Verbindungsaufbau 10.40.0.10:104 …
07:03:40  F: Association Rejected:
07:03:40  F:   Result: Rejected Permanent, Source: Service User
07:03:40  F:   Reason: Called AE Title Not Recognized
```
**Was du daran abliest:** Verbindung und Port stimmen jetzt, aber der
Ziel-AE-Title ist immer noch der des Werksarchivs — das echte Archiv kennt
diesen Namen nicht. Stufe 3.

5. AE Title korrigieren (`KLINIK-ARCHIV`), erneut senden

```
07:04:55  Sendeauftrag – Verbindungsaufbau 10.40.0.10:104 …
07:04:55  Association akzeptiert (Max PDU 16372)
07:04:56  Bild 1/2 gesendet – Status Success
07:04:57  Bild 2/2 gesendet – Status Success
07:04:58  Auftrag abgeschlossen – 2 von 2 Objekten übertragen
```
**Was du daran abliest:** Alle drei Teile der Adresse — AE Title, Host,
Port — müssen gleichzeitig stimmen. Das Adress-Trio aus Lektion 1.6 ist
kein Merkspruch, sondern drei unabhängig prüfbare, unabhängig kaputte
Felder.

6. Flag holen — wie in jeder Node: Study finden, dann die Serie darin.

### Was du mitnimmst

Neue Geräte kommen mit Werkseinstellungen, nicht mit eurer Klinikkonfiguration.
Die drei Adressfelder scheitern nacheinander, nicht gleichzeitig — jede
Fehlermeldung nennt genau die Stufe, die als Nächstes falsch ist. Wer alle
drei auf einmal ändert, ohne dazwischen zu testen, lernt daraus nichts über
die Reihenfolge; wer einzeln korrigiert, sieht das Regelwerk aus Lektion 1.8
in Aktion.

### Verwandte Inhalte

Lektion 1.6 — AE Title, Host, Port: das Adress-Trio
Lektion 1.5 — SCU und SCP, Called und Calling AE Title
Node Silent CT (easy) — derselbe Fehler auf der Called-AE-Seite
Node Wrong Door (easy) — derselbe Fehler auf der Calling-AE-Seite
