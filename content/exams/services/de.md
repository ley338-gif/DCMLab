---
title: Abschlussprüfung — Die Services
intro: 22 Fragen aus Track 2. Ab 80 % ist der Track abgeschlossen. Beliebig oft wiederholbar.
---

### f01 — Was macht ein C-ECHO gegenüber einem reinen Netzwerk-Ping zusätzlich?

1. Es baut eine vollständige DICOM-Association mit eigener SOP Class auf
2. Es prüft nur, ob der Host per TCP erreichbar ist
3. Es überträgt ein Testbild zur Kontrolle
4. Es fragt die Firewall-Konfiguration ab

**Erklärung:** Ein Ping prüft nur TCP/IP-Erreichbarkeit. Ein C-ECHO baut eine vollständige Association mit fünf eigenen Schritten auf — Verbindungsaufbau, Aushandlung, Anfrage, Antwort mit explizitem DIMSE-Status, Abbau.

### f02 — Welche SOP Class handelt ein C-ECHO aus?

1. CT Image Storage
2. Verification SOP Class
3. Modality Worklist Information Model
4. Study Root Query/Retrieve

**Erklärung:** Ein C-ECHO schlägt ausschließlich die Verification SOP Class vor — kein Bildtyp, keine Transfer Syntax für Pixeldaten wird dabei verhandelt.

### f03 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. Ein grünes C-ECHO beweist, dass ein bestimmter Bildtyp später angenommen wird
2. Ein C-ECHO durchläuft Verbindungsaufbau, Anfrage, Antwort und Abbau als eigene Schritte
3. Übungsserver sind bei der AE-Title-Prüfung oft großzügiger als ein echtes Haus-Archiv
4. Ein C-ECHO ist technisch identisch mit einem ICMP-Ping

**Erklärung:** Ein C-ECHO hat fünf eigene Ablaufschritte, und Übungsarchive akzeptieren oft mehr als ein echtes Haus-Archiv. Über spätere Bildübertragung sagt ein grünes C-ECHO nichts, und mit einem ICMP-Ping hat es nur den Namen "Ping" gemeinsam.

### f04 — Ein `echoscu`-Aufruf, der mit „Connection refused" scheitert, ist ein Hinweis auf einen falschen AE Title.

**Richtig / Falsch**

**Erklärung:** Falsch. „Connection refused" ist eine reine Transport-Aussage, die entsteht, bevor überhaupt eine DICOM-Aushandlung stattfindet — sie sagt nichts über AE Titles oder SOP Classes.

### f05 — Ein `storescu`-Aufruf schickt drei Dateien in einem Befehl. Wie viele Associations werden dafür aufgebaut?

1. Eine, mit drei eigenständigen MsgIDs
2. Drei, eine je Datei
3. Zwei
4. Das hängt vom Archiv ab

**Erklärung:** Mehrere Dateien in einem `storescu`-Aufruf laufen über eine einzige Association mit mehreren eigenständigen `MsgID`s — nicht über mehrere Verbindungen.

### f06 — Welche Aussagen zu C-STORE stimmen? *(Mehrfachauswahl)*

1. Jedes Objekt wird einzeln mit einem eigenen Status bestätigt
2. Ein Warning-Status (0xB000) bedeutet, dass das Archiv das Objekt unverändert übernommen hat
3. `storescp` benennt empfangene Dateien häufig nach der SOP Instance UID
4. Ein Failure-Status lehnt das Objekt vollständig ab

**Erklärung:** Jedes Objekt bekommt eine eigene Bestätigung, `storescp` benennt Dateien nach der SOP Instance UID, und ein Failure-Status lehnt vollständig ab. Ein Warning-Status bedeutet dagegen "angenommen, aber verändert" — nicht unverändert übernommen.

### f07 — Ein Warning-Status wie 0xB000 bedeutet, dass das Objekt ganz normal und unverändert angekommen ist.

**Richtig / Falsch**

**Erklärung:** Falsch. `0xB000` heißt "angenommen, aber verändert", etwa wenn Patientendaten aktiv gegen den eigenen Bestand überschrieben wurden (Coercion) — nicht "ganz normal angekommen".

### f08 — Welcher DIMSE-Status-Code steht für einen erfolgreichen C-STORE? *(Freitext)*

**Erklärung:** `0x0000`. Erst dieser explizite Status in der Store Response beweist etwas — nicht schon der Verbindungsaufbau.

