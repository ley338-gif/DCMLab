---
title: Zweiter Hop
scenario_title: Im PACS, am Ziel abgelehnt
---

## Briefing

Nach einer Änderung am CT fehlen einzelne Rekonstruktionen im
nachgelagerten Postprocessing. Im PACS selbst sind die Daten vorhanden.
Andere CT-Objekte derselben Arbeitsstrecke erreichen das Ziel weiterhin.

Reproduziere den Transfer mit den beiden bereitgestellten Objekten und
finde heraus, an welcher Systemgrenze das Problem entsteht.

Deine Umgebung:

| System | Adresse | Was du darfst |
|---|---|---|
| Deine Workstation | 10.84.0.50 | Shell mit `storescu`, `pacs`, `dcmdump` — plus Befehlsvorlagen; beide Dateien liegen bereits lokal |
| PACS | 10.84.0.10 | C-STORE annehmen, leitet intern nach einer Regel an ein nachgelagertes System weiter |
| Postprocessing-System | 10.84.0.30 | Ziel der PACS-Weiterleitung — keine eigene Shell in diesem Lab |

Deine Aufgabe: Sende beide bereitgestellten Objekte an das PACS.
Untersuche anschließend den Betriebszustand und ermittle, warum nur eines
das Postprocessing-System erreicht. Gib als Lösung die SOP Class UID des
Objekttyps ein, der beim zweiten Hop abgelehnt wird.

Vorkenntnisse: Lektion 3.7. Rechne mit 20 Minuten.

## Hints

### h1

Prüfe zuerst getrennt, welche Objekte im PACS liegen und welche
tatsächlich am Ziel angekommen sind.

### h2

Wenn ein Objekt im PACS liegt, frage als Nächstes: Wurde es von der Route
ausgewählt und existiert dafür ein Weiterleitungsjob?

### h3

Untersuche den fehlgeschlagenen Job bzw. seine Events und vergleiche
anschließend die SOPClassUID des betroffenen Objekts. Achte darauf, SOP
Class und Transfer Syntax nicht miteinander zu verwechseln.

## Write-up

### Was beobachtet wurde

```
$ storescu -aet CT-KONSOLE -aec PACS-ARCHIV 10.84.0.10 104 ct-classic.dcm
$ storescu -aet CT-KONSOLE -aec PACS-ARCHIV 10.84.0.10 104 ct-enhanced.dcm
$ pacs objects
OBJECT   SOP CLASS                       MODALITY  PRESENCE
obj-001  1.2.840.10008.5.1.4.1.1.2       CT        pacs, postproc-scp
obj-002  1.2.840.10008.5.1.4.1.1.2.1     CT        pacs
```

**Was du daran abliest:** Beide `storescu`-Aufrufe laufen fehlerfrei
durch — das PACS akzeptiert beide Objekte vollständig, kein
Erste-Hop-Problem. `pacs objects` zeigt aber sofort einen Unterschied in
der Presence-Spalte: `obj-001` (klassisches CT, SOP Class `...1.1.2`)
liegt an `pacs` **und** `postproc-scp`, `obj-002` (SOP Class `...1.1.2.1`)
nur an `pacs`.

```
$ pacs jobs
JOB    ROUTE             OBJECT   SOURCE  DESTINATION   DEPTH  STATUS  REASON
j-001  PACS-TO-POSTPROC  obj-001  pacs    postproc-scp  1      sent    -
j-002  PACS-TO-POSTPROC  obj-002  pacs    postproc-scp  1      failed  abstract_syntax_not_supported
```

**Was du daran abliest:** Anders als man nach einem reinen
Presence-Unterschied vermuten könnte, existiert für `obj-002` sehr wohl
ein Weiterleitungsjob — er ist nur `failed`, nicht abwesend. Das ist ein
wichtiger Unterschied zu einem Objekt, das eine Route gar nicht erst
auswählt (dort entstünde überhaupt kein Auftrag).

```
$ pacs events --object obj-002
EVENT    TYPE             OBJECT   ROUTE             JOB    DETAILS
evt-006  store.completed  obj-002  -                 -      host=pacs
evt-007  route.evaluated  obj-002  PACS-TO-POSTPROC  -      matched=true
evt-008  job.created      obj-002  PACS-TO-POSTPROC  j-002  pacs -> postproc-scp
evt-009  job.failed       obj-002  PACS-TO-POSTPROC  j-002  reason=abstract_syntax_not_supported
```

**Was du daran abliest:** `route.evaluated` zeigt `matched=true` — die
Route `PACS-TO-POSTPROC` hat `obj-002` ausdrücklich ausgewählt, genau wie
`obj-001`. Die Route ist also nicht die Ursache. Erst danach, beim
tatsächlichen Zustellversuch, scheitert der Job mit
`reason=abstract_syntax_not_supported`. Kein `job.sent` für dieses
Objekt.

```
$ pacs route test PACS-TO-POSTPROC obj-002
matched: true
```

**Was du daran abliest:** Die Gegenprobe bestätigt es strukturiert: Die
Route matcht `obj-002` unabhängig vom automatischen Lauf. Das schließt
die Objektselektion endgültig als Ursache aus.

```
$ dcmdump ct-enhanced.dcm
...
(0002,0010) UI [1.2.840.10008.1.2.1]  # xx, 1 TransferSyntaxUID
(0008,0016) UI [1.2.840.10008.5.1.4.1.1.2.1]  # xx, 1 SOPClassUID
(0008,0060) CS [CT]  # xx, 1 Modality
...
```

