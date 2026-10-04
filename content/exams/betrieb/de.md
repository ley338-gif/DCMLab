---
title: Abschlussprüfung — Betrieb und Integration
intro: 20 Fragen aus Track 5. Ab 80 % ist der Track abgeschlossen. Beliebig oft wiederholbar.
---

### f01 — Welcher DICOM-Dienst steckt hinter der IHE-Transaktion RAD-8?

1. Modality Worklist-Abfrage
2. C-STORE (Bildübertragung)
3. MPPS N-CREATE
4. MPPS N-SET

**Erklärung:** RAD-8 ("Modality Images Stored") ist der Storage-Transfer der Modalität ans Archiv — derselbe `storescu`-Aufruf, den fast jede andere Lektion zeigt, nur mit einer festen IHE-Transaktionsnummer versehen.

### f02 — Welches IHE-Profil setzt voraus, dass der Patient bei Untersuchungsbeginn bereits korrekt registriert ist?

1. XDS-I.b
2. PIR
3. SWF (das ursprüngliche Scheduled Workflow)
4. ATNA

**Erklärung:** Das ursprüngliche Scheduled Workflow (RAD TF-1, Kapitel 3) setzt eine bereits korrekt registrierte Patientenidentität voraus. Für den Fall, dass das nicht zutrifft (Notfall, falsche oder vorläufige Kennung), erweitert PIR es — unter anderem bekommen dann auch Archiv und Befundsystem die Patientenupdates. SWF.b (Kapitel 34) enthält diese Fälle bereits selbst.

### f03 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. SWF.b definiert neue DICOM-Dienste, die es vorher nicht gab
2. Das bei XDS-I.b veröffentlichte Manifest ist technisch ein Key Object Selection Document
3. PIR ist der IHE-Prozessrahmen für nachträglich korrigierte Patientenzuordnungen
4. Bei XDS-I.b wird das Manifest über Registry und Repository gefunden, die Bilder selbst kommen vom Imaging Document Source

**Erklärung:** Das XDS-I-Manifest ist ein echtes KOS, PIR behandelt nachträgliche Patientenkorrekturen, und bei XDS-I.b dient die Registry dem Finden: Mit dem Manifest holt der Consumer die Bilder beim Imaging Document Source der abgebenden Einrichtung (RAD-69, WADO, WADO-RS oder DICOM-Abruf, RAD TF-1 Tabelle 18.1-1). SWF.b definiert dagegen keine neuen Dienste — es legt Reihenfolge, Pflicht-Transaktionen und die Übernahme der Auftragswerte für vorhandene DICOM-Dienste fest.

### f55 — SWF.b legt fest, in welcher Reihenfolge und mit welchen Akteuren vorhandene DICOM-Dienste wie Worklist-Abfrage, C-STORE und MPPS zusammenspielen.

**Richtig / Falsch**

**Erklärung:** Richtig. SWF.b führt keine neuen technischen Mechanismen ein, sondern nimmt vorhandene DICOM-Dienste und schreibt fest, wer sie in welcher Reihenfolge nutzt — genau das macht ein IHE-Profil aus.

### f05 — In welchem Abschnitt des aktuellen Conformance-Statement-Musters (PS3.2, Annex N) stehen alle unterstützten Storage-SOP-Klassen mit Rollen und Transfer-Syntaxen?

