# ADR 0067 — P10.64: `min_per_lesson` für cross-lastige Track-Prüfungen

## Status

Angenommen.

## Kontext

ADR 0066 führte die Track-Abschlussprüfung ein und verlangte in
`ContentValidate::checkExamStructure()` für jede Lektion eines Tracks
mindestens vier eigene Poolfragen. Das passte für die Tracks
„Fundamente", „Die Services", „Das Bild selbst" und „Betrieb und
Integration" — jede ihrer Lektionen trägt ein eigenständiges,
abgrenzbares Wissensgebiet.

Track 4 („Troubleshooting") ist strukturell anders, und der P10-Prompt
sagt das explizit voraus (Abschnitt „Anschluss: die übrigen Tracks"):
*„Track 4 (Troubleshooting) ist der Sonderfall: dort sind praktisch
alle Fragen cross, weil jede Störung mehrere Lektionen berührt. […] die
Mindestquote je Lektion muss dort gelockert werden."* Jede der zehn
Lektionen dieses Tracks (4.1–4.10) behandelt selbst schon ein
Fehlerbild, das mehrere Konzepte aus den Tracks 1–3 verknüpft (z. B.
4.1 kombiniert TCP-Ebene und AE-Title-Konfiguration aus 1.5/1.6, 4.8
verbindet MPPS und Storage Commitment aus 2.6/2.7). Eine feste
Vier-Fragen-Quote *je Lektion* hätte hier künstliche, nicht wirklich
lektionsspezifische Fragen erzwungen, nur um die Zahl zu erreichen.

## Entscheidung

`exam.yml` bekommt ein neues, optionales Feld `min_per_lesson`
(Ganzzahl, ≥ 0). Fehlt es, gilt weiterhin der bisherige Default von 4 —
alle vier bestehenden Track-Prüfungen sind davon unberührt und
brauchten keine Änderung. Für Track 4 ist es auf `1` gesetzt: Jede der
zehn Lektionen trägt genau eine eigene, unmittelbar auf ihr Fehlerbild
bezogene Frage; der übrige, weit größere Teil des Pools (14 von 24
Fragen, 58 %) sind echte `cross`-Fragen. Ein negativer oder
nicht-ganzzahliger Wert wird von `ContentValidate` selbst als Verstoß
gemeldet, mit demselben Fallback auf 4 für die restliche Prüfung dieser
Datei.

Bewusst *nicht* gewählt: das Feld ganz abzuschaffen (dann hätten alle
Tracks keine Garantie mehr für lektionsspezifische Fragen) oder einen
Sonderfall nur für den Slug `troubleshooting` fest im Code zu verankern
(das wäre keine allgemeine, wiederverwendbare Regel für einen künftigen
ähnlich gelagerten Track).

## Konsequenzen

- `ContentValidate::checkExamStructure()` liest `min_per_lesson` aus
  `meta['min_per_lesson'] ?? 4` und validiert seinen Typ.
- `content-schema.md` Abschnitt „Track-Prüfung" dokumentiert das Feld.
- Neue Tests in `ContentValidateTest`: ein gültiger Override ändert das
  Ergebnis nicht, ein ungültiger (negativer) Wert erzeugt einen
  eigenen, klar benannten Verstoß.
- `review`-Ziele der `cross`-Fragen in Track 4 zeigen teils auf
  Lektionen anderer Tracks (1.6, 1.8, 2.2, 2.5, 2.6, 2.7) — das war
  schon vorher technisch möglich (`checkExamReview()` löst `review`
  global über alle Lektionen auf, nicht nur innerhalb des eigenen
  Tracks), wird hier aber zum ersten Mal genutzt, weil das Fehlerbild
  in Track 4 gerade auf Wissen aus den Tracks 1–3 aufbaut.
