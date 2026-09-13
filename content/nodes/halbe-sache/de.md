---
title: Halbe Sache
scenario_title: Zwei Schichten derselben Serie — eine davon wurde nur halb korrekt komprimiert
---

## Briefing

Ein Kollege hat vor dem Feierabend noch schnell zwei Schichten
komprimiert, um Platz zu sparen. Beim Nachsehen fällt auf: Eine der
beiden Dateien wirkt, als wäre sie unangetastet geblieben — obwohl sie
laut Ordnername zur selben nachbearbeiteten Serie gehört.

Deine Umgebung:

| System | Adresse | Was du darfst |
|---|---|---|
| Deine Workstation | 10.55.0.50 | Shell mit dcmdump — plus Befehlsvorlagen; beide Dateien liegen bereits lokal |

Deine Aufgabe: Finde heraus, welche der beiden Dateien nicht korrekt
als verlustbehaftet komprimiert gekennzeichnet ist, und gib ihren
Dateinamen als Flag ein.

Vorkenntnisse: Lektion 1.7. Rechne mit 10 Minuten.

## Hints

### h1

`ls` allein hilft hier kaum weiter — sieh dir stattdessen mit `dcmdump`
an, in welcher Transfer Syntax jede der beiden Dateien tatsächlich
vorliegt.

### h2

Beide Dateien liegen in derselben Transfer Syntax vor — einer
verlustbehafteten. Genau dann verlangt der Standard ein zusätzliches
Kennzeichnungsfeld: `LossyImageCompression`. Prüfe, ob es bei beiden
Dateien vorhanden ist.

### h3

`schicht-02.dcm` trägt kein `LossyImageCompression`-Feld, obwohl sie
in derselben verlustbehafteten Transfer Syntax vorliegt wie
`schicht-01.dcm` — ihr Dateiname ist das Flag.

## Write-up

### Der Weg

1. Erst die Dateigrößen vergleichen

```
$ ls
       52224  schicht-01.dcm
       51968  schicht-02.dcm
```
**Was du daran abliest:** Fast identische Größe — allein daraus lässt
sich nicht ablesen, ob beide gleich behandelt wurden. Eine
Größenprüfung reicht hier nicht aus.

2. Die Transfer Syntax jeder Datei nachsehen

```
$ dcmdump schicht-01.dcm
I: (0002,0010) UI [1.2.840.10008.1.2.4.50]  # xx, 1 TransferSyntaxUID
I: (0028,2110) CS [01]  # xx, 1 LossyImageCompression
```
**Was du daran abliest:** `1.2.840.10008.1.2.4.50` ist JPEG Baseline —
eine verlustbehaftete Transfer Syntax. Das Feld `LossyImageCompression`
steht korrekt auf `01`: genau das schreibt der Standard vor, sobald
verlustbehaftet komprimiert wurde.

3. Dieselbe Prüfung bei der zweiten Datei

```
$ dcmdump schicht-02.dcm
I: (0002,0010) UI [1.2.840.10008.1.2.4.50]  # xx, 1 TransferSyntaxUID
```
**Was du daran abliest:** Dieselbe Transfer Syntax wie bei
`schicht-01.dcm` — also derselbe Verlust. Aber `LossyImageCompression`
fehlt hier komplett. Die Datei sieht dadurch aus wie ein unangetastetes
Original, obwohl sie genauso komprimiert wurde.

4. Flag: die nicht korrekt gekennzeichnete Datei — `schicht-02.dcm`.

### Was du mitnimmst

Die Transfer Syntax allein sagt dir, *wie* ein Objekt kodiert ist —
nicht, ob die Pflichtangaben dazu vollständig sind. Bei verlustbehafteter
Kompression schreibt der Standard das Kennzeichnungsfeld
`LossyImageCompression` verbindlich vor; fehlt es, ist das kein
harmloses Versehen, sondern macht ein verändertes Bild von einem
unveränderten nicht mehr unterscheidbar — für jedes System und jeden
Menschen, der sich später nur auf die Metadaten verlässt. "Halbe Sache"
heißt hier: Die Kompression wurde gemacht, die dazugehörige
Dokumentation nicht.

### Verwandte Inhalte

Lektion 1.7 — Transfer Syntax und Kompression
