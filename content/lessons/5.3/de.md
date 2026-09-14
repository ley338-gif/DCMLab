---
title: "Migration und Archivwechsel: Fallstricke"
teaser: "Wenn das alte PACS abgeschaltet wird, entscheidet sich, ob zehn Jahre Bilddaten wirklich mitkommen — oder nur ihre Metadaten. Real durchgespielt: eine korrekte und eine kaputte Migration, an zwei echten Orthanc-Instanzen."
objectives:
  - "Kannst benennen, warum eine netzwerkbasierte Migration (C-STORE) UIDs und Pixeldaten unverändert lässt, aber andere Spuren hinterlässt"
  - "Kannst erklären, warum eine UID-Neuvergabe beim Ziel-Archiv real zu doppelten, unverbundenen Studien führt — nicht nur theoretisch"
  - "Kannst eine Migrationsstrategie (Big-Bang vs. schrittweise/parallel) hinsichtlich Risiko einordnen"
---

## Zwei echte Archive, eine echte Migration

Die folgenden Befunde stammen nicht aus der Standard-Spielwiese dieser
Plattform (die stellt pro Sitzung genau ein Orthanc bereit), sondern
aus einer eigens für diese Lektion aufgesetzten Umgebung mit **zwei**
unabhängigen, echten Orthanc-Instanzen auf einem eigenen Docker-Netz —
eine Migration braucht per Definition Quelle und Ziel. Alle Befehle
und Ausgaben unten sind real erfasst, nur eben nicht in der Sandbox
reproduzierbar, die diese Plattform sonst bereitstellt.

## Die naive Annahme: Migration verändert das Objekt

Eine verbreitete Sorge lautet: „Wenn wir migrieren, verlieren wir
UIDs, Pixel oder Tags." Ein echter Test widerlegt das für den
einfachsten Fall — eine Migration per DICOM-Netzwerktransfer
(C-STORE), nicht per Datei-Kopie oder proprietärem Export:

<!-- kein-beispiel -->
```
Aufbau: Orthanc A (Quelle), Orthanc B (Ziel), unabhängige Instanzen.

1. Drei echte Instanzen (Patient "MIGRATION^TEST") per storescu nach A.
2. Dieselben Instanzen per REST (GET /instances/{id}/file) aus A
   zurückgeholt -- simuliert den Export-Schritt eines Migrationstools.
3. Die zurückgeholten Dateien per storescu nach B geschickt --
   simuliert den Import-Schritt.

Ergebnis (dcmdump-Diff der Datei vor und nach dem Umweg durch A):

  (0002,0000) UL 206  →  UL 208   FileMetaInformationGroupLength
  (0002,0012) UI [1.2.826.0.1.3680043.8.498.1]        (pydicom)
           →  UI [1.2.276.0.7230010.3.0.3.7.0]        (DCMTK, Orthanc-intern)
  (0002,0013) SH [PYDICOM 3.0.2]  →  SH [OFFIS_DCMTK_370]

Alle anderen Zeilen -- PatientName, StudyInstanceUID,
SeriesInstanceUID, SOPInstanceUID, Pixeldaten -- identisch, Byte für
Byte.

Realer findscu-Vergleich StudyInstanceUID auf A und B nach der
Migration:
  A: (0020,000D) UI [1.2.826.0...555520]   NumberOfStudyRelatedInstances [3]
  B: (0020,000D) UI [1.2.826.0...555520]   NumberOfStudyRelatedInstances [3]

Sogar Orthancs eigene interne Instanz-ID (aus den DICOM-Identifiers
deterministisch abgeleitet, kein Zufallswert) ist auf A und B
identisch -- ohne jede Koordination zwischen den beiden Instanzen.
```

Die klinisch relevante Identität (Patient, Study, Series, Instance,
Pixeldaten) überlebt eine netzwerkbasierte Migration unverändert. Was
sich ändert, ist ausschließlich die **File-Meta-Signatur** — welche
Implementierung die Datei zuletzt geschrieben hat (`ImplementationClassUID`,
`ImplementationVersionName`). Das ist eine administrative Spur, keine
inhaltliche Veränderung — aber sie erklärt, warum ein naiver
Byte-für-Byte-Vergleich zweier Dateien nach einer Migration fälschlich
„verändert" melden kann, obwohl der klinische Inhalt exakt gleich ist.

## Der reale Fehlerfall: wenn ein Migrationstool UIDs neu vergibt

Manche älteren oder schlecht implementierten Migrationswerkzeuge
verstehen DICOM-UIDs nicht als feste Identität, sondern vergeben beim
Import neue. Real nachgestellt: dieselbe Studie noch einmal an B
gesendet, diesmal mit frisch generierter `StudyInstanceUID`,
`SeriesInstanceUID` und `SOPInstanceUID` (alles andere identisch,
inklusive `PatientName`/`PatientID`):

<!-- kein-beispiel -->
```
Reale findscu-Antwort auf B danach (STUDY-Query für PatientName
"MIGRATION^TEST"):

  Find Response 1: StudyInstanceUID [...73774488]  NumberOfStudyRelatedInstances [1]
  Find Response 2: StudyInstanceUID [...52555520]  NumberOfStudyRelatedInstances [3]
  Find SCP Result: 0x0000 (Success)
```

