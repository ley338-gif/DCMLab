---
title: "Nicht jedes DICOM-Objekt ist ein Bild"
teaser: "SR, RDSR, KOS und Encapsulated PDF landen im selben Archiv wie CT-Bilder — aber ein Viewer muss sie völlig anders behandeln."
objectives:
  - Ein DICOM-Objekt nicht automatisch mit einem Pixelbild gleichsetzen
  - Structured Report, Radiation Dose SR, Key Object Selection und Encapsulated PDF funktional unterscheiden
  - Über die SOP Class UID erkennen, welche Art Objekt vorliegt
  - Einordnen, warum ein Objekt korrekt gespeichert sein kann und trotzdem nicht in der gewohnten Bildserie erscheint
---

## „Im PACS ist etwas angekommen — aber wo ist das Bild?"

Ein C-STORE-Log zeigt Erfolg. Das Archiv zählt eine neue Instance. Der Radiologe öffnet die Untersuchung und sagt trotzdem: „Da ist nichts Neues."

Der erste Reflex ist oft: Transfer kaputt, Viewer kaputt oder Serie unvollständig. Das kann stimmen. Es gibt aber noch eine einfachere Erklärung: **Das neue DICOM-Objekt ist gar kein Bild.**

DICOM transportiert nicht nur Pixel. Derselbe Transportmechanismus kann auch strukturierte Messwerte, Dosisberichte, Verweise auf besonders wichtige Bilder oder ein eingebettetes PDF übertragen. Für die Administration ist deshalb die Frage „Ist die Instance da?“ nur die halbe Diagnose. Die zweite lautet: **Welche SOP Class hat sie?**

## Die SOP Class sagt, was ein Objekt fachlich ist

Die {{term:sop-class}} beschreibt nicht den Patienten und auch nicht die Untersuchung. Sie beschreibt, **welche Art DICOM-Objekt** eine Instance darstellt und welche Semantik dafür gilt.

Bei einem klassischen CT Image Storage Object erwartet der Viewer Pixeldaten. Bei einem Structured Report erwartet er dagegen einen strukturierten Inhaltsbaum. Bei einem Key Object Selection Document erwartet er hauptsächlich Verweise auf andere Instances. Ein Encapsulated PDF enthält wiederum ein Dokument als eingebetteten Binärinhalt.

Darum ist `(0008,0016) SOPClassUID` bei ungewöhnlichen Objekten einer der ersten Tags, die du prüfst.

```text
$ dcmdump +P SOPClassUID +P Modality objekt.dcm
(0008,0016) UI =XRayRadiationDoseSRStorage              #  30, 1 SOPClassUID
(0008,0060) CS [SR]                                     #   2, 1 Modality
```

**Was du daran abliest:** Das Objekt meldet sich nicht als CT-, MR- oder CR-Bild, sondern als SR-Objekt — konkret als `XRayRadiationDoseSRStorage`, also ein RDSR (dazu gleich mehr). `dcmdump` löst bekannte SOP-Class-UIDs zu diesem sprechenden Namen auf, statt die Ziffernfolge auszugeben. Allein an dieser Zeile wäre es falsch, im Viewer eine zusätzliche Bildserie mit neuen Pixeln zu erwarten.

## Vier Objektarten, die PACS-Admins regelmäßig begegnen

### Structured Report (SR)

Ein DICOM SR speichert strukturierte Inhalte: Text, Messwerte, Codes, Referenzen und Beziehungen zwischen diesen Informationen. Manche Systeme zeigen SR direkt im Viewer an, andere nur als Dokument oder eigene Registerkarte.

Für dich als Admin ist wichtig: Ein SR kann technisch perfekt im Archiv liegen, obwohl ein Anwender sagt, „der Befund fehlt“. Dann musst du klären, ob der Viewer die konkrete SR-SOP-Class überhaupt darstellen kann.

### Radiation Dose Structured Report (RDSR)

Ein RDSR ist ein spezialisierter Structured Report für Strahlenexpositionsdaten. CT, Röntgen- und andere Modalitäten können damit Dosisinformationen standardisiert an PACS, Dose-Management-Systeme oder Auswertungsplattformen senden.

