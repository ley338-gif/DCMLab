---
title: „Association rejected" — die Verbindung kommt gar nicht erst zustande
teaser: Eine Verbindung, die gar nicht erst zustande kommt, hat mehrere mögliche Ursachen zwischen Netzwerk und DICOM — dieses Kapitel zeigt, wie du sie sauber auseinanderhältst.
objectives:
  - Netzwerk-/TCP-Fehler eindeutig von einer DICOM-Association-Ablehnung unterscheiden
  - Eine Association-Rejection (Result/Source/Reason) von einem Association-Abort unterscheiden, unabhängig davon, wie weit die Verhandlung zuvor gekommen war
  - Called und Calling AE Title anhand des Ablehnungsgrunds richtig zuordnen, ohne daraus vorschnell die zu ändernde Konfigurationsseite abzuleiten
  - Die Aussagekraft eines erfolgreichen C-ECHO korrekt begrenzen
---

## Ein Ticket, das mehr als eine Ursache haben kann

Die Anmeldung meldet: „CT_RAUM3 sendet seit heute Morgen nicht mehr ins Archiv." Mehr steht nicht im Ticket — kein Fehlercode, keine Uhrzeit, keine Vorgeschichte.

Genau dieser eine Satz passt auf mehrere völlig verschiedene technische Ursachen, die sich am Gerät identisch anfühlen: ein rotes Symbol, sonst nichts. Bevor du irgendetwas änderst, brauchst du eine Diagnose — keine Vermutung.

## Zwei ganz verschiedene Arten von "es geht nicht"

„Es geht nicht" ist noch keine Diagnose. Die entscheidende Vorfrage lautet: Kam überhaupt eine Antwort von der Gegenstelle — und wenn ja, wie weit kam die Verhandlung, bevor es schiefging? Fünf Stufen sind dabei zu unterscheiden, mit fünf verschiedenen Fehlerbildern:

1. **Netzweg/Host-Erreichbarkeit** — kommt überhaupt ein Paket beim Zielrechner an? Vertieft in Lektion 4.4.
2. **TCP-Verbindung** — nimmt auf dem Zielport überhaupt jemand die Verbindung an? *Diese Lektion.*
3. **DICOM-Association** — akzeptiert oder lehnt die Gegenstelle die Verhandlung ab, bevor auch nur ein Bild unterwegs ist? *Diese Lektion.*
4. **Presentation Context** — die Association steht, aber ein konkreter Objekttyp oder eine Kodierung wird abgelehnt. Lektion 4.2.
5. **Laufender DIMSE-Auftrag** — die Verhandlung ist vollständig durch, und trotzdem geht danach etwas schief. Lektion 4.3 ff.

Diese Lektion behandelt die Stufen 2 und 3: die Fälle, in denen die Verbindung selbst nicht zustande kommt — sei es, weil TCP nicht steht, oder weil DICOM die Association ausdrücklich ablehnt. Sobald die Association tatsächlich akzeptiert wird, bist du nicht mehr hier, sondern bei Lektion 4.2.

## Vom Netzweg zum DIMSE-Auftrag: eine Kette, kein Rätsel

<!-- kein-beispiel -->
```
Netzweg/Host   ->  TCP-Verbindung  ->  Association   ->  Presentation Context  ->  DIMSE-Auftrag
   (4.4)             (4.1)              (4.1)              (4.2)                    (4.3 ff.)
   ping               echoscu -v         echoscu -v         storescu -d -cx          dcmdump / Log
```

**Was du daran abliest:** Jede Stufe hat ihr eigenes Werkzeug und ihre eigene Fehlermeldung — wer eine Stufe überspringt, rät bei der nächsten. Dieselbe Kette ist der rote Faden des gesamten Tracks; Lektion 4.10 macht sie am Ende zum durchgehenden Vorgehen für Fehlerbilder, die in keine der Einzellektionen passen.

## TCP-Fehlerbilder richtig lesen

Bevor DICOM überhaupt mitredet, entscheidet die reine TCP-Verbindung, ob überhaupt etwas möglich ist. Der Erfolgsfall zur Orientierung — aus 1.5/1.6 bekannt, hier nur als Vergleichspunkt:

```text
$ echoscu -v -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242
I: Requesting Association
I: Association Accepted
I: Sending Echo Request: MsgID 1
I: Received Echo Response (Status: 0x0000 - Success)
I: Releasing Association
```

**Was du daran abliest:** Fünf Zeilen zwischen Anfrage und Freigabe, jede DICOM-Verhandlung lief durch. Alles, was jetzt folgt, weicht spätestens ab der zweiten Zeile davon ab.

