---
title: Abschlussprüfung — Das Bild selbst
intro: 16 Fragen aus Track 3. Ab 80 % ist der Track abgeschlossen. Beliebig oft wiederholbar.
---

### f01 — Was unterscheidet ein Type-1- von einem Type-2-Attribut?

1. Type 1 muss einen echten Wert tragen, Type 2 darf leer, aber muss vorhanden sein
2. Type 1 ist optional, Type 2 ist Pflicht
3. Beide sind gleichwertig, nur unterschiedlich benannt
4. Type 2 darf komplett fehlen, Type 1 nicht unbedingt

**Erklärung:** Type 1 verlangt einen echten Wert — leer ist nicht erlaubt. Type 2 verlangt nur, dass das Tag vorhanden ist; der Wert selbst darf leer bleiben. Type 3 dagegen darf komplett fehlen.

### f02 — Welches Attribut führte im Lektionsbeispiel zur echten Ablehnung mit `0xA700`, wenn es fehlt?

1. PatientID
2. StudyInstanceUID
3. PhotometricInterpretation
4. WindowCenter

**Erklärung:** Ohne `StudyInstanceUID` (Type 1) weiß kein Archiv, zu welcher Untersuchung die Daten gehören — Orthanc lehnt mit `0xA700` und dem Klartextgrund "required tags … missing" ab. Ein fehlendes `PatientID` (Type 2) wurde dagegen anstandslos angenommen.

### f03 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. Ein Type-2-Attribut darf einen leeren Wert tragen und trotzdem vorhanden sein
2. Ein fehlendes Type-3-Attribut ist ein Standardverstoß
3. Ein Archiv prüft typischerweise gezielt die Attribute, die es für seinen eigenen Index braucht
4. Jedes Modul eines IOD ist entweder vollständig Pflicht oder vollständig optional

**Erklärung:** Type 2 erlaubt einen leeren Wert bei vorhandenem Tag, und ein Archiv prüft meist gezielt die für seinen eigenen Betrieb nötigen Attribute. Ein fehlendes Type-3-Attribut ist dagegen kein Verstoß, und innerhalb eines Moduls haben einzelne Attribute unterschiedliche Pflichtgrade — nicht das ganze Modul einheitlich.

### f04 — Ein fehlendes Attribut im Dump bedeutet automatisch, dass die Datei fehlerhaft ist.

**Richtig / Falsch**

**Erklärung:** Falsch. Type-2- und Type-3-Attribute dürfen leer sein oder ganz fehlen — das ist vom Standard so vorgesehen, kein Defekt.

### f05 — Was bedeutet `PhotometricInterpretation MONOCHROME1`?

1. Der kleinste Pixelwert wird hell dargestellt, der größte dunkel
2. Der kleinste Pixelwert wird dunkel dargestellt, der größte hell
3. Das Bild ist farbig
4. Es handelt sich um einen Fehlerzustand

**Erklärung:** MONOCHROME1 kehrt die übliche Konvention um: der kleinste Pixelwert wird hell/weiß dargestellt. MONOCHROME2 (kleinster Wert dunkel) ist der Normalfall bei CT — beide sind gültige, keine fehlerhaften Konventionen.

### f06 — Ein Objekt hat `SamplesPerPixel 3` und `PhotometricInterpretation RGB`. Was bedeutet `BitsAllocated 8` dabei?

1. Ein Byte pro Farbkanal
2. Acht Bytes pro Pixel insgesamt
3. Das Bild ist komprimiert
4. Es gibt keine Pixeldaten

**Erklärung:** `BitsAllocated 8` heißt ein Byte pro Kanal — bei drei Kanälen (RGB) also drei Byte pro Pixel insgesamt, nicht acht Byte für den ganzen Pixel.