### f09 — Wie viele Query-Retrieve-Ebenen bearbeitet eine einzelne C-FIND-Abfrage?

1. Beliebig viele gleichzeitig
2. Genau eine
3. Immer zwei, PATIENT und STUDY
4. Keine, das entscheidet das Archiv

**Erklärung:** Eine C-FIND-Abfrage arbeitet immer auf genau einer Ebene — PATIENT, STUDY, SERIES oder IMAGE.

### f10 — Ein `-k PatientID` ohne Wert in einer findscu-Abfrage ist …

1. ein Matching-Key, der auf leere PatientID filtert
2. ein Rückgabefeld, das nur um den Wert bittet
3. ein Syntaxfehler
4. gleichbedeutend mit einem Wildcard

**Erklärung:** Ein `-k` ohne Wert ist ein Rückgabefeld — er filtert nicht, sondern bittet nur um den Wert. Ein `-k` mit Wert ist dagegen ein Matching-Key.

### f11 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. Eine SERIES-Abfrage kann mehrere Response-Blöcke liefern
2. Eine leere Antwort mit Status Success ist ein gültiges Ergebnis
3. `*` steht für genau ein beliebiges Zeichen
4. Die Suchkriterien (der Identifier) werden als eigenes Paket getrennt vom C-FIND-Kommando übertragen

**Erklärung:** Eine tiefere Ebene kann mehrere Treffer liefern, eine leere, erfolgreiche Antwort ist gültig, und der Identifier — Matching-Keys *und* Rückgabefelder gemeinsam — reist als eigenes `C-FIND-RQ-DATA`-Paket getrennt vom Kommando `C-FIND-RQ`. `*` steht für eine beliebige Zeichenfolge — `?` steht für genau ein Zeichen.

### f12 — Eine `findscu`-Abfrage ohne Treffer, die mit `Success` endet, ist ein technischer Fehler.

**Richtig / Falsch**

**Erklärung:** Falsch. Eine leere Antwort mit `Success` ist ein vollständig gültiges Ergebnis — der Dienst hat korrekt gearbeitet, es gab nur nichts, was zum Filter passte.

### f13 — Bei einem C-MOVE — wer empfängt die eigentlichen Bilddaten?

1. Der anfragende SCU selbst
2. Das per `-aem` benannte Move-Ziel
3. Immer das Archiv, das die Anfrage entgegennimmt
4. Alle registrierten Gegenstellen gleichzeitig

**Erklärung:** C-MOVE hat drei Rollen — SCU, Quelle und Move-Ziel. Die Bilder gehen an das per `-aem` benannte Ziel, nicht an den anfragenden SCU selbst; der bekommt nur Fortschrittszahlen gemeldet.

### f14 — Welche Aussagen zu C-MOVE und C-GET stimmen? *(Mehrfachauswahl)*

1. C-MOVE baut für die Bildlieferung eine zweite, eigene Association auf
2. C-GET liefert die Bilder über dieselbe Association wie die Anfrage
3. `movescu` selbst empfängt bei C-MOVE die Bilddaten direkt
4. C-GET erspart eine zusätzliche eingehende Verbindung, etwa hinter einer Firewall

**Erklärung:** C-MOVE baut für die Lieferung eine zweite, von der Quelle selbst aufgebaute Association auf; `movescu` ist daran gar nicht beteiligt. C-GET dagegen liefert alles über die ursprüngliche Association zurück — genau deshalb kommt es ohne eine zusätzliche eingehende Verbindung aus.

### f15 — Scheitert ein C-MOVE mit `Completed: 0`, liegt die Ursache meistens beim ursprünglich anfragenden SCU.

**Richtig / Falsch**

**Erklärung:** Falsch. `Completed: 0` bedeutet meist, dass die Quelle das Move-Ziel nicht erreichen konnte — das Problem liegt zwischen Quelle und Ziel, an einer Stelle, die der ursprüngliche SCU gar nicht einsehen kann.

### f16 — Mit welcher Befehlszeilenoption von `movescu` wird das Move-Ziel benannt? *(Freitext)*

**Erklärung:** `-aem`. Sie benennt die dritte, real registrierte Gegenstelle — nicht automatisch den eigenen Rechner des anfragenden SCU.

### f17 — Welches Pflichtfeld hat eine gewöhnliche Study-Abfrage, das einer Worklist-Abfrage fehlt?

1. PatientName
2. QueryRetrieveLevel
3. AccessionNumber
4. StudyInstanceUID

