# 0055 — P10.46: Track 5 ("Betrieb und Integration") — acht Gerüste angelegt

Status: akzeptiert
Datum: 2026-09-14

## Kontext

Mit Track 2 (ADR 0042/0044/0045) und Track 3 (ADR 0048–0054) vollständig
abgeschlossen und veröffentlicht, ist Track 5 ("Betrieb und Integration",
`docs/konzept-lernplattform.md` Abschnitt 5, Fortgeschritten, ca. 8 Std.)
der nächste, explizit vom Nutzer benannte Track. Anders als Track 1–4
haben seine acht Lektionen laut Curriculum **keine Lab-Hinweis-Spalte** —
ein erstes Indiz, dass dieser Track überwiegend Betriebs-/Prozess-/
Rechtsthemen behandelt, nicht DICOM-Wire-Level-Mechanik.

Wie bei Track 2 (P10.24) und Track 3 (P10.39) zuerst nur Gerüste
angelegt: vollständige `meta.yml` und `de.md` mit Titel, Teaser, drei
Lernzielen und geplanter Gliederung, aber kein Fließtext, keine
Beispiele — Abschnitt 13 verbietet, Fachprosa oder Werkzeugausgaben
vorab zu erfinden.

## Entscheidung

**Acht neue Lektionen** `content/lessons/5.1` bis `5.8`, Titel direkt aus
der Curriculum-Tabelle übernommen, nicht erfunden:

| # | Titel | requires |
|---|---|---|
| 5.1 | IHE-Profile: SWF, PIR, XDS-I — was der Name verspricht | 2.5, 2.6 |
| 5.2 | Conformance Statements lesen und daraus Aussagen ableiten | 1.7, 2.1 |
| 5.3 | Migration und Archivwechsel: Fallstricke | 1.7, 2.5 |
| 5.4 | Anonymisierung, Pseudonymisierung, Forschungsdaten | 3.1, 3.6 |
| 5.5 | Datenschutz, Zugriffsprotokollierung, Aufbewahrungsfristen | 5.4 |
| 5.6 | Security: Netzsegmentierung, Legacy-Modalitäten, Angriffsflächen | 2.1, 5.5 |
| 5.7 | Monitoring und Betriebsführung: was man messen sollte | 2.6, 5.6 |
| 5.8 | Beschaffung: die richtigen Fragen an den Hersteller | 5.2, 5.6 |

**Neu gegenüber Track 2/3: erstmals `sandbox.required: false` ohne
`dataset`-Schlüssel**, für alle acht Lektionen. Vor dem Schreiben des
ersten Gerüsts wurde geprüft, ob das schema-/validator-seitig überhaupt
zulässig ist: `ContentValidate::checkDatasetReference()`
(`apps/web/app/Console/Commands/ContentValidate.php:214`) prüft einen
Datensatz-Slug ausschließlich, wenn `sandbox.dataset` nicht `null` ist —
fehlt der Schlüssel komplett (wie hier), wird sofort zurückgekehrt, ohne
Verstoß. `checkLessonStructure()` und `checkLessonTools()` prüfen
`sandbox.required`/`lab.node` an keiner Stelle. Diese Annahme wurde
zusätzlich empirisch bestätigt (siehe unten) — nicht nur aus dem
Quellcode abgeleitet.

Grund für die Abweichung: IHE-Profile (5.1), Conformance Statements
(5.2), Migration (5.3), Datenschutz/Protokollierung (5.5), Security
(5.6) und Beschaffung (5.8) sind Betriebs-/Prozess-/Rechtsthemen, die
sich mit der vorhandenen Toolbox (Orthanc + DCMTK-Kommandozeile) nicht
sinnvoll als Hands-on-Übung abbilden lassen, ohne Beispiele zu
erfinden — genau das verbietet Abschnitt 13. Für 5.4 (Anonymisierung)
und 5.7 (Monitoring) ist ein echter, kleiner Hands-on-Baustein
denkbar (`dcmodify`-basierte Tag-Entfernung bzw. Orthancs
REST-`/statistics`-Endpunkt) und wurde in „Was zum Schreiben noch
fehlt" als zu prüfender Kandidat vermerkt — für das Gerüst bleibt es
vorsichtig bei `false`, bis das in der jeweiligen Lektions-Slice
tatsächlich verifiziert ist. 5.8 ist zusätzlich als reine Synthese der
übrigen sieben Lektionen markiert und sollte als letzte Lektion des
Tracks geschrieben werden.

`content/tracks.yml: betrieb.status` bleibt `planned`, bis alle acht
Lektionen tatsächlich geschrieben sind — derselbe Maßstab wie bei
Track 2 (ADR 0042/PR #44) und Track 3 (ADR 0054/PR #52).

## Manuell verifiziert (gegen den echten Stack)

- Eigener isolierter Compose-Stack (`docker compose -p p10-46`, Ports
  15433/16380/18090 — bewusst weit weg von der Live-Umgebung des
  Nutzers und von früher verwendeten Testports gewählt).
- `content:validate` im echten App-Container: **0 Verstöße** (41
  Lektionen — 33 vorher + 8 neue Gerüste —, 16 Nodes, 23 Werkzeuge, 44
  Glossarbegriffe) — bestätigt empirisch, dass `sandbox: {required:
  false}` ohne `dataset`-Schlüssel tatsächlich validator-tolerant ist,
  nicht nur laut Code-Lektüre.
- `content:sync` im echten App-Container: **5 Tracks, 41 Lektionen, 16
  Nodes** synchronisiert — bestätigt, dass Track 5 korrekt als fünfter
  Track erkannt wird.
- Alle `requires`-Referenzen (2.1, 2.5, 2.6, 3.1, 3.6, 1.7, 5.2, 5.4,
  5.5, 5.6) zeigen auf tatsächlich existierende Lektions-IDs — vor dem
  Schreiben per Verzeichnislisting geprüft, nicht angenommen.
- Alle Docker-Ressourcen dieser Slice (projekt-eigene `p10-46-*`
  Images/Container/Volumes) nach Abschluss vollständig entfernt; die
  geteilten `dcmlab/toolbox:latest`/`dcmlab/orthanc:latest`-Images vor
  und nach der Slice per `docker images --filter reference='dcmlab/*'`
  geprüft — unangetastet (diese Slice erzeugt ohnehin keine
  Sandbox-Sessions, da keine Lektion `sandbox.required: true` setzt).
- `datasets/build`- und `services/sandbox`-Regressionssuites bewusst
  **nicht** erneut lokal ausgeführt — diese Slice ändert ausschließlich
  Content-YAML/Markdown, keinen Python-/PHP-Code; die CI-Pipeline
  deckt beide Suites ohnehin unverändert ab.

## Nicht Teil dieser Slice

- Kein Fließtext, keine Beispiele, keine Nodes für 5.1–5.8 — folgt
  lektionsweise in den nächsten Slices, wie bei Track 2/3.
- Keine Entscheidung, ob 5.4/5.7 tatsächlich einen echten
  Hands-on-Baustein bekommen — wird erst bei der jeweiligen Lektion
  empirisch geprüft, nicht vorab angenommen. Falls sich das trägt, wird
  `sandbox.required` in der jeweiligen Lektions-PR nachträglich auf
  `true` korrigiert.
