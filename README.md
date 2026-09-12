# DCM Lab

Lernplattform für DICOM und PACS. Arbeitsstand 12.09.2026.

Dieses Archiv enthält den vollständigen Stand: Konzept, verbindliche Schemas,
Track 1 komplett (neun Lektionen), eine ausgearbeitete Node und den spielbaren
Prototyp dazu.

## Aufbau

```
docs/          Konzept, Schemas, Ablaufpläne — das Denken hinter der Plattform
content/       Der Lernstoff im verbindlichen Repo-Format (content-schema.md)
prototyp/      Die spielbare Node "Silent CT" als einzelne HTML-Datei
```

## Wo anfangen

| Du willst … | Lies |
|---|---|
| verstehen, worum es geht | `docs/konzept-lernplattform.md` |
| Content schreiben | `docs/content-schema.md`, dann `docs/werkzeug-registry.md` |
| die Node testen | `prototyp/silent-ct.html` im Browser öffnen |
| die Testrunde starten | `docs/testrunde-phase-0.md` |
| eine Lektion lesen | `content/lessons/1.0/de.md` |

## Stand

**Fertig:** Konzept · Content-Schema · Werkzeug-Registry · Testrunde-Ablauf ·
Track 1 (1.0 bis 1.8) · Node "Silent CT" inklusive spielbarem Prototyp

**Offen:**

- Sieben Node-Slugs werden von den Lektionen referenziert, existieren aber noch
  nicht: `first-contact`, `zwei-ebenen-tiefer`, `wo-steht-das`, `zwillinge`,
  `neue-node`, `halbe-sache`, `mitgehoert`
- `content/glossary/de.yml` ist nur begonnen — die `{{term:…}}`-Markierungen in
  den Lektionen zeigen teilweise ins Leere
- `content/datasets.yml` fehlt
- `content:validate` ist beschrieben, aber nicht implementiert
- Track 2 bis 5 sind im Konzept geplant, nicht geschrieben
- Track 4 braucht echte Vorfälle aus dem Klinikbetrieb, keine erfundenen

**Nächster Schritt laut Konzept:** nicht weiterschreiben, sondern die Node an
fünf Kollegen geben. Ablauf in `docs/testrunde-phase-0.md`.

## Prüfliste für den gemeinsamen Durchgang

Die Stellen, an denen eine zweite Meinung am meisten wert ist:

- Exakte Schreibweise der dcmtk-Optionen: `dcmconv +ti`, `dcmcjpeg +e1`,
  `dcmdump +P`, `dcmdump -f`, `storescp -od`
- Wortlaut der Werkzeugausgaben, besonders die Ergebnisse der Presentation
  Contexts in 1.8
- Ob die Fehlerbilder realistisch sind — geschrieben aus Standardwissen,
  nicht aus Vorfällen eines echten Hauses

---

DICOM ist eine eingetragene Marke der NEMA. Dieses Projekt steht in keiner
Verbindung zu NEMA.