1. Introduction
2. Implementation Model
3. Overview (Abschnitt „Content and Transfer")
4. Security

**Erklärung:** Im aktuellen Muster listet die Overview im Abschnitt „Content and Transfer" alle Storage-SOP-Klassen mit Rollen und Transfer-Syntax-Sets. Im älteren Muster (bis PS3.2 2022d, Annex A), nach dem viele Herstellerdokumente noch aufgebaut sind, standen diese Angaben in den AE Specifications unter Networking.

### f06 — Zwei Geräte unterstützen dieselbe SOP-Klasse mit passenden Rollen, aber keine gemeinsame Transfer-Syntax. Was folgt daraus?

1. Die Verbindung für diese SOP-Klasse kommt nicht zustande
2. Die Verbindung funktioniert trotzdem, nur langsamer
3. Das Archiv erzwingt automatisch Implicit VR Little Endian
4. Das ist kein reales Problem

**Erklärung:** Selbst bei passender SOP-Klasse und Rolle scheitert die Presentation-Context-Verhandlung ohne mindestens eine gemeinsame Transfer-Syntax — derselbe Mechanismus wie in Lektion 1.7.

### f07 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. Ein Conformance Statement trifft eine Aussage über die tatsächliche Performance eines Geräts
2. „Unterstützt DICOM" ist ohne SOP-Klasse, Rolle und Transfer-Syntax keine überprüfbare Aussage
3. Eine unterstützte SOP-Klasse ist nicht automatisch eine getestete Kombination mit einem bestimmten anderen Hersteller
4. Ein Conformance Statement folgt einer festen Gliederung aus PS3.2

**Erklärung:** Ein Conformance Statement folgt einer festen PS3.2-Gliederung und macht „unterstützt DICOM" erst überprüfbar. Es sagt aber nichts über Performance oder tatsächlich getestete Gerätekombinationen aus.

### f08 — Ein grüner Abgleich von SOP-Klasse, Rolle und Transfer-Syntax zwischen zwei Conformance Statements garantiert, dass beide Geräte in der Praxis fehlerfrei zusammenarbeiten.

**Richtig / Falsch**

**Erklärung:** Falsch. Ein grüner Abgleich bedeutet nur, dass die Verbindung zustande kommen kann. Er belegt weder Performance noch eine tatsächlich getestete Kombination — und auch nicht, dass sich das Gerät bei unvollständigen Daten so verhält, wie das Dokument es beschreibt. Das zeigt erst ein Test.

### f09 — Im Versuch der Lektion wird ein Objekt per C-STORE nach Orthanc migriert, mit derselben Transfer-Syntax wie im Original. Was hat sich an der zurückgeholten Datei geändert?

1. Nur die File-Meta-Signatur (ImplementationClassUID/-VersionName)
2. Die PatientID
3. Die Pixeldaten werden neu komprimiert
4. Nichts ändert sich, auch nicht die Meta-Signatur

**Erklärung:** Der Test der Lektion zeigt: Der Datensatz hinter der File Meta Information bleibt Byte für Byte gleich, Pixeldaten eingeschlossen — es ändert sich nur, welche Implementierung die Datei zuletzt geschrieben hat (samt Gruppenlänge). Wird eine andere Transfer-Syntax ausgehandelt, speichert Orthanc das Objekt in dieser Kodierung — andere Archive kodieren beim Speichern teils selbst um; bei Implicit VR verlieren private Attribute dabei ihren Datentyp.

### f10 — Ein Migrationswerkzeug vergibt beim Import neue StudyInstanceUIDs. Was passiert am Ziel-Archiv mit einer bereits vorhandenen Studie desselben Patienten?

1. Sie werden automatisch zusammengeführt
2. Es entstehen zwei getrennte, unverbundene Studien
3. Das Archiv lehnt den Import ab
4. Das Archiv erkennt und meldet den Fehler automatisch

**Erklärung:** Beide Studien sind für sich genommen gültige DICOM-Objekte — das Archiv hat keine Möglichkeit zu erkennen, dass sie eigentlich zusammengehören. Es entstehen real zwei getrennte, unverbundene Studien, ohne Fehlercode.

### f11 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. Ein reiner Bildzahlenvergleich hätte den UID-Fehlerfall im Beispiel nicht erkannt
2. Externe Systeme wie Befundtexte können dieselbe StudyInstanceUID referenzieren wie das Archiv
3. Bei einer schrittweisen Migration mit Stichprobenvergleich fiele ein UID-Fehler tendenziell früher auf als bei einem Big-Bang-Cutover
4. Eine geänderte ImplementationClassUID nach einer Migration ist immer ein Fehler

**Erklärung:** Der Bildzahlenvergleich hätte den Fehlerfall nicht erkannt, externe Systeme referenzieren dieselben UIDs, und schrittweise Migration mit Stichproben deckt Fehler früher auf. Eine geänderte ImplementationClassUID ist dagegen normal — sie zeigt nur, welche Implementierung zuletzt geschrieben hat.

### f56 — Ein Migrationswerkzeug, das beim Import alle UIDs neu vergibt, kann die Bildzahl exakt erhalten und trotzdem jede Studie unter ihrer alten UID unauffindbar machen.

**Richtig / Falsch**

**Erklärung:** Richtig. Deshalb reicht „Anzahl Bilder vorher = Anzahl Bilder nachher" als Nachweis nicht: Der Zähler stimmt, aber keine Studie liegt mehr unter ihrer alten UID. Und doppelt geschriebene Objekte mit neuen UIDs bilden eigene Studien, die ein reiner Zähler nicht als solche erkennt.

### f13 — Welchen Aktionscode aus PS3.15 Annex E bekommt StudyInstanceUID bei der De-Identifikation?

1. Z (Länge Null)
2. X (entfernen)
3. U (durch neue, gültige UID ersetzen)
4. K (unverändert lassen)

**Erklärung:** `StudyInstanceUID` ist Pflicht (Type 1, Lektion 3.1) und kann selbst identifizieren. Deshalb wird sie nicht geleert oder entfernt, sondern mit **U** ersetzt: durch eine neue UID, die über alle Objekte der Menge konsistent ist — alle Bilder einer Studie bekommen dieselbe neue Study Instance UID.

### f14 — Wie lautet der DICOM-Aktionscode für „unverändert lassen" in PS3.15 Annex E? *(Freitext)*

**Erklärung:** `K` (keep). Im Basic Profile bekommt allerdings kaum ein Attribut mit Patientenbezug K — die `PatientID` etwa hat **Z/D**. K taucht vor allem in den Optionen auf, etwa „Retain UIDs" für die UIDs.

### f57 — Pseudonymisierte Daten lassen sich mit zusätzlichen, getrennt aufbewahrten Informationen wieder einer Person zuordnen — anonymisierte nicht.

**Richtig / Falsch**

**Erklärung:** Richtig. Der Unterschied ist die Rückführbarkeit, nicht der Grad der Verschleierung (DSGVO Art. 4 Nr. 5). Pseudonymisierte Daten bleiben personenbezogen; echte Anonymisierung lässt sich auch mit einem Schlüssel nicht zurückdrehen.

### f16 — In welcher DICOM-Sequenz (Tag) werden bei DICOMs eigenem Pseudonymisierungsmechanismus die verschlüsselten Originalwerte mitgeführt? *(Freitext)*

**Erklärung:** `0400,0500` (Encrypted Attributes Sequence). Die Originalwerte stehen in einer Modified Attributes Sequence (0400,0550); dieser Datensatz wird verschlüsselt und als Eintrag der Encrypted Attributes Sequence im de-identifizierten Objekt mitgeführt (PS3.15 E.1.1) — wer den Schlüssel hat, kann ihn auspacken.

### f17 — Ein Bild wird über Orthancs REST-API heruntergeladen. Was zeigt `/changes` danach?

1. Einen neuen Eintrag für den Lesezugriff
2. Keinen neuen Eintrag — Lesezugriffe werden nicht protokolliert
3. Eine Fehlermeldung
4. Einen Eintrag nur, wenn Authentifizierung aktiv ist

**Erklärung:** `/changes` bleibt nach dem Download beim selben Stand wie davor — es ist ein Änderungsprotokoll (Erstellen/Löschen), kein Zugriffsprotokoll (Lesen).

### f18 — Welche Aussagen zu IHE ATNA stimmen? *(Mehrfachauswahl)*

1. Es umfasst Node Authentication (gegenseitige Authentifizierung, z. B. per TLS)
2. Es umfasst einen Audit Trail mit strukturierten Nachrichten an ein zentrales Repository
3. Jedes DICOM-Gerät muss ATNA verpflichtend implementieren
4. Es beantwortet zusätzlich zu einem reinen Änderungsprotokoll auch die Frage „wer" hat zugegriffen

**Erklärung:** ATNA bringt Node Authentication und einen strukturierten Audit Trail. Node Authentication weist per TLS-Zertifikat aus, welches **System** spricht; der Audit Trail hält fest, wer über welches System auf welche Daten zugegriffen hat — mit Personenidentität, sofern das System sie kennt. ATNA ist ein IHE-Profil, keine Pflicht-Implementierung für jedes DICOM-Gerät.

### f58 — Verlangt eine rechtliche Pflicht wie § 85 StrlSchG die Aufbewahrung, besteht für diese Zeit kein Anspruch auf Löschung nach Art. 17 DSGVO.

**Richtig / Falsch**

**Erklärung:** Richtig. Art. 17 Abs. 3 Buchstabe b DSGVO nimmt die Verarbeitung zur Erfüllung einer rechtlichen Pflicht vom Löschanspruch aus. Der scheinbare Widerspruch ist damit aufgelöst — nach Ablauf der Frist entfällt dieser Grund wieder.

### f20 — Wie viele Jahre müssen Aufzeichnungen und Bilder einer Röntgenuntersuchung bei einer volljährigen Person nach § 85 StrlSchG in Deutschland mindestens aufbewahrt werden (nur die Zahl)? *(Freitext)*

**Erklärung:** `10` (§ 85 Abs. 2 StrlSchG). Behandlungen müssen dagegen 30 Jahre aufbewahrt werden, und bei Minderjährigen gilt Aufbewahrung bis zur Vollendung des 28. Lebensjahres. § 127 StrlSchV regelt Aufbewahrung und Weitergabe und verweist für die Dauer auf § 85 StrlSchG.

### f21 — Was zeigt der reale Test mit frei erfundenen AE-Titles (`PYNETDICOM`/`ANY-SCP`) gegen die Spielwiese?

1. Die Association wird abgelehnt, weil die AE-Titles unbekannt sind
2. Die Association wird akzeptiert, ein AE-Title ist kein kryptografisch geprüftes Passwort
3. DICOM verweigert grundsätzlich frei erfundene Namen
4. Nur C-ECHO funktioniert, C-FIND wird abgelehnt

**Erklärung:** Sowohl C-ECHO als auch C-FIND funktionieren mit frei erfundenen AE-Titles — ein AE-Title ist ein Klartextname, den DICOM per Standard nicht kryptografisch prüft. Die Spielwiese erlaubt Abfragen und Abrufe von Unbekannten allerdings ausdrücklich per Konfiguration; ein Orthanc mit Voreinstellungen hätte die C-FIND-Anfrage abgebrochen.

### f22 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. Netzsegmentierung beseitigt die fehlende AE-Title-Authentifizierung vollständig
2. DICOM TLS (Supplement 31) mit gegenseitiger Zertifikatsprüfung würde das gezeigte Problem lösen
3. Legacy-Modalitäten sind oft nicht mit einem AE-Title-Check nachrüstbar
4. Die zitierte 2026-Studie fand das Verhalten bei über 1.700 real erreichbaren Diensten

**Erklärung:** TLS mit gegenseitiger Zertifikatsprüfung erzwingt eine echte Authentifizierung der Systeme (eingeführt mit Supplement 31, heutige Profile in PS3.15 Anhang B), Legacy-Geräte sind selten nachrüstbar, und die Studie fand über 1.700 betroffene reale Dienste. Netzsegmentierung reduziert dagegen nur die Angriffsfläche — innerhalb des Segments funktioniert der Angriff weiterhin.

### f23 — Netzsegmentierung eines Modalitäts-VLANs schließt den gezeigten AE-Title-Angriff für jeden Angreifer innerhalb dieses Segments aus.

**Richtig / Falsch**

**Erklärung:** Falsch. Ein Angreifer, der bereits im Modalitäts-VLAN sitzt, kann weiterhin jeden AE-Title verwenden — Netzsegmentierung reduziert nur, wer überhaupt Zugang zum Segment hat.

### f24 — Wie viele real gefundene DICOM-Dienste gaben laut der zitierten 2026-Studie Patientendaten ohne Zugriffskontrolle heraus (nur die Zahl)? *(Freitext)*

**Erklärung:** `1780`. Von 1.903 erfolgreich aufgebauten Associations reichte in 93,54 % der Fälle die Association allein, um Patientendaten ohne weitere Zugriffskontrolle abzufragen.

### f25 — Über welchen Orthanc-REST-Endpunkt lässt sich die installierte DCMTK-/OpenSSL-Version abfragen?

1. /statistics
2. /system
3. /jobs
4. /changes

**Erklärung:** `/system` liefert Version und eingebettete Bibliotheksversionen — genau die Information, mit der sich veraltete, ungepatchte Installationen ohne externen Scan erkennen lassen.

### f26 — Ein Hintergrundjob zeigt `Progress: 100` und `State: Failure`. Wie ist das einzuordnen?

1. Der Job läuft noch
2. Der Job ist fertig durchgelaufen, aber nicht erfolgreich — ein eigenes Monitoring-Ereignis
3. Das ist ein Anzeigefehler, der Job war erfolgreich
4. Progress 100 bedeutet immer Erfolg

**Erklärung:** Ein Job mit `Progress: 100` und `State` ungleich `Success` ist ein eigenes, monitoring-relevantes Ereignis, das eine reine „läuft noch/läuft nicht mehr"-Prüfung nicht von einem echten Erfolg unterscheiden könnte. Maßgeblich ist immer `State`: Ein gescheiterter Job kann auch bei `Progress: 0` stehen, etwa wenn das Ziel gar nicht erreichbar war.

### f27 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. Eine leere `/jobs`-Antwort ist ein gültiger Ruhezustand
2. `/statistics`, `/system` und `/jobs` decken zusammen auch die Zugriffsprotokollierung ab
3. Der Trend von `/statistics` über die Zeit ist aussagekräftiger als eine einzelne Messung
4. Alle drei Endpunkte sind technisches Monitoring, kein fachliches

**Erklärung:** Eine leere `/jobs`-Antwort ist ein gültiger Ruhezustand, der Trend über die Zeit ist aussagekräftiger als eine Momentaufnahme, und alle drei Endpunkte sind rein technisches Monitoring. Keiner der drei sagt, wer wann auf ein Bild zugegriffen hat.

### f28 — Ein Archiv mit durchweg unauffälligen Werten in `/statistics` und `/jobs` hat damit sichergestellt, dass niemand unautorisiert auf Bilddaten zugegriffen hat.

**Richtig / Falsch**

**Erklärung:** Falsch. Kapazitäts- und Job-Erfolgs-Monitoring ist ein anderes Thema als Zugriffsprotokollierung — beide Lücken müssen unabhängig voneinander geschlossen werden.

### f29 — In welcher Phase eines Beschaffungsprozesses gehören einzeln überprüfbare Anforderungen wie ein Conformance-Statement-Abgleich laut der Lektion?

1. Erst bei der Inbetriebnahme
2. In die RFP-Phase (Request for Proposal), vor Vertragsabschluss
3. Nur in die RFI-Phase (Request for Information)
4. Gar nicht formal, sondern mündlich beim Techniker

**Erklärung:** Detaillierte, einzeln überprüfbare Anforderungen gehören in die verbindliche RFP-Phase, nicht erst in die Inbetriebnahme oder in die grobe RFI-Anfrage.

### f30 — Welche Lektion begründet die Ausschreibungsfrage nach dem Verhalten eines Systems bei einer Migration (UID-Erhalt vs. Neuvergabe)?

1. Lektion 5.1
2. Lektion 5.3
3. Lektion 5.6
4. Lektion 5.7

**Erklärung:** Lektion 5.3s Migrationstest (UID-Erhalt vs. UID-Neuvergabe) ist die Grundlage für die entsprechende Ausschreibungsfrage in Lektion 5.8.

### f31 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. Eine einzelne 'Ja'-Antwort des Herstellers zu einer SOP-Klasse sagt nichts über die konkret funktionierende Transfer-Syntax
2. Diese Lektion führt neue technische Mechanismen ein, die in den vorigen sieben Lektionen nicht vorkamen
3. Sicherheits- und Datenschutzfragen gehören laut der Lektion in die Ausschreibung, nicht erst in die Inbetriebnahme
4. Jede Checklistenfrage der Lektion verweist auf einen real geprüften Befund einer vorigen Lektion

**Erklärung:** Eine „Ja"-Antwort allein sagt nichts über die funktionierende Transfer-Syntax, Sicherheits-/Datenschutzfragen gehören in die Ausschreibung, und jede Checklistenfrage verweist auf einen real geprüften Befund. Die Lektion selbst führt aber keinen neuen Stoff ein — sie übersetzt nur die vorigen sieben Lektionen in Fragen.

### f32 — Diese Lektion (5.8) führt eigenständigen, neuen technischen Stoff ein, der in den Lektionen 5.1–5.7 nicht behandelt wurde.

**Richtig / Falsch**

**Erklärung:** Falsch. Die Lektion übersetzt ausschließlich, was die vorigen sieben Lektionen real geprüft und gezeigt haben, in konkrete Ausschreibungsfragen — ohne neuen Stoff einzuführen.

### f33 — Ein Archiv anonymisiert Objekte korrekt (Z-/U-Aktionen) und protokolliert dabei ausschließlich Änderungen, keine Lesezugriffe. Was folgt daraus?

1. Nichts über die Zugriffsprotokollierung — De-Identifikation und Zugriffsprotokollierung sind zwei unabhängige Datenschutzmaßnahmen
2. Die De-Identifikation macht eine Zugriffsprotokollierung überflüssig
3. Ein Archiv, das korrekt anonymisiert, protokolliert automatisch auch Lesezugriffe
4. Das Archiv verstößt damit automatisch gegen die DSGVO

**Erklärung:** De-Identifikation (5.4) und Zugriffsprotokollierung (5.5) beantworten unterschiedliche Fragen — korrekte Anonymisierung sagt nichts darüber aus, ob Lesezugriffe protokolliert werden, und umgekehrt.

### f34 — Ein Gerät hat laut Conformance Statement passende SOP-Klassen, Rollen und Transfer-Syntaxen für ein anderes Gerät. Welche Aussagen stimmen zusätzlich? *(Mehrfachauswahl)*

1. Das Conformance Statement allein sagt nichts darüber, ob das Gerät den Called/Calling-AE-Title tatsächlich prüft
2. Kompatibilität laut Conformance Statement schließt eine AE-Title-Sicherheitslücke wie in 5.6 automatisch aus
3. Eine funktionierende DICOM-Verbindung und eine sichere DICOM-Verbindung sind zwei unabhängige Fragen
4. Ein Conformance-Statement-Abgleich ersetzt eine Sicherheitsprüfung vollständig

**Erklärung:** Ein Conformance Statement beschreibt, was der Hersteller zusagt — nach dem aktuellen PS3.2-Muster auch, wie das System auf einen unbekannten Called AE Title reagiert und welche Sicherheitsprofile es unterstützt. Ob das Gerät den AE Title tatsächlich prüft, zeigt erst ein Test wie in 5.6. Eine funktionierende und eine sichere Verbindung bleiben zwei getrennte Fragen.

### f35 — Sowohl eine Migration (5.3) als auch eine Anonymisierung (5.4) können eine StudyInstanceUID verändern. Worin unterscheiden sich die beiden Fälle?

1. Bei der Migration ist eine geänderte UID meist ein Fehler des Werkzeugs, bei der Anonymisierung ist die U-Aktion eine bewusste, korrekte Ersetzung
2. Beide Fälle sind technisch und rechtlich identisch zu behandeln
3. Nur bei der Migration bleibt die Objektidentität erhalten
4. Eine UID-Änderung ist in beiden Fällen ein Standardverstoß

**Erklärung:** Eine UID-Änderung bei der Migration ist typischerweise ein ungewolltes Werkzeugverhalten mit realen Folgeschäden (getrennte Studien); bei der Anonymisierung ist sie die bewusst vorgesehene **U**-Aktion aus PS3.15 Annex E.

### f36 — Welcher Orthanc-Endpunkt würde real zeigen, dass ein per RAD-8 (C-STORE) übertragenes Bild tatsächlich zu mehr gespeicherten Instanzen geführt hat? *(Freitext)*

**Erklärung:** `/statistics`. Dieser Endpunkt zählt real Instanzen, Serien, Studies und Speicherbedarf — ein per SWF.b/RAD-8 gesendetes Bild erhöht real den dort sichtbaren `CountInstances`-Wert.

### f37 — Die in Lektion 5.7 gezeigten Endpunkte (/statistics, /system, /jobs) decken auch die in Lektion 5.5 beschriebene Zugriffsprotokollierung ab.

**Richtig / Falsch**

**Erklärung:** Falsch. Alle drei Endpunkte sind technisches Monitoring — keiner von ihnen sagt, wer wann auf ein Bild zugegriffen hat. Zugriffsprotokollierung bleibt eine eigene, unabhängige Lücke.

### f38 — Welche Aussagen verbinden die Sicherheitslücke aus 5.6 mit der Beschaffungs-Checkliste aus 5.8? *(Mehrfachauswahl)*

1. Die Checkliste verlangt explizit eine Frage nach echter AE-Title-Prüfung statt bloßer DICOM-Unterstützung
2. Sicherheitsfragen gehören laut 5.8 in die Ausschreibung, nicht erst in die Inbetriebnahme
3. Die Sicherheitslücke aus 5.6 ist nur ein theoretisches Beispiel ohne Bezug zur Checkliste
4. Die Checkliste verlangt eine Frage zum Patch-Support-Zeitraum des Herstellers

**Erklärung:** Die Checkliste übersetzt 5.6s realen Befund direkt in Ausschreibungsfragen zu echter AE-Title-Prüfung und Patch-Support-Dauer, und verortet Sicherheitsfragen ausdrücklich in der Ausschreibung selbst — kein theoretisches Beispiel ohne Bezug.

### f39 — Was ist der wichtigste Grund, warum ein grünes C-ECHO allein keine vollständige Abnahme ist?

1. C-ECHO prüft nur Verification, nicht Storage, Worklist oder den fachlichen Workflow
2. C-ECHO ist technisch unzuverlässig
3. C-ECHO benötigt immer ein zusätzliches Passwort
4. C-ECHO funktioniert nur bei CT-Geräten

**Erklärung:** C-ECHO testet ausschließlich Verification. Eine Modalität kann darüber erreichbar sein und trotzdem keine Bilder speichern oder eine leere Worklist haben — Storage, Worklist und der fachliche End-to-End-Workflow sind eigene, unabhängige Tests.

### f40 — Welche Aussagen zu einer sauberen Modalitäts-Abnahme stimmen? *(Mehrfachauswahl)*

1. Storage sollte mit einer tatsächlich benötigten SOP Class getestet werden, nicht nur mit irgendeinem Objekt
2. Eine Worklist-Abnahme prüft nur, ob überhaupt ein Treffer erscheint
3. Ein Negativtest mit bewusst falscher Konfiguration hilft, spätere Fehlerbilder wiederzuerkennen
4. Der fachliche End-to-End-Test ist der eigentliche Abschluss der Abnahme

**Erklärung:** Eine Worklist-Abnahme prüft mehr als nur irgendeinen Treffer — Patient ID, Accession Number, Modality, Scheduled Station AE Title, Zeitfenster und das Verhalten bei mehreren Treffern gehören dazu. Storage-Tests mit der tatsächlich benötigten SOP Class, ein bewusster Negativtest und der fachliche End-to-End-Test runden die Abnahme erst ab.

### f41 — Ein erfolgreicher C-STORE-Test mit einem Testobjekt beweist automatisch, dass auch Enhanced-CT- oder SR-Objekte derselben Modalität gespeichert werden können.

**Richtig / Falsch**

**Erklärung:** Falsch. Enhanced CT, SR oder RDSR sind gegebenenfalls eigene, zusätzliche Tests — ein erfolgreicher Test mit einem Objekttyp sagt nichts über einen anderen aus.

### f42 — Welcher DICOM-Dienst ist laut Lektion der erste sinnvolle Test bei einer Modalitäts-Inbetriebnahme, ohne bereits die vollständige Abnahme zu sein? *(Freitext)*

**Erklärung:** `C-ECHO`. Es bestätigt Netzweg und grundlegende Erreichbarkeit, sagt aber nichts über Storage, Worklist oder den fachlichen Workflow aus.

### f43 — Warum ist ein AE Title kein Ersatz für einen DNS-Namen?

1. AE Titles sind nur innerhalb der jeweiligen DICOM-Konfiguration eindeutig, nicht global auflösbar
2. AE Titles dürfen keine Zahlen enthalten
3. DNS-Namen sind immer kürzer
4. AE Titles werden automatisch von Orthanc vergeben

**Erklärung:** Ein AE Title ist eine lokale Application Entity Title innerhalb einer DICOM-Konfiguration, kein weltweit auflösbarer Name. Die Zuordnung zu Host und Port lebt in der Konfiguration der beteiligten Systeme, nicht in einem globalen Verzeichnis.

### f44 — Welche Angaben gehören laut Lektion sinnvollerweise zu jedem dokumentierten DICOM-Endpunkt? *(Mehrfachauswahl)*

1. AE Title, IP/DNS und Port
2. Rollen und benötigte SOP Classes
3. Owner und Herstellerkontakt
4. Die aktuelle CPU-Auslastung des Geräts

**Erklärung:** Identität und Netzwerkziel (AE Title, IP/DNS, Port), die tatsächlich genutzten Rollen/SOP Classes sowie Owner und Eskalationsweg sind die praktisch relevanten Felder einer Registry. Laufende Systemmetriken wie CPU-Auslastung gehören nicht in eine AE-Registry.

### f59 — Ein stillgelegter AE-Eintrag, der nicht aus der Registry entfernt wird, ist später kaum von einem noch gültigen, aber selten genutzten Ziel zu unterscheiden.

**Richtig / Falsch**

**Erklärung:** Richtig. Deshalb gehört das Entfernen zum Lebenszyklus eines AE-Eintrags, nicht nur das Anlegen — ein verwaister Eintrag sieht aus wie ein Ziel, das nur gerade nichts zu tun hat.

### f46 — Wie lautete der AE Title, den im Lektionsbeispiel ein altes CT, ein neues CT und eine Router-Route gleichzeitig trugen? *(Freitext)*

**Erklärung:** `CT01`. Der AE Title des stillgelegten Geräts blieb im PACS stehen, ein neues Gerät erhielt denselben Namen, und zusätzlich existierte ein Router-Alias mit derselben Bezeichnung.

### f47 — Das Primärsystem fällt aus, DICOM-Dateien liegen vollständig auf einem zweiten Storage. Anwender können sich trotzdem nicht anmelden. Warum?

1. Die DICOM-Dateien sind beschädigt
2. Ein PACS besteht ausschließlich aus DICOM-Dateien
3. Datenbank, Rechte, Routing und weitere Anwendungszustände fehlen trotz vorhandener Bilddateien
4. Der zweite Storage ist grundsätzlich nicht erreichbar

**Erklärung:** Ein produktives PACS besteht aus deutlich mehr als den Bilddateien — Datenbankzustand, Rechte, Routingregeln und Integrationsparameter gehören ebenso dazu. Vorhandene DICOM-Dateien allein stellen diesen Zustand nicht wieder her.

### f48 — Welche Aussagen zu RPO und RTO stimmen? *(Mehrfachauswahl)*

1. RPO beschreibt den maximal tolerierbaren Datenverlust
2. RTO beschreibt, wie lange ein Dienst maximal ausfallen darf
3. Replikation ersetzt automatisch einen getesteten Restore-Nachweis
4. Ein RPO von 15 Minuten bedeutet, dass ein Ausfall höchstens die letzten 15 Minuten kosten darf

**Erklärung:** RPO und RTO beantworten getrennte Fragen — wie viel Datenverlust ist tolerierbar (RPO) und wie lange darf der Dienst ausfallen (RTO). Replikation schützt vor Verfügbarkeitsausfall, ersetzt aber keinen nachgewiesenen Restore-Test, weil sie Fehler mit repliziert statt sie abzufangen.

### f49 — Ein DICOM-Retrieve von einem Zweitarchiv stellt bei einem vollständig verlorenen PACS automatisch auch Datenbank, Benutzer und Routingregeln wieder her.

**Richtig / Falsch**

**Erklärung:** Falsch. Ein Retrieve bringt DICOM-Objekte zurück, aber nicht automatisch Datenbank, Benutzer/Rechte, Routingregeln oder Jobhistorien — bei einem vollständig verlorenen PACS braucht es dafür ein eigenes DR-/Restore-Verfahren.

### f50 — Welche der beiden Kennzahlen RPO/RTO beantwortet die Frage „Wie lange darf der Dienst ausfallen?"? *(Freitext)*

**Erklärung:** `RTO`. RPO beantwortet dagegen, wie viele Daten im schlimmsten Fall verloren gehen dürfen.

### f51 — Eine Studie ist korrekt im PACS gespeichert, kommt aber nicht am vorgesehenen Routing-Ziel an. Was prüfst du zuerst?

1. Ob der ursprüngliche C-STORE erfolgreich war
2. Ob die Routingregel überhaupt gematcht hat
3. Ob das PACS neu gestartet werden muss
4. Ob die Modalität online ist

**Erklärung:** „Im PACS angekommen" ist nicht dasselbe wie „an alle Ziele verteilt". Die naheliegendste erste Prüfung ist, ob die zuständige Routingregel für diese Studie überhaupt gematcht hat.

### f52 — Welche Aussagen zu Prefetch und Routing stimmen? *(Mehrfachauswahl)*

1. Prefetch kombiniert häufig Query/Retrieve und Routing
2. Ein Abnahmetest für eine Route sollte auch ein absichtlich nicht erreichbares Ziel enthalten
3. Duplikaterkennung allein reicht als Schutz vor Routing-Loops aus, ein sauberes Routendesign ist dafür unnötig
4. Eine Routingregel lässt sich in Trigger, Bedingung, Ziel und Ergebnis zerlegen

**Erklärung:** Prefetch sucht Voruntersuchungen (Query/Retrieve) und überträgt sie anschließend (Routing). Ein Abnahmetest sollte auch das Fehlerverhalten bei nicht erreichbarem Ziel zeigen, und eine Regel lässt sich in die vier genannten Kernteile zerlegen. Duplikaterkennung allein genügt nicht — die Route sollte zusätzlich so entworfen sein, dass ein Loop erst gar nicht nötig wird.

### f60 — Eine wachsende Queue bei einem Routing-Ziel gehört in die aktive Betriebsüberwachung mit eigener Grenze und Alarmierung.

**Richtig / Falsch**

**Erklärung:** Richtig. Wer erst auf die erste Anwenderbeschwerde über fehlende Bilder wartet, erfährt viel zu spät davon. Eine Queue, die wächst, ist ein messbarer Zustand und bekommt eine Grenze und einen Alarm.

### f54 — Welcher der vier Kernteile einer Routingregel entscheidet, WANN sie überhaupt aktiv wird?

1. Trigger
2. Bedingung
3. Ziel
4. Ergebnis

**Erklärung:** Der Trigger ist der Auslöser, der eine Regel überhaupt erst aktiv werden lässt. Erst danach prüft sie ihre Bedingung, bevor sie an ein Ziel sendet und das Ergebnis dokumentiert.
