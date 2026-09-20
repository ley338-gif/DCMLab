---
title: Gefiltert
scenario_title: Die Bilder kommen an, der Dosisbericht nicht
---

## Briefing

Ticket aus dem PACS-Betrieb: Eine CT-Untersuchung wird laut
Modalitäts-Exportprotokoll vollständig gesendet — Bilder und ein
automatisch miterzeugter Dosisbericht (RDSR). Im nachgelagerten
Dose-Management-System kommen die Bilder regelmäßig an, der
Dosisbericht nie. Reproduziere die Übertragung und finde heraus, an
welcher Stelle der Weiterleitung der Dosisbericht verloren geht.

Deine Umgebung:

| System | Adresse | Was du darfst |
|---|---|---|
| Deine Workstation | 10.83.0.50 | Shell mit `storescu`, `pacs`, `dcmdump` — plus Befehlsvorlagen; alle vier Dateien liegen bereits lokal |
| PACS | 10.83.0.10 | C-STORE annehmen, leitet intern nach einer Regel an ein nachgelagertes System weiter |
| Dose-System | 10.83.0.30 | Nicht direkt erreichbar — nur über die PACS-Weiterleitung |

Deine Aufgabe: Sende die vier Objekte, untersuche danach den
tatsächlichen Betriebszustand des PACS, und finde heraus, warum genau
der Dosisbericht nie beim Dose-System ankommt. Gib den technischen Wert
ein, der die Ursache eindeutig belegt.

Vorkenntnisse: Lektion 3.8. Rechne mit 20 Minuten.

## Hints

### h1

Prüfe zuerst, ob der Dosisbericht überhaupt im PACS ankommt, oder schon
auf dem ersten Hop von der Modalität verloren geht.

### h2

Wenn ein Objekt im PACS vorhanden ist, sein Ziel aber nie erreicht:
Prüfe, ob für dieses Objekt überhaupt ein Weiterleitungsauftrag
entstanden ist — das ist etwas anderes als ein gescheiterter Auftrag.

### h3

Vergleiche die Metadaten des Dosisberichts mit der Bedingung der
zuständigen Weiterleitungsregel.

## Write-up

### Was beobachtet wurde

```
$ storescu -aet CT-KONSOLE -aec PACS-ARCHIV 10.83.0.10 104 schicht-1.dcm
$ storescu -aet CT-KONSOLE -aec PACS-ARCHIV 10.83.0.10 104 schicht-2.dcm
$ storescu -aet CT-KONSOLE -aec PACS-ARCHIV 10.83.0.10 104 schicht-3.dcm
$ storescu -aet CT-KONSOLE -aec PACS-ARCHIV 10.83.0.10 104 dose-report.dcm
$ pacs objects
OBJECT   SOP CLASS                      MODALITY  PRESENCE
obj-001  1.2.840.10008.5.1.4.1.1.2      CT        pacs, dose-scp
obj-002  1.2.840.10008.5.1.4.1.1.2      CT        pacs, dose-scp
obj-003  1.2.840.10008.5.1.4.1.1.2      CT        pacs, dose-scp
obj-004  1.2.840.10008.5.1.4.1.1.88.67  SR        pacs
```

**Was du daran abliest:** Alle vier `storescu`-Aufrufe laufen
fehlerfrei durch — die Übertragung Modalität → PACS ist vollständig
unauffällig. `pacs objects` zeigt aber sofort einen Unterschied in der
Presence-Spalte: die drei CT-Bilder (`obj-001`–`obj-003`) liegen an
`pacs` **und** `dose-scp`, der Dosisbericht (`obj-004`, erkennbar an
SOP Class `...88.67` und Modality `SR`) nur an `pacs`. Der Dosisbericht
ist damit nachweislich im PACS gespeichert — er geht nicht schon auf
dem ersten Hop verloren.

```
$ pacs jobs
JOB    ROUTE         OBJECT   SOURCE  DESTINATION  DEPTH  STATUS  REASON
j-001  PACS-TO-DOSE  obj-001  pacs    dose-scp     1      sent    -
j-002  PACS-TO-DOSE  obj-002  pacs    dose-scp     1      sent    -
j-003  PACS-TO-DOSE  obj-003  pacs    dose-scp     1      sent    -
```

**Was du daran abliest:** Es gibt drei Weiterleitungsaufträge, alle
`sent` — für die drei CT-Bilder. Für `obj-004` (den Dosisbericht) taucht
in dieser Liste **überhaupt kein** Auftrag auf — kein `failed`, gar
keine Zeile. Das ist ein wichtiger Unterschied: Ein fehlgeschlagener
Auftrag würde bedeuten, dass ein Transfer versucht und abgelehnt wurde.
Hier wurde für dieses Objekt nie ein Auftrag angelegt.

```
$ pacs events
...
evt-016  store.completed  obj-004  -             -      host=pacs
evt-017  route.evaluated  obj-004  PACS-TO-DOSE  -      matched=false modality expected CT actual SR
```

**Was du daran abliest:** Das Event-Log bestätigt es explizit: Für
`obj-004` wird die Route `PACS-TO-DOSE` ausgewertet (`route.evaluated`)
und liefert `matched=false` — mit der Begründung `modality expected CT
actual SR`. Auf `route.evaluated` folgt kein `job.created` mehr für
dieses Objekt. Die Route wurde also geprüft und hat bewusst nicht
gegriffen, bevor überhaupt ein Auftrag entstehen konnte.

