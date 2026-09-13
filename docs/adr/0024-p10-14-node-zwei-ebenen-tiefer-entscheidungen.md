# 0024 — P10.14: Node „Zwei Ebenen tiefer" — Feature 9 (dcmdump-Hierarchiefelder)

Status: akzeptiert
Datum: 2026-09-13

## Kontext

`zwei-ebenen-tiefer` (Lektion 1.2) war der letzte verbliebene, bereits
unblockierte, aber ungeschriebene Node-Stub aus der ursprünglichen
Sieben-Node-Liste (siehe `docs/content-todo.md`). Ihr ursprünglicher
Gerüst-Kommentar nannte einen deutlich größeren Blocker als tatsächlich
nötig war: "PATIENT-/INSTANCE-Ebene im C-FIND, mehrere Studies/Serien
pro Archiv". Lektion 1.2 ist aber **kein** Gerüst mehr — ihr eigener
Abschnitt **"Dein Lab"** spezifiziert die Aufgabe bereits präzise und
deutlich kleiner: *"Im Lab Zwei Ebenen tiefer bekommst du einen Ordner
mit gemischten Objekten und die Meldung, es fehle etwas. Deine
Aufgabe: sagen, wie viele Patienten, Studies und Series darin liegen —
und welche Ebene tatsächlich unvollständig ist. Du brauchst dafür nur
die Zählschleife aus dieser Lektion, einmal je Ebene."* Das ist eine
reine lokale Zählaufgabe über mehrere Objekte — kein C-FIND, keine
Association, keine PATIENT-/INSTANCE-Ebene nötig.

## Entscheidung — Feature 9: `dcmdump`-Hierarchiefelder

**Kein Netzwerk-Feature.** Wie bei `halbe-sache` (P10.12) und
`first-contact`/`wo-steht-das` (P10.13) reicht die bereits generalisierte
`dcmdump`-Feldtabelle (`DCMDUMP_FIELD_ORDER`) — erweitert um drei
weitere reale Felder: `patient_id` (0010,0020), `study_uid` (0020,000D),
`series_uid` (0020,000E). Dieselben Tags, die `app/find.py` bereits für
echtes C-FIND-Matching nutzt (Feature 1, P10.1) — hier aber ganz ohne
Association, rein lokal per `dcmdump` auf mehrere Objekte. Details:
`content-schema.md` Abschnitt 6i.

**Node-Design:** Fünf Objekte, zwei Patienten, zwei Studies — aber nur
drei Series statt der zu erwartenden vier: Patient `4711`s Study hat
zwei Series (wie ein normaler CT-Aufbau mit z. B. Topogramm und
Nativserie), Patient `4712`s Study nur eine, obwohl sie ebenfalls zwei
Instances umfasst. Jede Ebene für sich betrachtet wirkt unauffällig —
erst der Vergleich zwischen den beiden gleichartigen Studies zeigt die
Lücke auf der Series-Ebene. Das spiegelt Lektion 1.2s eigene Eröffnung
("die Untersuchung ist doppelt drin") thematisch, ohne sie zu
kopieren: dort wird eine Untersuchung fälschlich zu zwei Studies
gesplittet, hier fehlt einer von zwei erwarteten Studies eine ganze
Series — verwandte, aber unterscheidbare Fehlerbilder auf derselben
Hierarchie. Flag ist die Patient ID der unvollständigen Study
(`4712`) — das Schema erlaubt nur ein Flag pro Node, die anderen
beiden Zählungen (Patienten, Studies) werden im Write-up trotzdem
vollständig hergeleitet.

**`content/lessons/1.2/meta.yml`**: Kommentar zum Gerüst-Status
entfernt, `lab.optional` auf `false`.

## Manuell verifiziert

- `services/engine/tests/test_dcmdump_hierarchy.py` (neu, 1 Test):
  alle drei Hierarchiefelder in echter aufsteigender Tag-Reihenfolge.
- `services/engine/tests/test_real_content.py`: neuer Test lädt die
  echte Node, dumpt alle fünf Objekte, bestätigt zwei Patienten, zwei
  Studies, drei Series, Flag korrekt.
- Vollständige Engine-Testsuite (106 Tests), `ruff check .`: grün.
  `mypy app`: ein vorbestehender, unveränderter Fehler (fehlende
  `types-PyYAML`-Stubs).

## Nicht gebaut / offen (siehe `docs/content-todo.md`)

- Mit dieser Node ist die ursprüngliche Sieben-Node-Liste aus
  `docs/content-todo.md` (Abschnitt "Track 1 — Engine-Limits")
  vollständig abgearbeitet — keine offenen, unblockierten Node-Stubs
  mehr bekannt.
