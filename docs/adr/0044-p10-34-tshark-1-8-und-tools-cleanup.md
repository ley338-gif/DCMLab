# 0044 — P10.34: Lektion 1.8 (echter tshark-Mitschnitt statt erfundener Daten) + Werkzeugleisten-Bereinigung (4.1, 4.2, 4.4)

Status: akzeptiert
Datum: 2026-09-13

## Kontext

`docs/content-todo.md` (P10.19-Abschnitt) vermerkte am Rande, dass fünf
`meta.yml`-Dateien (1.8, 4.1, 4.2, 4.4, 4.10) `tshark` deklarieren,
obwohl `tshark` zum Zeitpunkt ihres Schreibens in der Spielwiese noch
nicht funktionierte (behoben erst mit ADR 0029) — "ob sich eine
nachträgliche Überarbeitung lohnt, ist eine eigene, spätere
Entscheidung." Nach Abschluss von Track 2 wurde dieser Punkt
aufgegriffen und tatsächlich geprüft statt weiter zurückgestellt.

**Echter Befund, kein Verdacht:** Lektion 1.8 zeigte tatsächlich einen
`tshark`-Mitschnitt — aber mit erfundenen IP-Adressen
(`10.20.0.30`/`10.20.0.10`) und erfundenen PDU-Typ-Zahlen, ohne
`<!-- kein-beispiel -->`-Markierung. Ein echter Verstoß gegen Abschnitt
13 des Auftrags, der nur deshalb nie von `content:validate` erkannt
wurde, weil der Validator nur prüft, ob *irgendeine*
„Was du daran abliest"-Erklärung folgt, nicht ob die gezeigten Daten
real sind.

Lektionen 4.1, 4.2 und 4.4 dagegen zeigten `tshark` in ihrem Fließtext
**überhaupt nicht** — das Werkzeug stand nur in `meta.yml: tools`,
ohne einen einzigen `$ tshark …`-Aufruf im Text. Bei genauerer Prüfung
stellte sich heraus, dass auch weitere deklarierte Werkzeuge dort nie
gezeigt wurden: `storescu` in 4.1 und 4.4, `storescp` in 4.2. Diese drei
Lektionen sind älter als die in P10.25 etablierte Praxis, `tools:` nach
jedem Fließtext gegen die tatsächlich gezeigten Befehle abzugleichen,
und wurden dabei nie rückwirkend korrigiert.

**Warum keine neuen tshark-Beispiele für 4.1/4.2/4.4:** Das
eigentliche Thema dieser drei Lektionen (AE-Title-Ablehnung,
Presentation-Context-Ablehnung) lässt sich in der aktuellen Spielwiese
strukturell nicht live erzeugen (Orthancs Großzügigkeit, ADR 0008/0025)
— das gilt unabhängig davon, ob `tshark` funktioniert. Ein
`tshark`-Mitschnitt einer *erfolgreichen* Verbindung hätte hier keinen
zusätzlichen Erkenntniswert gegenüber den bereits vorhandenen,
echten `echoscu`-/`storescu -d`-Beispielen geliefert.

## Entscheidung

**`content/lessons/1.8/de.md`**: Der erfundene tshark-Block durch einen
echten ersetzt — Mitschnitt eines echten `echoscu`-Laufs im
Sitzungscontainer, `-T fields`-Extraktion von `dicom.pdu.type`. Echte
Werte: `0x01`/`0x02` (A-ASSOCIATE-RQ/-AC), zweimal `0x04` (P-DATA:
C-ECHO-Anfrage und -Antwort), `0x05`/`0x06` (A-RELEASE-RQ/-RP). Direkt
danach ein neuer, ehrlich markierter `<!-- kein-beispiel -->`-Block
(Muster wie in 4.1/4.2): Eine echte Ablehnung ist aus demselben Grund
wie in 4.1 nicht live erzeugbar, die beiden dafür realen,
standarddefinierten PDU-Typen (`0x03` A-ASSOCIATE-RJ, `0x07` A-ABORT,
PS3.8 Tabelle 9-1) werden benannt, ohne einen erfundenen Mitschnitt
vorzutäuschen.

**`content/lessons/4.1/meta.yml`**: `tools` von `[echoscu, storescu,
tshark]` auf `[echoscu]` reduziert — einziges im Fließtext tatsächlich
gezeigtes Werkzeug.

**`content/lessons/4.2/meta.yml`**: `tools` von `[storescu, storescp,
dcmdump, tshark]` auf `[storescu, dcmdump]` reduziert.

**`content/lessons/4.4/meta.yml`**: `tools` von `[echoscu, storescu,
tshark]` auf `[echoscu]` reduziert.

`content/lessons/4.10/de.md` wurde geprüft und zeigt bereits drei echte
`tshark`-Aufrufe mit Sitzungscontainer-Beispieldaten — kein
Nachbesserungsbedarf, entgegen der ursprünglichen Fünf-Lektionen-Liste
aus P10.19 (die zum damaligen Zeitpunkt vor Fertigstellung von 4.10
erstellt wurde).

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Isolierter Vortest (eigenständiges Orthanc + Toolbox, ohne
  Orchestrator): `tshark -i lo -Y 'dicom' -T fields -e ip.src -e
  ip.dst -e dicom.pdu.type` während eines echten `echoscu`-Laufs
  aufgezeichnet — sechs PDUs, Werte wie oben übernommen.
- Vollständig über einen echten, isolierten Orchestrator-Stack
  (docker-compose, eigene Ports) wiederholt: `content:sync` und
  `content:validate` im echten App-Container — 0 Verstöße (27
  Lektionen, 16 Nodes, 22 Werkzeuge, 36 Glossarbegriffe).
- Lektionen 1.8, 4.1, 4.2 und 4.4 im Browser gegen den echten Stack
  aufgerufen — Werkzeugleisten zeigen jetzt exakt die im Fließtext
  gezeigten Werkzeuge, der neue tshark-Block in 1.8 rendert
  fehlerfrei mit erhaltenen Tabulatoren.
- `datasets/build` (5 passed) und `services/sandbox` (14 passed, ruff
  clean) erneut ausgeführt — unverändert, von dieser Slice nicht
  betroffen. (Die zehn vorbestehenden `services/sandbox`-mypy-Fehler
  aus ADR 0042/0043 bestehen in diesem Arbeitsbaum weiter, da er vor
  dem Merge von PR #40 von `main` abgezweigt wurde — kein Bezug zu
  dieser Slice.)
- Alle Docker-Ressourcen dieser Slice (isolierter Vortest und
  Compose-Stack) nach Abschluss vollständig entfernt.

## Nicht Teil dieser Slice

- Keine neuen Beispiele für 4.1/4.2/4.4 — deren eigentliches Thema
  bleibt strukturell nicht live erzeugbar (siehe ADR 0008/0025), ein
  tshark-Mitschnitt der erfolgreichen Gegenprobe hätte keinen neuen
  Erkenntniswert gegenüber den vorhandenen Beispielen geliefert.
- `content/lessons/4.10` unverändert — bereits vollständig mit echten
  Daten.
