---
title: "Beschaffung: die richtigen Fragen an den Hersteller"
teaser: "Der letzte Track schließt den Kreis — was aus den vorigen sieben Lektionen wird, ist eine Checkliste für die nächste Ausschreibung, direkt aus dem abgeleitet, was du dort tatsächlich real geprüft hast."
objectives:
  - "Kannst aus den real geprüften Inhalten dieses Tracks mindestens sieben konkrete, überprüfbare Fragen für eine Ausschreibung/Herstellerabfrage ableiten"
  - "Kannst erklären, warum 'unterstützt DICOM' als Anforderung in einer Ausschreibung unzureichend ist (Verweis auf Lektion 5.2)"
  - "Kannst benennen, an welcher Stelle im Beschaffungsprozess ein Conformance-Statement-Abgleich stattfinden sollte"
---

## Sieben Lektionen, eine Checkliste

<!-- kein-beispiel -->
```
Die sieben Fragenkategorien dieser Lektion, mit ihrer jeweiligen
Quelllektion und dem real geprüften Befund, auf dem sie beruht:

  Konformität    (5.2) -> Orthancs reales Conformance Statement,
                           reale storescu-Verhandlung
  Integration    (5.1) -> vier real live gezeigte SWF.b-Transaktionen
  Migration      (5.3) -> realer Zwei-Archiv-Test (UID-Erhalt vs.
                           UID-Neuvergabe)
  Datenschutz    (5.4) -> reale dcmodify-Anonymisierung (Z-/U-Aktion)
  Zugriffsschutz (5.5) -> realer /changes-Test (Schreiben ja,
                           Lesen nein)
  Sicherheit     (5.6) -> reale AE-Title-Lücke + zitierte 2026-Studie
  Betrieb        (5.7) -> reale /statistics-/system-/jobs-Endpunkte

Diese Übersicht ersetzt keine der sieben Lektionen -- sie ist eine
Gedächtnisstütze für die folgende Checkliste.
```

Diese Lektion führt keinen neuen Stoff ein — sie übersetzt, was die
vorigen sieben Lektionen dieses Tracks real geprüft und gezeigt haben,
in konkrete Fragen für eine Ausschreibung. Jede Frage unten verweist
auf die Lektion, deren real verifizierter Befund sie begründet.

## Konformität (Lektion 5.2)

„Unterstützt DICOM" ist keine überprüfbare Aussage — das
Conformance-Statement-Muster aus 5.2 zeigt, warum: Orthancs eigenes,
real zitiertes Conformance Statement listet SOP-Klassen, Rollen
(SCU/SCP) und Transfer-Syntaxen einzeln auf, nicht pauschal.

- Welche SOP-Klassen unterstützt das Gerät als SCP, welche als SCU —
  für genau die Bildtypen, die tatsächlich erzeugt werden (nicht nur
  CT, auch Enhanced-Varianten, siehe 3.4)?
- Welche Transfer-Syntaxen werden angeboten, und welche davon bevorzugt
  das Gerät bei mehreren gemeinsamen Optionen (5.2s real getestete
  `LittleEndianExplicit`-Präferenz von Orthanc als Vergleichsmaßstab)?
- Unterstützt das Gerät Extended Negotiation — und falls nicht (wie
  Orthanc, real im Conformance Statement dokumentiert), welche
  Presentation-Context-Verhandlung ist die Folge?

## Integration (Lektion 5.1)

- Welche IHE-Profile werden konkret unterstützt — SWF.b (Worklist,
  Bildtransfer, MPPS-Statusmeldung, siehe die vier real live gezeigten
  RAD-5/6/7/8-Transaktionen), PIR, XDS-I.b?
- Falls PIR nicht genannt wird: Wie behandelt das System einen
  nachträglich korrigierten Patienten (5.1s reale Verbindung zu
  Lektion 4.6s Coercion-Mechanismus)?
- Falls XDS-I.b relevant ist: Wird das Manifest als echtes Key Object
  Selection Document erzeugt (die SOP-Klasse aus Lektion 3.5)?

## Migration (Lektion 5.3)

- Werden bei einer Migration `StudyInstanceUID`/`SeriesInstanceUID`/
  `SOPInstanceUID` unverändert übernommen — oder neu vergeben? (5.3s
  real getesteter Unterschied: identische UIDs vs. zwei getrennte,
  unverbundene Studien bei UID-Neuvergabe.)
- Über welchen Mechanismus exportiert/importiert das System Daten bei
  einem Archivwechsel — Netzwerktransfer (UID-erhaltend, wie real
  gezeigt) oder ein proprietäres Format?
