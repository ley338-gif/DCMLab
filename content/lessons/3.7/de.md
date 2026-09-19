---
title: "Gespeichert, aber nicht darstellbar — SOP-Class-Support im PACS-Betrieb"
teaser: "Ein Objekt kann gespeichert, indexiert und sogar per Query auffindbar sein — und trotzdem nirgendwo dargestellt oder weiterverarbeitet werden."
objectives:
  - Storage-, Query-, Display- und Routing-/Verarbeitungsunterstützung als unabhängige Fähigkeiten unterscheiden
  - Erklären, warum ein erfolgreicher C-STORE nichts über Darstellung oder Weiterverarbeitung eines Objekts aussagt
  - Anhand der SOP Class einordnen, an welcher Stelle einer Betriebskette eine Inkompatibilität typischerweise entsteht
  - Structured Report, Radiation Dose SR, Key Object Selection und Encapsulated PDF als Betriebsbeispiele einordnen, ohne sie erneut strukturell zu definieren
---

## „Im Archiv gespeichert — aber niemand kann es öffnen"

Ein eingescanntes Zuweiserschreiben liegt als Encapsulated PDF im
Archiv. Der C-STORE war erfolgreich, das Objekt lässt sich per Query
finden. Trotzdem kann es niemand öffnen: Der verwendete Viewer besitzt
für diese SOP Class keinen Renderer.

Aus Lektion 3.5 weißt du bereits, dass ein DICOM-Objekt kein Bild sein
muss — Structured Reports, Key Object Selections und Encapsulated PDFs
transportieren andere Inhalte als Pixeldaten. Diese Lektion setzt das
voraus und fragt weiter: Was bedeutet es für den laufenden Betrieb,
wenn verschiedene Systeme — Archiv, Viewer, Router, Dose-System —
dieselbe SOP Class unterschiedlich gut unterstützen?

## SOPClassUID ist präzise, Modality nur grob

```text
$ dcmdump +P SOPClassUID +P Modality objekt.dcm
(0008,0016) UI =XRayRadiationDoseSRStorage              #  30, 1 SOPClassUID
(0008,0060) CS [SR]                                     #   2, 1 Modality
```
**Was du daran abliest:** `Modality` meldet nur `SR` — denselben Wert,
den auch ein einfacher Structured Report melden würde. Erst
`SOPClassUID` (0008,0016) sagt präzise, dass hier ein
`XRayRadiationDoseSRStorage`, also ein RDSR, vorliegt (Details zu
SR/RDSR in Lektion 3.5). Diese Zeile beantwortet aber nur „was ist es"
— nicht „was kann dieses System damit tatsächlich anfangen".

## Sechs getrennt zu prüfende Stufen

<!-- kein-beispiel -->
```text
SOP Class wird vom Sender erzeugt
        ↓
Presentation Context wird akzeptiert
        ↓
Archiv kann Objekt speichern
        ↓
Archiv kann Objekt indexieren/querybar machen
        ↓
Viewer kann Objekt darstellen
        ↓
Downstream-System kann Objekt verarbeiten
```

Jeder Pfeil ist ein eigener, separat zu belegender Schritt — keiner
folgt automatisch aus dem vorherigen. Ein System kann zum Beispiel eine
SOP Class speichern, per Query liefern, aber keinen Renderer dafür
besitzen. Ein anderes kann sie speichern und anzeigen, aber nicht an
einen Downstream-Prozess weiterleiten. Ein drittes lehnt die
zugehörige {{term:presentation-context}} bereits bei der Verhandlung
ab, bevor überhaupt ein C-STORE versucht wird.

## Storage-Support ≠ Display-Support

> Ein erfolgreicher C-STORE beweist nicht, dass ein Viewer den Inhalt
> auch darstellen kann.
>
> Ein im Archiv vorhandenes, indexiertes Objekt beweist nicht, dass ein
> Dose-System, ein Export-Dienst oder ein Router diesen Objekttyp
> überhaupt unterstützt.

Das ist die zentrale Aussage dieser Lektion — und der Grund, warum
„PACS unterstützt DICOM" bei einer Integration wenig aussagt.

## Beispiele aus dem Betrieb

Du kennst Structured Report, RDSR, Key Object Selection und
Encapsulated PDF bereits strukturell aus Lektion 3.5. Hier dienen sie
nur noch als Beispiele für unterschiedliche Kombinationen aus Storage-,
Query-, Display- und Routing-Unterstützung:

| Fähigkeit | Beispiel |
|---|---|
| Storage | Ein PACS akzeptiert ein Encapsulated PDF |
| Query | Das Objekt ist im Bestand auffindbar |
| Display | Der Viewer besitzt keinen PDF-Renderer für diese SOP Class |
| Routing | Ein Router hat keine Weiterleitungsregel für RDSR-Objekte |
| Referenzauflösung | Ein KOS ist vorhanden, aber eine referenzierte Instance fehlt oder ist gerade nicht verfügbar |

