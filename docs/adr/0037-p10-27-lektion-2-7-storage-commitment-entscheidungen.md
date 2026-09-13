# 0037 — P10.27: Lektion 2.7 (Storage Commitment) — Dienst-Blickwinkel

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Lektion 2.7 ("Storage Commitment — hast du's wirklich?") lag seit
P10.24 als Gerüst vor — die letzte der drei Lektionen (2.5, 2.6, 2.7),
die die in P10.20–P10.21 gebaute Infrastruktur direkt mitnutzen
konnten. Wie erwartet: kein neuer Infrastruktur-Fund nötig, Orthanc
beherrscht Storage Commitment weiterhin nativ über die REST-API
(ADR 0031).

**Abgrenzung zu Lektion 4.8:** 4.8 zeigte Storage Commitment (Erfolg
und Ablehnung) als Teil einer Troubleshooting-Statuskette gemeinsam mit
MPPS. 2.7 erklärt den Dienst selbst — insbesondere den strukturellen
Unterschied zu MPPS (Lektion 2.6): MPPS nutzt eine einzige Assoziation
für beide Nachrichten, Storage Commitment typischerweise zwei
getrennte.

**Neuer, in dieser Slice real verifizierter Fund:** 4.8 zeigte nur die
REST-Schicht (`curl`/JSON) von Storage Commitment. Ein echter
`tshark`-Mitschnitt auf Port 4242 während einer echten
Commitment-Anfrage zeigt jetzt erstmals die zugrundeliegende
DICOM-Mechanik direkt: **zwei vollständig eigenständige
Assoziationen** — die erste trägt `N-ACTION-RQ`/`-RSP` (die Anfrage),
die zweite (vom Archiv selbst neu aufgebaut) trägt
`N-EVENT-REPORT-RQ`/`-RSP` (die Rückmeldung). Das macht den in 4.8 nur
behaupteten Satz „läuft oft über eine eigene Verbindung" erstmals
sichtbar nachweisbar, nicht nur behauptet.

## Entscheidung — Fließtext mit drei echten Beispielen, MPPS-Kontrast
als Aufhänger

**`content/lessons/2.7/de.md`**: vollständig neu geschrieben.
- Ein echter Erfolgsfall (Objekt speichern, `modalities/self`
  registrieren, Commitment anfragen, `Status: "Success"` beim zweiten
  Abruf) — dieselbe REST-Mechanik wie in 4.8, aber neu in diesem
  Slice erzeugt und verifiziert.
- Ein echter `tshark`-Mitschnitt der zugrundeliegenden DICOM-Ebene —
  neu gegenüber 4.8, zeigt zwei getrennte Assoziationen statt nur der
  REST-Wrapper-Sicht.
- Eine echte Ablehnung mit demselben `FailureReason 274` wie in 4.8,
  aber mit frischen UIDs in einer eigenen Sitzung erzeugt.
- Expliziter struktureller Kontrast zu MPPS (Lektion 2.6): dort eine
  Assoziation für beide Nachrichten, hier typischerweise zwei.

**`content/lessons/2.7/meta.yml`**: `tools` von `[storescu, orthanc]`
auf `[storescu, orthanc, tshark]` ergänzt (`tshark` wurde im Text
gebraucht, um die DICOM-Ebene zu zeigen). `status: draft` → `fertig`.

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Ein echtes Objekt in einem echten, vom Orchestrator erzeugten
  Sitzungscontainer gespeichert, UID per `dcmdump` extrahiert.
- `modalities/self` mit `AllowStorageCommitment: true` real
  registriert, Commitment angefragt — `Status: "Success"` beim zweiten
  Abruf bestätigt.
- Echter `tshark`-Mitschnitt auf Port 4242 während einer Commitment-
  Anfrage: zwei vollständig getrennte Assoziationen
  (`N-ACTION-RQ`/`-RSP`, dann separat `N-EVENT-REPORT-RQ`/`-RSP`) real
  aufgezeichnet und im Text wörtlich übernommen.
- Dieselbe Anfrage für eine real generierte, nie gespeicherte SOP
  Instance UID: echte Ablehnung mit `FailureReason 274`.
- „Spielwiese beenden" über die echte Oberfläche geklickt — Container
  vollständig entfernt (`docker ps -a` bestätigt).
- `content:validate`: 0 Verstöße (27 Lektionen, 35 Glossarbegriffe).
- `datasets/build` und `services/sandbox`: pytest erneut ausgeführt
  (unverändert von dieser Slice betroffen) — beide grün.
- Lektion 2.7 im Browser gegen den echten Stack aufgerufen — rendert
  vollständig fehlerfrei, `{{term:storage-commitment}}`-Tooltip
  korrekt.
- Alle Docker-Ressourcen dieser Slice (Compose-Stack, geteilte
  Test-Images, Sitzungscontainer/-Netz) nach Abschluss vollständig
  entfernt.

## Nicht Teil dieser Slice

- Kein Node-Stub für Lektion 2.7 (`lab.node` bleibt `null`).
- Mit 2.5, 2.6 und 2.7 abgeschlossen sind alle drei Lektionen, die die
  bestehende Track-4-Infrastruktur direkt mitnutzen konnten, jetzt
  geschrieben. Offen bleiben in Track 2 nur noch 2.1–2.4 und 2.8,
  siehe `docs/content-todo.md`.