- Wie prüft der Hersteller selbst eine Migration — reicht ein reiner
  Bildzahlenvergleich (5.3s Gegenbeispiel: bestehen, obwohl eine
  zusätzliche, fehlerhafte Studie entstanden ist), oder wird ein
  UID-basierter Abgleich empfohlen?

## Datenschutz (Lektion 5.4 und 5.5)

- Unterstützt das System die Aktionscodes aus DICOM PS3.15 Annex E
  (D/Z/X/K/C/U) für De-Identifikation — insbesondere die **U**-Aktion
  für Type-1-UIDs wie `StudyInstanceUID` (5.4s real verifizierter
  Zusammenhang mit Lektion 3.1)?
- Unterstützt das System DICOMs eigenen Pseudonymisierungs-Mechanismus
  (Encrypted Attributes Data Set, `0400,0550`) oder verlangt es eine
  externe Mapping-Tabelle?
- Protokolliert das System tatsächlich **Lesezugriffe** — oder nur
  Änderungen? (5.5s realer Test an Orthancs `/changes`: Schreiben wird
  protokolliert, Lesen nicht.) Falls nicht: Unterstützt es IHE ATNA
  oder einen vorgeschalteten Reverse-Proxy als Kompensation?

## Sicherheit (Lektion 5.6)

- Prüft das System den Called/Calling-AE-Title tatsächlich, oder
  akzeptiert es (wie Orthanc in dieser Spielwiese, real gezeigt) jeden
  beliebigen Titel? Die real zitierte 2026-Studie fand genau diese
  Lücke bei 1.780 von 1.903 real erreichbaren DICOM-Diensten.
- Unterstützt das Gerät DICOM TLS (Supplement 51, real in Lektion 4.9
  verifiziert) — und wenn ja, mit welchem Zertifikatsmanagement?
- Wie lange bietet der Hersteller Sicherheits-Patches für dieses
  Gerät an, und was passiert nach Ende dieses Zeitraums (5.6s Befund
  zu Legacy-Modalitäten als eigentlichem Risikofaktor)?

## Betrieb (Lektion 5.7)

- Welche Kennzahlen kann das System selbst über eine REST-Schnittstelle
  liefern — Kapazität, Softwareversion, Job-Erfolgsrate (die drei real
  getesteten Kategorien aus 5.7)?
- Liefert das System bei einem fehlgeschlagenen Hintergrundjob eine
  vom „läuft noch" unterscheidbare Fehlermeldung (5.7s realer
  `Progress: 100`/`State: Failure`-Unterschied)?

## Wann im Prozess das passieren sollte

Ein Conformance-Statement-Abgleich (Lektion 5.2) gehört **vor**
Vertragsabschluss in die Ausschreibung, nicht erst in die
Inbetriebnahme — als Teil der Anforderungsspezifikation, nicht der
Abnahmeprüfung. Eine reale, öffentlich referenzierte Vorlage für
diesen Prozess bietet die Deutsche Röntgengesellschaft: Die
Arbeitsgemeinschaft Informationstechnologie (AGIT) hat eine
PACS-Checkliste veröffentlicht, die sich an der IEEE-Praxis für
Software-Anforderungsspezifikationen orientiert und zwischen einer
groben Anfrage (Request for Information) und einer verbindlichen
Ausschreibung (Request for Proposal) unterscheidet — die Fragen oben
gehören strukturell in die RFP-Phase, wo einzelne, überprüfbare
Anforderungen (nicht nur „unterstützt DICOM") verlangt werden können.

## Stolperfallen

- **Diese Checkliste isoliert von den Lektionen lesen.** Jede Frage
  hier ist nur so belastbar wie der reale Test dahinter — im Zweifel
  in die jeweilige Lektion zurückgehen, nicht die Frage allein zitieren.
- **Eine einzelne „Ja"-Antwort des Herstellers für ausreichend
  halten.** Lektion 5.2 zeigt: „Unterstützt SOP-Klasse X" sagt nichts
  über die konkrete Transfer-Syntax, die tatsächlich funktioniert.
- **Sicherheits- und Datenschutzfragen erst bei der Inbetriebnahme
  stellen.** Beides gehört, wie der Conformance-Abgleich, in die
  Ausschreibung selbst.

## Selbstcheck

1. Ein Hersteller antwortet auf die Frage „Unterstützt Ihr Gerät
   DICOM?" mit „Ja". Welche Lektion dieses Tracks zeigt, warum das
   allein nichts über Kompatibilität aussagt?
2. Formuliere eine konkrete, überprüfbare Ausschreibungsfrage zum
   Thema Migration, die sich direkt aus Lektion 5.3s realem
   Testergebnis ableiten lässt.
3. In welcher Phase eines Beschaffungsprozesses (RFI oder RFP) gehören
   detaillierte, einzeln überprüfbare Anforderungen wie die oben
   gezeigten?
