# 0019 — P10.9: Node „Verbindung ohne Bild" — Feature 4 (Abstract-Syntax-Ablehnung)

Status: akzeptiert
Datum: 2026-09-13

## Kontext

`verbindung-ohne-bild` (Lektion 4.2) lag seit P9 nur als Gerüst vor,
blockiert durch dasselbe Engine-Limit wie `halbe-sache`: die Engine
kannte keine Presentation-Context-Aushandlung. Feature 3 (ADR 0013,
P10.3) hat das für die **Transfer Syntax** bereits gelöst und
`syntax-negotiation-fails` sowie `mitgehoert` darauf aufgebaut. Beide
Nodes drehen sich um dieselbe Ursache: eine falsche Transfer Syntax.
Lektion 4.2s eigener Gerüst-Text (`content/lessons/4.2/de.md`) plant
aber ausdrücklich **beides** — "Abstract Syntax und die angebotenen
Transfer Syntaxes" sowie "Result-Codes eines Presentation Context"
(Plural) — und nicht nur die Transfer-Syntax-Seite.

## Entscheidung — Feature 4: Abstract-Syntax-(SOP-Class-)Ablehnung

Ein Presentation Context handelt zwei unabhängige Dinge aus: den
**Abstract Syntax** (der vorgeschlagene SOP Class UID — welcher
Objekttyp) und die **Transfer Syntax** (welche Kodierung). PS3.8
Table 9-18 definiert dafür zwei getrennte Result-Werte im selben Feld:
Wert 3 "abstract-syntax-not-supported" und Wert 4
"transfer-syntaxes-not-supported" (Letzteres bereits Feature 3). Beide
sind reale, im Standard definierte Werte derselben Tabelle — Feature 4
ist also kein neues Konzept, sondern die bislang fehlende zweite Hälfte
von Feature 3.

**Neuer Engine-Mechanismus** (`services/engine/app/rules.py`,
`trigger_action`): Der Ziel-Dienst trägt optional
`accepted_sop_classes` (Liste realer SOP-Class-UIDs), die
Modalitäts-Konfiguration ein neues Feld `sop_class`. Der Check läuft
**vor** dem bestehenden Transfer-Syntax-Check (beide weiterhin erst
nach Host/Port/AE-Title, Reihenfolge aus Abschnitt 5.3 unverändert) —
schlägt der Abstract Syntax fehl, ist die Transfer Syntax irrelevant,
wie es eine echte DCMTK-Ablehnung auch zeigen würde. Details und die
neue Schema-Sektion: `content-schema.md` Abschnitt 6d.

**Reale UIDs, per pydicom verifiziert:**
- Verification SOP Class: `1.2.840.10008.1.1`
- CT Image Storage: `1.2.840.10008.5.1.4.1.1.2`
- Enhanced CT Image Storage: `1.2.840.10008.5.1.4.1.1.2.1`

**Node-Design — bewusst distinkt von `syntax-negotiation-fails`
(P10.3) und `mitgehoert` (P10.6):** Statt einer falschen Transfer
Syntax ist hier ein CT-Gerät nach einem Software-Update werksseitig auf
Enhanced CT Image Storage umgestellt — ein reales, dokumentiertes
Interoperabilitätsmuster (neuere, erweiterte Objekttypen, die ältere
Archive nicht kennen). Der Name "Verbindung ohne Bild" bildet dabei
genau den Lernpunkt ab, den auch Lektion 4.2s Gerüst-Teaser schon
nennt: **C-ECHO (Verification SOP Class) ist grün — das beweist aber
nichts über die separate Aushandlung, die C-STORE für den eigentlichen
Objekttyp braucht.** Der Node-Write-up lässt den Spieler das exakt in
dieser Reihenfolge selbst erleben (`echoscu` zuerst, dann der
fehlschlagende Sendeversuch), bevor die Ursache über
`sop-class-registrierung.txt` (fiktive Konformitätserklärung des
Klinikums, Abschnitt 8) auflösbar wird.

**`content/lessons/4.2/meta.yml`**: Kommentar zum Gerüst-Status entfernt,
`lab.optional` auf `false` — die Node ist jetzt vollständig und deckt
das Kernthema der Lektion ab. Der Lektionstext selbst (`de.md`) bleibt
weiterhin Gerüst; das ist ein separater, künftiger Schritt (analog zum
Verhältnis zwischen `mitgehoert`/P10.6 und Lektion 1.8, die zum
Zeitpunkt der Node-Fertigstellung bereits geschrieben war).

## Manuell verifiziert

- `services/engine/tests/test_abstract_syntax.py` (neu, 5 Tests):
  isolierte `NodeDefinition` prüft Ablehnung, Erfolg nach Korrektur,
  Priorität gegenüber einer (hier korrekten) Transfer Syntax, Priorität
  von Host/Port/AE-Title gegenüber Abstract Syntax, und
  unverändertes Verhalten für Nodes ohne `accepted_sop_classes`.
- `services/engine/tests/test_real_content.py`: neuer Test
  `test_verbindung_ohne_bild_is_solvable_from_the_real_content` lädt
  die echte, ausgelieferte Node — bestätigt `echoscu` erfolgreich,
  Sendeversuch mit Enhanced CT Image Storage abgelehnt
  (`abstract_syntax_not_supported`), nach Korrektur erfolgreich, Flag
  korrekt.
- Vollständige Engine-Testsuite (77 Tests), `ruff check .`: grün.
  `mypy app`: ein vorbestehender, unveränderter Fehler (fehlende
  `types-PyYAML`-Stubs, nicht durch diese Änderung verursacht).

## Nicht gebaut / offen (siehe `docs/content-todo.md`)

- Lektion 4.2s eigener Fließtext ist weiterhin Gerüst — ein separater,
  künftiger Schritt (die Node ist jetzt bereit, ihn zu stützen).
- `halbe-sache` (1.7) bleibt weiterhin Gerüst; sie ist durch Feature 3
  (nicht Feature 4) unblockiert und noch nicht gebaut.
