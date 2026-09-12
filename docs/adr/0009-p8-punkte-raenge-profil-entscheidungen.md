# 0009 — Punkte, Ränge, öffentliches Profil (P8)

Status: akzeptiert
Datum: 2026-09-15

## Kontext

Abschnitt 7 verlangt einen Rangfortschritt (Novize → Operator →
Administrator → Architekt → Bannerträger), ein Skill-Radar über fünf feste
Kategorien, First Blood und ein öffentliches, login-freies Profil samt
PDF-Export und einer opt-in-basierten Bestenliste. Der Auftrag nennt nur die
fünf Rangnamen und die fünf Skill-Kategorien als feste Fakten — alles
Übrige (Punktschwellen, First-Blood-Belohnung, Sichtbarkeit) ist eine
umkehrbare Design-Entscheidung.

## Entscheidungen

**Rang-Punktschwellen sind erfunden, aber bewusst und leicht revidierbar.**
Abschnitt 7 nennt nur die fünf Namen, keine Zahlen. Statt das zu erfinden
und als Fakt auszugeben, stehen die Schwellen (0/50/150/300/500) als
einzige `private const RANK_THRESHOLDS` in `ProfileService` — eine
Zahlenänderung, kein Redesign, falls sie sich beim Spielen als falsch
kalibriert herausstellen. Die P8-DoD selbst rechnet vor: `silent-ct` mit
einem Hint ergibt 9 Punkte und bleibt "Novize" — ein Einzel-Node reicht bei
diesen Schwellen nirgendwo hin, was für den Anfang plausibel ist.

**First Blood ist ein reines Achievement, kein Punktebonus.** Der Auftrag
nennt "First Blood" als Konzept, aber keine Bonus-Punktzahl. Statt eine
Zahl zu erfinden, ist es ein `Achievement` mit `type=first_blood`, race-sicher
über den Unique-Index `(node_id, type)` — bei einem Gleichstand gewinnt der
zuerst committete Insert, der zweite Versuch fängt die
`UniqueConstraintViolationException` ab und bleibt ein No-Op.

**Punkte sind auf `profiles.points` denormalisiert, nicht nur im
Skill-Vektor.** Ein Node kann mehrere Skills tragen; würde man Punkte in
jede passende Skill-Kategorie addieren, wäre die Summe des Skill-Vektors
kein verlässlicher Gesamtpunktestand mehr. Deshalb trägt `Profile` ein
eigenes `points`-Feld (Rang- und Bestenlisten-Sortierung), während
`skill_vector` weiterhin pro Kategorie addiert — beide werden in
`ProfileService::recomputeAfterSolve` aus derselben `node_attempts`-Summe
berechnet, bleiben also nie inkonsistent zueinander.

**Öffentliches Profil zeigt nur Rang, Punkte, Skill-Radar und First
Bloods — keine E-Mail, kein echter Benutzername-Login-Bezug.** Der Slug
(`Str::random(10)`) ist absichtlich nicht erratbar und ersetzt den Login
als Identifikator; `profiles.leaderboard_opt_in` ist **per Default `false`**,
sodass niemand ungefragt auf einer öffentlichen Bestenliste auftaucht.

**PDF-Export nutzt `barryvdh/laravel-dompdf` statt eines eigenen
Renderers.** Ein serverseitiger PDF-Export ist reine Commodity-Funktionalität
ohne DICOM-spezifischen Anteil — eine etablierte, gut gepflegte Bibliothek
spart hier Aufwand ohne Kompromiss an der Kernaufgabe der Plattform.

**Bestenliste per SQL-Sortierung über `profiles.points`, nicht live
aggregiert.** Bei wenigen Nutzern wäre eine Live-Summe über
`node_attempts` pro Seitenaufruf unproblematisch, aber `points` existiert
ohnehin schon denormalisiert (siehe oben) — die Bestenliste ist dann eine
einfache `WHERE leaderboard_opt_in = true ORDER BY points DESC`-Abfrage statt
einer zusätzlichen Aggregation.

## Manuell verifiziert (gegen den echten Stack, P8-DoD)

- `silent-ct` mit einem verwendeten Hint (-1 Punkt) gelöst über die reale
  UI (Konsolen-Fehlkonfiguration korrigiert, Sendeauftrag ausgelöst,
  Flag per `findscu` ermittelt und eingereicht) → **9 Punkte im Konto**.
- Skill-Radar zeigt **Netzwerk: 9**, alle anderen vier Kategorien bei 0.
- Rang bleibt korrekt "Novize" (unter der Operator-Schwelle von 50).
- First-Blood-Achievement für `silent-ct` wurde vergeben (Unique-Index
  bestätigt in `achievements`).
- Das öffentliche Profil unter `/de/profiles/{slug}` ist per `curl` ganz
  ohne Cookies mit `200` erreichbar; `/export` liefert `200` mit
  `Content-Type: application/pdf`.
- Bestenliste ist standardmäßig leer, solange niemand `leaderboard_opt_in`
  aktiviert (siehe automatisierte Tests).

## Nicht gebaut (bewusst)

Keine Sprach-/Zeitzonen-Varianten des PDF-Exports (nur Deutsch, wie der
Rest der Plattform) und keine Bild-Rasterisierung des SVG-Radars im PDF —
die PDF-Ansicht zeigt die Skill-Punkte als Tabelle, da `dompdf` SVG nur
eingeschränkt rendert und die Tabelle dieselbe Information verlustfrei
trägt.
