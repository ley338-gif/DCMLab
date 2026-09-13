---
title: "C-MOVE vs. C-GET: warum C-MOVE drei Beteiligte hat"
teaser: Zwei Wege, an dieselben Bilder zu kommen — einer davon zieht einen Dritten mit hinein, der andere nicht.
objectives:
  - Die Rollenverteilung bei C-MOVE (SCU, Ziel, Quelle) beschreiben
  - Erklären, warum C-GET ohne eine dritte Association auskommt
  - Begründen, wann welcher der beiden Dienste in der Praxis verwendet wird
---

## Ein Retrieve-Auftrag kommt nie an

Eine Befundstation fordert eine alte Studie an — an der Quelle sieht
alles normal aus, aber die Bilder tauchen nie auf der Station auf.
Genau hier lohnt sich der Blick auf ein Detail, das bei C-MOVE leicht
übersehen wird: Der anfragende SCU muss die Bilder gar nicht selbst
empfangen.

## Drei Beteiligte, nicht zwei

{{term:c-move}} hat drei Rollen: der SCU (fordert an), die Quelle
(hat die Bilder) und das Move-Ziel (soll sie bekommen) — und Quelle
und Ziel sind zwei unabhängige, neu verbundene Systeme. Ein echtes
drittes Ziel lässt sich mit einem eigenen `storescp` und einer
dynamischen Registrierung beim Archiv aufbauen:

```
$ storescp -v -aet DRITTES-ZIEL -od zielverzeichnis 11113 & curl -s -X PUT \
    http://127.0.0.1:8042/modalities/drittes-ziel \
    -d '{"AET":"DRITTES-ZIEL","Host":"127.0.0.1","Port":11113}'
```
**Was du daran abliest:** Ein zweiter, unabhängiger `storescp`-Prozess
im selben Sitzungscontainer genügt als „dritte Partei" — DICOM
unterscheidet nach Rollen (AE Title, Port), nicht danach, auf welcher
Maschine ein Prozess läuft. `PUT /modalities/<name>` trägt die
Gegenstelle beim Archiv ein, genau wie beim Rückruf für Storage
Commitment (Lektion 2.7).

```
$ movescu -v -S -k QueryRetrieveLevel=STUDY \
          -k StudyInstanceUID=1.2.826.0.1.3680043.8.498.37604615939944133976558208138108614643 \
          -aet MEINE-WS -aem DRITTES-ZIEL -aec ORTHANC 127.0.0.1 4242
I: Requesting Association
I: Association Accepted
I: Sending Move Request: MsgID 1
I: 
I: # Request Identifier
I: (0008,0052) CS [STUDY]                                  # 1 QueryRetrieveLevel
I: (0020,000D) UI [1.2.826.0.1.3680043.8.498.37604615939944133976558208138108614643] # 1 StudyInstanceUID
I: 
I: Move SCP Response: 1 - 0xFF00 (Pending)
I: Sub-Operations Remaining: 1, Completed: 1, Failed: 0, Warning: 0
I: Move SCP Result: 0x0000 (Success)
I: Sub-Operations Remaining: 0, Completed: 2, Failed: 0, Warning: 0
I: Releasing Association
```
**Was du daran abliest:** `-aem DRITTES-ZIEL` benennt das Move-Ziel —
eine dritte, real registrierte Gegenstelle (`storescp -aet
DRITTES-ZIEL`, per `PUT /modalities/drittes-ziel` beim Archiv
eingetragen). `movescu` selbst bekommt nie ein Bild zu sehen — es
bekommt nur die Fortschrittszahlen (`Completed: 2`) der Quelle
gemeldet.

## Auf der Leitung: zwei getrennte Associationen

