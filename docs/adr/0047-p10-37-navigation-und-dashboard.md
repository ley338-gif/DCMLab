# 0047 — P10.37: Drei echte Navigationslücken behoben (Tracks-Sperre, Node-Link, Dashboard)

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Der Nutzer meldete drei konkrete, im echten Browser beobachtete
Probleme in der laufenden Entwicklungsumgebung — keine Content-, sondern
Plattformfehler:

1. **Tracks 2 und 4 blieben gesperrt**, obwohl beide seit P10.32/P10.16
   vollständig geschrieben sind (8 bzw. 10 Lektionen). Lektionen waren
   nur über einen direkten Link erreichbar, nicht über die
   Tracks-Übersicht.
2. **Kein Weg von einer Lektion zum zugehörigen Lab-Node.** Die
   Werkzeugleiste zeigt „Lab: Node „Silent CT" (easy, 10 Pkt.)" als
   reinen Text, ohne Verlinkung — obwohl die Route
   (`/de/nodes/{slug}`) und der Node-Slug in den Daten längst vorhanden
   sind.
3. **Das Dashboard war der unveränderte Laravel-Starter-Kit-Platzhalter**
   (`PlaceholderPattern`-Boxen, `Route::inertia('dashboard',
   'Dashboard')` ohne einen einzigen Prop) — nie mit echten Daten
   befüllt, obwohl die dafür nötigen Modelle (`Profile`, `LessonProgress`,
   `Achievement`, `ProfileService`) seit P8/P9 vollständig existieren.

Alle drei sind echte, im Code auffindbare Ursachen — keine Vermutung:
`content/tracks.yml: status` stand für `services`/`troubleshooting`
noch auf `planned`, obwohl beide Tracks fertigen Content haben;
`Lessons/Show.vue` rendert `lab_node` als `<span>` statt `<Link>`,
obwohl `toolbar.lab_node.slug` schon vom Backend geliefert wird;
`routes/web.php` registrierte `dashboard` als reine `Route::inertia`
ohne Controller.

## Entscheidung

**`content/tracks.yml`**: `status` für `services` und `troubleshooting`
von `planned` auf `published` gesetzt — beide Tracks sind seit P10.32
(ADR 0042) bzw. seit den Track-4-Slices (P10.7–P10.20) vollständig
geschrieben. `bild` und `betrieb` bleiben `planned`, da dort noch keine
einzige Lektion existiert.

**`apps/web/resources/js/pages/Lessons/Show.vue`**: Der Lab-Node-Hinweis
ist jetzt ein `<Link :href="showNode(toolbar.lab_node.slug)">` statt
eines reinen `<span>` — nutzt die bereits generierte
Wayfinder-Route `routes/nodes.show`.

**Neue `app/Http/Controllers/DashboardController.php`** ersetzt die
rohe `Route::inertia('dashboard', …)`. Liefert ausschließlich real
vorhandene Daten:
- Punkte und Rang aus `ProfileService::profileFor()` (bestehender,
  bereits genutzter Service — legt bei Bedarf ein `Profile` an, das
  vorher für neue Nutzer schlicht nie existierte).
- Fortschritt je veröffentlichtem Track (`completed_lessons_count` /
  `lessons_count`), über `Track::withCount()` mit einem
  `whereHas('progress', …)`-Constraint auf die echte
  `lesson_progress`-Tabelle.
- Die fünf zuletzt bearbeiteten Lektionen (`LessonProgress`, sortiert
  nach `started_at`).
- Alle `Achievement`-Einträge des Nutzers.

**Neue `resources/js/pages/Dashboard.vue`**: Drei Statuskarten
(Punkte, Rang, Lektionen gesamt), eine Track-Fortschrittsliste (klickbar
zum jeweiligen Track), zuletzt bearbeitete Lektionen (klickbar) und
Achievements — alles aus echten Props, keine Platzhalter-Grafik mehr.
Neue Übersetzungsschlüssel in `lang/de.json` ergänzt (Identity-Mapping,
wie im Rest der Datei üblich).

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Eigener isolierter Compose-Stack (Ports 55337/56337/58337):
  `content:sync` und `content:validate` — 0 Verstöße (27 Lektionen, 16
  Nodes, 22 Werkzeuge, 36 Glossarbegriffe), unverändert.
- Docker-Image-Build (Multi-Stage inkl. `npm run build`) lief ohne
  Fehler durch — kein TypeScript-/Vite-Fehler in den geänderten
  `.vue`-Dateien. (Pest/PHPUnit lassen sich in diesem Environment
  weiterhin nicht ausführen — vorbestehende, in `docs/content-todo.md`
  dokumentierte Einschränkung, kein Bezug zu dieser Slice.)
- Im Browser gegen den echten Stack: Tracks-Übersicht zeigt „Die
  Services" und „Troubleshooting" jetzt als „Verfügbar", klickbar, mit
  korrekter Lektionsliste (8 bzw. 10 Einträge).
- Lektion 1.5 direkt aufgerufen: „Lab: Node „Silent CT" (easy, 10
  Pkt.)" ist ein echter Link, führt zu `/de/nodes/silent-ct`, die Node
  lädt vollständig (Terminal, Briefing, Umgebung).
- Dashboard direkt aufgerufen: zeigt real `0 Punkte`, `Novize`, `0/27
  Lektionen`, alle drei veröffentlichten Tracks mit `0 von N
  Lektionen`. Lektion 1.5 über „Als erledigt markieren" abgeschlossen,
  Dashboard neu geladen: zeigt jetzt real `1/27`, `Fundamente: 1 von 9`,
  und die Lektion unter „Zuletzt bearbeitet" mit Badge „Erledigt" —
  echter End-to-End-Beleg, keine angenommene Funktionsweise.
- Alle Docker-Ressourcen dieser Slice nach Abschluss vollständig
  entfernt.

## Nicht Teil dieser Slice

- Kein Skill-Radar-Widget auf dem Dashboard (Daten liegen in
  `profile.skill_vector`, aber keine Visualisierung — reine
  Umfangsentscheidung, keine technische Lücke).
- Keine Migration/Backfill für bereits existierende Nutzer ohne
  `Profile`-Zeile — `ProfileService::profileFor()` erzeugt sie beim
  ersten Dashboard-Aufruf automatisch (`firstOrCreate`), kein manueller
  Eingriff nötig.
