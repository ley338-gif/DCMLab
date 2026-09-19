---
title: Halbe Sache
scenario_title: Drei Bilder kommen an, ein viertes Objekt nicht
---

## Briefing

Ein CT hat seit einem Software-Update eine neue Angewohnheit: Es sendet
neben den eigentlichen Schichten automatisch einen zusätzlichen
Dosisbericht mit. Die Study liegt lokal bereit — vier Objekte, die alle
zusammengehören sollen.

Deine Umgebung:

| System | Adresse | Was du darfst |
|---|---|---|
| Deine Workstation | 10.77.0.50 | Shell mit echoscu, storescu, dcmdump — plus Befehlsvorlagen; alle vier Dateien liegen bereits lokal |
| Archiv | 10.77.0.10 | C-ECHO, C-STORE annehmen; Konfiguration gesperrt (Herstellerzugang) |

Deine Aufgabe: Finde heraus, ob die Verbindung grundsätzlich
funktioniert, welches Objekt am Archiv nicht ankommt — und warum genau
dieses. Gib den Dateinamen des abgelehnten Objekts als Flag ein.

Vorkenntnisse: Lektion 1.7. Rechne mit 15 Minuten.

## Hints

### h1

Ein teilweiser Erfolg schließt bestimmte Fehlerdomänen von vornherein
aus. Prüfe zuerst, ob die Verbindung selbst grundsätzlich steht, und
sende danach jedes Objekt einzeln statt alle auf einmal — eine Ablehnung
auf C-STORE-Ebene betrifft immer nur das eine Objekt.

### h2

`dcmdump <datei>` zeigt dir Transfer Syntax und SOP Class jedes Objekts,
ohne es erst zu senden. Vergleiche gezielt ein angenommenes mit dem
abgelehnten Objekt — sind sie wirklich in derselben Kodierung? Wirf
zusätzlich einen Blick in `sop-class-liste.txt`.

### h3

Alle vier Objekte liegen in derselben Transfer Syntax vor — das lässt
sich mit `dcmdump` an jedem einzelnen Objekt nachprüfen. Vergleiche
stattdessen die SOP Class UID des abgelehnten Objekts mit den in
`sop-class-liste.txt` registrierten SOP Classes.

## Write-up

### Was beobachtet wurde

```
$ echoscu -aet DCMLAB-WS -aec RAD-ARCHIV 10.77.0.10 104
$ storescu -aet DCMLAB-WS -aec RAD-ARCHIV 10.77.0.10 104 bild-1.dcm
$ storescu -aet DCMLAB-WS -aec RAD-ARCHIV 10.77.0.10 104 bild-2.dcm
$ storescu -aet DCMLAB-WS -aec RAD-ARCHIV 10.77.0.10 104 bild-3.dcm
$ storescu -aet DCMLAB-WS -aec RAD-ARCHIV 10.77.0.10 104 dosisbericht.dcm
F: No Acceptable Presentation Contexts
F:   Presentation Context Result: 3 (abstract-syntax-not-supported)
```

**Was du daran abliest:** `echoscu` und drei von vier `storescu`-Aufrufen
laufen fehlerfrei durch. Erst `dosisbericht.dcm` wird abgelehnt — und
zwar erst beim eigentlichen Sendeversuch, nicht schon beim
Verbindungsaufbau.

### Welche Hypothesen möglich waren

1. **Netzwerkverbindung während der Übertragung abgebrochen.** Widerlegt
   durch `echoscu` und drei erfolgreiche `storescu`-Läufe direkt davor
   und danach — die Verbindung ist während der gesamten Sitzung stabil.
2. **Die Transfer Syntax wird für diese SOP Class nicht akzeptiert.**
   Naheliegend, weil Lektion 1.7 genau davon handelt — aber
   `dcmdump bild-1.dcm` und `dcmdump dosisbericht.dcm` zeigen exakt
   dieselbe `TransferSyntaxUID`. Wäre die Kodierung das Problem, hätte
   sie alle vier Objekte gleichermaßen betroffen.
3. **Eine einzelne SOP Class wird vom Archiv nicht unterstützt.**
   `dcmdump dosisbericht.dcm` zeigt `SOPClassUID
   1.2.840.10008.5.1.4.1.1.88.67` (X-Ray Radiation Dose SR) —
   `sop-class-liste.txt` bestätigt, dass nur Verification und CT Image
   Storage registriert sind. Genau das erklärt die Ablehnung.

### Wo die erste fehlerhafte Stelle liegt

Nicht Netzwerk, nicht Association, nicht Kodierung — die Presentation-
Context-Aushandlung für dieses eine Objekt scheitert am Abstract Syntax
(PS3.8 Table 9-18, Result 3), weil das Archiv den Dosisbericht als
Objekttyp nie registriert hat. Die drei CT-Schichten sind davon völlig
unberührt, weil jedes Objekt in einer Association einzeln gegen die
unterstützten SOP Classes geprüft wird.

### Saubere betriebliche Maßnahme

Nicht Netzwerk oder Transfer Syntax anfassen — beides funktioniert
bereits. Stattdessen beim Archiv-Hersteller klären, ob X-Ray Radiation
Dose SR Storage registriert werden kann oder soll, oder das CT so
konfigurieren, dass es den Dosisbericht an ein dafür vorgesehenes Ziel
sendet statt an ein Archiv, das nur Bilder erwartet. Ein „stiller
Teilerfolg" wie dieser fällt sonst erst auf, wenn jemand später nach dem
Dosisbericht sucht und ihn nicht findet.

### Was du mitnimmst

Ein teilweise erfolgreicher C-STORE-Transfer beweist, dass Netzwerk und
Association grundsätzlich funktionieren — Fehler ab diesem Punkt sind
objekt- oder Presentation-Context-spezifisch, nie pauschal. Wer nur
zählt, ob überhaupt etwas angekommen ist, übersieht genau diesen Fall:
Die Study wirkt vorhanden, ein einzelnes Objekt fehlt trotzdem.

### Verwandte Inhalte

Lektion 1.7 — Transfer Syntax und Kompression