### f07 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. MONOCHROME1 ist ein Fehlerzustand
2. PlanarConfiguration legt fest, ob Farbkanäle interleaved oder in getrennten Ebenen liegen
3. PhotometricInterpretation ist Type 1, wird aber nicht von jedem Archiv beim Empfang geprüft
4. RGB-Bilder haben SamplesPerPixel 3

**Erklärung:** PlanarConfiguration steuert die Anordnung der Farbkanäle, PhotometricInterpretation ist zwar Type 1, wird aber nicht zwangsläufig beim Speichern geprüft, und RGB-Bilder tragen SamplesPerPixel 3. MONOCHROME1 ist dagegen eine gültige Konvention, kein Fehler.

### f08 — Type 1 bedeutet, dass jedes Archiv dieses Attribut beim Empfang zwingend prüft.

**Richtig / Falsch**

**Erklärung:** Falsch. Wie das Nebenfund-Beispiel zeigt, nahm Orthanc ein Objekt ohne `PhotometricInterpretation` (Type 1) trotzdem an — ein Archiv prüft gezielt, was es für seinen eigenen Betrieb braucht, nicht automatisch jedes Type-1-Attribut jedes Moduls.

### f09 — Wie werden Hounsfield Units aus dem Rohwert berechnet?

1. HU = Rohwert × RescaleSlope + RescaleIntercept
2. HU = Rohwert / WindowWidth
3. HU = WindowCenter − Rohwert
4. HU = Rohwert × WindowCenter

**Erklärung:** `RescaleSlope` und `RescaleIntercept` rechnen den gerätespezifischen Rohwert in die genormte Hounsfield-Skala um: `HU = Rohwert × RescaleSlope + RescaleIntercept`.

### f10 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. WindowCenter/WindowWidth legen fest, welcher HU-Bereich sichtbar wird
2. Ein schwarzes, aber valides Bild bedeutet immer eine beschädigte Datei
3. Für nicht-lineare Umrechnungen gibt es alternativ Modality- bzw. VOI-LUT-Sequenzen
4. Es gibt nur eine einzige richtige Fensterung für jeden Datensatz

**Erklärung:** WindowCenter/WindowWidth bestimmen den sichtbaren HU-Ausschnitt, und für nicht-lineare Umrechnungen stehen Modality- bzw. VOI-LUT-Sequenzen bereit. Ein schwarzes Bild bedeutet aber nicht automatisch eine kaputte Datei, und es gibt je nach Fragestellung (Weichteil, Lunge, Knochen) mehrere sinnvolle Fensterungen.

### f11 — Zwei Viewer, die denselben CT-Datensatz unterschiedlich hell darstellen, haben zwangsläufig einen davon falsch konfiguriert.

**Richtig / Falsch**

**Erklärung:** Falsch. Es gibt eine sinnvolle Fensterung für eine bestimmte Frage (Weichteil, Lunge, Knochen — jeweils andere Center/Width-Werte). Zwei unterschiedliche, beide korrekte Darstellungen desselben Datensatzes sind normal.

### f12 — Auf welchen ungefähren Hounsfield-Wert wird Luft in der CT-Praxis normiert (nur die Zahl, ohne Einheit)? *(Freitext)*

**Erklärung:** `-1000`. `RescaleIntercept -1024` mit `RescaleSlope 1` ist die in der CT-Praxis nahezu universelle Konvention, die einen unsigned Rohwertbereich so verschiebt, dass Luft auf etwa `-1000 HU` landet.

### f13 — Ein Archiv meldet für eine Study `NumberOfStudyRelatedInstances: 1`. Was folgt daraus über die Bildzahl?

1. Es kann trotzdem mehr als ein Bild enthalten sein, wenn das Objekt multiframe ist
2. Es ist sicher genau ein Bild
3. Das Archiv hat einen Fehler
4. Es handelt sich zwingend um ein Enhanced-Objekt

**Erklärung:** Das Archiv zählt Instances, nicht Frames. Ein multiframe-Objekt mit `NumberOfFrames 2` zählt trotzdem als eine Instance — ein Skript, das Instances mit Bildern gleichsetzt, liegt hier falsch.

