---
title: Teiltransfer
scenario_title: Drei Objekte gesendet, aber die Study im Archiv wirkt unvollständig
---

## Briefing

Eine Serie aus drei Objekten liegt lokal bereit: zwei CT-Schichten und
ein Screenshot, den die Befund-Software beim Speichern automatisch mit
in denselben Ordner gelegt hat. Alle drei sollen ins Archiv — sende sie
und prüfe, was tatsächlich ankommt.

Deine Umgebung:

| System | Adresse | Was du darfst |
|---|---|---|
| Deine Workstation | 10.65.0.50 | Shell mit storescu, dcmdump — plus Befehlsvorlagen; alle drei Dateien liegen bereits lokal |
| Archiv | 10.65.0.10 | C-STORE annehmen; Konfiguration gesperrt (Herstellerzugang) |

Deine Aufgabe: Finde heraus, welches Objekt abgelehnt wird, und gib
seine SOP Class UID als Flag ein.

Vorkenntnisse: Lektion 1.4, 4.2. Rechne mit 15 Minuten.

## Hints

### h1

Sende alle drei Dateien einzeln, nicht als ein Vorgang — schau dir
jede Antwort für sich an. Eine Ablehnung auf C-STORE-Ebene betrifft
nur das eine Objekt, nicht die ganze Association.

### h2

`dcmdump <datei>` zeigt dir die SOP Class UID jedes Objekts, ohne es
erst zu senden. `sop-class-uebersicht.txt` auf deiner Workstation
listet, welche SOP Classes das Archiv überhaupt registriert hat.

### h3

`screenshot.dcm` trägt die SOP Class Secondary Capture Image Storage
(`1.2.840.10008.5.1.4.1.1.7`) — die steht nicht in der
Konformitätserklärung des Archivs. Genau diese UID ist das Flag.

## Write-up

### Der Weg

1. Erst nachsehen, was die drei Dateien überhaupt sind — ohne zu senden

```
$ dcmdump schicht-01.dcm
(0008,0016) UI [1.2.840.10008.5.1.4.1.1.2]  # SOPClassUID
$ dcmdump screenshot.dcm
(0008,0016) UI [1.2.840.10008.5.1.4.1.1.7]  # SOPClassUID
```
**Was du daran abliest:** Zwei unterschiedliche SOP Class UIDs im
selben Ordner — `schicht-01.dcm` ist ein CT-Bild, `screenshot.dcm`
etwas anderes. Noch bevor überhaupt gesendet wurde, ist erkennbar,
dass hier kein einheitlicher Objekttyp vorliegt.

2. In `sop-class-uebersicht.txt` nachsehen, was das Archiv registriert hat

Registriert sind nur Verification und CT Image Storage — Secondary
Capture Image Storage steht dort ausdrücklich als nicht registriert.

3. Alle drei Objekte einzeln senden

```
$ storescu -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.65.0.10 104 schicht-01.dcm
$ echo $?
0
$ storescu -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.65.0.10 104 schicht-02.dcm
$ echo $?
0
$ storescu -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.65.0.10 104 screenshot.dcm
F: No Acceptable Presentation Contexts
F:   Presentation Context Result: 3 (abstract-syntax-not-supported)
```
**Was du daran abliest:** Die beiden CT-Schichten kommen still an —
kein Fehler, keine besondere Meldung. Erst der Screenshot wird
abgelehnt, und zwar mit demselben Presentation-Context-Ablehnungsgrund
wie bei einer nicht unterstützten SOP Class auf Verbindungsebene
(PS3.8 Table 9-18, Result 3) — hier trifft er nur dieses eine Objekt,
nicht die ganze Association.

4. Flag: die SOP Class UID des abgelehnten Objekts —
   `1.2.840.10008.5.1.4.1.1.7` (Secondary Capture Image Storage).

### Was du mitnimmst

Ein "Teiltransfer" ist kein Netzwerkfehler und kein Zufall: Jedes
Objekt in einer Association wird einzeln gegen die vom Archiv
unterstützten SOP Classes geprüft, und ein still angenommenes Objekt
sagt nichts über die anderen aus. Wer nur zählt, ob überhaupt etwas im
Archiv gelandet ist, übersieht genau diesen Fall — die Study wirkt
vorhanden, ist aber unvollständig. Screenshots, Dosisreports und andere
"Nebenobjekte", die eine Modalität oder Befund-Software zusätzlich zu
den eigentlichen Bildern erzeugt, tragen eigene SOP Classes, die ein
Archiv nicht automatisch mitregistriert, nur weil es Bilder derselben
Studie schon kennt.

### Verwandte Inhalte

Lektion 1.4 — UIDs: Study, Series, SOP Instance, SOP Class
Lektion 4.2 — Verbindung steht, aber nichts kommt an
Lektion 4.3 — „Nur manche Bilder kommen an"
