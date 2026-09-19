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
aus. Prüfe zuerst, ob die Verbindung selbst grundsätzlich steht. Sende
in diesem Lab jedes Objekt einzeln — so kannst du erkennen, ob nur ein
bestimmter Objekttyp an seiner benötigten Presentation Context
scheitert.

### h2

`dcmdump <datei>` zeigt dir Transfer Syntax und SOP Class jedes Objekts,
ohne es erst zu senden. Lies bei der Ablehnung genau den Presentation-
Context-Result-Code — PS3.8 Table 9-18 unterscheidet zwischen einem
Objekttyp- und einem Kodierungsproblem. Wirf zusätzlich einen Blick in
`sop-class-liste.txt`.

### h3

Der Result-Code der Ablehnung ist 3 (abstract-syntax-not-supported),
nicht 4 (transfer-syntaxes-not-supported) — das zeigt auf den
Objekttyp, nicht auf die Kodierung. Vergleiche die SOP Class UID des
abgelehnten Objekts mit den in `sop-class-liste.txt` registrierten SOP
Classes.

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
laufen fehlerfrei durch. Erst für `dosisbericht.dcm` scheitert bereits
die Presentation-Context-Verhandlung für dieses eine Objekt — nicht
eine laufende Datenübertragung. Ein C-STORE für dieses Objekt findet
dadurch gar nicht erst statt.

### Welche Hypothesen möglich waren

1. **Netzwerkverbindung während der Übertragung abgebrochen.** Widerlegt
   durch `echoscu` und drei erfolgreiche `storescu`-Läufe direkt davor
   und danach — die Verbindung ist während der gesamten Sitzung stabil,
   und die Ablehnung tritt bereits bei der Association-Verhandlung für
   dieses eine Objekt auf, nicht mitten in einer laufenden Übertragung.
2. **Die Transfer Syntax wird für diese SOP Class nicht akzeptiert.**
   Naheliegend, weil Lektion 1.7 genau davon handelt. Aber der
   zurückgegebene Result-Code ist 3 (abstract-syntax-not-supported),
   nicht 4 (transfer-syntaxes-not-supported, PS3.8 Table 9-18) — der
   Standard unterscheidet beide Ablehnungsgründe ausdrücklich, und die
   Meldung nennt eindeutig den Objekttyp, nicht die Kodierung. Dass
   `dcmdump bild-1.dcm` und `dcmdump dosisbericht.dcm` sogar
   unterschiedliche `TransferSyntaxUID`-Werte zeigen (JPEG Lossless für
   die CT-Schichten, Explicit VR Little Endian für den Dosisbericht —
   realistisch, weil Dose-SR-Objekte keine Bildkompressionssyntax
   nutzen), beweist für sich genommen nichts: Ein Presentation Context
   koppelt Abstract Syntax und Transfer Syntax gemeinsam, daher entscheidet
   der Result-Code, nicht ein bloßer TS-Vergleich zwischen zwei
   verschiedenen SOP Classes.
3. **Eine einzelne SOP Class wird vom Archiv nicht unterstützt.**
   `dcmdump dosisbericht.dcm` zeigt `SOPClassUID
   1.2.840.10008.5.1.4.1.1.88.67` (X-Ray Radiation Dose SR) —
   `sop-class-liste.txt` bestätigt, dass nur Verification und CT Image
   Storage registriert sind. Zusammen mit Result 3 erklärt genau das
   die Ablehnung.

### Wo die erste fehlerhafte Stelle liegt

Nicht Netzwerk, nicht Transfer Syntax — die Presentation-Context-
Verhandlung für den Dosisbericht scheitert bereits bei der Association
Negotiation, weil das Archiv seinen Abstract Syntax (die SOP Class)
nicht kennt (PS3.8 Table 9-18, Result 3). Ein C-STORE für dieses Objekt
kann dadurch gar nicht erst stattfinden — es handelt sich nicht um eine
abgelehnte C-STORE-Antwort nach erfolgter Übertragung, sondern um ein
Objekt, das mangels akzeptiertem Presentation Context nie übertragen
wird. In diesem Lab wird jedes Objekt mit einem separaten
`storescu`-Aufruf übertragen. Dadurch wird für jeden Versuch eine neue
Association ausgehandelt; die Ablehnung des Dosisberichts verhindert
deshalb nicht die drei vorherigen CT-Transfers. Das ist keine
allgemeine DICOM-Regel — innerhalb einer einzelnen Association können
mehrere Presentation Contexts ausgehandelt werden, und mehrere Objekte
können dieselbe Association gemeinsam nutzen.

### Saubere betriebliche Maßnahme

Nicht Netzwerk oder Transfer Syntax anfassen — beides funktioniert
bereits. Stattdessen beim Archiv-Hersteller klären, ob X-Ray Radiation
Dose SR Storage registriert werden kann oder soll, oder das CT so
konfigurieren, dass es den Dosisbericht an ein dafür vorgesehenes Ziel
sendet statt an ein Archiv, das nur Bilder erwartet. Ein „stiller
Teilerfolg" wie dieser fällt sonst erst auf, wenn jemand später nach dem
Dosisbericht sucht und ihn nicht findet.

### Was du mitnimmst

Ein teilweise erfolgreicher Transfer beweist, dass Netzwerk und
Association-Mechanismus grundsätzlich funktionieren — eine Ablehnung ab
diesem Punkt ist objekt- bzw. Presentation-Context-spezifisch, nie
pauschal. Ein einzelnes Objekt kann dabei schon an der Verhandlung
scheitern, bevor überhaupt ein C-STORE für dieses Objekt versucht wird.
Wer nur zählt, ob überhaupt etwas angekommen ist, übersieht genau
diesen Fall: Die Study wirkt vorhanden, ein einzelnes Objekt fehlt
trotzdem.

### Verwandte Inhalte

Lektion 1.7 — Transfer Syntax und Kompression
