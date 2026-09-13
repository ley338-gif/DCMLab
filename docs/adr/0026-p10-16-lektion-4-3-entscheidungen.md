# 0026 — P10.16: Lektion 4.3 — echte Sandbox-Beispiele plus zwei Node-Verweise

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Lektion 4.3 ("Nur manche Bilder kommen an") lag seit P9 als Gerüst vor.
Beide zugehörigen Nodes sind fertig: `oversized-image` (P10.2,
Größenlimit) und `teiltransfer` (P10.10, SOP-Class-Ablehnung pro
Objekt) — aber das Schema erlaubt nur einen `lab.node`-Wert pro
Lektion (offene Lücke aus P10.10, siehe `docs/content-todo.md`). Wie
bei 4.1 und 4.2 war vorab zu prüfen, ob sich die beiden Kernursachen
der Lektion — nicht akzeptierte SOP Class, Größenlimit — live in der
echten Spielwiese reproduzieren lassen.

## Entscheidung — ein dritter Beleg, plus ein echtes, drittes Fehlerbild

**Beide engineered Ursachen wurden getestet, nicht angenommen:** ein
echtes Secondary-Capture-Objekt (SOP Class `1.2.840.10008.5.1.4.1.1.7`)
und ein echtes, rund 400 MB großes synthetisches CT-Volumen (2048×2048×16
Bit × 50 Frames) wurden beide vom selben Orthanc-Server ohne jede
Ablehnung gespeichert. Ein dritter, unabhängiger Beleg für dieselbe
Infrastrukturgrenze aus ADR 0017/0025 — diesmal für Größen- **und**
SOP-Class-Ablehnung gleichzeitig.

**Ein echtes, drittes Fehlerbild blieb aber reproduzierbar und ist der
eigentliche Kern dieser Lektion:** Ein Sendeskript, das nicht wirklich
alle Dateien schickt (z. B. weil es abbricht oder eine Datei beim
Kopieren übersehen wurde), erzeugt exakt dasselbe äußere Bild wie eine
serverseitige Ablehnung — eine unvollständige Study, ohne dass
irgendein Log einen Fehler zeigt. Das trifft direkt Lernziel 1
("Teiltransfer als eigenes Fehlerbild erkennen, statt ihn als
Netzwerkproblem zu behandeln") und ist mit dem realen Datensatz
`ct-thorax-60` (60 Dateien, `datasets.yml`) unmittelbar nachstellbar:
59 von 60 Dateien real gesendet, dann per `findscu` das reale Feld
`NumberOfStudyRelatedInstances` (PS3.4 C.6.2.1.1) abgefragt — zeigt
`59` statt `60`. Das ist der eigentliche diagnostische Kniff der
Lektion: nicht dem Sendelog vertrauen, sondern das Archiv selbst nach
seiner tatsächlichen Instanzzahl fragen.

**Für die beiden engineered Ursachen (Lernziele 2 und 3):** ehrlicher
Verweis auf die beiden fertigen Nodes — `teiltransfer` für die
SOP-Class-Ablehnung, `oversized-image` (Titel „Die Serie, die zu groß
ist") für das Größenlimit. `lab.node` bleibt `oversized-image` (bereits
gesetzt); `teiltransfer` wird nur im Fließtext genannt, ohne
toolbar-seitige Verknüpfung — die im Content-Todo dokumentierte,
akzeptierte Übergangslösung für die Ein-Node-pro-Lektion-Grenze des
Schemas.

**`content/lessons/4.3/meta.yml`**: `findscu` zu `tools` ergänzt (im
neuen Fließtext verwendet, vorher nicht deklariert). Kommentar zum
Node-Verhältnis aktualisiert.

## Manuell verifiziert (gegen den echten Stack)

- Reales Secondary-Capture-Objekt und reales ~400-MB-CT-Volumen erzeugt
  und beide erfolgreich an Orthanc gesendet — keine Ablehnung, wie
  erwartet und jetzt bestätigt.
- Realer `ct-thorax-60`-Datensatz exakt wie vom Sandbox-Orchestrator
  erzeugt (`generate.py` mit identischen Parametern aus
  `datasets.yml`/`docker_ops.py`); 59 von 60 Dateien real gesendet,
  `findscu` mit `NumberOfStudyRelatedInstances` zeigt real `59` —
  wörtlich übernommen.
- Lektion 4.3 im Browser: vollständiger Fließtext rendert fehlerfrei,
  Lab-Verweis zeigt korrekt „Node „Die Serie, die zu groß ist" (medium,
  15 Pkt.)", `teiltransfer` wird im Fließtext korrekt genannt.
- `content:validate`: keine neuen Verstöße (weiterhin 19
  vorbestehende); `findscu`-Ergänzung in `tools` verhindert eine neue
  Tool-Deklarations-Verletzung.
- Pest/Pint/PHPStan: nicht lokal ausführbar (bekanntes
  PHP-Versionsproblem); CI ist hier maßgeblich.

## Nicht gebaut / offen (siehe `docs/content-todo.md`)

- Die Ein-`lab.node`-pro-Lektion-Schema-Lücke bleibt offen — mit dieser
  Lektion ist sie zum zweiten Mal (nach P10.10) sichtbar geworden, aber
  weiterhin nicht behoben.
- Kein `storescp`-Beispiel, obwohl `meta.yml` das Werkzeug deklariert —
  für diese Lektion nicht zwingend nötig (SCU-seitige Diagnose).
