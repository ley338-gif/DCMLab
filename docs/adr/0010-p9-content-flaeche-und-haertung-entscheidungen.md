# 0010 — Content-Fläche und Härtung (P9)

Status: akzeptiert
Datum: 2026-09-16

## Kontext

P9 ist die letzte Phase aus Abschnitt 10: Gerüste für Track 1 und Track 4,
mindestens zehn Node-Definitionen, Betriebsdokumentation, Rate Limits,
Fehlerseiten und rechtliche Platzhalter. Track 1 war vollständig, Track 4
existierte nur als uncommittete Arbeit einer parallel laufenden Session im
selben Checkout (siehe unten). Die Node-Engine kann strukturell nur eine
einzige Fehlerklasse simulieren (Host/Port/Called-AE/Calling-AE), was die
Node-Strategie dieser Phase bestimmt hat.

## Entscheidungen

**Track-4-Gerüste einer parallel laufenden Session übernommen, nicht neu
geschrieben.** Eine zweite interaktive Session (`dcmlab-1f`) hatte
`content/lessons/4.1`–`4.10` bereits als Gerüste im selben Checkout
angelegt, aber nie committet. Der Nutzer hat entschieden, diese Dateien
unverändert zu übernehmen statt sie unabhängig neu zu erfinden (kein
doppelter Aufwand, keine widersprüchliche zweite Fassung derselben
Curriculum-Zeilen).

**Node-Strategie: drei spielbare Nodes, sieben ehrliche Gerüste, keine
Fantasie-Umgebungen.** Track 1 verweist bereits im ursprünglich
mitgelieferten Content (nicht von mir erfunden) auf sieben feste
Node-Slugs (`first-contact`, `zwei-ebenen-tiefer`, `wo-steht-das`,
`zwillinge`, `neue-node`, `halbe-sache`, `mitgehoert`), die es nie gab —
mit `lab.optional: false`. Eine Prüfung von `services/engine/app/rules.py`
zeigt: Die simulierte Engine kennt ausschließlich eine
Association-Prüfung in fester Reihenfolge (Host unbekannt → falscher Port
→ Called AE → Calling AE → akzeptiert), keine Datei-Introspektion
(`dcmdump` auf lokale Dateien liefert nur einen Platzhaltertext), keine
Mehrfach-Studies pro Archiv und keine Presentation-Context-/
Transfer-Syntax-Aushandlung.

