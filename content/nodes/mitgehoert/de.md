---
title: Mitgehört
scenario_title: Ein neues Ultraschallgerät, drei Fehlermeldungen, drei verschiedene Ebenen
---

## Briefing

US-2 ist neu angeschlossen. Der Sendeauftrag scheitert — aber die
Fehlermeldung ändert sich nach jeder Korrektur. Drei unabhängige Fehler,
jeder auf einer anderen Ebene der Verbindung.

Deine Umgebung:

| System | Adresse | Was du darfst |
|---|---|---|
| Archiv | 10.100.0.10 | Konfiguration gesperrt (Herstellerzugang) |
| US-2 | 10.100.0.41 | Netzwerkkonfiguration ansehen und ändern, Studie senden |

Auf US-2 gibt es keine Shell — nur das Konfigurationsmenü und den
Sende-Knopf. `protokoll-vorlage.txt` (per `cat` lesbar) ordnet
Fehlermeldungen den drei Ebenen einer DICOM-Verbindung zu: TCP
(Host/Port), Association (AE Title), Presentation Context (Transfer
Syntax).

Deine Aufgabe: Korrigiere Stufe für Stufe, lies nach jeder Korrektur die
neue Meldung, und ordne sie der richtigen Ebene zu. Flag ist die am Ende
akzeptierte Transfer Syntax.

## Hints

### h1

Ändere immer nur ein Feld, dann löse die Sendeaktion erneut aus — sonst
verlierst du den Zusammenhang zwischen Korrektur und neuer Meldung.

### h2

Die erste Meldung ist reine TCP-Ebene (noch keine DICOM-Verhandlung), die
zweite ist Verbindungsebene, die dritte betrifft nur die
Presentation-Context-Aushandlung. `protokoll-vorlage.txt` nennt die
genauen Formulierungen.

### h3

Zielhost `10.100.0.10`, Zielport `104`, Transfer Syntax
`1.2.840.10008.1.2.1` (Explicit VR Little Endian) — in dieser
Reihenfolge, jeweils erst nach der vorigen Korrektur sichtbar.

## Write-up

### Der Weg

1. Erster Versuch mit der Werkseinstellung

```
$ [Sendeauftrag auslösen]
00:58:43  Sendeauftrag – Verbindungsaufbau 10.100.0.99:11112 …
00:58:43  TCP Initialization Error: Connection timed out
```
**Was du daran abliest:** Reine TCP-Ebene — der Host ist im Netz nicht
erreichbar. Es gab noch keine DICOM-Verhandlung, weder Association noch
Presentation Context.

2. Host korrigieren (`10.100.0.10`), erneut senden

```
$ [Host korrigieren, Sendeauftrag erneut auslösen]
00:58:43  Sendeauftrag – Verbindungsaufbau 10.100.0.10:11112 …
00:58:43  Connection refused
```
**Was du daran abliest:** Der Host antwortet jetzt — aber auf Port 11112
lauscht dort kein Dienst. Immer noch reine TCP-Ebene, noch keine DICOM-
Verhandlung.

3. Port korrigieren (`104`), erneut senden

```
$ [Port korrigieren, Sendeauftrag erneut auslösen]
00:58:43  Sendeauftrag – Verbindungsaufbau 10.100.0.10:104 …
00:58:43  F: No Acceptable Presentation Contexts
00:58:43  F:   Presentation Context Result: 4 (transfer-syntaxes-not-supported)
```
**Was du daran abliest:** Ein neuer Fehlertyp, keine Wiederholung des
alten. Die Association selbst kommt jetzt zustande (kein
Verbindungsfehler mehr) — erst die Presentation-Context-Ebene lehnt ab,
weil keine gemeinsame Transfer Syntax vorliegt.

4. Transfer Syntax korrigieren, erneut senden

```
$ [Transfer Syntax auf 1.2.840.10008.1.2.1 setzen, Sendeauftrag erneut auslösen]
00:58:43  Sendeauftrag – Verbindungsaufbau 10.100.0.10:104 …
00:58:43  Association akzeptiert (Max PDU 16372)
00:58:43  Bild 1/1 gesendet – Status Success
00:58:43  Auftrag abgeschlossen – 1 von 1 Objekten übertragen
```
**Was du daran abliest:** Alle drei Ebenen jetzt korrekt — TCP-Verbindung,
Association, Presentation Context.

5. Flag: die am Ende akzeptierte Transfer Syntax —
   `1.2.840.10008.1.2.1`.

### Was du mitnimmst

Jede der drei Fehlermeldungen gehört zu einer anderen Ebene der
Verbindung, und keine Korrektur "vermischt" sich mit der nächsten — die
Meldung ändert sich jedes Mal vollständig, nicht nur graduell. Das ist
der Kern von Lektion 1.8: Ein Association-Log lesen heißt, jede Zeile der
richtigen Ebene zuzuordnen, statt nur "es hat wieder nicht geklappt" zu
registrieren. AE Title war hier bei jedem Versuch bereits korrekt — genau
deshalb tauchte diese Ebene im Log nie auf.

### Verwandte Inhalte

Lektion 1.8 — Association, Presentation Context, Negotiation
Node „Neue Node“ (medium) — dieselbe Drei-Stufen-Systematik, dort mit AE Title statt Transfer Syntax als dritter Fehler
Node „Kein gemeinsames Format“ (medium) — Transfer-Syntax-Fehler im Detail
