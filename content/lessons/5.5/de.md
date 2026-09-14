---
title: "Datenschutz, Zugriffsprotokollierung, Aufbewahrungsfristen"
teaser: "Wer hat wann welchen Befund geöffnet — und wie lange muss die Klinik das überhaupt noch aufheben? Ein echter Test zeigt: Das Archiv dieser Spielwiese protokolliert weniger, als man denkt."
objectives:
  - "Kannst den Unterschied zwischen Änderungsprotokoll (was wurde gespeichert/gelöscht) und Zugriffsprotokoll (wer hat wann etwas geöffnet) an einem echten Beispiel erklären"
  - "Kannst benennen, was der IHE-ATNA-Ansatz zusätzlich zu einem einfachen Änderungsprotokoll leistet"
  - "Kannst die reale gesetzliche Aufbewahrungsfrist für Röntgenbilder benennen und einordnen, warum sie im Konflikt mit dem DSGVO-Löschanspruch steht"
---

## Ein echter Test: Was protokolliert Orthanc eigentlich?

Bevor man über Zugriffsprotokollierung diskutiert, lohnt sich ein
Blick darauf, was das Archiv dieser Spielwiese tatsächlich mitschreibt
— nicht, was man annimmt.

```
$ storescu -aec ORTHANC 127.0.0.1 4242 instance-0001.dcm
$ curl -s http://127.0.0.1:8042/changes
{
   "Changes" : [
      {
         "ChangeType" : "NewInstance",
         "Date" : "20260914T011654",
         "ID" : "9f9efe59-0c735c5d-78a6b612-ae37b266-7cca81e3",
         "Path" : "/instances/9f9efe59-0c735c5d-78a6b612-ae37b266-7cca81e3",
         "ResourceType" : "Instance",
         "Seq" : 1
      },
      {
         "ChangeType" : "NewSeries",
         "Date" : "20260914T011654",
         "ID" : "477894fb-de4b6793-df002043-465bcdac-90bbe4be",
         "ResourceType" : "Series",
         "Seq" : 2
      },
      {
         "ChangeType" : "NewStudy",
         "Date" : "20260914T011654",
         "ID" : "97288bda-ac77fc10-c26068e1-f60d53b4-cd924628",
         "ResourceType" : "Study",
         "Seq" : 3
      },
      {
         "ChangeType" : "NewPatient",
         "Date" : "20260914T011654",
         "ID" : "e8fed7c5-621fcc32-f5db606f-efee7c98-f36cc2fa",
         "ResourceType" : "Patient",
         "Seq" : 4
      }
   ],
   "Done" : true, "First" : 1, "Last" : 4
}
```
**Was du daran abliest:** Orthancs REST-Endpunkt `/changes` protokolliert
real jede strukturelle Änderung — mit Zeitstempel und fortlaufender
Sequenznummer. Was fehlt, ist ebenso auffällig: **kein Feld für „wer"**.
Kein Nutzername, keine Client-IP, keine Session-ID — nur „was" und
„wann".

## Der entscheidende Unterschied: Lesen hinterlässt keine Spur

```
$ curl -s http://127.0.0.1:8042/instances/9f9efe59-0c735c5d-78a6b612-ae37b266-7cca81e3/file -o bild.dcm
$ curl -s "http://127.0.0.1:8042/changes?last"
{
   "Changes" : [
      {
         "ChangeType" : "NewPatient",
         "Date" : "20260914T011654",
         "ID" : "e8fed7c5-621fcc32-f5db606f-efee7c98-f36cc2fa",
         "ResourceType" : "Patient",
         "Seq" : 4
      }
   ],
   "Done" : true, "First" : 4, "Last" : 4
}
```
**Was du daran abliest:** Nach dem Herunterladen des Bildes bleibt
`/changes` bei `Seq 4` — **derselbe Stand wie vor dem Download.** Das
Öffnen/Herunterladen eines echten Bildes hinterlässt in Orthancs
eigenem Protokoll keine einzige Spur. `/changes` ist ein
**Änderungsprotokoll** (Erstellen/Löschen), kein
**Zugriffsprotokoll** (Lesen/Ansehen) — zwei unterschiedliche Dinge,
die im Alltag oft verwechselt werden.

## Warum das kein Versäumnis, sondern eine bewusste Grenze ist