RDSR ist **kein Screenshot der Dosisanzeige**. Das ist der entscheidende Unterschied: Die Werte liegen strukturiert vor und können automatisch ausgewertet werden.

### Key Object Selection (KOS)

Ein KOS-Dokument enthält vor allem **Referenzen auf andere DICOM-Objekte**. Damit kann ein System beispielsweise ausgewählte Schlüsselbilder markieren, ohne diese Bilder zu duplizieren.

Wenn ein KOS fehlt, fehlen deshalb nicht automatisch die Bilder selbst. Es fehlt möglicherweise nur die Information, welche vorhandenen Bilder als besonders relevant markiert wurden.

### Encapsulated PDF

DICOM kann ein PDF als eigene SOP Class transportieren. Das ist praktisch, wenn externe Dokumente zusammen mit der Bildgebung archiviert werden sollen.

Ein PACS kann ein solches Objekt korrekt annehmen und trotzdem keinen eingebauten PDF-Renderer besitzen. Auch hier gilt: **Storage-Support und Display-Support sind zwei verschiedene Fähigkeiten.**

## Im Alltag heißt das

Wenn eine Untersuchung „unvollständig“ wirkt, prüfst du nicht nur die Anzahl der Images. Du prüfst die Arten der enthaltenen Instances.

| Frage | Was sie klärt |
|---|---|
| Welche SOP Class UID hat das Objekt? | Bild, SR, RDSR, KOS, PDF oder etwas anderes |
| Hat C-STORE Erfolg gemeldet? | Transport und Storage wurden akzeptiert |
| Kennt das Archiv die Instance? | Objekt ist indexiert/gespeichert |
| Unterstützt der Viewer diese SOP Class? | Das Objekt kann tatsächlich dargestellt werden |
| Erwartet der Anwender Pixel oder Dokumentinhalt? | Fachliche Erwartung passt zum Objekttyp |

Die saubere Trennung spart besonders bei herstellerübergreifenden Integrationen Zeit. „PACS unterstützt DICOM“ sagt nicht, dass jede gespeicherte SOP Class auch in jeder Oberfläche sinnvoll dargestellt wird.

## Stolperfallen

- **Jede Instance für ein Bild halten.** DICOM ist ein Objektstandard, kein reines Bildformat.
- **C-STORE Success mit Viewer-Support verwechseln.** Das Archiv kann etwas speichern, das der Viewer nicht rendert.
- **Modality allein verwenden.** `SR` hilft bei der Einordnung, aber die konkrete SOP Class ist präziser.
- **KOS als Bildkopie behandeln.** Ein KOS verweist auf Bilder; es ersetzt sie nicht.

## Selbstcheck

1. Welcher Tag ist deine erste Anlaufstelle, wenn du wissen willst, welche Objektart eine Instance darstellt?
2. Warum kann ein RDSR im Archiv vorhanden sein, ohne als normale Bildserie zu erscheinen?
3. Was ist der wesentliche Unterschied zwischen einem KOS und einem zusätzlichen Bild?
4. Beweist ein erfolgreicher C-STORE, dass der Viewer das Objekt darstellen kann?

## Quiz

*Wissenskarten — kommen später zur Wiederholung zurück.*

**q1 — Ein Radiologe sagt: „Da ist nichts Neues", obwohl das Archiv eine neue Instance zählt. Was ist die wahrscheinlichste einfache Erklärung, bevor du Transfer oder Viewer verdächtigst?**
1. Der Transfer ist fehlgeschlagen
2. Das neue Objekt ist gar kein Bild — z. B. ein SR, RDSR, KOS oder Encapsulated PDF
3. Der Viewer-Cache ist veraltet
4. Die Instance gehört zu einer anderen Study

**q2 — Welche Aussagen stimmen?** *(Mehrfachauswahl)*
1. Ein Structured Report kann technisch korrekt im Archiv liegen, obwohl der Viewer ihn nicht darstellt
2. Ein RDSR ist im Kern ein Screenshot der Dosisanzeige
3. Ein KOS verweist auf andere Instances, statt sie zu duplizieren
4. Ein Encapsulated PDF kann von einem Archiv gespeichert werden, auch ohne dass es einen PDF-Renderer besitzt

**q3 — Welcher Tag verrät dir, welche Art DICOM-Objekt eine Instance ist?** *(Freitext)*
