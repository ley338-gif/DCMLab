# 0027 — P10.17: Lektion 4.4 — echte Sandbox-Beispiele, kein Lab nötig

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Lektion 4.4 ("Timeout") lag seit P9 als Gerüst vor, ohne zugehörige
Node (`lab.node: null`). Anders als bei 4.1–4.3 war hier vorab nicht
nur zu prüfen, *ob* sich das Thema live reproduzieren lässt, sondern
*welcher* der vier geplanten Fehlerfälle es ist — die Lektion selbst
unterscheidet vier Ursachenklassen, die unterschiedlich gut in einer
einzelnen Docker-Umgebung nachstellbar sind.

## Entscheidung

**Zwei der vier Fälle sind real reproduzierbar, getestet gegen den
echten Stack:**

- **Geschlossener Port:** `echoscu` gegen einen nicht belegten Port
  liefert real `TCP Initialisation Error: [Errno 111] Connection
  refused` — eine sofortige TCP-Ablehnung, kein Timeout.
- **Offener, aber falscher Port:** `echoscu` gegen Orthancs HTTP-Port
  (8042 statt 4242) liefert real `Unknown PDU type received '0x48'` —
  die TCP-Verbindung steht, aber die Gegenstelle spricht kein DICOM.
  Ein echter, lehrreicher Verwechslungsklassiker (mehrere Dienste auf
  verschiedenen Ports derselben Gegenstelle).

**Zwei der vier Fälle sind in einer einzelnen Docker-Umgebung nicht
herstellbar** — nicht wegen derselben Orthanc-Großzügigkeit wie in
ADR 0017/0025/0026, sondern strukturell: Fall 3 (Association-Anfrage
bleibt unbeantwortet) bräuchte eine absichtlich fehlverhaltende
Gegenstelle, Fall 4 (Abbruch mitten in einer laufenden Übertragung
durch Idle Timeout/MTU) bräuchte eine Netzwerkbedingung, die sich in
einem einzelnen internen Docker-Netz nicht erzeugen lässt. Beide
Konzepte werden deshalb als `<!-- kein-beispiel -->` erklärt, gestützt
auf reale, verifizierte Fakten: die drei unabhängig konfigurierbaren
Timeout-Kategorien `--acse-timeout`/`--dimse-timeout`/
`--network-timeout` (real, aus `storescu --help`, bereits in P10.15
dokumentiert) sowie Pfad-MTU als allgemeines, nicht DICOM-spezifisches
Netzwerkphänomen.

**Kein Lab für diese Lektion.** Die P10-Roadmap sieht keinen
Timeout-spezifischen Node vor, und keiner der noch offenen Node-Stubs
passt zum Thema. `lab.node` bleibt `null` — das Frontend blendet den
Lab-Abschnitt dafür einfach aus (kein Sonderfall nötig,
`LessonController::toolbarData` prüft bereits `$nodeSlug !== null`).

**Nebenfund, direkt behoben:** Die Objectives-Frontmatter enthielt
einen unquotierten Doppelpunkt (`- Die Schicht eingrenzen: Netzweg,
Port, …`), den YAML als eigene Ein-Schlüssel-Zuordnung statt als
Klartext-Bullet interpretierte — sichtbar als `{ "Die Schicht
eingrenzen": "…" }` beim Rendern in der Browser-Verifikation. Behoben
durch Anführungszeichen um das gesamte Objective. Andere Lektionen
wurden auf dasselbe Muster geprüft — kein weiterer Fund.

## Manuell verifiziert (gegen den echten Stack)

- Beide reale Beispiele (geschlossener Port, offener Nicht-DICOM-Port)
  live gegen einen frisch gebauten Orthanc+Toolbox erzeugt, wörtlich
  übernommen; Container/Netz/Images danach vollständig entfernt.
- Lektion 4.4 im Browser: vollständiger Fließtext rendert fehlerfrei
  (nach Behebung des YAML-Fundes), kein Lab-Abschnitt sichtbar (da
  `lab.node: null`) — kein defektes UI-Element.
- `content:validate`: keine neuen Verstöße (weiterhin 19
  vorbestehende).
- Pest/Pint/PHPStan: nicht lokal ausführbar (bekanntes
  PHP-Versionsproblem); CI ist hier maßgeblich.

## Nicht gebaut / offen (siehe `docs/content-todo.md`)

- Kein Node für diese Lektion — falls künftig ein Timeout-spezifischer
  Node sinnvoll erscheint, wäre er ein komplett neuer Stub (nicht in
  der ursprünglichen Roadmap benannt).
- Kein `tshark`-Mitschnitt-Beispiel (wie bei 4.1/4.2), obwohl
  `meta.yml` das Werkzeug deklariert.