**Was du daran abliest:** Am Ziel-Archiv erscheinen jetzt real **zwei
getrennte, unverbundene Studien** für denselben Patienten — eine mit
den ursprünglichen 3 Instanzen, eine mit der einen neu-UID-vergebenen
Instanz. Kein Fehlercode, keine Warnung: Beide Studien sind für sich
genommen vollständig gültige DICOM-Objekte, das Archiv hat keine
Möglichkeit zu erkennen, dass sie eigentlich zusammengehören. Genau
das ist der reale Mechanismus hinter der Klage „nach der Migration
sind alte und neue Bilder getrennt" — keine Dateibeschädigung, sondern
eine UID-Entscheidung des Migrationswerkzeugs.

## Warum UID-Konsistenz über das Archiv hinausreicht

Das Archiv ist nicht das einzige System, das eine `StudyInstanceUID`
kennt: Befundsysteme, HL7-ORU-Nachrichten und teils auch
DICOM-Structured-Reports (Lektion 3.5) referenzieren dieselbe UID, um
sich auf „diese Untersuchung" zu beziehen. Ändert eine Migration die
UID, verweisen alle diese externen Referenzen anschließend ins Leere —
ein Bruch, der sich nicht am Archiv selbst zeigt, sondern erst dort,
wo jemand versucht, Befund und Bild wieder zusammenzuführen.

## Was ein einfacher Bildvergleich nicht zeigt

„Anzahl Bilder vorher = Anzahl Bilder nachher" ist kein ausreichender
Migrationstest — das oben gezeigte Beispiel hätte diesen Test
bestanden (3 Bilder vorher, 3 Bilder in der korrekten Studie danach)
und trotzdem eine zusätzliche, fehlerhafte Studie erzeugt, die ein
reiner Zähler nie gesehen hätte. Eine belastbare Prüfung vergleicht
stattdessen gezielt:

1. **UID-Identität** — dieselbe `StudyInstanceUID`/`SeriesInstanceUID`/
   `SOPInstanceUID` vorher und nachher, nicht nur dieselbe Bildanzahl.
2. **Stichproben-Diff** — ein echter `dcmdump`-Vergleich einzelner
   Objekte vor und nach der Migration, wie oben gezeigt.
3. **Private/herstellerspezifische Tags** — ob das Zielsystem sie
   überhaupt kennt oder beim Import verwirft, ist implementierungs-
   abhängig. Das wurde für diese Lektion nicht eigens getestet (ein
   Versuch, einen privaten Tag testweise einzufügen, scheiterte an
   `dcmodify`s VR-Anforderungen für unbekannte private Elemente) — die
   Aussage bleibt an dieser Stelle unbelegt und ist entsprechend mit
   Vorsicht zu behandeln, nicht als geprüfte Tatsache.

## Zwei Migrationsstrategien im Risikovergleich

| Strategie | Vorgehen | Risiko |
|---|---|---|
| Big-Bang-Cutover | Alter Betrieb endet, neues System übernimmt an einem Stichtag vollständig | Migrationsfehler (wie oben) wirken sich sofort und vollständig aus, wenig Zeit zum Gegenprüfen |
| Paralleler Betrieb / schrittweise Migration | Beide Systeme laufen zeitweise nebeneinander, Daten wandern schrittweise oder werden doppelt gespeist | Migrationsfehler betreffen zunächst nur einen Teilbestand, mehr Zeit für Stichproben — höherer Betriebsaufwand während der Übergangsphase |

Der oben gezeigte UID-Fehlerfall wäre bei einem Big-Bang-Cutover erst
bemerkt worden, wenn Nutzer bereits produktiv mit dem neuen Archiv
arbeiten — bei einer schrittweisen Migration mit Stichprobenvergleich
schon an der ersten migrierten Studie.

## Stolperfallen

- **Bildanzahl als vollständigen Migrationstest behalten.** Der
  UID-Fehlerfall oben besteht diesen Test trotzdem.
- **Annehmen, ein Netzwerktransfer verändere UIDs oder Pixeldaten.**
  Das Gegenteil ist real gezeigt — nur die File-Meta-Signatur ändert
  sich.
- **UID-Konsistenz für ein rein archivinternes Thema halten.** Externe
  Systeme (Befund, HL7) referenzieren dieselben UIDs.

## Selbstcheck

1. Ein `dcmdump`-Diff zeigt nach einer Migration eine geänderte
   `ImplementationClassUID`, aber identische `StudyInstanceUID` und
   Pixeldaten. Ist das ein Fehler?
2. Ein Migrationswerkzeug vergibt beim Import neue
   `StudyInstanceUID`-Werte. Was passiert am Ziel-Archiv mit einer
   bereits dort vorhandenen Studie desselben Patienten — verschmelzen
   sie, oder passiert etwas anderes?
3. Ein Migrationsbericht meldet „Anzahl Bilder vorher = Anzahl Bilder
   nachher, Migration erfolgreich". Warum reicht das allein nicht als
   Nachweis?
