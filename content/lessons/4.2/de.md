---
title: „Verbindung steht, aber nichts kommt an"
teaser: Das C-ECHO ist grün, die Bilder bleiben trotzdem liegen — die Entscheidung fällt im Presentation Context.
objectives:
  - Ein Association-Log bis auf die Ebene der Presentation Contexts lesen
  - Eine abgelehnte Association von einem abgelehnten Presentation Context unterscheiden
  - Erkennen, wann schlicht keine gemeinsame Transfer Syntax vorliegt
---

## „Der Scanner meldet Erfolg, das Archiv zeigt nichts"

Ticket aus der Anmeldung: Ein CT-Gerät zeigt nach dem Senden keinen
Fehler an — trotzdem taucht die Study nicht im Archiv auf. `echoscu`
gegen dasselbe Ziel läuft grün. AE Titles, Host und Port stimmen. Und
trotzdem: nichts kommt an.

Lektion 1.8 hat erklärt, was beim Verbindungsaufbau ausgehandelt wird.
Diese Lektion liest genau diese Aushandlung diagnostisch — dort, wo sie
tatsächlich scheitert, ohne dass die Association selbst abgelehnt wird.

## Zwei Dinge werden pro Objekttyp ausgehandelt

Ein Presentation Context bündelt zwei Angebote gleichzeitig:

| | |
|---|---|
| **Abstract Syntax** | Welcher Objekttyp — der SOP Class UID, z. B. CT Image Storage |
| **Transfer Syntax** | In welcher Kodierung — unkomprimiert, welches Kompressionsverfahren |

Eine Association kann vollständig zustande kommen, während einzelne
Presentation Contexts darin abgelehnt werden — pro Objekttyp einzeln.
Das ist der Kern des Fehlerbilds: `echoscu` prüft nur, ob überhaupt eine
Association zustande kommt (dafür reicht die Verification SOP Class,
real `1.2.840.10008.1.1`, die praktisch jede Gegenstelle akzeptiert).
Ob der eigentliche Bildtyp durchgeht, ist eine davon unabhängige,
zweite Aushandlung.

## In der Spielwiese nachsehen, was tatsächlich ausgehandelt wird

```
$ storescu -d -cx -aec ORTHANC 127.0.0.1 4242 instance-0001.dcm
D: Presentation Context:
D:   Context ID:        1 (Proposed)
D:     Abstract Syntax: =CT Image Storage
D:     Proposed SCP/SCU Role: Default
D:     Proposed Transfer Syntax:
D:       =Explicit VR Little Endian
D:   Context ID:        1 (Accepted)
D:     Abstract Syntax: =CT Image Storage
D:     Accepted SCP/SCU Role: Default
D:     Accepted Transfer Syntax: =Explicit VR Little Endian
I: Association Accepted
I: Sending Store Request: MsgID 1, (CT)
I: Received Store Response (Status: 0x0000 - Success)
```
**Was du daran abliest:** `-cx` beschränkt den Vorschlag auf genau den
Objekttyp der zu sendenden Datei, statt pauschal alles anzubieten, was
der Sender kennt — dadurch stehen Proposed und Accepted nebeneinander
sichtbar für denselben Context. Hier stimmen beide Seiten überein:
CT Image Storage, Explicit VR Little Endian, angenommen. Genau diesen
Vergleich liest man bei einer Störung — nur dass dort Proposed und
Accepted eben nicht übereinstimmen.

```
$ dcmdump +P TransferSyntaxUID +P SOPClassUID instance-0001.dcm
(0002,0010) UI =LittleEndianExplicit                    #  20, 1 TransferSyntaxUID
(0008,0016) UI =CTImageStorage                          #  26, 1 SOPClassUID
```
**Was du daran abliest:** Dieselbe Information steht auch direkt in der
Datei, ohne zu senden — `TransferSyntaxUID` im File Meta Header
(Gruppe 0002, siehe Lektion 1.7), `SOPClassUID` im Datensatz selbst.
Bevor überhaupt gesendet wird, lässt sich so schon prüfen, was die
Datei anbieten wird.

