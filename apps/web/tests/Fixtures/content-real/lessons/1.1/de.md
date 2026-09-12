---
title: Was DICOM eigentlich ist
teaser: Zwei völlig verschiedene Dinge tragen denselben Namen. Wer das trennt, halbiert seine Fehlersuche.
objectives:
  - DICOM als Dateiformat und als Netzwerkprotokoll auseinanderhalten
  - Erklären, warum Bild und Kontext untrennbar in einem Objekt stecken
  - Bei einem Störungsbild entscheiden, ob es ein Inhalts- oder ein Transportproblem ist
---

## Freitag, 8:40 Uhr

Der Anruf kommt aus der Radiologie: „Die Thorax-Aufnahmen von heute Morgen sind nicht im PACS."

Du schaust auf den Zwischenspeicher der Modalität. Da liegen sie. 312 Dateien, sauber durchnummeriert, Endung `.dcm`. Die Daten existieren also. Sie sind nur nicht da, wo sie hingehören.

Und jetzt kommt die Frage, die über die nächste halbe Stunde entscheidet: Ist das ein Problem mit den Dateien — oder mit dem Weg, den sie nehmen sollten?

Diese Frage lässt sich nur beantworten, wenn man weiß, dass DICOM zwei völlig verschiedene Dinge gleichzeitig ist.

## Die zwei Hälften

Wenn jemand {{term:dicom}} sagt, meint er eines von beidem — und meistens sagt er nicht dazu, welches:

<!-- kein-beispiel -->
```
         ┌────────────────────────┐      ┌────────────────────────┐
         │   DICOM als FORMAT     │      │  DICOM als PROTOKOLL   │
         ├────────────────────────┤      ├────────────────────────┤
         │ Was in der Datei steht │      │ Wie Geräte miteinander │
         │                        │      │ reden                  │
         │ Pixel + Kontext in     │      │ C-ECHO, C-STORE,       │
         │ einem Objekt           │      │ C-FIND, C-MOVE         │
         │                        │      │                        │
         │ Werkzeug: dcmdump      │      │ Werkzeug: Logs,        │
         │                        │      │ Wireshark              │
         └────────────────────────┘      └────────────────────────┘
                     └──────────── dieselben Tags ────────────┘
```

Beide Hälften benutzen dieselben Datenstrukturen. Deshalb tragen sie denselben Namen — und deshalb werden sie ständig verwechselt.

## Hälfte 1: Das Format

Eine DICOM-Datei ist **kein Bild mit Beipackzettel.** Das ist die wichtigste Erkenntnis der ganzen Lektion.

Ein JPEG vom Handy ist ein Bild, und in einer Datenbank irgendwo steht, wer drauf ist. Bei DICOM ist das anders: Der Name des Patienten, das Geburtsdatum, die Fallnummer, die Modalität, die Schichtdicke, der Untersuchungszeitpunkt — all das steht **in derselben Datei** wie die Pixel. Untrennbar. Ein einzelnes Bild heißt deshalb {{term:instance}} — eine Instanz, ein vollständiges Objekt. (Bei neueren Objekttypen kann eine Instanz auch eine ganze Serie als Stapel enthalten; dazu kommt Lektion 3.4.)

Das war eine bewusste Entscheidung, und sie hat zwei Seiten:

**Der Vorteil:** Eine Datei auf einem USB-Stick weiß immer noch, zu wem sie gehört. Kein verlorener Kontext, keine Datenbank nötig, um ein Bild zuzuordnen. Genau das braucht man in der Medizin.

**Der Preis:** Wenn sich der Nachname einer Patientin ändert, steht der alte Name in jeder einzelnen der 312 Dateien. Die Korrektur muss jedes Objekt anfassen. Aus dieser einen Eigenschaft entstehen die hässlichsten PACS-Probleme überhaupt — dazu kommen wir in Track 4 ausführlich.

Der innere Aufbau ist erstaunlich schlicht:

<!-- kein-beispiel -->
```
Byte 0-127    Preamble — Platz für einen Fremdformat-Header,
              in der Praxis fast immer Nullen
Byte 128-131  "DICM"        ← daran erkennt man eine DICOM-Datei
ab Byte 132   File Meta Information (Group 0002)
              → wie ist der Rest kodiert?
              (immer Explicit VR Little Endian —
               deshalb kann jedes Werkzeug den Kopf lesen,
               auch bei unbekannter Kompression)
danach        Der eigentliche Datensatz
```

### Das nachprüfen, statt es zu glauben

Dafür gibt es ein Werkzeug, das genau eine Frage beantwortet:

```
$ dcmftest daten/ct-thorax/0001.dcm
yes: daten/ct-thorax/0001.dcm
```