```
$ pacs route show PACS-TO-DOSE
Route:       PACS-TO-DOSE
Source:      pacs
Destination: dose-scp / dose-store
Enabled:     yes

Match:
  modality equals CT

$ pacs route test PACS-TO-DOSE obj-004
matched: false
field: modality
operator: equals
expected: CT
actual: SR
```

**Was du daran abliest:** Die einzige Weiterleitungsregel zum
Dose-System ist aktiv (`Enabled: yes`) und prüft ausschließlich
`modality equals CT`. Der gezielte Test gegen `obj-004` bestätigt
strukturiert dieselbe Diagnose wie das Event-Log: Das Feld `modality`
erwartet `CT`, der Dosisbericht trägt aber `SR`.

### Welche Hypothesen möglich waren

1. **Die Modalität hat den Dosisbericht nie erzeugt oder übertragen.**
   Widerlegt durch `pacs objects`: `obj-004` existiert im PACS, mit der
   korrekten SOP Class für einen Dosisbericht. Der erste Hop
   (Modalität → PACS) ist vollständig geglückt.
2. **Der zweite Transfer (PACS → Dose-System) ist fehlgeschlagen.**
   Naheliegend, weil der Dosisbericht das Dose-System nicht erreicht.
   Widerlegt durch `pacs jobs`: Für `obj-004` existiert **kein**
   Eintrag — weder `sent` noch `failed`. Ein gescheiterter Transfer
   würde einen tatsächlich unternommenen, aber abgelehnten Versuch
   voraussetzen (wie bei den drei CT-Bildern, die als `sent` erscheinen)
   — hier wurde nie einer gestartet.
3. **Das Dose-System unterstützt oder akzeptiert den Dosisbericht
   nicht.** Ebenfalls naheliegend, aber nicht prüfbar überhaupt
   relevant: Da für `obj-004` nie ein Auftrag entstand, wurde dem
   Dose-System das Objekt nie angeboten — seine Annahmefähigkeit spielt
   für diesen Fall keine Rolle.
4. **Die PACS-Weiterleitungsregel selbst selektiert Objekte nach einem
   Kriterium, das der Dosisbericht nicht erfüllt.** Bestätigt durch
   `pacs events`/`pacs route test`: Die Regel `PACS-TO-DOSE` prüft
   `modality equals CT` — der Dosisbericht trägt `Modality = SR` und
   erfüllt diese Bedingung nicht.

### Wo die erste fehlerhafte Stelle liegt

Nicht die Modalität, nicht der zweite C-STORE, nicht die
Annahmefähigkeit des Dose-Systems — die PACS-interne
Weiterleitungsregel selbst schließt den Dosisbericht bereits bei der
Auswahlprüfung aus, bevor überhaupt ein Übertragungsauftrag entstehen
kann. `PACS-TO-DOSE` wählt Objekte anhand von `Modality == CT` aus. Der
Dosisbericht ist ein strukturierter Bericht (kein Bild) und trägt
deshalb `Modality = SR`, unabhängig davon, dass er von einem CT-Gerät
erzeugt wurde — dieselbe Regel, die für die drei CT-Bilder korrekt
greift, schließt den Dosisbericht folgerichtig aus. `0` Weiterleitungsaufträge
für ein vorhandenes Objekt ist dabei diagnostisch etwas anderes als ein
`failed`-Auftrag: Ersteres bedeutet „nie ausgewählt", Letzteres würde
einen unternommenen und abgelehnten Versuch bedeuten.

Ein erreichbares Dose-System (das dieser Fall gar nicht erst infrage
stellt) würde ebenfalls nur die grundsätzliche Netzwerk-Erreichbarkeit
zeigen — das erklärt nicht, warum für ein vorhandenes Objekt überhaupt
kein Weiterleitungsauftrag entsteht. Das ist eine andere Fehlerdomäne
als die Objektauswahl einer Weiterleitungsregel.

### Saubere betriebliche Maßnahme

Die Regel nicht einfach auf `modality in [CT, SR]` erweitern — `SR`
umfasst alle strukturierten Berichte, nicht nur Dosisberichte, und
würde ungeprüft auch andere SR-Objekttypen an das Dose-System
weiterleiten. Sauberer ist eine eigene, zusätzliche Auswahlbedingung
anhand der SOP Class des Dosisberichts selbst
(`1.2.840.10008.5.1.4.1.1.88.67`, X-Ray Radiation Dose SR Storage) —
entweder als separate Regel oder als eigener SOP-Class-Match neben der
bestehenden Modality-Bedingung, nicht als zusätzliche UND-Bedingung an
derselben Regel (das würde weiterhin ausschließlich CT-Bilder treffen).

### Was du mitnimmst

Wenn ein Objekt sein Ziel nicht erreicht, zuerst feststellen, an
welcher Grenze es tatsächlich verschwindet, statt sofort Netzwerk oder
Zielsystem zu verdächtigen: Ist es überhaupt gespeichert? Wurde es für
eine Weiterleitung ausgewählt? Wurde dafür ein Auftrag angelegt? Wurde
er verschickt? Hat das Ziel ihn angenommen? `0` Weiterleitungsaufträge
für ein vorhandenes Objekt ist dabei etwas grundlegend anderes als ein
gescheiterter Auftrag — die erste fehlerhafte Stelle liegt oft weiter
vorn, als das Symptom vermuten lässt.

### Verwandte Inhalte

Lektion 3.8 — RDSR und Structured Report