**Was du daran abliest:** `obj-002` trägt die SOP Class UID
`1.2.840.10008.5.1.4.1.1.2.1` — Enhanced CT Image Storage, nicht
klassisches CT Image Storage (`...1.1.2`), obwohl beide `Modality = CT`
tragen. Die Transfer Syntax (`1.2.840.10008.1.2.1`, Explicit VR Little
Endian) ist bei beiden Objekten identisch.

### Welche Hypothesen möglich waren

1. **Das PACS hat das Objekt nie angenommen.** Widerlegt durch
   `pacs objects`: `obj-002` existiert im PACS mit der korrekten SOP
   Class. Der erste Hop (Workstation → PACS) ist vollständig geglückt.
2. **Die Route hat das Objekt nicht ausgewählt.** Naheliegend nach dem
   Presence-Unterschied. Widerlegt durch `pacs events`/`pacs route test`:
   `route.evaluated` zeigt `matched=true`, die Gegenprobe bestätigt
   dasselbe unabhängig vom automatischen Lauf.
3. **Die Transfer Syntax wurde beim zweiten Hop abgelehnt.** Naheliegend,
   weil Transfer-Syntax-Probleme real vorkommen. Widerlegt durch
   `dcmdump`: Beide Objekte verwenden identisch
   `1.2.840.10008.1.2.1`, und der Fehlgrund lautet ausdrücklich
   `abstract_syntax_not_supported`, nicht `transfer_syntaxes_not_supported`.
4. **Das Postprocessing-System unterstützt die konkrete SOP Class des
   Objekts nicht.** Bestätigt: Ein Weiterleitungsjob wurde erzeugt (die
   Route hat gematcht), scheitert aber beim Zustellversuch mit
   `reason=abstract_syntax_not_supported` — der Zielservice akzeptiert
   Enhanced CT Image Storage nicht, obwohl er CT Image Storage und
   dieselbe Transfer Syntax akzeptiert.

### Wo die erste fehlerhafte Stelle liegt

Nicht das PACS, nicht die Route, nicht die Transfer Syntax — der
Weiterleitungsversuch scheitert bereits bei der Aushandlung des
Presentation Context, weil das nachgelagerte Postprocessing-System die
Enhanced-CT-SOP-Class als Abstract Syntax nicht akzeptiert. Die Route
`PACS-TO-POSTPROC` selektiert
nach `Modality == CT` und wählt damit folgerichtig beide Objekttypen aus
— klassisches CT und Enhanced CT tragen beide `Modality = CT`. Das
Postprocessing-System selbst unterstützt aber nur klassisches CT Image
Storage (`1.2.840.10008.5.1.4.1.1.2`), nicht Enhanced CT Image Storage
(`1.2.840.10008.5.1.4.1.1.2.1`).

Route-Match und erfolgreiche Zustellung sind damit zwei unterschiedliche
Betriebsgrenzen: Eine Route kann ein Objekt korrekt auswählen, und der
eigentliche Transfer kann trotzdem an der Fähigkeit des Zielsystems
scheitern, den konkreten Objekttyp zu verarbeiten.

### Saubere betriebliche Maßnahme

Kurzfristig kann die Route bewusst auf nachweislich unterstützte SOP
Classes begrenzt werden, um dauerhaft fehlschlagende Jobs zu vermeiden.
Die eigentliche Integrationslösung ist jedoch die abgestimmte
Enhanced-CT-Unterstützung des Zielsystems; bei einer späteren Erweiterung
muss die Route entsprechend angepasst werden.

### Was du mitnimmst

Wenn ein Objekt sein Ziel nicht erreicht, zuerst feststellen, an welcher
Grenze es tatsächlich verschwindet: Ist es gespeichert? Wurde es von einer
Route ausgewählt? Wurde dafür ein Auftrag angelegt? Wurde er verschickt?
Hat das Ziel ihn angenommen? Ein `failed`-Auftrag ist dabei etwas anderes
als gar kein Auftrag — Ersteres bedeutet „ausgewählt, aber abgelehnt",
Letzteres „nie ausgewählt". Und: Ob ein System eine SOP Class unterstützt,
muss am aktuellen Conformance Statement und praktisch an der konkreten
Integration geprüft werden — Storage-Unterstützung an einer Stelle sagt
nichts über Verarbeitungs-Unterstützung an einer anderen.

### Im Vergleich zu „Gefiltert"

|  | Gefiltert | Zweiter Hop |
|---|---|---|
| Objekt gespeichert? | ja | ja |
| Route hat gematcht? | nein | ja |
| Job entstanden? | nein | ja |
| Zustellung erfolgreich? | – | nein |

Bei „Gefiltert" schließt die Route das Objekt bereits bei der Auswahl aus
— es entsteht nie ein Auftrag. Hier wählt dieselbe Art Route das Objekt
korrekt aus, und der Auftrag entsteht auch — er scheitert erst beim
tatsächlichen Zustellversuch, weil das Ziel den konkreten Objekttyp nicht
verarbeiten kann. Zwei unterschiedliche Betriebsgrenzen derselben
automatischen PACS-Weiterleitung.

### Verwandte Inhalte

Lektion 3.7 — Storage-Support vs. Verarbeitungs-Support, SOPClassUID als
präzisere Objektbeschreibung als Modality