Die letzte Zeile zeigt eine eigene, fünfte Fehlerart: Ein KOS kann
technisch vollständig und storage-seitig unauffällig sein, und trotzdem
ins Leere verweisen, wenn eine referenzierte Instance fehlt.

## Keine Herstellerbehauptungen ohne Beleg

Wichtig für den echten Betrieb: Ob ein konkretes PACS, ein konkreter
Viewer oder ein konkretes Dose-System eine bestimmte SOP Class
unterstützt, ist eine Frage an das jeweilige Conformance Statement,
keine allgemeine Aussage über „DICOM-Systeme". Diese Lektion nennt
deshalb bewusst keine echten Produktnamen — nur abstrakte Rollen
(`PACS`, `Viewer`, `Router`, `Dose-System`). Ein Conformance Statement
beschreibt dabei immer ein einzelnes Produkt, nie das Zusammenspiel
mehrerer Systeme: Für jedes beteiligte Produkt und dessen
Softwarestand muss das aktuelle Conformance Statement geprüft und das
Zusammenspiel anschließend praktisch verifiziert werden.

## Im Alltag heißt das

| Beobachtung | Was sie noch nicht beweist |
|---|---|
| C-STORE meldet Erfolg | Nicht, dass ein Viewer den Inhalt darstellen kann |
| Objekt ist im Archiv auffindbar | Nicht, dass ein Downstream-System (Router, Dose-System) es verarbeitet |
| Ein KOS ist gespeichert | Nicht, dass die referenzierten Instances noch verfügbar sind |
| Ein System kennt eine SOP Class beim Speichern | Nicht, dass es dieselbe SOP Class auch beim Routing oder in der Darstellung unterstützt |

## Stolperfallen

- **„PACS unterstützt DICOM" als vollständige Aussage nehmen.** Storage-,
  Query-, Display- und Routing-Unterstützung sind vier verschiedene
  Fähigkeiten, die pro SOP Class einzeln gelten.
- **C-STORE Success mit Rendering- oder Verarbeitungsfähigkeit
  verwechseln.** Das Archiv kann etwas speichern, das kein
  angeschlossenes System darstellen oder weiterverarbeiten kann.
- **Ein KOS für genauso robust wie ein Bild halten.** Es verweist auf
  andere Instances — fehlt eine davon, verweist es ins Leere, ohne
  selbst beschädigt zu sein.
- **Herstellerbehauptungen ungeprüft übernehmen.** Nur das konkrete,
  aktuelle Conformance Statement einer konkreten Systemkombination
  beantwortet, was tatsächlich unterstützt wird.

## Selbstcheck

1. Was beweist ein erfolgreicher C-STORE — und was beweist er
   ausdrücklich nicht?
2. Ein KOS ist im Archiv vorhanden, aber eine referenzierte Instance
   fehlt. Ist das automatisch ein Fehler im KOS selbst?
3. Nenne zwei der sechs getrennt zu prüfenden Stufen aus der Kette
   dieser Lektion.
4. Warum reicht `SOPClassUID` allein nicht aus, um zu wissen, ob ein
   Viewer ein Objekt auch darstellen kann?

## Quiz

*Wissenskarten — kommen später zur Wiederholung zurück.*

**q1 — Ein eingescanntes Zuweiserschreiben liegt erfolgreich gespeichert im Archiv und lässt sich per Query finden, aber niemand kann es öffnen. Was ist die wahrscheinlichste einfache Erklärung?**
1. Der C-STORE ist fehlgeschlagen
2. Das Archiv hat das Objekt falsch indexiert
3. Der verwendete Viewer besitzt für diese SOP Class keinen Renderer — Storage- und Display-Support sind getrennte Fähigkeiten
4. Die Datei ist beim Speichern beschädigt worden

**q2 — Welche Aussagen stimmen?** *(Mehrfachauswahl)*
1. Ein erfolgreicher C-STORE beweist, dass der Viewer das Objekt auch darstellen kann
2. Storage-, Query-, Display- und Routing-Unterstützung sind unabhängige Fähigkeiten
3. Ein KOS kann im Archiv vorhanden sein, obwohl eine der referenzierten Instances fehlt oder gerade nicht verfügbar ist
4. Unterstützt ein System eine SOP Class beim Speichern, unterstützt es dieselbe SOP Class automatisch auch beim Routing

**q3 — Welcher Tag sagt dir präzise, welche Art DICOM-Objekt eine Instance ist — nicht nur grob wie `Modality`?** *(Freitext)*