### f14 — Wodurch unterscheidet sich ein Enhanced IOD strukturell von einem klassischen multiframe-Objekt?

1. Durch eine höhere NumberOfFrames-Zahl
2. Durch Shared/Per-Frame Functional Groups Sequences für pro-Frame-unterschiedliche Parameter
3. Durch eine andere Bildkompression
4. Durch einen zusätzlichen DIMSE-Dienst

**Erklärung:** Der Unterschied liegt nicht in der Frame-Zahl, sondern darin, wo Bildparameter stehen: Enhanced IODs bringen eine Shared Functional Groups Sequence (für alle Frames gleich) und/oder eine Per-Frame Functional Groups Sequence (pro Frame einzeln) mit.

### f15 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. Schon klassische IODs wie Secondary Capture erlauben mehrere Frames pro Instance
2. NumberOfFrames größer 1 bedeutet automatisch ein Enhanced-Objekt
3. Bei klassischem multiframe gelten Attribute wie WindowCenter einmal für die ganze Datei
4. Ein Auswertungsskript, das Dateien statt Frames zählt, kann bei multiframe-Objekten falschliegen

**Erklärung:** Secondary Capture erlaubt bereits mehrere Frames ohne Enhanced zu sein, klassisches multiframe teilt Attribute wie WindowCenter für alle Frames, und ein dateibasiertes Zählskript liegt bei solchen Objekten falsch. `NumberOfFrames > 1` bedeutet aber nicht automatisch Enhanced — das ist eine eigene, zusätzliche Strukturebene.

### f16 — NumberOfFrames > 1 bedeutet automatisch, dass es sich um ein Enhanced-Objekt handelt.

**Richtig / Falsch**

**Erklärung:** Falsch. Multiframe ist schon bei klassischen IODs wie Secondary Capture möglich. Enhanced ist eine zusätzliche, eigene Strukturebene (Functional Groups), keine reine Frage der Frame-Zahl.

### f17 — Welche SOPClassUID hat ein Structured Report in der Lektion?

1. SecondaryCaptureImageStorage
2. BasicTextSRStorage
3. KeyObjectSelectionDocumentStorage
4. CTImageStorage

**Erklärung:** `BasicTextSRStorage` ist ein eigener Objekttyp ohne Pixeldaten — `ValueType CONTAINER` bündelt strukturierten Inhalt, jede Aussage trägt ihren eigenen Code.

### f18 — Was enthält die ContentSequence eines Key Object Selection anstelle eines TEXT-Elements?

1. Ein IMAGE-Element mit ReferencedSOPSequence als Verweis auf ein vorhandenes Bild
2. Rohe Pixeldaten des referenzierten Bilds
3. Eine Kopie des gesamten referenzierten Objekts
4. Nichts, die Sequenz bleibt leer

**Erklärung:** Ein KOS enthält ein `IMAGE`-Element mit einer `ReferencedSOPSequence` — einem echten Verweis auf die SOPInstanceUID eines bereits vorhandenen Objekts, ohne selbst Pixel mitzubringen.

### f19 — Ein Key Object Selection dupliziert die Pixeldaten des Bilds, auf das es verweist.

**Richtig / Falsch**

**Erklärung:** Falsch. Ein KOS verweist nur über `ReferencedSOPInstanceUID` — die Pixeldaten existieren weiterhin genau einmal, im ursprünglichen Bildobjekt.

### f20 — Welches Feld einer Study zeigt bei einer C-FIND-Abfrage, dass sie CT-, KO- und SR-Objekte gleichzeitig enthält? *(Freitext)*

**Erklärung:** `ModalitiesInStudy`. Im Lektionsbeispiel liefert es `CT\KO\SR` — dieselbe Study enthält real ein CT-Bild, ein Key Object Selection und einen Structured Report.

