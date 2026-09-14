---
title: "Monitoring und Betriebsführung: was man messen sollte"
teaser: "Ein PACS, das erst auffällt, wenn der Radiologe sich beschwert, wird schon zu spät überwacht — Orthancs eigene REST-API liefert dafür mehr reale Kennzahlen, als man erwartet."
objectives:
  - "Kannst mindestens drei reale, über Orthancs eigene REST-API abrufbare Kennzahlen benennen (Kapazität, Konfiguration, Job-Erfolgsrate)"
  - "Kannst erklären, warum ein abgeschlossener, aber fehlgeschlagener Hintergrundjob (Progress 100, State ≠ Success) ein anderes Monitoring-Ereignis ist als ein laufender Job"
  - "Kannst einordnen, was die in dieser Lektion gezeigten Kennzahlen NICHT abdecken (z. B. Zugriffsverhalten, siehe Lektion 5.5)"
---

## Drei reale Endpunkte, drei unterschiedliche Kennzahlen-Kategorien

„Läuft" ist keine Kennzahl. Ein Archiv kann online sein und trotzdem
still gefüllt, überlastet oder mit fehlgeschlagenen Hintergrundjobs
laufen, ohne dass irgendjemand es bemerkt — bis das nächste Bild fehlt.
Orthanc liefert dafür drei reale, unterschiedliche REST-Endpunkte, die
zusammen mehr abdecken, als man von einem Archiv ohne dedizierte
Monitoring-Lösung erwarten würde.

### Kapazität: `/statistics`

```
$ curl -s http://127.0.0.1:8042/statistics
{
   "CountInstances" : 0,
   "CountPatients" : 0,
   "CountSeries" : 0,
   "CountStudies" : 0,
   "TotalDiskSize" : "0",
   "TotalDiskSizeMB" : 0,
   "TotalUncompressedSize" : "0",
   "TotalUncompressedSizeMB" : 0
}
```
**Was du daran abliest:** Ein frisches Archiv, alle Zähler bei Null.

```
$ storescu -aec ORTHANC 127.0.0.1 4242 ct-thorax-60/*.dcm
$ curl -s http://127.0.0.1:8042/statistics
{
   "CountInstances" : 60,
   "CountPatients" : 1,
   "CountSeries" : 2,
   "CountStudies" : 1,
   "TotalDiskSize" : "50760",
   ...
}
```
**Was du daran abliest:** Nach dem Senden der 60 Objekte dieser
Sitzung zählt Orthanc real 60 Instanzen und den tatsächlichen
Speicherbedarf. In einem echten Betrieb ist genau dieser Wert über
die Zeit beobachtet die Grundlage für „wann läuft die Platte voll" —
eine einzelne Messung sagt wenig, der Verlauf schon.

### Konfiguration und Version: `/system`

```
$ curl -s http://127.0.0.1:8042/system
{
   "Version" : "1.13.0",
   "DicomAet" : "ORTHANC",
   "DicomPort" : 4242,
   "ThirdPartyVersions" : {
      "dcmtk" : "3.7.0",
      "openssl" : "3.1.4",
      ...
   },
   "Performance" : {
      "ConcurrentJobs" : 2,
      "DicomThreadsCount" : 4,
      ...
   }
}
```
**Was du daran abliest:** Version und eingebettete Bibliotheksversionen
(DCMTK, OpenSSL) real abfragbar — genau die Information, die Lektion
5.6s zitierte Studie für ihren Fund zu Software-Clustern („44 % der
Server laufen mit identischer Software") ausgewertet hat. Ein
Monitoring, das diese Werte über die Zeit vergleicht, erkennt
veraltete, ungepatchte Installationen, ohne einen einzigen Scan von
außen zu brauchen.

### Durchsatz und Fehlerrate: `/jobs`

```
$ curl -s http://127.0.0.1:8042/jobs
[]
```
**Was du daran abliest:** Keine laufenden Hintergrundjobs — eine leere
Liste ist hier ein gültiger, ruhiger Zustand, kein Fehler (dasselbe
Muster wie die leere `findscu`-Antwort in Lektion 2.5).

```
$ curl -s -X POST http://127.0.0.1:8042/studies/23036cbe-d3bea139-11c4c9ac-71e1548d-0ce1cd8b/anonymize -d '{"Asynchronous":true}'
{"ID":"4ea49b82-4bfe-4037-af39-3333058e13fd","Path":"/jobs/4ea49b82-4bfe-4037-af39-3333058e13fd"}

$ curl -s http://127.0.0.1:8042/jobs/4ea49b82-4bfe-4037-af39-3333058e13fd
{
   "State" : "Success",
   "Progress" : 100,
   "EffectiveRuntime" : 0.051,
   "Content" : {
      "InstancesCount" : 60,
      "FailedInstancesCount" : 0
   }
}
```
**Was du daran abliest:** Ein real ausgelöster Hintergrundjob
(Anonymisierung von 60 Instanzen, Lektion 5.4) liefert reale
Laufzeit- und Erfolgskennzahlen: `EffectiveRuntime` (Latenz),
`FailedInstancesCount` (Fehlerrate), `State` (Erfolg/Misserfolg). Ein
Job mit `Progress: 100` und `State` ungleich `Success` ist dabei ein
eigenes, monitoring-relevantes Ereignis — der Job ist fertig
durchgelaufen, aber nicht erfolgreich, ein Zustand, den eine reine
„läuft noch/läuft nicht mehr"-Prüfung nicht von einem echten Erfolg
unterscheiden könnte.

## Was diese drei Endpunkte nicht abdecken

Alle drei Endpunkte sind **technisches** Monitoring — sie sagen, ob
das System funktioniert, nicht, ob der fachliche Workflow stimmt. Ein
Archiv kann technisch einwandfrei laufen (Kapazität ok, keine
fehlgeschlagenen Jobs) und trotzdem fachlich falsch bedient sein — im
Zusammenhang mit Lektion 5.5 wichtig: **keiner** dieser drei Endpunkte
sagt, wer wann auf ein Bild zugegriffen hat. `/jobs` zeigt
Hintergrundoperationen (Anonymisierung, Export), nicht normale
Lesezugriffe eines Radiologen — Kapazitäts- und
Job-Erfolgs-Monitoring ist ein anderes Thema als Zugriffsprotokollierung,
und beide Lücken müssen unabhängig voneinander geschlossen werden.

## Stolperfallen

- **Eine leere `/jobs`-Antwort für einen Fehler halten.** Wie bei einer
  leeren `findscu`-Antwort ist das ein gültiger Ruhezustand.
- **Technisches Monitoring für ausreichend halten.** Ein Archiv kann
  jede hier gezeigte Kennzahl im grünen Bereich haben und trotzdem
  einen falschen Workflow bedienen.
- **`/statistics` einmalig statt im Verlauf betrachten.** Eine einzelne
  Messung sagt wenig — erst der Trend über Zeit zeigt, wann eine
  Ressource knapp wird.

## Selbstcheck

1. Nenne drei reale, über Orthancs REST-API abrufbare Kennzahlen und
   den jeweiligen Endpunkt.
2. Ein Hintergrundjob zeigt `Progress: 100` und `State: Failure`. Ist
   das dasselbe wie ein noch laufender Job?
3. Ein Archiv zeigt in `/statistics` und `/jobs` durchweg unauffällige
   Werte. Ist damit sichergestellt, dass niemand unautorisiert auf
   Bilddaten zugegriffen hat?