**Erklärung:** Eine Study/Series-Abfrage braucht immer ein `QueryRetrieveLevel`. Die Worklist kennt das nicht — sie ist ein eigenes, flaches Informationsmodell, kein Ausschnitt aus der Patient/Study/Series-Hierarchie.

### f18 — In welcher Sequenz stehen Modality, ScheduledStationAETitle und Startzeit einer Worklist-Antwort?

1. RequestAttributesSequence
2. ScheduledProcedureStepSequence
3. ReferencedStudySequence
4. PerformedSeriesSequence

**Erklärung:** Diese vier typischen Matching-Keys stecken in der `ScheduledProcedureStepSequence` — dem Kernstück des flachen Worklist-Informationsmodells.

### f19 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. Die Worklist ist ein eigenes, flaches Informationsmodell ohne QueryRetrieveLevel
2. Eine leere Worklist-Antwort mit Success ist ein gültiges Ergebnis
3. Die Worklist muss immer vom Bildarchiv selbst beantwortet werden
4. Das DCMTK-`findscu` (`/usr/bin/findscu`) schlägt mit `-W` genau eine Presentation Context vor

**Erklärung:** Die Worklist ist flach und ohne `QueryRetrieveLevel`, eine leere, erfolgreiche Antwort ist gültig, und das DCMTK-`findscu` schlägt mit `-W` genau eine Presentation Context vor (Modality Worklist Information Model – FIND). Das `findscu` der Spielwiese (pynetdicom) schlägt dagegen neunzehn vor — die Zahl ist eine Eigenschaft des Werkzeugs, nicht des Dienstes. Wer die Worklist beantwortet, legt jedes Haus selbst fest: häufig ein RIS oder ein Worklist-Broker, nicht zwingend das Bildarchiv.

### f20 — Eine Worklist-Anfrage wird immer vom Bildarchiv selbst beantwortet.

**Richtig / Falsch**

**Erklärung:** Falsch. Wer die Worklist beantwortet, legt jedes Haus selbst fest — häufig ein RIS oder ein eigener Worklist-Broker, der die Aufträge verwaltet; das Bildarchiv speichert Bilder. Ob beides dieselbe Installation ist, hängt vom Haus ab. In dieser Spielwiese beantwortet Orthanc selbst die Worklist, als bewusste Vereinfachung.

### f21 — Mit welcher DIMSE-Nachricht meldet eine Modalität den Beginn einer Untersuchung per MPPS?

1. C-STORE
2. N-CREATE
3. N-SET
4. C-FIND

**Erklärung:** `N-CREATE` meldet den Beginn mit den vollständigen Stammdaten. Das Ende wird mit `N-SET` gemeldet — beide über dieselbe Assoziation.

### f22 — Welche drei Endzustände kennt ein MPPS-Zyklus für `PerformedProcedureStepStatus`?

1. SUCCESS, WARNING, FAILURE
2. IN PROGRESS, COMPLETED, DISCONTINUED
3. PENDING, ACCEPTED, REJECTED
4. STARTED, STOPPED, CANCELLED

**Erklärung:** `COMPLETED` ist nicht der einzige gültige Endzustand — eine abgebrochene Untersuchung meldet sich genauso gültig als `DISCONTINUED`, beide werden mit demselben Erfolgsstatus `0x0` bestätigt.

### f23 — Welche Aussagen zu MPPS stimmen? *(Mehrfachauswahl)*

1. Ein `N-SET` mit DISCONTINUED wird bei erfolgreicher Zustellung trotzdem mit Status 0x0 beantwortet
2. MPPS bestätigt automatisch, dass die Bilddaten ebenfalls angekommen sind
3. N-CREATE und N-SET laufen typischerweise über dieselbe Assoziation
4. In echten Häusern nimmt meist ein RIS/Broker die MPPS-Meldung entgegen, nicht das Archiv

**Erklärung:** `DISCONTINUED` ist eine erfolgreich zugestellte Meldung wie jede andere, N-CREATE und N-SET laufen über dieselbe Assoziation, und meist ist es ein RIS/Broker, der MPPS entgegennimmt. MPPS bestätigt aber nichts über den Bildtransfer — das ist Aufgabe von C-STORE, ein unabhängiger Meldeweg.

### f24 — Ein Bild ist im Archiv angekommen, aber keine MPPS-Meldung wurde je gesendet — daraus folgt, dass die Untersuchung nicht stattgefunden hat.

**Richtig / Falsch**