```text
$ echoscu -v -aet MEINE-WS -aec ORTHANC 127.0.0.1 9999
I: Requesting Association
E: Association request failed: unable to connect to remote
E: TCP Initialisation Error: [Errno 111] Connection refused
I: Aborting Association
```

**Was du daran abliest:** „Connection refused" heißt: Der TCP-Verbindungsversuch wurde aktiv zurückgewiesen (ein TCP-RST) — häufig, weil auf dem angesprochenen Port kein Dienst lauscht. Es kann aber ebenso ein aktives Firewall- oder Netzwerk-`REJECT` irgendwo auf dem Weg sein, nicht zwingend vom Zielhost selbst. „Connection refused" beweist deshalb nicht pauschal, dass der Zielhost geantwortet hat oder überhaupt sicher erreichbar ist — nur, dass irgendetwas auf dem Weg dorthin aktiv abgelehnt hat, nicht zwingend „hier läuft überhaupt kein Dienst". Ein reiner Timeout (gar keine Antwort statt einer sofortigen Ablehnung) sieht dagegen komplett anders aus und deutet eher auf ein still verwerfendes `DROP` oder eine Route ins Leere — ein eigenes Fehlerbild mit eigener Ursache, siehe Lektion 4.4.

```text
$ echoscu -v -aet MEINE-WS -aec ORTHANC 10.255.255.1 4242
I: Requesting Association
E: Association request failed: unable to connect to remote
E: TCP Initialisation Error: [Errno 101] Network is unreachable
I: Aborting Association
```