```
$ tshark -i lo -Y dicom
4   0.000791   127.0.0.1 → 127.0.0.1   DICOM 1949 A-ASSOCIATE request MEINE-WS --> ORTHANC
6   0.000969   127.0.0.1 → 127.0.0.1   DICOM 616  A-ASSOCIATE accept  MEINE-WS <-- ORTHANC
8   0.004633   127.0.0.1 → 127.0.0.1   DICOM 186  P-DATA, C-MOVE-RQ ID=1
10  0.045920   127.0.0.1 → 127.0.0.1   DICOM 164  P-DATA, C-MOVE-RQ-DATA
15  0.047118   127.0.0.1 → 127.0.0.1   DICOM 9534 A-ASSOCIATE request ORTHANC --> DRITTES-ZIEL
17  0.109859   127.0.0.1 → 127.0.0.1   DICOM 4011 A-ASSOCIATE accept  ORTHANC <-- DRITTES-ZIEL
21  0.110448   127.0.0.1 → 127.0.0.1   DICOM 250  P-DATA, C-STORE-RQ ID=1
24  0.110472   127.0.0.1 → 127.0.0.1   DICOM 560  P-DATA, CT Image Storage
27  0.163674   127.0.0.1 → 127.0.0.1   DICOM 236  P-DATA, C-STORE-RSP ID=1 (Success)
29  0.163841   127.0.0.1 → 127.0.0.1   DICOM 194  P-DATA, C-MOVE-RSP ID=1 C=1 R=1
32  0.164025   127.0.0.1 → 127.0.0.1   DICOM 250  P-DATA, C-STORE-RQ ID=2
36  0.164046   127.0.0.1 → 127.0.0.1   DICOM 560  P-DATA, CT Image Storage
39  0.166309   127.0.0.1 → 127.0.0.1   DICOM 236  P-DATA, C-STORE-RSP ID=2 (Success)
41  0.166406   127.0.0.1 → 127.0.0.1   DICOM 184  P-DATA, C-MOVE-RSP ID=1 C=2 (Success)
42  0.166428   127.0.0.1 → 127.0.0.1   DICOM 76   A-RELEASE request
44  0.168620   127.0.0.1 → 127.0.0.1   DICOM 76   A-RELEASE response
49  0.168986   127.0.0.1 → 127.0.0.1   DICOM 76   A-RELEASE request
51  0.169038   127.0.0.1 → 127.0.0.1   DICOM 76   A-RELEASE response
```
**Was du daran abliest:** Zwei vollständig getrennte Associationen —
`MEINE-WS --> ORTHANC` für die Anfrage, und mittendrin eine eigene,
von der Quelle selbst aufgebaute `ORTHANC --> DRITTES-ZIEL` für die
tatsächlichen Bilder. `movescu` ist an der zweiten Association gar
nicht beteiligt.

## C-GET: dieselbe Anfrage, keine dritte Association

```
$ getscu -v -S -k QueryRetrieveLevel=STUDY \
         -k StudyInstanceUID=1.2.826.0.1.3680043.8.498.37604615939944133976558208138108614643 \
         -aet MEINE-WS -aec ORTHANC -od eingang 127.0.0.1 4242
I: Requesting Association
I: Association Accepted
I: Sending Get Request: MsgID 1
I: 
I: # Request Identifier
I: (0008,0052) CS [STUDY]                                  # 1 QueryRetrieveLevel
I: (0020,000D) UI [1.2.826.0.1.3680043.8.498.37604615939944133976558208138108614643] # 1 StudyInstanceUID
I: 
I: Received Store Request
I: Storing DICOM file: CT.1.2.826.0.1.3680043.8.498.83573607740441423371813010334490382972
I: Get SCP Response: 1 - 0xFF00 (Pending)
I: Sub-Operations Remaining: 1, Completed: 1, Failed: 0, Warning: 0
I: Received Store Request
I: Storing DICOM file: CT.1.2.826.0.1.3680043.8.498.29599513373051410853225411696395538342
I: Get SCP Response: 2 - 0xFF00 (Pending)
I: Sub-Operations Remaining: 0, Completed: 2, Failed: 0, Warning: 0
I: Get SCP Result: 0x0000 (Success)
I: Sub-Operations Remaining: 0, Completed: 2, Failed: 0, Warning: 0
I: Releasing Association
```
**Was du daran abliest:** `getscu` „Received Store Request" direkt in
seinem eigenen Log — die Bilder kommen über dieselbe Association
zurück, über die die Anfrage ging. Kein Move-Ziel, keine zweite
Verbindung: `getscu` ist SCU und Empfänger in einer Instanz.