### f21 — Welche Zeichenkodierung gilt laut Standard, wenn SpecificCharacterSet fehlt?

1. UTF-8 (ISO_IR 192)
2. Latin-1 (ISO_IR 100)
3. 7-Bit-ASCII (ISO_IR 6)
4. Die des jeweiligen Betriebssystems

**Erklärung:** Fehlt `SpecificCharacterSet`, gilt laut Standard der Default: reines 7-Bit-ASCII (`ISO_IR 6`) — keine Umlaute, keine Sonderzeichen.

### f22 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. SpecificCharacterSet legt die Kodierung aller Textwerte des Objekts fest
2. Ein fehlendes SpecificCharacterSet macht die Rohbytes automatisch ungültig
3. Orthanc lehnt Objekte mit fehlendem SpecificCharacterSet beim Speichern ab
4. Verschiedene Werkzeuge können bei fehlender Deklaration unterschiedlich raten

**Erklärung:** SpecificCharacterSet legt die Kodierung aller Textwerte fest, und Werkzeuge reagieren bei fehlender Deklaration unterschiedlich (dcm2json verweigert, Orthancs REST-API rät und korrigiert still). Die Rohbytes selbst bleiben dabei korrekt, und Orthanc lehnt die Speicherung trotz fehlender Deklaration nicht ab.

### f23 — Ein korrekt angezeigter Name in einem Werkzeug beweist, dass ein anderes Werkzeug dieselbe Datei ebenso korrekt verarbeitet.

**Richtig / Falsch**

**Erklärung:** Falsch. Verschiedene Werkzeuge raten bei fehlender Deklaration unterschiedlich — ein korrekt angezeigter Name in einem Tool beweist nicht, dass ein anderes Tool (etwa ein strikter Report-Export) dieselbe Datei ebenso verarbeitet.

### f24 — Welches Attribut fehlt, wenn `dcm2json` mit der Meldung „dataset contains extended characters but no SpecificCharacterSet" abbricht? *(Freitext)*

**Erklärung:** `SpecificCharacterSet`. Die Bytes selbst sind dabei nicht beschädigt — es fehlt nur die Deklaration, mit der ein Werkzeug sie korrekt lesen kann.

### f25 — Ein Objekt hat weder StudyInstanceUID-Probleme noch fehlendes SpecificCharacterSet, wird aber trotzdem beim Speichern angenommen, obwohl mehrere Type-1-Attribute fehlen. Was folgt daraus über Orthancs Prüfverhalten?

1. Orthanc prüft beim Speichern gezielt nur die für seinen eigenen Index nötigen Attribute, nicht jede Standard-Pflicht
2. Orthanc ignoriert den Standard vollständig
3. Das Objekt ist automatisch ungültig
4. Type-1-Angaben sind bei Orthanc bedeutungslos

**Erklärung:** Sowohl bei fehlendem `PhotometricInterpretation` (Type 1) als auch bei fehlendem `SpecificCharacterSet` nahm Orthanc das Objekt an — es prüft gezielt, was es für seinen eigenen Index braucht (vor allem die Hierarchie-UIDs), nicht automatisch jede Standard-Pflicht jedes Moduls.

### f26 — Welche Aussagen stimmen, wenn ein Auswertungsskript naiv „eine Datei = ein Bild" annimmt? *(Mehrfachauswahl)*

1. Es kann bei multiframe-Objekten zu wenige Bilder zählen
2. Es kann bei einer Study mit SR/KOS zu viele echte Aufnahmen annehmen
3. NumberOfStudyRelatedInstances zählt Bild- und Nicht-Bild-Objekte gleichberechtigt mit
4. Das Skript liegt nur bei Enhanced-Objekten falsch

**Erklärung:** Ein dateibasiertes Skript unterschätzt die Bildzahl bei multiframe-Objekten und überschätzt die Zahl echter Aufnahmen, wenn eine Study zusätzlich SR- oder KOS-Objekte enthält — das Archiv zählt alle Instances gleichberechtigt. Das Problem betrifft nicht nur Enhanced-Objekte, sondern schon klassisches multiframe.