Von den sieben Slugs passt nur `neue-node` (Lektion 1.6, „AE Title, Host,
Port: das Adress-Trio") zur vorhandenen Engine — sie ist jetzt vollständig
und spielbar, ausschließlich aus dem Schema erzeugt (kein neuer
Engine-Code, wie schon Wrong Door in P6): ein neu installierter
MR-Scanner mit drei falschen Werkseinstellungen (Host, Port, AE-Title),
die der Lernende nacheinander korrigiert. Die anderen sechs Slugs plus ein
neuer Track-4-Slug (`verbindung-ohne-bild`, Lektion 4.2) sind jetzt echte,
aber absichtlich leere Gerüste (`node.yml` ohne `environment`, `de.md` mit
einer klar markierten Gerüst-Sektion) — jedes benennt sein konkretes
fehlendes Engine-Feature (Details: `docs/content-todo.md`). Die
betroffenen Lektionen bekamen `lab.optional: true`, damit das Feld nicht
länger eine Node als Pflicht ausgibt, die es nicht gibt.

**Dritter Datensatz `ct-thorax-2-slices` mit Serie „Thorax 3.0 B60f"
angelegt.** Die beiden kanonischen Serienbezeichnungen aus
`content-schema.md` Abschnitt 8 sind bereits an Silent CT und Wrong Door
vergeben; ein dritter, spielbarer Node braucht einen eigenen Flag-Wert.
Die neue Bezeichnung folgt exakt demselben Namensschema
(Schichtdicke + Rekonstruktionskern) und bleibt synthetisch (Leitplanke 2).

**Rate Limits nur auf den Endpunkten, die Nutzercode ausführen lassen.**
`throttle:60,1` auf allen `nodes/{node}/*`-Routen (Engine-Simulation),
`throttle:30,1` auf `sandbox/{id}/*` (echte Container) und `throttle:10,1`
auf dem Erzeugen einer Spielwiese-Sitzung aus einer Lektion — bewusst
enger, weil das einen Container-Start auslöst. Bestehende
Auth-/2FA-Limits aus P2 (Fortify) bleiben unverändert.

**Eigene Fehlerseiten nur wenn `APP_DEBUG=false`.** Der
Exceptions-Handler in `bootstrap/app.php` rendert für 403/404/419/429/
500/503 eine Inertia-Seite `Error.vue` statt der Laravel-Standardseite —
aber nur außerhalb des Debug-Modus, damit lokale Entwicklung weiterhin
die volle Stacktrace-Seite bekommt. Der Handler fängt zusätzlich jeden
Fehler beim Rendern der eigenen Fehlerseite selbst ab (z. B. fehlendes
Vite-Manifest in einem frischen Checkout ohne `npm run build`) und fällt
dann auf die ursprüngliche Antwort zurück — ein Fehlerhandler darf nie
selbst eine unbehandelte Exception werfen.

**`phpunit.xml` setzt jetzt `APP_DEBUG=false`.** Ohne diese Zeile lief die
gesamte Testsuite seit P0 mit `APP_DEBUG=true` aus der geerbten `.env` —
das hätte den neuen Fehlerseiten-Test unbemerkt falsch bestehen lassen
(er hätte "richtig", aber aus dem falschen Grund gezeigt, dass im
Debug-Modus die Standardseite erscheint). Produktionsnahes Verhalten
gehört in die Testkonfiguration, nicht in einzelne Tests hineingeraten.

**`fakerphp/faker` von `require-dev` nach `require` verschoben.** Ein
echter, mit dem P9-DoD gefundener Bug: `make seed` auf einem frischen
Server ruft `DatabaseSeeder` → `User::factory()` auf, dessen
`definition()` bedingungslos `fake()->name()` und
`fake()->unique()->safeEmail()` aufruft — auch wenn beide Felder später
überschrieben werden. Das Docker-Image baut mit `composer install
--no-dev`, hatte Faker also gar nicht installiert. Da dieses Projekt
`make seed` als dokumentierten, unterstützten Schritt für einen frischen
Server führt (nicht nur für Tests), gehört die Abhängigkeit in `require`.

**Rechtliche Seiten sind absichtlich unausgefüllte Platzhalter.**
Impressum, Datenschutz und Nutzungsbedingungen sind öffentlich erreichbar
und im Footer verlinkt, tragen aber `[Platzhalter]`-Markierungen statt
erfundener Firmenangaben — Abschnitt 13 verbietet erfundene Fakten, und
eine Adresse oder ein Registereintrag wäre genau das. Die
Nutzungsbedingungen benennen den einzigen bereits wahren, nicht
erfundenen Fakt ausdrücklich: die Spielwiese führt Nutzercode in echten
Containern aus.

## Manuell verifiziert (gegen den echten Stack, P9-DoD)

- `make up && make seed` auf einer frischen Instanz (eigener
  Compose-Projektname, eigene Ports, damit die parallele Session
  ungestört weiterläuft): `content:sync` meldet 5 Tracks, 19 Lektionen,
  10 Nodes.
- Node `neue-node` vollständig gelöst über die echte UI/API: alle drei
  Association-Stufen (Host → Port → AE) treten in der dokumentierten
  Reihenfolge mit den erwarteten Meldungen auf, Sendevorgang überträgt
  2 von 2 Objekten, Flag „Thorax 3.0 B60f" korrekt, 25 Punkte, Rang- und
  Skill-Radar-Update (`netzwerk: 25`) über `ProfileService` bestätigt.
- Lektion 1.6 zeigt korrekt „Lab: Node „Neue Node" (medium, 25 Pkt.)" —
  ihr ursprünglich mitgelieferter Fließtext beschreibt exakt dieses
  Szenario („ein Gerät kommt ins Haus, Angaben teilweise falsch").
- Ein Node-Gerüst (`first-contact`) im Browser aufgerufen: rendert ohne
  Fehler, zeigt die Gerüst-Notiz, keine funktionslosen UI-Elemente.
- Öffentliches Profil, Bestenliste, Impressum, Datenschutz,
  Nutzungsbedingungen ganz ohne Login per `curl` erreichbar.

## Nicht gebaut (bewusst)

Kein Ausbau der Node-Engine um die fehlenden Fähigkeiten (Datei-Lesen,
Mehrfach-Bestand, Presentation-Context-Aushandlung) — das wäre ein
eigenes Feature mit eigenem Umfang, nicht Teil von "Content-Fläche und
Härtung" in dieser Phase. Kein Metrik-Dashboard (siehe `docs/betrieb.md`).
Keine automatisierte Backup-Cron-Job-Einrichtung (hosting-abhängig,
außerhalb dieses Repos).