```
$ tshark -i lo -Y dicom
4   0.009605   127.0.0.1 → 127.0.0.1   DICOM 20915 A-ASSOCIATE request MEINE-WS --> ORTHANC
6   0.011464   127.0.0.1 → 127.0.0.1   DICOM 8122  A-ASSOCIATE accept  MEINE-WS <-- ORTHANC
8   0.024056   127.0.0.1 → 127.0.0.1   DICOM 166   P-DATA, C-GET-RQ ID=1
10  0.064743   127.0.0.1 → 127.0.0.1   DICOM 164   P-DATA, C-GET-RQ-DATA
13  0.065674   127.0.0.1 → 127.0.0.1   DICOM 224   P-DATA, C-STORE-RQ ID=1
15  0.065689   127.0.0.1 → 127.0.0.1   DICOM 556   P-DATA, CT Image Storage
17  0.067935   127.0.0.1 → 127.0.0.1   DICOM 236   P-DATA, C-STORE-RSP ID=1 (Success)
19  0.068056   127.0.0.1 → 127.0.0.1   DICOM 194   P-DATA, C-GET-RSP ID=1 C=1 R=1
21  0.068145   127.0.0.1 → 127.0.0.1   DICOM 224   P-DATA, C-STORE-RQ ID=2
23  0.068162   127.0.0.1 → 127.0.0.1   DICOM 556   P-DATA, CT Image Storage
25  0.072501   127.0.0.1 → 127.0.0.1   DICOM 236   P-DATA, C-STORE-RSP ID=2 (Success)
27  0.072582   127.0.0.1 → 127.0.0.1   DICOM 194   P-DATA, C-GET-RSP ID=1 C=2
29  0.072598   127.0.0.1 → 127.0.0.1   DICOM 184   P-DATA, C-GET-RSP ID=1 C=2 (Success)
31  0.075451   127.0.0.1 → 127.0.0.1   DICOM 76    A-RELEASE request
32  0.075509   127.0.0.1 → 127.0.0.1   DICOM 76    A-RELEASE response
```
**Was du daran abliest:** Nur eine einzige Association, direkt neben
der `C-GET-RQ` liegen die `C-STORE-RQ`-Pakete für dieselben Bilder —
alles innerhalb desselben Verbindungsaufbaus/-abbaus. Genau diese eine
Association ist der strukturelle Unterschied zu C-MOVE.

## Wenn das Ziel nicht erreichbar ist

```
$ movescu -v -S -k QueryRetrieveLevel=STUDY \
          -k StudyInstanceUID=1.2.826.0.1.3680043.8.498.37604615939944133976558208138108614643 \
          -aet MEINE-WS -aem NICHT-ERREICHBAR -aec ORTHANC 127.0.0.1 4242
I: Requesting Association
I: Association Accepted
I: Sending Move Request: MsgID 1
I: 
I: # Request Identifier
I: (0008,0052) CS [STUDY]                                  # 1 QueryRetrieveLevel
I: (0020,000D) UI [1.2.826.0.1.3680043.8.498.37604615939944133976558208138108614643] # 1 StudyInstanceUID
I: 
I: Move SCP Result: 0xC000 (Failure)
I: Sub-Operations Remaining: 0, Completed: 0, Failed: 0, Warning: 0
I: Releasing Association
```
**Was du daran abliest:** `Sub-Operations Remaining: 0, Completed: 0` —
die Quelle hat gar nicht erst versucht, etwas zu übertragen, weil sie
das Move-Ziel selbst nicht erreichen konnte (im Server-Log als „TCP
Initialization Error" sichtbar). Genau das ist die Kehrseite der drei
Beteiligten: Ein unerreichbares Ziel scheitert an einer Stelle, die
der ursprüngliche SCU nicht einsehen kann.

## Im Alltag

| Frage | Womit prüfen |
|---|---|
| Kommt die Anfrage überhaupt an? | `movescu`/`getscu` gegen die Quelle, unverändert wie C-FIND |
| Kommt die Antwort nie an, obwohl die Quelle Erfolg meldet? | Move-Ziel-Erreichbarkeit prüfen — eine eigene Verbindung |
| Firewall/NAT im Weg? | C-GET statt C-MOVE erwägen — keine zweite eingehende Verbindung nötig |

## Stolperfallen

- **Move-Ziel mit dem anfragenden SCU verwechseln.** `-aem` benennt
  eine dritte Gegenstelle — nicht automatisch den eigenen Rechner.
- **Bei C-MOVE-Fehlern nur beim SCU suchen.** Ein `0xC000` mit
  `Completed: 0` verweist auf ein Problem zwischen Quelle und Ziel,
  nicht zwischen SCU und Quelle.
- **C-GET für „moderner" oder "immer besser" halten.** C-GET erspart
  die dritte Verbindung, verlangt aber, dass SCU und Ziel zwingend
  dieselbe Instanz sind — nicht immer praktikabel.

## Selbstcheck

1. Welche drei Rollen sind bei einem C-MOVE beteiligt, und welche
   davon empfängt die eigentlichen Bilder?
2. Warum braucht C-GET keine dritte Association?
3. Ein C-MOVE scheitert mit `Completed: 0, Failed: 0`. Wo liegt die
   Ursache eher — bei der Quelle oder beim Move-Ziel?