**Erklärung:** Falsch. C-STORE und MPPS sind unabhängige Meldewege für dieselbe Untersuchung. Ein fehlendes MPPS sagt nichts darüber, ob die Untersuchung stattgefunden hat — nur, dass diese eine Meldung nicht ankam.

### f25 — Welche zwei Nachrichten bilden einen Storage-Commitment-Zyklus?

1. N-CREATE und N-SET
2. N-ACTION und N-EVENT-REPORT
3. C-STORE und C-FIND
4. A-ASSOCIATE und A-RELEASE

**Erklärung:** `N-ACTION` stößt die Prüfung an, `N-EVENT-REPORT` liefert das Ergebnis — typischerweise über zwei eigene, getrennte Associations, anders als bei MPPS.

### f26 — Welcher REST-Parameter muss bei Orthanc für die Gegenstelle gesetzt sein, damit eine Storage-Commitment-Anfrage nicht scheitert? *(Freitext)*

**Erklärung:** `AllowStorageCommitment`. Ohne diese Berechtigung beim Eintrag der Gegenstelle (`modalities/<name>`) scheitert die Anfrage — ohne dass der ursprüngliche Speichervorgang selbst je fehlschlägt.

### f27 — Ein erfolgreiches C-STORE beweist, dass das Archiv das Objekt auch dauerhaft behält.

**Richtig / Falsch**

**Erklärung:** Falsch. Ein erfolgreiches C-STORE beweist nur den Moment der Übertragung. Ob das Archiv das Objekt dauerhaft behält, beantwortet erst Storage Commitment — eine eigene, spätere Zusicherung.

### f28 — Wie lautet der FailureReason-Code aus PS3.4 Annex J.3.4 für eine referenzierte, nicht vorhandene SOP Instance bei einer Storage-Commitment-Ablehnung? *(Freitext)*

**Erklärung:** `274`. Ein konkreter, im Standard definierter Wert — kein vages "Störung", sondern ein exakter, nachschlagbarer Ablehnungsgrund.

### f29 — Welcher DIMSE-Dienst entspricht QIDO-RS?

1. C-STORE
2. C-FIND
3. C-GET
4. C-MOVE

**Erklärung:** QIDO-RS ist das HTTP-Gegenstück zu C-FIND — dieselben Werte, dasselbe Tag-System, nur als DICOM-JSON über eine gewöhnliche `GET`-Anfrage statt über eine Association.

### f30 — Ein STOW-RS-Upload scheitert mit `415 Unsupported Media Type`. Was ist die wahrscheinlichste Ursache?

1. Die DICOM-Datei ist zu groß
2. Der Content-Type-Header hat keine gültige boundary=-Angabe für den Multipart-Body
3. Der AE Title ist falsch
4. Das Zielarchiv unterstützt kein STOW-RS

**Erklärung:** STOW-RS verlangt eine präzise `multipart/related`-Kodierung mit korrekter `boundary=`-Angabe im Header und im Body. Fehlt sie, scheitert der Upload nicht leise, sondern mit dieser klaren Fehlermeldung.

### f31 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. WADO-RS liefert die Bilddaten über dieselbe Verbindung wie die Anfrage, wie bei C-GET
2. DICOMweb ersetzt DIMSE vollständig, Modalitäten sprechen künftig nur noch HTTP
3. Ein per STOW-RS hochgeladenes Objekt landet im selben Index wie ein per DIMSE gesendetes
4. Die Tags in DICOM-JSON sind dieselben wie in DIMSE, nur als Hex-Schlüssel notiert

**Erklärung:** WADO-RS entspricht strukturell C-GET (eine Verbindung für Anfrage und Lieferung), ein Archiv führt für DIMSE und DICOMweb denselben Index, und die Tags bleiben identisch, nur anders notiert. DICOMweb ist aber eine zweite Zugangsart zu denselben Daten, kein Ersatz — Modalitäten sprechen weiterhin DIMSE.

### f32 — Ein per STOW-RS hochgeladenes Objekt liegt in einem separaten Index, getrennt von per DIMSE gesendeten Objekten.

**Richtig / Falsch**

**Erklärung:** Falsch. Ein Archiv führt intern nur einen einzigen Index — DIMSE und DICOMweb sind zwei Zugangswege zu genau derselben Ablage, keine getrennten Systeme.

### f33 — Eine Modalität hat laut Worklist eine Untersuchung geplant, aber nie eine MPPS-Meldung gesendet. Was lässt sich daraus über C-STORE schließen?