### f27 — Ein Bild wirkt in einem Viewer komplett falsch dargestellt. Welche zwei Attribute prüfst du zuerst, unabhängig voneinander?

1. PhotometricInterpretation (Farb-/Graukonvention) und WindowCenter/WindowWidth (Fensterung)
2. StudyInstanceUID und SeriesInstanceUID
3. AE Title und Port
4. TransferSyntaxUID und SOPClassUID

**Erklärung:** Die Grau-/Farbkonvention (`PhotometricInterpretation`) und die Fensterung (`WindowCenter`/`WindowWidth`) sind zwei unabhängige Umrechnungsschritte — jeder kann für sich allein ein Bild unbrauchbar erscheinen lassen, ohne dass die Datei beschädigt ist.

### f28 — Welcher Pflichtgrad erlaubt, dass ein Tag ganz fehlt, ohne dass das ein Standardverstoß ist? *(Freitext)*

**Erklärung:** `Type 3`. Type 1 und Type 2 verlangen, dass das Tag vorhanden ist (mit bzw. ohne Pflichtwert) — nur Type 3 darf vollständig fehlen.

### f29 — Eine Study mit `NumberOfStudyRelatedInstances: 5` hat zwangsläufig fünf tatsächliche Aufnahmen.

**Richtig / Falsch**

**Erklärung:** Falsch. Die Zählung schließt Nicht-Bild-Objekte wie Structured Reports oder Key Object Selections gleichberechtigt mit ein — und ein multiframe-Objekt kann umgekehrt mehr Bilder enthalten, als es Instances zählt.

### f30 — Welche Aussagen stimmen für Objekte, die trotz fehlender oder unklarer Attribute (Fensterung, Zeichenkodierung) von einem Archiv anstandslos gespeichert werden? *(Mehrfachauswahl)*

1. Die Speicherung allein beweist nicht, dass jedes nachgelagerte Werkzeug dieselbe Datei korrekt verarbeitet
2. Ein Archiv, das speichert, hat automatisch alle Attribute geprüft
3. Unterschiedliche Werkzeuge können bei denselben, unvollständigen Daten unterschiedlich reagieren
4. Fehlende Fensterung und fehlende Zeichenkodierung sind derselbe Fehlertyp

**Erklärung:** Speicherung und nachgelagerte Verarbeitung sind getrennte Prüfschritte mit unterschiedlichen Werkzeugreaktionen — ein Archiv, das speichert, hat damit nicht jedes Attribut geprüft. Fehlende Fensterung (Anzeigeproblem) und fehlende Zeichenkodierung (Textdarstellung) sind aber zwei unabhängige, unterschiedliche Fehlertypen.

### f31 — Ein Radiologe sagt: „Da ist nichts Neues", obwohl das Archiv eine neue Instance zählt. Welcher Tag klärt zuerst, was diese Instance überhaupt ist?

1. Modality
2. SOPClassUID
3. SeriesDescription
4. StudyInstanceUID

**Erklärung:** `Modality` gibt nur eine grobe Einordnung (z. B. `SR` für mehrere verschiedene Objektarten). `SOPClassUID` (0008,0016) sagt präzise, welche Art DICOM-Objekt vorliegt — Bild, SR, RDSR, KOS oder PDF.

### f32 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. Ein Structured Report kann technisch korrekt im Archiv liegen, obwohl der Viewer ihn nicht darstellt
2. Ein RDSR ist im Kern ein Screenshot der Dosisanzeige
3. Ein KOS verweist auf andere Instances, statt sie zu duplizieren
4. Ein Encapsulated PDF kann von einem Archiv gespeichert werden, auch ohne dass es einen PDF-Renderer besitzt

