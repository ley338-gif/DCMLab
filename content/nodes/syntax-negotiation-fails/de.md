---
title: Kein gemeinsames Format
scenario_title: Der neue CT-Scanner findet sein Archiv, sendet aber nichts
---

## Briefing

CT-3 ist frisch installiert. AE Title, Host und Port sind schon korrekt
eingetragen — trotzdem kommt beim Archiv nichts an, und der
Sendeauftrag bricht sofort ab.

Deine Umgebung:

| System | Adresse | Was du darfst |
|---|---|---|
| Deine Workstation | 10.70.0.50 | Shell mit findscu, dcmdump — plus Befehlsvorlagen |
| Archiv | 10.70.0.10 | C-FIND stellen; Konfiguration gesperrt (Herstellerzugang) |
| CT-3 | 10.70.0.40 | Netzwerkkonfiguration ansehen und ändern, Studie senden |

Auf CT-3 gibt es keine Shell — nur das Konfigurationsmenü und den
Sende-Knopf, und dort steht jetzt zusätzlich ein Feld „Transfer Syntax“.

Deine Aufgabe: Finde die Transfer Syntax, die das Archiv akzeptiert, trag
sie ein und sende erfolgreich. Flag ist genau diese Transfer-Syntax-UID.

Vorkenntnisse: Lektion 1.7, 1.8. Rechne mit 15 Minuten.

## Hints

### h1

AE Title, Host und Port sind alle richtig — der Fehler liegt woanders.
Öffne die Netzwerkkonfiguration von CT-3 und sieh dir das Feld
„Transfer Syntax“ an.

### h2

`transfer-syntax-registry.txt` auf deiner Workstation listet die
UIDs, um die es hier geht. CT-3 steht werksseitig auf einer
JPEG-2000-Variante — das ist Kompression, keine der beiden
unkomprimierten Syntaxen.

### h3

Trag `1.2.840.10008.1.2` (Implicit VR Little Endian) ein — das ist die
einzige Transfer Syntax, die dieses Archiv akzeptiert.

## Write-up

### Der Weg

1. Sendeauftrag mit der Werkseinstellung auslösen

```
$ [Sendeauftrag mit Transfer Syntax 1.2.840.10008.1.2.4.91 auslösen]
00:01:35  Sendeauftrag – Verbindungsaufbau 10.70.0.10:104 …
00:01:35  F: No Acceptable Presentation Contexts
00:01:35  F:   Presentation Context Result: 4 (transfer-syntaxes-not-supported)
```
**Was du daran abliest:** Die Fehlermeldung nennt explizit Presentation
Context Result 4 — laut PS3.8 Table 9-18 bedeutet das
"transfer-syntaxes-not-supported". AE Title, Host und Port waren also
nie das Problem: Die Association selbst wird nicht mit einem
Verbindungsfehler abgelehnt, sondern weil keine gemeinsame Transfer
Syntax für die Bildübertragung zustande kommt.

2. In `transfer-syntax-registry.txt` nachsehen, welche UIDs es überhaupt gibt

Die Werkseinstellung `1.2.840.10008.1.2.4.91` ist JPEG 2000 — eine
komprimierte Syntax. Das Archiv ist älter und kennt nur unkomprimierte
Daten.

3. Auf Implicit VR Little Endian umstellen, erneut senden

```
$ [Transfer Syntax auf 1.2.840.10008.1.2 setzen, Sendeauftrag erneut auslösen]
00:01:35  Sendeauftrag – Verbindungsaufbau 10.70.0.10:104 …
00:01:35  Association akzeptiert (Max PDU 16372)
00:01:35  Bild 1/1 gesendet – Status Success
00:01:35  Auftrag abgeschlossen – 1 von 1 Objekten übertragen
```
**Was du daran abliest:** Dieselben Host-, Port- und AE-Title-Werte wie
beim ersten Versuch — der einzige Unterschied ist die Transfer Syntax,
und jetzt klappt es.

4. Zur Kontrolle: die Study ist jetzt wirklich da

```
$ findscu -S -k QueryRetrieveLevel=STUDY -k PatientID=4711 \
          -k StudyInstanceUID -k StudyDescription \
          -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.70.0.10 104
I: # Dicom-Data-Set
I: (0008,0052) CS [STUDY]  # xx, 1 QueryRetrieveLevel
I: (0010,0020) LO [4711]  # xx, 1 PatientID
I: (0020,000d) UI [1.2.276.0.7230010.3.1.4.276086387106]  # xx, 1 StudyInstanceUID
I: (0008,1030) LO [CT Thorax nativ]  # xx, 1 StudyDescription
I: Number of Matches: 1
```
**Was du daran abliest:** Ein Treffer bestätigt, dass die Study wirklich
angekommen ist — nicht nur, dass der Sendeauftrag keinen Fehler mehr
zeigte.

5. Flag: die akzeptierte Transfer Syntax — `1.2.840.10008.1.2`.

### Was du mitnimmst

Eine Association kann technisch zustande kommen und trotzdem nichts
übertragen — Verbindung (Host/Port/AE-Title) und Bildformat (Transfer
Syntax) sind zwei getrennte Aushandlungen. "Called AE Title Not
Recognized" und "transfer-syntaxes-not-supported" sind unterschiedliche,
beide im Standard definierte Ablehnungsgründe — die Fehlermeldung sagt
dir bereits, in welcher der beiden Aushandlungen das Problem liegt.
Neue Geräte kommen oft mit Kompressions-Voreinstellungen, die ein
älteres Archiv schlicht nicht kennt.

### Verwandte Inhalte

Lektion 1.7 — Transfer Syntax und Kompression
Lektion 1.8 — Association, Presentation Context, Negotiation