1. Nichts — MPPS, Worklist und C-STORE sind unabhängige Meldewege für dieselbe Untersuchung
2. C-STORE kann in diesem Fall nicht stattgefunden haben
3. Das Archiv lehnt automatisch jedes zugehörige C-STORE ab
4. Die Worklist-Anfrage war fehlerhaft

**Erklärung:** Worklist, MPPS und C-STORE sind drei unabhängige Meldewege für dieselbe Untersuchung. Eine fehlende MPPS-Meldung sagt nichts darüber, ob Bilder tatsächlich per C-STORE angekommen sind — das muss getrennt geprüft werden.

### f34 — Ein Objekt wurde per C-STORE mit Status 0x0000 bestätigt. Welche Aussagen über eine spätere Storage-Commitment-Anfrage für dasselbe Objekt stimmen? *(Mehrfachauswahl)*

1. Sie kann trotzdem mit einem FailureReason abgelehnt werden, wenn das Objekt später nicht mehr referenzierbar ist
2. Ein erfolgreiches C-STORE macht eine Storage-Commitment-Prüfung überflüssig
3. N-ACTION und N-EVENT-REPORT laufen typischerweise über zwei eigene Associations
4. Die ursprüngliche C-STORE-Bestätigung reicht als Nachweis für dauerhafte Speicherung

**Erklärung:** Ein `0x0000` bei C-STORE beweist nur den Übertragungsmoment — eine spätere Storage-Commitment-Anfrage prüft unabhängig davon, ob das Objekt noch vorhanden ist, und kann durchaus mit einem echten FailureReason scheitern. Die beiden Nachrichten dieser Prüfung laufen typischerweise über zwei eigene Associations.

### f35 — Eine Befundstation hinter einer restriktiven Firewall soll Bilder abrufen, ohne dass das Archiv eine neue eingehende Verbindung zur Station aufbauen muss. Welche zwei Dienste kommen dafür eher infrage als C-MOVE?

1. C-GET und WADO-RS
2. C-FIND und QIDO-RS
3. N-ACTION und N-EVENT-REPORT
4. MPPS und Storage Commitment

**Erklärung:** Sowohl C-GET als auch sein HTTP-Gegenstück WADO-RS liefern die Bilddaten über dieselbe, vom Anfragenden aufgebaute Verbindung zurück — anders als C-MOVE, das eine neue, vom Archiv aufgebaute Verbindung zum Ziel braucht und dadurch an restriktiven Firewalls scheitern kann.

### f36 — Welches Pflichtfeld unterscheidet eine gewöhnliche C-FIND-Study-Abfrage von einer Worklist-Abfrage am deutlichsten? *(Freitext)*

**Erklärung:** `QueryRetrieveLevel`. Eine Study/Series-Abfrage braucht es zwingend, die Worklist kennt es gar nicht — sie ist ein eigenes, flaches Informationsmodell statt eines Ausschnitts aus der Patient/Study/Series-Hierarchie.

### f37 — Ein erfolgreiches C-ECHO zwischen zwei Systemen beweist, dass ein nachfolgendes C-STORE für ein CT-Bild ebenfalls angenommen wird.

**Richtig / Falsch**

**Erklärung:** Falsch. Ein C-ECHO verhandelt ausschließlich die Verification SOP Class. Ob ein bestimmter Bildtyp in seiner Kodierung später akzeptiert wird, entscheidet erst die eigene Presentation Context der C-STORE-Association — darüber sagt ein C-ECHO nichts.

### f38 — Welche Gemeinsamkeit haben C-MOVE und Storage Commitment strukturell? *(Mehrfachauswahl)*

1. Beide können eine zweite, vom ursprünglichen Empfänger unabhängig aufgebaute Association beinhalten
2. Beide übertragen die Bilddaten selbst über eine dritte Verbindung
3. Beide benötigen eine bei der Gegenstelle registrierte Rückruf-Adresse
4. Beide sind ausschließlich lesende Operationen ohne jede Rückmeldung

**Erklärung:** Bei C-MOVE baut die Quelle eine eigene zweite Association zum Move-Ziel auf, bei Storage Commitment baut das Archiv eine eigene zweite Association für den `N-EVENT-REPORT` auf — beide brauchen dafür eine vorab registrierte Gegenstelle. Storage Commitment überträgt dabei aber keine Bilddaten, nur eine Bestätigung.