**Was du daran abliest:** Die Datei erfüllt das DICOM-Dateiformat — Preamble vorhanden, `DICM` an der richtigen Stelle, File Meta Header lesbar. Das ist die erste Frage bei jeder Datei unbekannter Herkunft, und sie kostet eine Sekunde.

Die vier Zeichen selbst kannst du dir ansehen, ganz ohne DICOM-Werkzeug:

```
$ head -c 132 daten/ct-thorax/0001.dcm | tail -c 4
DICM
```

**Was du daran abliest:** Ab Byte 128 stehen wirklich diese vier Zeichen. Davor liegen 128 Byte Preamble, die hier — wie fast immer — nur Nullen enthalten. Deshalb funktioniert die Erkennung unabhängig von Dateiendung, Dateiname und Kompression.

### Und jetzt die Umkehrung, die überrascht

Schneide die ersten 132 Byte weg — also Preamble und `DICM`:

```
$ tail -c +133 daten/ct-thorax/0001.dcm > /tmp/ohne-header.dcm
$ dcmftest /tmp/ohne-header.dcm
no: /tmp/ohne-header.dcm
```

**Was du daran abliest:** Für `dcmftest` ist das keine DICOM-Datei mehr. Die Prüfung hängt ausschließlich an diesen Bytes.

Die Daten sind aber vollständig da:

```
$ dcmdump -f +P Modality +P PatientName /tmp/ohne-header.dcm
(0008,0060) CS [CT]                                     #   2, 1 Modality
(0010,0010) PN [MUSTER^ERIKA]                           #  12, 1 PatientName
```

**Was du daran abliest:** Mit der Option, den Inhalt als reinen Datensatz zu lesen (`-f`), kommt alles zum Vorschein. Der Datensatz war nie beschädigt — es fehlte nur der Dateikopf. **Genau so sieht ein DICOM-Objekt aus, wenn es über das Netz geht:** Preamble, `DICM` und Group 0002 existieren dort nicht, weil diese Angaben in den Verbindungsparametern stecken. Wer einen Netzwerkmitschnitt öffnet und nach `DICM` sucht, findet nichts — und hält eine völlig gesunde Übertragung für kaputt.

## Hälfte 2: Das Protokoll

Jetzt der Teil, den die meisten unterschätzen: Zwei DICOM-Geräte tauschen **keine Dateien** aus.

Es gibt kein „Datei kopieren". Es gibt Dienste. Ein CT sagt nicht „hier ist eine Datei", es sagt: *Ich möchte ein Objekt vom Typ CT-Bild ablegen.* Dieser Dienst heißt {{term:c-store}}. Fragen nach vorhandenen Untersuchungen heißen C-FIND, das Anfordern von Bildern C-MOVE. Die Familie dieser Dienste heißt {{term:dimse}}.

Und bevor überhaupt etwas passiert, handeln beide Seiten aus, worüber sie reden können — welche Objekttypen, in welcher Kodierung. Dieses Aushandeln heißt Association und ist der Ort, an dem in der Praxis die meisten Verbindungen scheitern. Lektion 1.8 nimmt das auseinander.

Zwei Ergänzungen der Vollständigkeit halber: Es gibt daneben den **Medienweg** — eine CD oder ein Stick mit DICOMDIR, der Normalfall bei Fremdbefunden — und **DICOMweb**, dieselben Dienste über HTTP. Beide führen die Objekte aber ebenfalls über einen definierten Import, nie über das blanke Dateisystem des Archivs.

Und genau deshalb funktioniert das, was jeder Neuling einmal versucht, nie: **Dateien ins Archivverzeichnis kopieren.** Das Archiv bekommt davon nichts mit. Es indexiert sie nicht, es prüft sie nicht, es benachrichtigt niemanden. Die Bilder sind physisch da und für das System nicht existent.

## Im Alltag heißt das

Bei jedem Ticket stellst du ab jetzt zuerst diese eine Frage:

| Symptom | Hälfte | Erste Werkzeuge |
|---|---|---|
| Bilder kommen gar nicht an | Protokoll | Archiv-Log, `echoscu`, Firewall |
| Bilder kommen an, sehen aber falsch aus | Format | `dcmdump`, Tag-Vergleich |
| Bilder landen beim falschen Patienten | beides | erst Tags prüfen, dann Coercion-Regeln |
| Nur manche Serien kommen an | Protokoll | Presentation Context, SOP Classes |
| Datei lässt sich nirgends öffnen | Format | `dcmftest`, dann `dcmdump -f` |

Diese Sortierung spart mehr Zeit als jedes andere Wissen in diesem Track. Der häufigste Zeitfresser bei Störungen ist, stundenlang im Netzwerk zu suchen, während das Problem in einem Tag steckt — oder umgekehrt.

