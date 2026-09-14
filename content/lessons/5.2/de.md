---
title: "Conformance Statements lesen und daraus Aussagen ableiten"
teaser: "Das Dokument, das jeder Hersteller mitliefert und kaum jemand wirklich liest — dabei steht dort, was ein Gerät wirklich kann. Sogar Orthanc, das Archiv dieser Spielwiese, veröffentlicht eines."
objectives:
  - "Kannst die Pflichtabschnitte eines DICOM Conformance Statement benennen (unterstützte SOP-Klassen, Transfer-Syntaxen, Rollen)"
  - "Kannst aus einem konkreten Conformance Statement ableiten, ob zwei Geräte miteinander kommunizieren können"
  - "Kannst benennen, welche Aussagen ein Conformance Statement NICHT trifft (z. B. Performance, tatsächliches Verhalten bei Kantenfällen)"
---

## „Unterstützt DICOM" heißt fast nichts

DICOM ist kein Plug-and-Play-Standard, sondern ein Baukasten: Ein Gerät
kann C-STORE beherrschen und trotzdem kein einziges CT-Bild
entgegennehmen, wenn es die passende SOP-Klasse oder Transfer-Syntax
nicht unterstützt. Das {{term:conformance-statement}} ist das
Dokument, das genau das verbindlich festhält — nicht als Marketing,
sondern nach einer festen Gliederung aus DICOM PS3.2.

## Die Pflichtabschnitte

