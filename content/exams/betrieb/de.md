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
3. SWF.b
4. ATNA

**Erklärung:** SWF.b (Scheduled Workflow) setzt eine bereits korrekt registrierte Patientenidentität voraus. Genau für den Fall, dass das nicht zutrifft (Notfall, Doppelanlage), existiert das eigene Profil PIR.

### f03 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. SWF.b definiert neue DICOM-Dienste, die es vorher nicht gab
2. Das bei XDS-I.b veröffentlichte Manifest ist technisch ein Key Object Selection Document
3. PIR ist der IHE-Prozessrahmen für nachträglich korrigierte Patientenzuordnungen
4. XDS-I.b läuft über eine Registry, nicht über eine direkte Archiv-zu-Archiv-Verbindung

**Erklärung:** Das XDS-I-Manifest ist ein echtes KOS, PIR behandelt nachträgliche Patientenkorrekturen, und XDS-I.b greift über Registry/Repository, nie direkt. SWF.b definiert dagegen keine neuen Dienste — es legt nur Reihenfolge und Pflicht-Transaktionen für vorhandene DICOM-Dienste fest.

### f04 — SWF.b führt neue technische Mechanismen ein, die es vor IHE in DICOM nicht gab.

**Richtig / Falsch**

**Erklärung:** Falsch. SWF.b nimmt vorhandene DICOM-Dienste (Worklist-Abfrage, C-STORE, MPPS) und schreibt nur fest, in welcher Reihenfolge und mit welchen Akteuren sie zusammenspielen müssen.

### f05 — In welchem Abschnitt eines Conformance Statement nach PS3.2 stehen unterstützte SOP-Klassen und Transfer-Syntaxen einer AE?

1. Einleitung
2. Implementation Model
3. AE Specifications
4. Media Interchange

**Erklärung:** Die AE Specifications listen für jede Application Entity Rolle, unterstützte SOP-Klassen und Transfer-Syntaxen pro SOP-Klasse — der Abschnitt, der in der Praxis am häufigsten gebraucht wird.

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

**Erklärung:** Falsch. Ein grüner Abgleich bedeutet nur, dass die Verbindung zustande kommen kann — nichts über Performance, Verhalten bei kaputten Daten oder tatsächlich getestete Kombinationen.

### f09 — Was ändert sich bei einer netzwerkbasierten Migration (C-STORE) laut dem realen Test typischerweise, wenn UIDs und Pixeldaten unverändert bleiben?

1. Nur die File-Meta-Signatur (ImplementationClassUID/-VersionName)
2. Die PatientID
3. Die Pixeldaten werden neu komprimiert
4. Nichts ändert sich, auch nicht die Meta-Signatur

**Erklärung:** Der reale Zwei-Archiv-Test zeigt: Patient, Study, Series, Instance und Pixeldaten überleben unverändert — nur welche Implementierung die Datei zuletzt geschrieben hat (die File-Meta-Signatur), ändert sich.

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

### f12 — Anzahl Bilder vorher = Anzahl Bilder nachher ist ein ausreichender Nachweis für eine korrekte Migration.

**Richtig / Falsch**

**Erklärung:** Falsch. Der gezeigte UID-Fehlerfall hätte diesen Test bestanden (3 Bilder vorher, 3 Bilder in der korrekten Studie danach) und trotzdem eine zusätzliche, fehlerhafte Studie erzeugt.

### f13 — Welchen Aktionscode aus PS3.15 Annex E bekommt StudyInstanceUID bei der De-Identifikation?

1. Z (Länge Null)
2. X (entfernen)
3. U (durch neue, gültige UID ersetzen)
4. K (unverändert lassen)

**Erklärung:** `StudyInstanceUID` ist Type 1 (Lektion 3.1) — ein leerer oder fehlender Wert würde vom Archiv abgelehnt. Deshalb bekommt sie den eigenen Aktionscode **U**: durch eine neue, aber weiterhin gültige UID ersetzen.

### f14 — Wie lautet der DICOM-Aktionscode für „unverändert lassen" in PS3.15 Annex E? *(Freitext)*

**Erklärung:** `K`. Im Lektionsbeispiel bleibt `PatientID` bewusst mit **K** unverändert, weil sie für eine spätere Pseudonymisierungs-Zuordnung noch gebraucht werden könnte.

### f15 — Anonymisierung und Pseudonymisierung unterscheiden sich vor allem darin, wie stark die Daten verschleiert werden.

**Richtig / Falsch**

**Erklärung:** Falsch. Der Unterschied ist die Rückführbarkeit: Bei Pseudonymisierung lassen sich die Originaldaten mit einem geschützten Schlüssel wiederherstellen, bei echter Anonymisierung nicht — auch nicht mit einem Schlüssel.

### f16 — In welcher DICOM-Sequenz (Tag) werden bei DICOMs eigenem Pseudonymisierungsmechanismus die verschlüsselten Originalwerte mitgeführt? *(Freitext)*