## Wenn die Aushandlung scheitert

<!-- kein-beispiel -->
```
Das lässt sich in der aktuellen Spielwiese nicht live erzeugen: Orthanc
laeuft hier (containers/orthanc/orthanc.json, orthancteam/orthanc-Image)
mit breiter Codec-Unterstuetzung und nimmt in diesem Aufbau jede getestete
Kombination aus SOP Class und Transfer Syntax an -- getestet sowohl mit
unkomprimiertem Explicit VR Little Endian als auch mit JPEG Lossless
(1.2.840.10008.1.2.4.70): beide wurden vom selben Orthanc-Server
akzeptiert. Dieselbe Grosszuegigkeit, die in Lektion 4.1 schon
AE-Title-Ablehnungen unmoeglich gemacht hat (siehe dort), betrifft hier
die Presentation-Context-Aushandlung.

Real, im Standard definiert, sind trotzdem zwei unterschiedliche
Ablehnungsgruende fuer genau diesen Fall (PS3.8 Table 9-18,
Presentation-Context-Result-Feld):

  Result 3 -- abstract-syntax-not-supported
    Das Archiv kennt den angebotenen Objekttyp (SOP Class) nicht.
  Result 4 -- transfer-syntaxes-not-supported
    Das Archiv kennt den Objekttyp, aber keine der angebotenen
    Kodierungen.

Genau das durchspielen -- und unterscheiden -- ist die Aufgabe der Node
"Verbindung ohne Bild": siehe Lab unten.
```

## Im Alltag

| Symptom | Ebene |
|---|---|
| `echoscu` schlägt schon fehl | Association (Host/Port/AE Title, Lektion 4.1) |
| `echoscu` grün, Sendeauftrag bricht sofort mit Ablehnungsgrund ab | Presentation Context (diese Lektion) |
| Sendeauftrag meldet Erfolg, Objekt fehlt trotzdem im Archiv | eine andere Ursache — z. B. Coercion (Lektion 4.6) oder Größenlimit (Lektion 4.3) |

Die Reihenfolge lohnt sich, weil jede Zeile denselben ersten Befehl
braucht (`echoscu`), aber unterschiedliche nächste Schritte: Zeile eins
führt zurück zu Host/Port/AE-Title, Zeile zwei zu genau der Aushandlung,
die diese Lektion beschreibt.

## Stolperfallen

- **„C-ECHO grün heißt sendefähig."** C-ECHO prüft nur die Verification
  SOP Class — sie sagt nichts darüber, ob der eigentliche Bildtyp
  angenommen wird.
- **Kompression beim Empfänger vergessen.** Ein Gerät, das nach einem
  Update plötzlich komprimiert (Lektion 1.7), kann an genau dieser
  Stelle scheitern, obwohl vorher monatelang alles funktioniert hat.
- **Result 3 und Result 4 verwechseln.** Beide brechen den
  Sendeversuch ab, aber der eine betrifft den Objekttyp, der andere die
  Kodierung — die Fehlermeldung selbst nennt den Result-Wert, ein Raten
  ist nicht nötig.

## Selbstcheck

1. `echoscu` läuft grün, ein Sendeauftrag scheitert sofort. Ist die
   Association abgelehnt worden — und wenn nicht, was dann?
2. Ein Ablehnungsgrund nennt `Presentation Context Result: 3`. Betrifft
   das den Objekttyp oder die Kodierung?
3. Warum lässt sich diese Ablehnung nicht in der Spielwiese, aber in der
   Node „Verbindung ohne Bild" nachvollziehen?

*Wissenskarten (spaced repetition) folgen, sobald `meta.yml` ein
strukturiertes `quiz`-Feld mit Antwortschlüssel bekommt — siehe
`docs/content-todo.md`, Abschnitt P3.*

Werkzeuglage geprüft am: 2026-09-13
