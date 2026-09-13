# 0036 — P10.26: Lektion 2.6 (MPPS) — Dienst-Blickwinkel

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Lektion 2.6 ("MPPS: Status-Rückmeldung der Modalität") lag seit P10.24
als Gerüst vor. Wie bereits für Lektion 2.5 vorhergesagt, war kein
neuer Infrastruktur-Fund nötig: Die Spielwiese hat seit P10.21
(ADR 0031) einen echten MPPS-SCP
(`containers/toolbox/scripts/mppsscp.py`) — dieselbe Infrastruktur,
die Lektion 4.8 bereits nutzt.

**Abgrenzung zu Lektion 4.8:** 4.8 (Troubleshooting) behandelt MPPS
gemeinsam mit Storage Commitment als Teil einer Statuskette, die
reißen kann. 2.6 (Services) erklärt MPPS als eigenständigen Dienst —
die beiden Nachrichten, ihr Verhältnis zueinander, und (neu gegenüber
4.8) die dritte mögliche Endmeldung `DISCONTINUED`.

**Neuer, in dieser Slice real verifizierter Zustand:** 4.8 zeigte nur
den Übergang `IN PROGRESS` → `COMPLETED`. `DISCONTINUED` ist laut
DICOM-Standard ein ebenso gültiger Endzustand (eine abgebrochene statt
abgeschlossenen Untersuchung) — real gegen den bestehenden MPPS-SCP
getestet: ein `N-SET` mit `PerformedProcedureStepStatus=DISCONTINUED`
wird identisch mit Status `0x0` (Success) beantwortet wie ein
`COMPLETED`. Der DIMSE-Erfolgsstatus beschreibt nur die Zustellung der
Meldung, nicht deren Inhalt — ein Punkt, der in 4.8 nicht vorkam, weil
dort nur ein einziger, erfolgreicher Ablauf gezeigt wurde.

## Entscheidung — Fließtext mit zwei vollständigen Zyklen (COMPLETED
und DISCONTINUED), kein Infrastruktur-Wiring nötig

**`content/lessons/2.6/de.md`**: vollständig neu geschrieben.
- Ein vollständiger, echter `N-CREATE`/`N-SET`-Zyklus mit realistischem
  Attributsatz (Patient, geplante Prozedur, Start-/Endzeit) für den
  `COMPLETED`-Fall.
- Derselbe Zyklus, vollständig ausgeschrieben (kein gekürzter
  Pseudocode — die Beispielregel verlangt kopierbare, vollständige
  Befehle) für den `DISCONTINUED`-Fall — beide mit echtem
  `hex(status.Status)`-Ergebnis `0x0`.
- Ein echter `tshark`-Mitschnitt beider Assoziationen — zeigt, dass
  beide Zyklen auf der Leitung identisch aussehen (zwei vollständige
  A-ASSOCIATE/N-CREATE/N-SET/A-RELEASE-Folgen), der Unterschied steckt
  ausschließlich im DIMSE-Nachrichteninhalt, nicht im Ablaufmuster.

**`content/lessons/2.6/meta.yml`**: `objectives` Punkt 2 umformuliert
(„die drei möglichen Zustände" statt nur „IN PROGRESS von COMPLETED
abgrenzen"), `tools` von `[pynetdicom, dcmdump]` auf
`[pynetdicom, tshark]` korrigiert (`dcmdump` wurde im geschriebenen
Text nicht gebraucht, `tshark` dagegen schon). `status: draft` →
`fertig`.

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Beide Zustandsübergänge (`COMPLETED` und `DISCONTINUED`) in einem
  echten, vom Orchestrator erzeugten Sitzungscontainer erzeugt —
  Session über die echte Produkt-Oberfläche gestartet, MPPS-SCP im
  echten Toolbox-Container gestartet.
- `hex(status.Status)` für beide `N-CREATE`/`N-SET`-Paare real
  geprüft: alle vier Nachrichten mit `0x0` beantwortet.
- Das SCP-eigene Log bestätigt beide empfangenen Statuswerte
  (`PerformedProcedureStepStatus=COMPLETED` bzw. `=DISCONTINUED`).
- Echter `tshark`-Mitschnitt über beide vollständigen Assoziationen
  hinweg (24 Pakete, zwei komplette A-ASSOCIATE/A-RELEASE-Zyklen).
- „Spielwiese beenden" über die echte Oberfläche geklickt — Container
  vollständig entfernt (`docker ps -a` bestätigt).
- `content:validate`: 0 Verstöße (27 Lektionen, 35 Glossarbegriffe).
- `datasets/build` und `services/sandbox`: pytest erneut ausgeführt
  (unverändert von dieser Slice betroffen) — beide grün.
- Lektion 2.6 im Browser gegen den echten Stack aufgerufen — rendert
  vollständig fehlerfrei, `{{term:mpps}}`-Tooltip korrekt.
- Alle Docker-Ressourcen dieser Slice (Compose-Stack, geteilte
  Test-Images, Sitzungscontainer/-Netz) nach Abschluss vollständig
  entfernt.

## Nicht Teil dieser Slice

- Kein Node-Stub für Lektion 2.6 (`lab.node` bleibt `null`).
- `mppsscp.py` selbst wurde nicht geändert — der Erfolgsstatus `0x0`
  für jeden beliebigen `PerformedProcedureStepStatus`-Wert ist bereits
  das reale, unveränderte Verhalten aus P10.21.
