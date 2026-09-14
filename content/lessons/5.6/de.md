---
title: "Security: Netzsegmentierung, Legacy-Modalitäten, bekannte Angriffsflächen"
teaser: "DICOM wurde 1993 nicht für ein feindliches Netz entworfen — ein einziger, frei erfundener AE-Title reicht in dieser Spielwiese, um einen echten Patientennamen abzufragen."
objectives:
  - "Kannst live zeigen, dass ein DICOM-Archiv ohne zusätzliche Konfiguration einen beliebigen, frei erfundenen AE-Title akzeptiert"
  - "Kannst benennen, warum Netzsegmentierung (VLAN-Trennung von Modalitäten) die naheliegende Kompensationsmaßnahme ist, nicht ein Nachrüsten der Modalität selbst"
  - "Kannst eine real veröffentlichte, aktuelle Studie zur Häufigkeit ungeschützter DICOM-Server benennen und ihre zentrale Zahl nennen"
---

## Ein frei erfundener Name genügt

Du hast in dieser Spielwiese in jeder einzelnen Lektion `-aec ORTHANC`
verwendet — den korrekten AE-Title des Archivs. Was passiert, wenn du
stattdessen einen völlig frei erfundenen Calling- **und** Called-AE-Title
benutzt?

```
$ echoscu -aet PYNETDICOM -aec ANY-SCP 127.0.0.1 4242 -v
I: Requesting Association
I: Association Accepted (Max Send PDV: 16372)
I: Sending Echo Request (MsgID 1)
I: Received Echo Response (Success)
I: Releasing Association
```
**Was du daran abliest:** `PYNETDICOM` und `ANY-SCP` sind frei
erfunden, keine echten AE-Titles dieses Aufbaus — die Association wird
trotzdem akzeptiert. Ein AE-Title ist ein Name, kein Passwort: DICOM
prüft ihn per Standard nicht kryptografisch, und diese Sandbox lässt
zusätzlich per Konfiguration (`DicomAlwaysAllowEcho`,
`containers/orthanc/orthanc.json`) jeden Called/Calling-Namen zu.

```
$ findscu -aet PYNETDICOM -aec ANY-SCP -S -k QueryRetrieveLevel=STUDY -k PatientName 127.0.0.1 4242
I: Find Response: 1 (Pending)
I: 
I: # Dicom-Data-Set
I: (0008,0052) CS [STUDY]                                  # 1 QueryRetrieveLevel
I: (0008,0054) AE [ORTHANC]                                # 1 RetrieveAETitle
I: (0010,0010) PN [MUSTER^ERIKA]                           # 1 PatientName
```
**Was du daran abliest:** Mit denselben frei erfundenen Titeln liefert
eine C-FIND-Anfrage einen echten Patientennamen zurück — ohne jede
Anmeldung, ohne dass irgendein Geheimnis gestimmt haben musste. Das ist
kein Sandbox-Bug, sondern exakt das reale Verhalten, das DICOM-Geräte
seit 1993 standardmäßig zeigen, wenn niemand zusätzlich absichert.

## Keine Einzelmeinung — real gemessen, aktuell