**Erklärung:** `0400,0550` (Encrypted Attributes Sequence). Die Originalwerte werden verschlüsselt im de-identifizierten Objekt selbst mitgeführt — wer den Schlüssel hat, kann sie extrahieren.

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

**Erklärung:** ATNA bringt Node Authentication und einen strukturierten Audit Trail — beides beantwortet die Frage „wer" zusätzlich zu einem reinen Änderungsprotokoll. ATNA ist aber ein Muster, keine Pflicht-Implementierung für jedes DICOM-Gerät.

### f19 — Eine gesetzliche Aufbewahrungspflicht wie § 127 StrlSchV steht laut DSGVO in einem unauflösbaren Widerspruch zum Löschanspruch.

**Richtig / Falsch**

**Erklärung:** Falsch. Art. 17 Abs. 3 DSGVO löst diesen scheinbaren Widerspruch ausdrücklich auf: Eine gesetzliche Aufbewahrungspflicht ist ein expliziter Grund, aus dem eine Löschung verweigert werden darf.

### f20 — Wie viele Jahre müssen Röntgenuntersuchungen (Bilder/Aufzeichnungen) nach § 127 StrlSchV in Deutschland mindestens aufbewahrt werden (nur die Zahl)? *(Freitext)*

**Erklärung:** `10`. Röntgenbehandlungen müssen dagegen 30 Jahre aufbewahrt werden, und bei Minderjährigen gilt Aufbewahrung bis zur Vollendung des 28. Lebensjahres.

### f21 — Was zeigt der reale Test mit frei erfundenen AE-Titles (`PYNETDICOM`/`ANY-SCP`) gegen die Spielwiese?

1. Die Association wird abgelehnt, weil die AE-Titles unbekannt sind
2. Die Association wird akzeptiert, ein AE-Title ist kein kryptografisch geprüftes Passwort
3. DICOM verweigert grundsätzlich frei erfundene Namen
4. Nur C-ECHO funktioniert, C-FIND wird abgelehnt

**Erklärung:** Sowohl C-ECHO als auch C-FIND funktionieren mit frei erfundenen AE-Titles — ein AE-Title ist ein Klartextname, den DICOM per Standard nicht kryptografisch prüft.

### f22 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. Netzsegmentierung beseitigt die fehlende AE-Title-Authentifizierung vollständig
2. DICOM TLS (Supplement 51) würde das gezeigte Problem lösen
3. Legacy-Modalitäten sind oft nicht mit einem AE-Title-Check nachrüstbar
4. Die zitierte 2026-Studie fand das Verhalten bei über 1.700 real erreichbaren Diensten

**Erklärung:** TLS würde eine echte Authentifizierung erzwingen, Legacy-Geräte sind selten nachrüstbar, und die Studie fand über 1.700 betroffene reale Dienste. Netzsegmentierung reduziert dagegen nur die Angriffsfläche — innerhalb des Segments funktioniert der Angriff weiterhin.

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

**Erklärung:** Ein Job mit `Progress: 100` und `State` ungleich `Success` ist ein eigenes, monitoring-relevantes Ereignis, das eine reine „läuft noch/läuft nicht mehr"-Prüfung nicht von einem echten Erfolg unterscheiden könnte.

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

**Erklärung:** Lektion 5.3s realer Zwei-Archiv-Test (UID-Erhalt vs. UID-Neuvergabe) ist die Grundlage für die entsprechende Ausschreibungsfrage in Lektion 5.8.

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

**Erklärung:** Ein Conformance Statement beschreibt Fähigkeiten (SOP-Klassen, Rollen, Transfer-Syntaxen), nicht Sicherheit — ob ein Gerät den AE-Title tatsächlich prüft, ist eine unabhängige Frage, die das Dokument nicht beantwortet.

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

### f45 — Ein stillgelegter AE-Eintrag bleibt auch ohne Entfernung aus der Registry zuverlässig von einem noch gültigen, aber selten genutzten Eintrag unterscheidbar.

**Richtig / Falsch**

**Erklärung:** Falsch. Ein verwaister AE-Eintrag ist später kaum von einem noch gültigen, aber selten genutzten Ziel zu unterscheiden — deshalb gehört das Entfernen zum Lebenszyklus, nicht nur das Anlegen.

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

### f53 — Ein wachsender Queue-Zustand bei einem Routing-Ziel wird laut Lektion am zuverlässigsten dadurch erkannt, dass sich Anwender über fehlende Bilder beschweren.

**Richtig / Falsch**

**Erklärung:** Falsch. Eine wachsende Queue gehört in die aktive Betriebsüberwachung mit eigener Grenze und Alarmierung — nicht in die Warteschleife bis zur ersten Anwenderbeschwerde.

### f54 — Welcher der vier Kernteile einer Routingregel entscheidet, WANN sie überhaupt aktiv wird?

1. Trigger
2. Bedingung
3. Ziel
4. Ergebnis

**Erklärung:** Der Trigger ist der Auslöser, der eine Regel überhaupt erst aktiv werden lässt. Erst danach prüft sie ihre Bedingung, bevor sie an ein Ziel sendet und das Ergebnis dokumentiert.