Zurück zum Freitagmorgen: Die Dateien liegen auf der Modalität, im Archiv fehlen sie. Ein `dcmftest` auf eine davon bestätigt in einer Sekunde, dass die Format-Hälfte in Ordnung ist. Bleibt die Transport-Hälfte. Du musst die Datei gar nicht weiter öffnen.

> ### Stolperfallen
>
> **„Der Viewer zeigt es an, also ist die Datei in Ordnung."**
> Viewer sind extrem tolerant und ergänzen fehlende Angaben stillschweigend. Archive sind es nicht. Eine Datei, die im Viewer perfekt aussieht, kann vom Archiv zu Recht abgelehnt werden.
>
> **„DICOM-Dateien enden auf `.dcm`."**
> Es gibt keine Pflicht zur Dateiendung. Viele Archive speichern komplett ohne Endung. `dcmftest` beantwortet die Frage zuverlässig — ein `no` beweist aber nicht das Gegenteil: Datensätze ohne File-Meta-Header kommen in Altbeständen und in manchen Archiv-Speichern durchaus vor, wie oben gezeigt.
>
> **„DICOM ist ein Bildformat."**
> Nur zum Teil. Ein Structured Report enthält überhaupt keine Pixel, sondern Messwerte und Befundtexte. Auch er ist ein vollwertiges DICOM-Objekt.
>
> **„Ich kopiere die Dateien einfach rüber."**
> Siehe oben. Es gibt Wege, Dateien nachträglich einzuspielen — aber immer über den Dienst, nie über das Dateisystem.

## Dein erstes Lab

Im Lab **First Contact** bekommst du eine einzelne Datei ohne Endung und ohne Kontext. Deine Aufgaben: Nachweisen, dass es sich um ein DICOM-Objekt handelt. Herausfinden, von welchem Gerätetyp sie stammt. Und den Namen der Untersuchung nennen.

Du brauchst dafür genau die zwei Werkzeuge aus dieser Lektion.

## Selbstcheck

<details>
<summary>Ein Kollege sagt: „Das PACS nimmt die DICOM nicht an." Welche Hälfte meint er vermutlich — und was fragst du zurück?</summary>

Vermutlich die Protokoll-Hälfte: Es kommt nichts an. Die richtige Rückfrage ist trotzdem, ob im Archiv-Log überhaupt eine Verbindung auftaucht. Steht dort nichts, ist es Transport. Steht dort eine abgelehnte Übertragung, kann es sehr wohl am Inhalt liegen — etwa an einem Objekttyp, den das Archiv nicht annimmt.
</details>

<details>
<summary>`dcmftest` sagt „no". Ist die Datei kaputt?</summary>

Nicht zwangsläufig. Es fehlt der File-Meta-Header — Preamble, `DICM` und Group 0002. Der eigentliche Datensatz kann völlig intakt sein; mit `dcmdump -f` liest man ihn als reinen Datensatz. Solche Dateien entstehen, wenn jemand einen Netzwerkmitschnitt speichert oder aus einem Archivspeicher direkt kopiert.
</details>

<details>
<summary>Warum kann man ein PACS nicht befüllen, indem man Dateien in sein Speicherverzeichnis kopiert?</summary>

Weil das Archiv Objekte über einen Dienst entgegennimmt und dabei indexiert, prüft und verbucht. Eine Datei, die auf dem Dateisystem auftaucht, durchläuft nichts davon. Sie existiert für das System nicht — und wird bei der nächsten Konsistenzprüfung im schlimmsten Fall entfernt.
</details>

## Quiz

*Wissenskarten — kommen später zur Wiederholung zurück.*

**q1 — Wo steht der Name des Patienten bei einer DICOM-Aufnahme?**
1. In der Datenbank des Archivs
2. In einer Begleitdatei neben dem Bild
3. In derselben Datei wie die Pixeldaten
4. Im Dateinamen

**q2 — Welche Aussagen stimmen?** *(Mehrfachauswahl)*
1. DICOM ist gleichzeitig Dateiformat und Netzwerkprotokoll
2. Jede DICOM-Datei muss auf `.dcm` enden
3. Ein Structured Report enthält keine Pixeldaten
4. Ein Archiv indexiert Dateien, die man in sein Verzeichnis kopiert

**q3 — Welche vier Zeichen stehen ab Byte 128 einer DICOM-Datei?** *(Freitext)*

---

**Als Nächstes:** [1.2 — Patient, Study, Series, Instance](../1.2/) ordnet die Objekte, die du gerade kennengelernt hast, in die Hierarchie ein, nach der jedes Archiv der Welt aufgebaut ist.