Eine 2026 veröffentlichte Studie von Fraunhofer SIT und der FH Münster
(„Measuring Healthcare Data Leaks and Security Flaws at Internet
Scale", Brüggemann et al.) hat genau dieses Verhalten großflächig im
echten Internet gemessen — mit derselben Methode wie oben: Association
mit Calling-AE-Title `PYNETDICOM` und Called-AE-Title `ANY-SCP`.
Ergebnis, real aus der Studie zitiert:

- **1.903** Associations wurden mit dieser Methode erfolgreich
  aufgebaut.
- In **93,54 %** dieser Fälle reichte die erfolgreiche Association
  bereits aus, um Patientendaten ohne weitere Zugriffskontrolle
  abzufragen.
- Das ergibt **1.780** real gefundene DICOM-Dienste, die Patientendaten
  an beliebige Clients herausgeben.
- Gegenprobe: **3.355** von **3.777** gescheiterten Verbindungsversuchen
  scheiterten konkret an einer AE-Title-Prüfung — es gibt also reale
  Gegenbeispiele, bei denen ein korrekt konfiguriertes System diesen
  einfachen Angriff tatsächlich abwehrt.

Die Studie selbst benennt die Ursache genauso, wie es der Test oben
zeigt: „the DICOM standard does not require vendors [or] operators to
use [security measures]. In practice, this means that DICOM systems
can be operated without any security measures in place."

## Warum die Modalität selbst selten die Lösung ist

Medizingeräte haben laut derselben Studie ungewöhnlich lange
Lebenszyklen — ein CT- oder MR-Scanner läuft oft zehn Jahre oder
länger, meist ohne laufenden Sicherheits-Patch-Support des
Herstellers. Ein AE-Title-Check nachträglich in die Modalität
einzubauen ist damit selten praktikabel: Die Software ist eingefroren,
ein Herstellerupdate oft nicht mehr verfügbar oder nur gegen hohe
Kosten und mit Rezertifizierungsaufwand.

## Netzsegmentierung als Standardkompensation

Wenn das Gerät selbst nicht nachrüstbar ist, verlagert sich die
Absicherung auf das Netz drumherum: Modalitäten in einem eigenen,
isolierten VLAN, ohne direkten Zugang zum allgemeinen Klinik- oder
Internet-Netz, mit Firewall-Regeln, die ausschließlich die Verbindung
zum PACS/Archiv erlauben. Das löst das AE-Title-Problem nicht — ein
Angreifer, der bereits im Modalitäts-VLAN sitzt, kann noch immer jeden
AE-Title verwenden — aber es reduziert real, wer diesen Angriff
überhaupt versuchen kann: nicht mehr „jeder mit Internetzugang",
sondern „jemand mit bereits vorhandenem Zugang zu genau diesem
Netzsegment".

## Die eigentlich vorhandene Alternative: DICOM TLS

DICOM definiert mit Supplement 51 eine TLS-Absicherung der
Netzwerkverbindung — bereits in Lektion 4.9 real gegen einen echten,
TLS-fähigen DCMTK-`storescp` verifiziert: eine anonyme TLS-Verbindung
funktioniert, eine Verbindung ohne vertrauenswürdiges Zertifikat wird
real mit einem reinen TLS-Fehler abgelehnt, noch vor jeder
DICOM-Statusmeldung. TLS würde das oben gezeigte Problem lösen (kein
gültiges Zertifikat, keine Verbindung) — es wird in der Praxis aber
selten eingesetzt, aus genau dem Grund wie oben: Legacy-Modalitäten,
die es nicht unterstützen, und der Aufwand einer
Zertifikatsinfrastruktur für ein Gerät, das nie für das offene
Internet gedacht war.

## Stolperfallen

- **Einen AE-Title für ein Passwort halten.** Er ist ein Klartextname,
  keine geprüfte Berechtigung — wie oben live gezeigt.
- **Annehmen, das sei ein Sandbox-spezifisches Problem.** Die reale
  2026-Studie zeigt genau dasselbe Verhalten bei über 1.700 echten,
  im Internet erreichbaren Systemen.
- **Netzsegmentierung für eine vollständige Lösung halten.** Sie
  reduziert die Angriffsfläche, beseitigt aber nicht die fehlende
  Authentifizierung selbst — innerhalb des Segments funktioniert der
  Angriff weiterhin.

## Selbstcheck

1. Welche zwei Werte hat der `findscu`-Aufruf oben absichtlich frei
   erfunden, und warum hat das trotzdem funktioniert?
2. Nenne die zentrale Zahl aus der 2026-Studie: Wie viele real
   gefundene DICOM-Dienste gaben Patientendaten ohne Zugriffskontrolle
   heraus?
3. Ein Krankenhaus segmentiert seine Modalitäten in ein eigenes VLAN.
   Ist der oben gezeigte AE-Title-Angriff damit ausgeschlossen?
