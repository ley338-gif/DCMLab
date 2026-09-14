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
