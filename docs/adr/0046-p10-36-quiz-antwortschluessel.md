# 0046 — P10.36: Echte Antwortschlüssel für die 27 Quiz-Fragen aus Track 1 (P3)

Status: akzeptiert
Datum: 2026-09-13

## Kontext

`docs/content-todo.md` (P3) beschrieb einen scheinbar offenen
Formatpunkt: `content/lessons/*/meta.yml` habe kein strukturiertes
`quiz:`-Feld, die Fragen stünden nur als freie Prosa unter `## Quiz`
in `de.md`, ohne maschinenlesbaren Antwortschlüssel — eine
"redaktionelle Entscheidung, kein Plattform-Bug".

**Bei der Prüfung stellte sich heraus, dass das Format bereits
vollständig spezifiziert war** — sowohl im ursprünglichen Projektauftrag
(`dcm-lab-agent-prompt.md`, Abschnitt 4.1) als auch in
`docs/content-schema.md` (Zeile 115ff.): `quiz:` ist ein Array aus
`{id, type: single|multi|input, answer}`, wobei `answer` ein
(0-basierter) Index bzw. bei `multi` eine Liste von Indizes bzw. bei
`input` ein exakter String ist. Es gab also keine offene Formatfrage —
nur fehlende Werte.

Die eigentliche Lücke: Für alle 27 bereits geschriebenen Fragen (9
Lektionen `1.0`–`1.8` × 3 Fragen) fehlte der `quiz:`-Block komplett.
Jede Frage ist aber eine reine Verständnisfrage zu einem in derselben
Lektion bereits real verifizierten Fakt (z. B. 1.7/q3 "UID von Explicit
VR Little Endian" — der Wert `1.2.840.10008.1.2.1` steht bereits im
Fließtext derselben Lektion) — die Antwort abzuleiten ist keine
Erfindung im Sinne von Abschnitt 13, sondern eine Ableitung aus
bereits geschriebenem, geprüftem Inhalt.

## Entscheidung

**Alle 9 `content/lessons/{1.0…1.8}/meta.yml`**: `quiz:`-Feld ergänzt,
je drei Einträge (`q1`, `q2`, `q3`), Antworten direkt aus dem
jeweiligen Lektionstext abgeleitet. Eine Abweichung vom Standardmuster
wurde dabei bemerkt und korrekt übernommen: 1.5/q3 trägt entgegen den
anderen acht Lektionen **keine** `(Freitext)`-Markierung, sondern ist
eine gewöhnliche Single-Choice-Frage mit vier Antwortoptionen —
`type: single` statt `type: input`.

Keine Fließtextänderung — die Fragen selbst waren bereits vollständig
und korrekt formuliert, es fehlte nur der strukturierte Schlüssel.

**Bewusst nicht Teil dieser Slice:** Die interaktive,
graded Spaced-Repetition-Kartenfunktion selbst (Vue-Komponente,
Backend-Auswertung, Wiederholungsplanung) existiert in der Codebasis
noch nicht — `content:sync` liest das neue `quiz:`-Feld aktuell nicht
einmal in eine Datenbankspalte ein (`ContentSync.php` kennt nur
`glossary_terms`, `tools_checked` u. a., aber kein `quiz`). Das Feld
ist damit vorerst inerte, aber jetzt vollständige und korrekte
Content-Grundlage für eine spätere, separate Engineering-Slice — genau
der in P3 beschriebene Rest der Arbeit, der nun keine redaktionelle
Klärung mehr braucht.

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Eigener isolierter Compose-Stack (Ports 55336/56336/58336):
  `content:sync` und `content:validate` im echten App-Container — 0
  Verstöße (27 Lektionen, 16 Nodes, 22 Werkzeuge, 36 Glossarbegriffe),
  unverändert gegenüber vor dieser Slice.
- Per `tinker` bestätigt: Das `quiz`-Feld landet erwartungsgemäß nicht
  in der `lessons`-Tabelle (keine solche Spalte) — die Lektionsseiten
  rendern dadurch unverändert.
- Lektion 1.7 im Browser gegen den echten Stack aufgerufen — Seite
  rendert identisch zu vorher, keine sichtbare Regression.
- `datasets/build` (5 passed), `services/sandbox` (14 passed, ruff
  clean, mypy 0 Fehler) erneut ausgeführt — unverändert, von dieser
  Slice nicht betroffen.
- Alle Docker-Ressourcen dieser Slice nach Abschluss vollständig
  entfernt.

## Nicht Teil dieser Slice

- Keine interaktive Quiz-Karten-UI, keine Spaced-Repetition-Planung,
  keine Datenbankspalte für `quiz`. Diese Engineering-Arbeit bleibt
  ein separater, deutlich größerer nächster Schritt, sobald gewünscht.
- Keine Fragen für Track 2 oder Track 4 — diese Lektionen benutzen
  bereits `## Selbstcheck` mit eingeklappten Freitext-Antworten statt
  des ursprünglichen `## Quiz`-Formats und sind davon nicht betroffen.
