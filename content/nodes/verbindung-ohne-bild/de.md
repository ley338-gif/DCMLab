---
title: Verbindung ohne Bild
scenario_title: Der neue CT-9 kann sich verbinden, aber keine Bilder abliefern
---

## Briefing

CT-9 ist frisch installiert und hat gerade ein Software-Update bekommen.
`echoscu` gegen das Archiv läuft grün — die Verbindung steht. Trotzdem
kommt beim Versuch, eine Study zu senden, sofort ein Fehler.

Deine Umgebung:

| System | Adresse | Was du darfst |
|---|---|---|
| Deine Workstation | 10.90.0.50 | Shell mit echoscu, findscu, dcmdump — plus Befehlsvorlagen |
| Archiv | 10.90.0.10 | C-ECHO, C-FIND stellen; Konfiguration gesperrt (Herstellerzugang) |
| CT-9 | 10.90.0.41 | Netzwerkkonfiguration ansehen und ändern, Studie senden |

Auf CT-9 gibt es keine Shell — nur das Konfigurationsmenü und den
Sende-Knopf, dort steht neben "Transfer Syntax" auch ein Feld
"SOP Class".

Deine Aufgabe: Finde heraus, welche SOP Class das Archiv für
CT-Bilder tatsächlich akzeptiert, trag sie ein und sende erfolgreich.
Flag ist genau diese SOP-Class-UID.

Vorkenntnisse: Lektion 1.7, 1.8. Rechne mit 15 Minuten.

## Hints

### h1

`echoscu` funktioniert — das schließt Host, Port und AE Title als
Ursache aus. Der Fehler muss also in der Aushandlung liegen, die erst
beim eigentlichen Sendeauftrag stattfindet. Sieh dir auf CT-9 nicht
nur das Feld "Transfer Syntax" an, sondern auch "SOP Class".

### h2

Die Fehlermeldung nennt Presentation Context Result 3, nicht 4 — das
ist ein anderer Ablehnungsgrund als bei einer falschen Transfer
Syntax. `sop-class-registrierung.txt` auf deiner Workstation listet,
welche SOP Classes das Archiv registriert hat.

### h3

Trag `1.2.840.10008.5.1.4.1.1.2` (CT Image Storage) ein — das
Software-Update hat CT-9 werksseitig auf Enhanced CT Image Storage
umgestellt, und genau das kennt dieses Archiv nicht.

## Write-up

### Der Weg

1. Erst prüfen: steht die Verbindung überhaupt?

```
$ echoscu -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.90.0.10 104
```
**Was du daran abliest:** Kein Fehler — die Association kommt zustande.
Host, Port und AE Title sind also nicht die Ursache für das, was als
Nächstes passiert.

2. Sendeauftrag mit der Werkseinstellung auslösen

```
$ [Sendeauftrag mit SOP Class 1.2.840.10008.5.1.4.1.1.2.1 auslösen]
00:03:12  Sendeauftrag – Verbindungsaufbau 10.90.0.10:104 …
00:03:12  F: No Acceptable Presentation Contexts
00:03:12  F:   Presentation Context Result: 3 (abstract-syntax-not-supported)
```
**Was du daran abliest:** Presentation Context Result 3 — laut PS3.8
Table 9-18 bedeutet das "abstract-syntax-not-supported", nicht
"transfer-syntaxes-not-supported" (Result 4, das andere in dieser
Tabelle definierte Beispiel). Der Abstract Syntax eines Presentation
Context ist der vorgeschlagene SOP Class UID — dieser Fehler bedeutet:
Das Archiv kennt den Objekttyp selbst nicht, unabhängig davon, in
welcher Kodierung er käme.

3. In `sop-class-registrierung.txt` nachsehen, was das Archiv registriert hat

Registriert sind Verification, CT Image Storage und die Study-Root-
Q/R-FIND-Klasse — Enhanced CT Image Storage steht dort ausdrücklich
als nicht registriert.

4. Auf CT Image Storage umstellen, erneut senden

```
$ [SOP Class auf 1.2.840.10008.5.1.4.1.1.2 setzen, Sendeauftrag erneut auslösen]
00:03:40  Sendeauftrag – Verbindungsaufbau 10.90.0.10:104 …
00:03:40  Association akzeptiert (Max PDU 16372)
00:03:40  Bild 1/1 gesendet – Status Success
00:03:40  Auftrag abgeschlossen – 1 von 1 Objekten übertragen
```
**Was du daran abliest:** Dieselben Host-, Port- und AE-Title-Werte,
dieselbe Transfer Syntax wie beim ersten Versuch — der einzige
Unterschied ist die SOP Class, und jetzt klappt es.

5. Zur Kontrolle: die Study ist jetzt wirklich da

```
$ findscu -S -k QueryRetrieveLevel=STUDY -k PatientID=8842 \
          -k StudyInstanceUID -k StudyDescription \
          -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.90.0.10 104
I: # Dicom-Data-Set
I: (0008,0052) CS [STUDY]  # xx, 1 QueryRetrieveLevel
I: (0010,0020) LO [8842]  # xx, 1 PatientID
I: (0020,000d) UI [1.2.276.0.7230010.3.1.4.<uid>]  # xx, 1 StudyInstanceUID
I: (0008,1030) LO [CT Abdomen nativ]  # xx, 1 StudyDescription
I: Number of Matches: 1
```
**Was du daran abliest:** Ein Treffer bestätigt, dass die Study
wirklich angekommen ist — nicht nur, dass der Sendeauftrag keinen
Fehler mehr zeigte.

6. Flag: die akzeptierte SOP Class — `1.2.840.10008.5.1.4.1.1.2`.

### Was du mitnimmst

Ein Presentation Context handelt zwei Dinge gleichzeitig aus: den
Abstract Syntax (welcher Objekttyp, also welcher SOP Class UID) und
die Transfer Syntax (welche Kodierung). Beide können unabhängig
voneinander abgelehnt werden, mit unterschiedlichen, beide im Standard
definierten Result-Codes (PS3.8 Table 9-18: 3 für den Objekttyp, 4 für
die Kodierung) — die Fehlermeldung sagt dir bereits, welche der beiden
Aushandlungen gescheitert ist. Ein erfolgreiches C-ECHO beweist nur,
dass die Verification-SOP-Class-Aushandlung funktioniert — sie sagt
nichts darüber aus, ob das eigentliche Bild ankommt. Software-Updates
an Geräten schalten manchmal neue, "bessere" Objekttypen frei, die ein
älteres Archiv schlicht nicht kennt.

### Verwandte Inhalte

Lektion 1.7 — Transfer Syntax und Kompression
Lektion 1.8 — Association, Presentation Context, Negotiation
Lektion 4.2 — Verbindung steht, aber nichts kommt an