Nach PS3.2 (Abschnitt B.4/C.4, „Conformance Statement") gliedert sich
jedes vollständige Dokument in:

1. **Einleitung** — Hersteller, Zweck, referenzierte Standards
2. **Implementation Model** — welche Application Entities (AE) das
   Gerät hat und wie sie zusammenspielen
3. **AE Specifications** — für jede AE: Rolle (SCU/SCP), unterstützte
   SOP-Klassen, unterstützte Transfer-Syntaxen **pro** SOP-Klasse,
   Presentation-Context-Auswahlregeln
4. **Networking** — TCP-Parameter, Timeouts, maximale PDU-Länge
5. **Media Interchange** — falls das Gerät Daten auch offline
   austauscht (DICOM-Datenträger)

Abschnitt 3 ist der, den du in der Praxis am häufigsten brauchst: Er
beantwortet direkt die Frage „kann Gerät A mit Gerät B reden".

## Ein echtes Beispiel: Orthancs eigenes Conformance Statement

Orthanc — das Archiv dieser Spielwiese — veröffentlicht sein
Conformance Statement offen im eigenen Quellcode-Repository. Ein
Auszug aus dessen „Store SCP"- und „Transfer Syntaxes"-Abschnitt:

```
---------------------
Store SCP Conformance
---------------------

Orthanc supports the following SOP Classes as an SCP for C-Store:

  CTImageStorage                                                    | 1.2.840.10008.5.1.4.1.1.2
  EnhancedCTImageStorage                                            | 1.2.840.10008.5.1.4.1.1.2.1
  MRImageStorage                                                    | 1.2.840.10008.5.1.4.1.1.4
  ...

-----------------
Transfer Syntaxes
-----------------

  LittleEndianImplicitTransferSyntax                              | 1.2.840.10008.1.2
  LittleEndianExplicitTransferSyntax                              | 1.2.840.10008.1.2.1
  BigEndianExplicitTransferSyntax                                 | 1.2.840.10008.1.2.2
  ...
  RLELosslessTransferSyntax                                       | 1.2.840.10008.1.2.5
  ...

When possible, Orthanc will prefer the LittleEndianExplicitTransferSyntax
transfer syntax (1.2.840.10008.1.2.1).

Orthanc does not support extended negotiation.
```
**Was du daran abliest:** Drei konkrete, überprüfbare Aussagen in
wenigen Zeilen: Orthanc nimmt `CTImageStorage` (das Format aus dieser
Spielwiese) und `EnhancedCTImageStorage` (Lektion 3.4) als SCP an, es
unterstützt sowohl Implicit als auch Explicit VR sowie RLE-Kompression
(Lektion 3.4), und es hat eine erklärte Präferenz für eine bestimmte
Transfer-Syntax, falls mehrere angeboten werden. Der letzte Satz —
„unterstützt keine Extended Negotiation" — ist eine ebenso konkrete
Aussage über eine Grenze.

## Die Behauptung live gegenprüfen

Ein Conformance Statement ist eine Behauptung des Herstellers — die
Aussage oben lässt sich in dieser Spielwiese direkt verifizieren.
`storescu` bietet für `CTImageStorage` standardmäßig mehrere
Presentation Contexts mit unterschiedlichen Transfer-Syntaxen an:

```
$ storescu -d -aec ORTHANC 127.0.0.1 4242 instance-0005.dcm
D:   Context ID:        41 (Proposed)
D:     Abstract Syntax: =CTImageStorage
D:     Proposed SCP/SCU Role: Default
D:     Proposed Transfer Syntax(es):
D:       =LittleEndianExplicit
D:   Context ID:        43 (Proposed)
D:     Abstract Syntax: =CTImageStorage
D:     Proposed SCP/SCU Role: Default
D:     Proposed Transfer Syntax(es):
D:       =BigEndianExplicit
D:       =LittleEndianImplicit
...
D:   Context ID:        41 (Accepted)
D:     Abstract Syntax: =CTImageStorage
D:     Accepted SCP/SCU Role: Default
D:     Accepted Transfer Syntax: =LittleEndianExplicit
...
I: Sending Store Request (MsgID 1, CT)
I: Received Store Response
D: Presentation Context ID       : 41
D: DIMSE Status                  : 0x0000: Success
```
**Was du daran abliest:** `storescu` bietet `CTImageStorage` in zwei
getrennten Presentation Contexts an — einen nur mit
`LittleEndianExplicit`, einen mit `BigEndianExplicit`/
`LittleEndianImplicit`. Orthanc nimmt exakt Context 41 an
(`LittleEndianExplicit`) und beantwortet den späteren C-STORE über
genau diese Context-ID mit `0x0000 (Success)` — die im Conformance
Statement behauptete Präferenz zeigt sich hier nicht nur auf dem
Papier, sondern im tatsächlichen Verhandlungsergebnis der Association.

## Kompatibilität in drei Schritten prüfen

Um zu beurteilen, ob zwei Geräte kommunizieren können, reicht ein
Abgleich von genau drei Listen aus je einem Conformance Statement:

1. **SOP-Klasse gemeinsam?** Ein Gerät muss dieselbe SOP-Klasse als
   SCU anbieten, die das andere als SCP unterstützt (oder umgekehrt).
2. **Rolle passend?** SCU auf der einen Seite braucht ein SCP auf der
   anderen — zwei reine SCUs (oder zwei reine SCPs) für dieselbe
   SOP-Klasse verbinden sich nie sinnvoll.
3. **Mindestens eine gemeinsame Transfer-Syntax?** Selbst bei
   passender SOP-Klasse und Rolle scheitert die Presentation-Context-
   Verhandlung, wenn keine gemeinsame Transfer-Syntax übrig bleibt —
   genau das Muster aus Lektion 1.7.

Fehlt einer der drei Punkte, verbindet sich die Association entweder
gar nicht, oder der betroffene Presentation Context wird beim
Verhandeln abgelehnt, während andere weiterhin funktionieren — wie
oben, wo Context 41 angenommen wurde und andere Contexts für nicht
unterstützte SOP-Klassen implizit unbeachtet blieben.

## Was ein Conformance Statement nicht sagt

Ein grüner Abgleich aller drei Punkte bedeutet nur: **die Verbindung
kann zustande kommen.** Er sagt nichts über:

- **Performance** — wie viele Bilder pro Sekunde ein Gerät tatsächlich
  verarbeitet, steht in keinem Conformance Statement.
- **Verhalten bei kaputten/unvollständigen Daten** — ob ein Gerät ein
  Objekt mit fehlendem Pflichtattribut ablehnt oder stillschweigend
  akzeptiert (Lektion 3.1), ist eine Frage der tatsächlichen
  Implementierung, keine Aussage des Dokuments.
- **Tatsächlich getestete Kombinationen** — ein Gerät kann eine
  SOP-Klasse *unterstützen* und trotzdem nie mit einem bestimmten
  anderen Hersteller *getestet* worden sein.

Ein Conformance Statement ist die Grundlage für die erste
Verträglichkeitsprüfung in einer Ausschreibung (Lektion 5.8) — kein
Ersatz für einen echten Interop-Test.

## Stolperfallen

- **„Unterstützt DICOM" für eine Aussage halten.** Ohne SOP-Klasse,
  Rolle und Transfer-Syntax ist das keine überprüfbare Behauptung.
- **Eine unterstützte SOP-Klasse mit einer getesteten Kombination
  verwechseln.** Das Dokument beschreibt Fähigkeiten, keine
  Testabdeckung.
- **Ein Conformance Statement für eine Leistungsgarantie halten.**
  Es sagt nichts über Geschwindigkeit oder Robustheit.

## Selbstcheck

1. In welchem Abschnitt eines Conformance Statement nach PS3.2 stehen
   die unterstützten SOP-Klassen und Transfer-Syntaxen einer
   Application Entity?
2. Orthancs Conformance Statement listet `LittleEndianExplicit` als
   bevorzugte Transfer-Syntax für Store SCP. Mit welchem realen Befehl
   und welcher Beobachtung in dieser Spielwiese lässt sich das direkt
   überprüfen?
3. Zwei Geräte unterstützen dieselbe SOP-Klasse mit passenden Rollen
   (SCU/SCP), aber keine gemeinsame Transfer-Syntax. Kommt eine
   Verbindung für diese SOP-Klasse zustande?
