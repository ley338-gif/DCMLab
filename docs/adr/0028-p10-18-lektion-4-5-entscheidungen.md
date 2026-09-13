# 0028 — P10.18: Lektion 4.5 — vollständig real, kein Lab nötig

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Lektion 4.5 ("Studie ist gesplittet / doppelt") lag seit P9 als Gerüst
vor, ohne zugehörige Node. Anders als bei 4.1–4.3 (Presentation
Context) betrifft ihr Thema reine Dateninhalte (Study Instance UID),
keine Verbindungs- oder Ablehnungsmechanik — deshalb war vorab zu
prüfen, ob sich Split und Dublette live erzeugen und **unterscheiden**
lassen, nicht nur, ob überhaupt etwas reproduzierbar ist.

## Entscheidung — vollständig real, wie Lektion 4.6

**Beide Fälle wurden tatsächlich erzeugt und ausgewertet, nicht nur
angenommen:**

- **Dublette:** Dieselben drei real generierten Dateien wurden zweimal
  per `storescu` gesendet. `findscu` zeigt danach weiterhin **eine**
  Study mit `NumberOfStudyRelatedInstances` `3` — unverändert. Realer
  Beleg dafür, dass DICOM-Archive Instances an ihrer SOP Instance UID
  erkennen (Lektion 1.4) und ein erneutes Einspielen keinen sichtbaren
  Fehler erzeugt.
- **Split:** Eine zweite, unabhängig generierte Aufnahme (gleicher
  Patient, gleiche Study Description, aber frische UIDs) wurde
  gesendet. `findscu` liefert danach zwei getrennte
  `Find SCP Response`-Blöcke mit zwei unterschiedlichen
  `StudyInstanceUID`-Werten.

Der Kontrast zwischen beiden realen Ergebnissen — eine Antwort vs. zwei
Antworten, gleiche Instance-Zahl vs. zwei getrennte Studies — ist der
eigentliche Kern der Lektion und vollständig ohne
`<!-- kein-beispiel -->`-Block darstellbar, genau wie bei Lektion 4.6
(ADR 0018): Das Thema betrifft Dateninhalte, nicht die
Orthanc-Großzügigkeit bei Verbindungs-/Ablehnungsmechanik, die 4.1–4.3
einschränkt.

**Kein Lab.** Kein passender Node-Stub für Split-vs.-Dublette
vorhanden. Node "Zwillinge" (Lektion 1.4) behandelt ein verwandtes,
aber unterschiedliches Problem — zwei ähnlich aussehende, aber
*legitim eigenständige* Studies anhand der Accession Number
unterscheiden, nicht Split/Dublette diagnostizieren — und wird deshalb
nur unter "Verwandte Inhalte" verlinkt, nicht als `lab.node`.

**`content/lessons/4.5/meta.yml`**: `storescu` zu `tools` ergänzt (im
neuen Fließtext erwähnt).

## Manuell verifiziert (gegen den echten Stack)

- Realer 3-Instance-Datensatz erzeugt, zweimal gesendet — `findscu`
  bestätigt unveränderten Bestand (Dublette).
- Zweiter, real generierter Datensatz mit frischen UIDs gesendet —
  `findscu` bestätigt zwei getrennte Study-Einträge (Split).
- `dcmdump` und `dcm2json` auf realen Dateien für den
  UID-Direktvergleich — wörtlich übernommen.
- Lektion 4.5 im Browser: vollständiger Fließtext rendert fehlerfrei,
  kein Lab-Abschnitt sichtbar (erwartetes Verhalten bei
  `lab.node: null`).
- `content:validate`: keine neuen Verstöße (weiterhin 19
  vorbestehende).
- Container/Netz/Images danach vollständig entfernt.
- Pest/Pint/PHPStan: nicht lokal ausführbar (bekanntes
  PHP-Versionsproblem); CI ist hier maßgeblich.

## Nicht gebaut / offen (siehe `docs/content-todo.md`)

- Kein Node für Split-vs.-Dublette — ein möglicher künftiger,
  eigenständiger Node-Stub, in der ursprünglichen Roadmap nicht
  benannt.