Orthanc bringt keinen eingebauten Access-Log für HTTP-/DICOM-Zugriffe
mit — die Projekt-eigene Dokumentation empfiehlt für vollständige
Zugriffsprotokollierung ausdrücklich einen vorgeschalteten
Reverse-Proxy (z. B. nginx/Apache), der jede Anfrage mitschreibt,
statt sich auf Orthancs eigenes Logging zu verlassen. In dieser
Spielwiese kommt eine weitere reale Einschränkung hinzu: Die
Orthanc-Konfiguration (`containers/orthanc/orthanc.json`) setzt
`AuthenticationEnabled: false` — es gibt hier gar keine Nutzeridentität,
die protokolliert werden könnte, selbst wenn Orthanc es versuchen
würde.

## IHE ATNA: die standardisierte Antwort auf genau diese Lücke

Für echte, einrichtungsübergreifend vergleichbare Zugriffsprotokollierung
existiert ein eigenes IHE-Profil: **{{term:atna}}**. Zwei Bausteine
über das hinaus, was `/changes` bietet:

1. **Node Authentication** — Systeme authentifizieren sich gegenseitig
   (TLS mit Zertifikaten), bevor überhaupt eine Verbindung zustande
   kommt. Das beantwortet die Frage „wer" bereits auf Verbindungsebene.
2. **Audit Trail** — jedes beteiligte System sendet strukturierte
   Audit-Nachrichten (nach RFC 3881, in DICOM als Audit-Message-Format
   übernommen) an ein zentrales Audit-Repository — nicht nur „ein
   Objekt wurde gespeichert", sondern „Nutzer X hat über Anwendung Y
   auf Patient Z zugegriffen".

ATNA ist ein Muster, keine Pflicht-Implementierung, die jedes DICOM-Gerät
mitbringen muss — die Lektion 5.2 gezeigte Frage „unterstützt dieses
Gerät ATNA?" gehört deshalb in jede Beschaffungs-Checkliste (Lektion 5.8).

## Aufbewahrungsfristen: eine reale, konkrete Zahl

Nach § 127 der Strahlenschutzverordnung (StrlSchV, seit dem
31.12.2018 Nachfolgeregelung der früheren Röntgenverordnung) gilt in
Deutschland:

- **Röntgenuntersuchungen** (Bilder und Aufzeichnungen): **10 Jahre**
- **Röntgenbehandlungen**: **30 Jahre**
- **Minderjährige** (unter 18 bei der Untersuchung): Aufbewahrung bis
  zur Vollendung des **28. Lebensjahres**

**Was du daran abliest:** „Nie löschen" ist in der Praxis oft nicht
Bequemlichkeit, sondern schlicht die längste dieser drei Fristen, die
für einen gemischten Bestand gilt — bei einem Kinderröntgenbild kann
das deutlich mehr als 10 Jahre bedeuten.

## Das Spannungsfeld: Aufbewahrungspflicht vs. Löschanspruch

Art. 17 Abs. 3 DSGVO löst den scheinbaren Widerspruch zwischen
Löschrecht und gesetzlicher Aufbewahrungspflicht ausdrücklich auf:
Eine gesetzliche Aufbewahrungspflicht (wie § 127 StrlSchV) ist einer
der explizit genannten Gründe, aus denen ein Verantwortlicher eine
Löschung **verweigern darf** — die Daten dürfen währenddessen aber nur
noch für den Aufbewahrungszweck selbst verarbeitet werden, nicht mehr
für andere Zwecke. Bildgebungsdaten sind zudem Gesundheitsdaten
besonderer Kategorie nach Art. 9 DSGVO, was die Anforderungen an
technische und organisatorische Maßnahmen (wie eine echte
Zugriffsprotokollierung) zusätzlich verschärft.

## Stolperfallen

- **Ein Änderungsprotokoll für ein Zugriffsprotokoll halten.** Der
  reale Test oben zeigt: Ein Archiv kann jede Speicherung protokollieren
  und trotzdem jeden Lesezugriff komplett unbemerkt lassen.
- **Annehmen, ein PACS protokolliere automatisch, wer zugegriffen
  hat.** Ohne Authentifizierung (wie in dieser Spielwiese) gibt es gar
  keine Identität, die protokolliert werden könnte.
- **Löschanspruch und Aufbewahrungspflicht als unlösbaren Widerspruch
  behandeln.** Art. 17 Abs. 3 DSGVO regelt das Verhältnis explizit.

## Selbstcheck

1. Ein Bild wird in dieser Spielwiese heruntergeladen. Erscheint dieser
   Zugriff in Orthancs `/changes`-Protokoll? Warum (nicht)?
2. Was leistet IHE ATNA zusätzlich zu einem reinen
   Änderungsprotokoll wie `/changes`?
3. Ein Röntgenbild eines elfjährigen Patienten wird 2026 aufgenommen.
   Bis zu welchem Jahr muss es laut § 127 StrlSchV mindestens
   aufbewahrt werden?