**Erklärung:** Ein SR kann gespeichert, aber vom Viewer nicht darstellbar sein — Storage-Support und Display-Support sind getrennte Fähigkeiten. Ein KOS dupliziert referenzierte Bilder nicht, sondern verweist nur auf sie. Ein RDSR ist dagegen kein Screenshot, sondern strukturiert und automatisch auswertbar — Option 2 ist deshalb falsch, Option 4 richtig, weil Speicherung und Darstellung ebenfalls getrennte Fähigkeiten sind.

### f33 — Ein erfolgreicher C-STORE beweist, dass der Viewer das gespeicherte Objekt auch darstellen kann.

**Richtig / Falsch**

**Erklärung:** Falsch. Das Archiv kann ein Objekt korrekt annehmen und indexieren, obwohl der Viewer die konkrete SOP Class nicht rendert — Storage-Support und Display-Support sind zwei verschiedene Fähigkeiten.

### f34 — Im Eingangsbeispiel meldet ein RDSR-Objekt beim Tag `Modality` denselben Wert wie ein einfacher Structured Report. Wie lautet dieser Wert? *(Freitext)*

**Erklärung:** `SR`. Sowohl ein klassischer Structured Report als auch ein spezialisierter Radiation Dose SR melden sich über `Modality` gleich — erst `SOPClassUID` unterscheidet die beiden konkret.

### f35 — Ein CT sendet seine Bilder erfolgreich ins PACS. Im Dose-Management-System erscheint die Untersuchung trotzdem nicht. Was folgt daraus zuerst?

1. Der Bildtransfer war fehlerhaft
2. RDSR ist ein eigenes Objekt mit eigener SOP Class, das unabhängig vom Bildtransfer scheitern kann
3. Das Dose-System ist grundsätzlich falsch konfiguriert
4. RDSR wird automatisch mit jedem Bild mitgesendet

**Erklärung:** Bildübertragung und RDSR-Übertragung sind zwei unterschiedliche DICOM-Objekte mit eigener SOP Class und eigener Verhandlung — ein erfolgreicher Bildtransfer sagt nichts über den RDSR-Weg aus.

### f36 — Welche Aussagen zu RDSR stimmen? *(Mehrfachauswahl)*

1. Ein RDSR enthält Dosisdaten als strukturierte, maschinenlesbare Inhalte
2. Ein Dose-Screenshot ist für automatische Auswertung genauso geeignet wie ein RDSR
3. Ein RDSR kann PACS → Dose-System über einen zweiten, unabhängigen Hop erreichen
4. Sobald die Bildserie im PACS gespeichert ist, ist automatisch auch das RDSR gespeichert

**Erklärung:** RDSR liegt strukturiert vor und lässt sich automatisch auswerten — ein Screenshot dagegen kaum. Der Weg zum Dose-System läuft häufig über einen zweiten, eigenständigen Transfer ab PACS oder Router. Bild- und RDSR-Instance sind getrennte Objekte; das eine zu speichern garantiert nicht das andere.

### f37 — Ein grüner Bildtransfer beweist, dass auch die RDSR-SOP-Class auf einer Association akzeptiert wurde.

**Richtig / Falsch**

**Erklärung:** Falsch. C-ECHO und CT Image Storage können funktionieren, während die RDSR-SOP-Class im Presentation Context eigenständig abgelehnt wird — jeder Objekttyp verhandelt seine eigene Zulassung.

### f38 — Welche symbolische SOP-Class-Bezeichnung gibt `dcmdump` für das RDSR-Beispielobjekt aus 3.8 aus?

1. CTImageStorage
2. XRayRadiationDoseSRStorage
3. BasicTextSRStorage
4. KeyObjectSelectionDocumentStorage

**Erklärung:** `dcmdump` löst die SOP-Class-UID des Beispielobjekts zu `XRayRadiationDoseSRStorage` auf — dem sprechenden Namen für X-Ray Radiation Dose SR, statt die Ziffernfolge auszugeben.