**Was du daran abliest:** Eine andere Fehlermeldung für ein anderes Problem — aus Sicht des eigenen Netzwerkstacks gibt es keine nutzbare Route zum Zielnetz, die Anfrage verlässt den lokalen Host gar nicht erst in Richtung des Ziels. Für den hier konkret nachgebauten, isolierten Docker-Aufbau lässt sich das genau benennen: Das Egress-lose interne Docker-Netz hatte schlicht keine Route nach außen — dieselbe Absicherung, die die echte Spielwiese laut ADR 0008 bewusst einsetzt. Vier Meldungen für vier unterscheidbare Ursachen, die sich nicht gegenseitig ersetzen: `Connection refused` (irgendetwas auf dem Weg lehnt aktiv ab — Zielhost oder Firewall, nicht zwingend „kein Dienst"), `Network unreachable` (aus Sicht des eigenen Netzwerkstacks keine Route zum Zielnetz), `Host unreachable` (typischerweise: eine Route zum Zielnetz existiert, aber der konkrete Zielhost antwortet auf dieser Route nicht — hier nicht live geprüft) und ein reiner Timeout (gar keine Antwort). Die ersten beiden lassen sich hier live zeigen; `Host unreachable` und der reine Timeout brauchen eine andere Netzwerktopologie und sind Thema von Lektion 4.4.

## Association Request, Accept, Reject und Abort

Stimmen Host und Port, verhandelt DICOM als Nächstes die {{term:association}} selbst. Der Standard unterscheidet dabei sauber zwischen vier Ereignissen (PS3.8, Abschnitt 9.3):

- **A-ASSOCIATE Request** — der Anrufer schlägt eine Verbindung vor.
- **A-ASSOCIATE Accept** — die Gegenstelle nimmt an; die Association steht.
- **A-ASSOCIATE Reject (A-ASSOCIATE-RJ)** — die Gegenstelle lehnt ab, *bevor* die Association zustande gekommen ist.
- **A-ABORT** (bzw. providerseitig **A-P-ABORT**) — eine bereits stehende oder in Verhandlung befindliche Association wird abgebrochen.

Der Unterschied ist mehr als Wortklauberei, auch wenn beide Meldungsarten im Alltag gleich klingen können: „es kam keine Verbindung zustande":

- **A-ASSOCIATE-RJ** ist die bestätigte, ausgehandelte Ablehnung des Verbindungsaufbaus selbst — mit den drei Angaben Result/Source/Reason-Diagnostic aus der Tabelle unten.
- **A-ABORT** (userseitig) bzw. **A-P-ABORT** (providerseitig) ist dagegen ein abrupter Abbruch, ausgelöst entweder vom Service-User selbst oder vom Service-Provider (PS3.8 Tabelle 9-26, Abschnitt 9.3.8: providerseitig z. B. `unrecognized-PDU` oder `unexpected-PDU`).
- Ein Abort kann **während der laufenden Verhandlung** auftreten oder **nachdem die Association bereits vollständig aufgebaut war** — beides ist möglich, der Standard unterscheidet im Reason/Diagnostic-Feld nicht danach, wie weit die Verhandlung zuvor gekommen war.
- Aus der bloßen Meldung „Abort" folgt deshalb **nicht automatisch**, ob überhaupt schon eine Association stand oder der Abbruch noch während der Verhandlung kam — das lässt sich nur am umgebenden Log bzw. Zeitstempel ablesen, nicht am PDU-Typ allein.

Für eine A-ASSOCIATE-RJ definiert der Standard drei Angaben (PS3.8 Tabelle 9-21, Abschnitt 9.3.4):

| Feld | Bedeutung |
|---|---|
| Result | `rejected-permanent` oder `rejected-transient` — bei „permanent" ist ein erneuter Versuch mit denselben Werten sinnlos |
| Source | Wer ablehnt: `DICOM UL service-user` (die Gegenstelle selbst, aus fachlichen Gründen), `DICOM UL service-provider (ACSE-related)` oder `DICOM UL service-provider (Presentation-related)` — alle drei sind Protokollebene, keine Netzwerkebene |
| Reason/Diagnostic | Der konkrete Grund; `calling-AE-title-not-recognized` und `called-AE-title-not-recognized` gelten bei Source `DICOM UL service-user` |

Wichtig zur Abgrenzung nach oben: Ein A-ASSOCIATE-RJ ist immer eine echte DICOM-PDU. Die TCP-/Netzwerkfehler aus dem vorigen Abschnitt (`Connection refused`, `Network is unreachable`) erzeugen **keine** A-ASSOCIATE-RJ — dafür müsste überhaupt erst eine TCP-Verbindung stehen, auf der sich DICOM-PDUs austauschen lassen. Beide Fehlerklassen gehören zu dieser Lektion, sind aber auf Protokollebene strikt getrennt.

Zwei der `DICOM UL service-user`-Gründe stehen im Zentrum dieser Lektion und werden häufig verwechselt:

- **`Called AE Title Not Recognized`** — die Gegenstelle wurde unter einem Namen angesprochen, den sie nicht als ihren eigenen erkennt.
- **`Calling AE Title Not Recognized`** — die Gegenstelle kennt zwar ihren eigenen Namen, aber nicht den des Anrufers.

*Called* betrifft also das Ziel, *Calling* den Absender — wer das verwechselt, sucht am falschen Gerät.

## Called und Calling: zwei Gegenfälle

Beide Meldungen beschreiben eine fehlerhafte *Zuordnung* — sie schreiben für sich genommen noch nicht vor, auf welcher Seite ein Wert geändert werden muss. Das hängt davon ab, welcher Name zwischen beiden Systemen eigentlich vereinbart war: Vielleicht ist die Gegenstelle korrekt konfiguriert und die Modalität trägt den falschen Namen ein; vielleicht ist es umgekehrt. Der Ablehnungsgrund grenzt nur ein, *welcher* der beiden Namen betroffen ist — nicht, *wessen* Konfigurationseintrag vom vereinbarten Soll-Zustand abweicht.

AE Titles selbst sind zeichengenau und case-sensitive: `PACS-ARCHIV` und `PACS_ARCHIV` sind zwei vollständig verschiedene Namen, ebenso `Archiv` und `ARCHIV`. Eine Ausnahme kennt der Standard nur für führende und angehängte Leerzeichen; ein Leerzeichen *mitten* im Namen ist dagegen ein Zeichen wie jedes andere und macht daraus einen anderen Namen — die vollständige Herleitung dazu steht in Lektion 1.5.

<!-- kein-beispiel -->
```
Beide Ablehnungen lassen sich in der aktuellen Spielwiese nicht live
erzeugen: Orthanc laeuft hier bewusst mit DicomAlwaysAllowEcho und den
verwandten DicomAlwaysAllow{Find,FindWorklist,Move,Get,Store}-Optionen
(containers/orthanc/orthanc.json, ADR 0008) und nimmt deshalb jeden
Called/Calling AE Title fuer jeden Dienst an -- eine bewusste
P7-Entscheidung, damit Lernende nicht an einer Modalitaetenliste
scheitern, bevor sie ueberhaupt etwas ausprobiert haben. Erneut gegen
einen frisch gestarteten Sandbox-Orthanc geprueft (2026-09-21): selbst
`echoscu -aet BELIEBIGER-NAME -aec VOELLIG-FALSCH 127.0.0.1 4242` wird
weiterhin klaglos akzeptiert.
```

Genau diese beiden Ablehnungen zu erleben — und zu beheben — ist die Aufgabe der Nodes „Silent CT" (Called-Seite) und „Wrong Door" (Calling-Seite). Beide laufen in einer eigenen, simulierten Umgebung, nicht in der Spielwiese dieser Lektion (Lektionen üben in der Spielwiese, Nodes im fiktiven Klinikum) — und melden dort tatsächlich genau die PS3.8-Reject-Reason-Texte von oben. Siehe Lab unten.

## Kleine Diagnosematrix

| Beobachtung | Bewiesen | Nicht bewiesen | Nächster Schritt |
|---|---|---|---|
| `ping` zum Ziel antwortet | Host im Netz erreichbar | nichts über TCP-Port oder DICOM | `echoscu` gegen den Zielport probieren |
| `echoscu` liefert `Connection refused` | Der TCP-Verbindungsversuch wurde aktiv zurückgewiesen (RST) — durch den Zielhost selbst oder ein Gerät auf dem Weg dorthin | dass der Zielhost selbst geantwortet hat, dass generell kein Dienst existiert, oder dass der Host sicher erreichbar ist | Port und Erreichbarkeit mit dem Betreiber der Gegenstelle abgleichen |
| `echoscu` liefert `Network is unreachable` | Aus Sicht des eigenen Netzwerkstacks gibt es keine nutzbare Route zum Zielnetz | irgendetwas über den Zielhost selbst, falls eine Route existierte | Routing/Firewall zwischen den Netzen prüfen (Lektion 4.4) |
| `echoscu` von der eigenen Workstation mit bekannten, funktionierenden Werten läuft erfolgreich | Die Association funktioniert mit *genau diesen* Werten | dass die tatsächlich betroffene Modalität mit *ihren* Werten ebenfalls durchkäme | denselben Test mit den tatsächlichen Werten der betroffenen Modalität wiederholen |
| Association abgelehnt: `Called AE Title Not Recognized` | Der angesprochene Name wird von der Gegenstelle nicht als der eigene erkannt | welche der beiden Seiten vom vereinbarten Soll-Zustand abweicht | Soll-Konfiguration beider Seiten vergleichen (nächster Abschnitt) |
| Association abgelehnt: `Calling AE Title Not Recognized` | Der Absendername ist beim Ziel nicht als bekannter Calling AE Title registriert | welche der beiden Seiten vom vereinbarten Soll-Zustand abweicht | Soll-Konfiguration beider Seiten vergleichen |

## Soll und Ist beider Seiten vergleichen

Der Ablehnungsgrund sagt dir, welcher der beiden Namen betroffen ist — nicht automatisch, wessen Eintrag falsch ist. Bevor du irgendwo etwas änderst:

1. **Kläre den Soll-Zustand.** Welcher AE Title war für welches System eigentlich vereinbart — nachschlagen, nicht aus dem Gedächtnis.
2. **Vergleiche beide Seiten zeichengenau mit diesem Soll-Zustand**, nicht nur miteinander. Beide könnten davon abweichen, nicht zwangsläufig nur eine.
3. **Ändere eine Variable pro Versuch.** Wer AE Title und Port gleichzeitig anpasst, weiß hinterher nicht, was gewirkt hat (vertieft in Lektion 4.10).
4. **Dokumentiere die Korrektur dort, wo der Soll-Zustand steht** — sonst wiederholt sich derselbe Fehler beim nächsten Gerätewechsel.

## Abgrenzung zu Lektion 4.2

Diese Lektion endet dort, wo die Association tatsächlich zustande kommt. Steht die Verbindung, scheitert aber der Sendeversuch trotzdem sofort — mit einem eigenen Ablehnungsgrund für den Objekttyp oder die Kodierung —, bist du nicht mehr hier, sondern beim Presentation Context: Lektion 4.2.

## Lab: Silent CT und Wrong Door

Zwei Nodes zu demselben Muster, unterschiedliche Seite:

Node **„Silent CT"** (dein Lab zu dieser Lektion): Der Fehler liegt auf der **Called-Seite** — das Archiv wird unter einem falschen Namen angesprochen. Node **„Wrong Door"**: derselbe Mechanismus, diesmal auf der **Calling-Seite** — der Absender stellt sich unter einem Namen vor, den das Ziel nicht kennt.

## Stolperfallen

- **„Connection refused heißt, der Zielhost hat geantwortet, nur der Dienst läuft dort nicht."** Auch ein aktives Firewall- oder Netzwerk-`REJECT` irgendwo auf dem Weg erzeugt dasselbe Bild — weder ist damit sicher, dass der Zielhost selbst geantwortet hat, noch dass er allgemein erreichbar ist.
- **„Der Ablehnungsgrund sagt mir, welche Seite ich ändern muss."** Er sagt nur, welcher Name betroffen ist. Welche Seite vom vereinbarten Soll-Zustand abweicht, zeigt erst der Vergleich.
- **„C-ECHO von meiner Workstation lief, also ist das Archiv nicht schuld."** Bewiesen ist nur, dass genau dieser Absender mit genau diesen Werten akzeptiert wurde — nicht, dass die tatsächlich betroffene Modalität mit ihren eigenen Werten ebenfalls durchkäme.
- **Association-Rejection mit Association-Abort verwechseln.** Eine Rejection ist die bestätigte Ablehnung des Verbindungsaufbaus mit Result/Source/Reason; ein Abort ist ein abrupter Abbruch durch Service-User oder Service-Provider, der sowohl während der Verhandlung als auch nach einer bereits stehenden Association auftreten kann — aus „Abort" allein folgt nicht, wie weit es vorher kam.
- **AE Titles wie Hostnamen behandeln.** Zeichengenau, case-sensitive — Bindestrich und Unterstrich sind zwei verschiedene Zeichen.

## Selbstcheck

1. Ein Sendeauftrag scheitert mit `Connection refused`. Welche zwei Erklärungen sind beide möglich — und was schließt diese Meldung noch nicht aus?
2. Die Fehlermeldung nennt `Calling AE Title Not Recognized`. Was genau sagt das aus — und was nicht?
3. Warum beweist ein erfolgreiches C-ECHO von deiner eigenen Workstation nicht, dass die tatsächlich betroffene Modalität mit ihren eigenen Werten ebenfalls durchkäme?
4. Worin unterscheidet sich eine Association-Rejection von einem Association-Abort — und was verrät die bloße Meldung „Abort" noch nicht darüber, wie weit die Verhandlung zuvor gekommen war?

## Quiz

*Wissenskarten — kommen später zur Wiederholung zurück.*

**q1 — Ein Sendeversuch scheitert mit `TCP Initialisation Error: [Errno 111] Connection refused`. Was folgt daraus?**
1. Der Host ist im Netz nicht erreichbar
2. Auf dem angesprochenen Port läuft aktuell kein Dienst, der die Verbindung annimmt — oder eine Firewall lässt die Anfrage passieren und weist sie aktiv zurück
3. Der Called AE Title ist falsch
4. Die Association wurde von DICOM abgelehnt

**q2 — Im Log steht `Reason: Calling AE Title Not Recognized`. Was bedeutet das?**
1. Das Ziel wurde unter einem falschen Namen angesprochen
2. Der Empfänger kennt den Namen nicht, unter dem sich der Absender vorgestellt hat
3. Der Port ist falsch
4. Die Transfer Syntax wird nicht unterstützt

**q3 — Ein erfolgreiches C-ECHO von der eigenen Workstation zum Archiv beweist, dass auch die tatsächlich betroffene Modalitätenkonsole mit ihren eigenen Werten erfolgreich eine Association aufbauen kann.**
1. Richtig
2. Falsch

**q4 — Ein Sendeauftrag von `CT_RAUM3` scheitert. `echoscu` von der Workstation mit `DCMLAB-WS` gegen dieselbe Zieladresse (`PACS-ARCHIV`) läuft erfolgreich. Welche Aussagen sind dadurch gerechtfertigt?** *(Mehrfachauswahl)*
1. Zum Zeitpunkt dieses Tests hat das Archiv unter `PACS-ARCHIV` eine Association mit genau den Werten Calling `DCMLAB-WS` / Called `PACS-ARCHIV` angenommen
2. Damit ist bewiesen, dass auch `CT_RAUM3`s eigene Werte akzeptiert würden
3. Der Fehler lässt sich nicht mehr durch einen komplett ausgefallenen oder unerreichbaren Archivdienst erklären
4. Um sicher zu sein, muss der Fehler mit `CT_RAUM3`s tatsächlichen Werten nachgestellt werden

**q5 — Ein Archiv lehnt eine eingehende Association ab, weil der Absender nicht in seiner Liste bekannter Geräte steht. Welchen der beiden PS3.8-Ablehnungsgründe zeigt das Log?** *(Freitext)*
