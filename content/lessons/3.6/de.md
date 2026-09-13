---
title: "Specific Character Set — Umlaute und was schiefgeht"
teaser: '"Müller" lässt sich in einem Werkzeug nicht mehr lesen — nicht weil DICOM keine Umlaute kann, sondern weil ein Attribut fehlt, das die Kodierung erst festlegt.'
objectives:
  - SpecificCharacterSet als Attribut erklären, das die Kodierung aller Textwerte im Objekt festlegt
  - Ein Encoding-Problem von einem echten Dateninhaltsfehler unterscheiden
  - Kaputt dargestellte Umlaute auf ein fehlendes oder falsches SpecificCharacterSet zurückführen
---

## „dcm2json bricht ab — die Datei ist doch nicht kaputt"

Ein Objekt mit einem Patientennamen mit Umlaut lässt sich mit `dcmdump`
ansehen, sendet sich anstandslos ins Archiv — und `dcm2json` bricht mit
einer Fehlermeldung ab. Keine der beiden Beobachtungen widerspricht der
anderen: Die Datei ist nicht beschädigt, aber ein Attribut fehlt, das
manche Werkzeuge zwingend brauchen, um Textwerte korrekt zu lesen.

## SpecificCharacterSet legt die Kodierung fest

{{term:specific-character-set}} sagt, in welcher Zeichenkodierung
alle Textwerte des Objekts stehen. Fehlt das Attribut, gilt laut
Standard der Default: reines 7-Bit-ASCII (`ISO_IR 6`) — keine Umlaute,
keine Sonderzeichen. Für deutsche Namen kommen typischerweise
`ISO_IR 100` (Latin-1) oder `ISO_IR 192` (UTF-8) infrage.

## Derselbe Name, einmal mit, einmal ohne Deklaration

```
$ dcmdump +P SpecificCharacterSet +P PatientName umlaut-correct.dcm
(0008,0005) CS [ISO_IR 100]                             #  10, 1 SpecificCharacterSet
(0010,0010) PN [Müller^Jürgen]                          #  14, 1 PatientName
```
**Was du daran abliest:** Mit gesetztem `SpecificCharacterSet` zeigt
`dcmdump` den Namen korrekt an — Latin-1 kodiert die Umlaute als
Ein-Byte-Werte (`ü` = `0xFC`), und das Attribut sagt jedem Werkzeug,
dass genau diese Kodierung gilt.

Jetzt dieselbe Datei ohne das Attribut — mit identischen Rohbytes für
den Namen, real per Hex-Vergleich bestätigt:

```
$ dcm2json umlaut-correct.dcm | grep -A2 00100010
  "00100010": {
    "vr": "PN",
    "Value": [
      {
        "Alphabetic": "Müller^Jürgen"
      }
$ dcm2json umlaut-broken.dcm
F: dataset contains extended characters but no SpecificCharacterSet (0008,0005)
```
**Was du daran abliest:** `dcm2json` verweigert die Umwandlung der
zweiten Datei komplett, mit einer klaren, konkreten Fehlermeldung —
kein Absturz, kein stilles Vertauschen von Zeichen, sondern ein
Werkzeug, das zu Recht sagt: „Ich finde Bytes über 127, aber niemand
hat mir gesagt, wie ich sie lesen soll." Das ist kein
Dateninhaltsfehler — die Bytes für „ü" sind in beiden Dateien
identisch. Es fehlt nur die Deklaration.

## Dasselbe Objekt, drei Werkzeuge, drei Reaktionen

```
$ storescu -v -aec ORTHANC 127.0.0.1 4242 umlaut-correct.dcm
I: Received Store Response (Success)
$ storescu -v -aec ORTHANC 127.0.0.1 4242 umlaut-broken.dcm
I: Received Store Response (Success)
```
**Was du daran abliest:** Orthanc nimmt beide Objekte anstandslos an —
für die reine Speicherung ist `SpecificCharacterSet` kein
Pflichtattribut, dasselbe großzügige Muster wie bei den
Type-1-Attributen aus Lektion 3.1/3.2.

```
$ curl -s http://127.0.0.1:8042/dicom-web/studies | grep -A2 Alphabetic
    "Alphabetic" : "Müller^Jürgen"
    "Alphabetic" : "Müller^Jürgen"
```
**Was du daran abliest:** Orthancs eigene REST-API zeigt für **beide**
Objekte denselben, korrekt dekodierten Namen — auch für das, dem
`SpecificCharacterSet` fehlte. Orthanc rät hier offenbar Latin-1 als
Fallback und gibt in seiner eigenen JSON-Antwort konsequent `ISO_IR
192` (UTF-8) als Kodierung an, unabhängig davon, was die Ursprungsdatei
deklarierte. Drei echte, unterschiedliche Reaktionen auf dieselbe
Lücke: `dcm2json` verweigert die Arbeit, Orthancs Speicherung ignoriert
sie, Orthancs REST-API rät und korrigiert still. Kein Werkzeug ist hier
„falsch" — jedes trifft eine eigene, in sich konsistente Entscheidung.

## Im Alltag heißt das

| Beobachtung | Wahrscheinliche Ursache |
|---|---|
| Ein Werkzeug verweigert die Verarbeitung eines Objekts mit Namen wie „Müller" | `SpecificCharacterSet` fehlt oder passt nicht zu den tatsächlichen Bytes |
| Ein Name sieht in Tool A korrekt, in Tool B falsch aus | Unterschiedliche Fallback-Strategien der Werkzeuge bei fehlender Deklaration |
| Archiv nimmt ein Objekt mit Umlaut anstandslos an | Speicherung prüft `SpecificCharacterSet` meist nicht — Downstream-Verarbeitung (JSON-Export, Reports) tut es oft doch |

> ### Stolperfallen
>
> **„Zeichensalat heißt Tippfehler."**
> Nicht zwangsläufig. Die Rohbytes können korrekt sein — es fehlt nur
> die Angabe, wie sie zu lesen sind. `SpecificCharacterSet` prüfen,
> bevor man den Namen für falsch eingegeben hält.
>
> **„Wenn es hier richtig aussieht, ist die Datei in Ordnung."**
> Verschiedene Werkzeuge raten bei fehlender Deklaration unterschiedlich
> — ein korrekt angezeigter Name in einem Tool beweist nicht, dass ein
> anderes Tool (z. B. ein strikter Report-Export) dieselbe Datei
> ebenso verarbeitet.

## Selbstcheck

1. Ein Objekt hat keine `SpecificCharacterSet`-Angabe. Welche Kodierung
   gilt laut Standard?
2. `dcm2json` verweigert die Umwandlung eines Objekts mit der Meldung
   „dataset contains extended characters but no SpecificCharacterSet".
   Ist die Datei beschädigt?
3. Ein Patientenname erscheint in einem Werkzeug korrekt, in einem
   anderen falsch. Beweist das, dass eines der beiden Werkzeuge einen
   Fehler hat?
