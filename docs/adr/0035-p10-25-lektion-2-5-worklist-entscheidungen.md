# 0035 — P10.25: Lektion 2.5 (Modality Worklist) — Dienst-Blickwinkel

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Lektion 2.5 ("Modality Worklist (MWL): das meistunterschätzte Thema")
lag seit P10.24 als Gerüst vor. Ihre eigene Notiz sagte bereits voraus,
dass keine neue Infrastruktur nötig sein würde: Die Spielwiese hat seit
P10.20 (ADR 0030) einen echten Worklist-Dienst — Orthancs
Worklists-Plugin, pro Sitzung frisch generierter Auftrag aus
`content/worklists.yml`. Bestätigt: kein Infrastruktur-Fund in diesem
Slice, nur Fließtext.

**Abgrenzung zu Lektion 4.7:** Beide Lektionen nutzen dieselbe echte
Infrastruktur, aber mit unterschiedlichem Blickwinkel. 4.7
(Troubleshooting) zeigt die Falle „leere Antwort wegen zu engem
Filter". 2.5 (Services) erklärt den Dienst selbst — das eigene
Informationsmodell, das vollständige Feld-Set eines Auftrags, die
Presentation-Context-Mechanik — mit der leeren Antwort nur als
Randnotiz, nicht als Kern der Lektion.

**Neuer, in dieser Slice verifizierter Fund:** DCMTKs echtes `findscu`
schlägt mit `-W` **nur eine** Presentation Context vor (die Modality
Worklist Information Model FIND), während eine gewöhnliche
Study-Abfrage ein ganzes Bündel aus Patient-/Study-Root-FIND/MOVE/GET
mitschleppt (13 vorgeschlagene Contexts, größtenteils abgelehnt) —
verifiziert per `findscu -d`. Zusätzlich bestätigt: pynetdicoms eigenes
`findscu` (das den echten DCMTK-Namen verdeckt, siehe ADR 0025)
schlägt selbst mit `-W` weiterhin das volle Bündel vor, statt sich auf
die Worklist zu beschränken — ein zusätzlicher, konkreter Beleg für die
bereits dokumentierte Shadowing-Falle (jetzt an einem neuen Befehl
demonstriert, nicht nur an `storescu`/`echoscu`).

## Entscheidung — Fließtext mit fünf echten Beispielen, kein
Infrastruktur-Wiring nötig

**`content/lessons/2.5/de.md`**: vollständig neu geschrieben.
- Ein echter STUDY-Level-`findscu` als struktureller Kontrast (kein
  `QueryRetrieveLevel` in einer Worklist-Anfrage).
- Ein echter filterloser `findscu -W` mit vollständigem Rückgabefeld-Satz
  — zeigt das komplette, für die Sitzung generierte Auftragsobjekt.
- Ein echter `findscu -d -W`-Mitschnitt der Presentation-Context-
  Aushandlung — die „nur eine Context"-Beobachtung, inklusive des
  Shadowing-Hinweises.
- Ein echter, treffender Modality-Filter (`Modality=CT`) im nativen
  DCMTK-`findscu`-Ausgabeformat (Sequenz-Item-Darstellung, anders als
  die kompaktere Form der ersten Beispiele — echt, nicht
  vereinheitlicht).
- Ein echter, nicht-treffender Filter (`Modality=MR`) als Kontrast:
  `Success` ohne jede Antwortzeile.

**`content/lessons/2.5/meta.yml`**: `tools` von `[findscu, wlmscpfs]`
auf `[findscu]` reduziert — `wlmscpfs` wurde im geschriebenen Fließtext
nicht gebraucht (Orthancs eigenes Plugin beantwortet die Abfrage
bereits real, ein zusätzlicher, separat aufzusetzender
`wlmscpfs`-Dateisystem-SCP hätte keinen zusätzlichen Erkenntniswert
gegenüber dem bereits real Gezeigten gebracht). `status: draft` →
`fertig`.

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Alle fünf Beispiele wurden in einem echten, vom Orchestrator
  erzeugten Sitzungscontainer erzeugt (nicht in einem isolierten
  Testaufbau) — Session gestartet über die echte Produkt-Oberfläche,
  „Spielwiese starten" geklickt, Befehle im echten Toolbox-Container
  ausgeführt.
- Die Presentation-Context-Differenz (1 vs. 13 vorgeschlagene
  Contexts) direkt verglichen: `findscu -d` (Study-Root, Standard)
  gegen `/usr/bin/findscu -d -W` (Worklist).
- Das Shadowing-Verhalten für `-W` gezielt gegengeprüft: pynetdicoms
  `findscu` (Standardpfad) vs. `/usr/bin/findscu` (echtes DCMTK) — nur
  Letzteres beschränkt sich auf die eine Presentation Context.
- „Spielwiese beenden" über die echte Oberfläche geklickt — Container
  vollständig entfernt (`docker ps -a` bestätigt).
- `content:validate`: 0 Verstöße (27 Lektionen, 35 Glossarbegriffe).
- `datasets/build` und `services/sandbox`: pytest erneut ausgeführt
  (unverändert von dieser Slice betroffen) — beide grün.
- Lektion 2.5 im Browser gegen den echten Stack aufgerufen — rendert
  vollständig fehlerfrei, `{{term:worklist}}`-Tooltip zeigt korrekt
  „Modality Worklist".
- Alle Docker-Ressourcen dieser Slice (Compose-Stack, geteilte
  Test-Images, Sitzungscontainer/-Netz) nach Abschluss vollständig
  entfernt.

## Nicht Teil dieser Slice

- Kein Node-Stub für Lektion 2.5 (`lab.node` bleibt `null`) — wie im
  ursprünglichen Curriculum vorgesehen, kein Lab-Aufbau in diesem
  Slice begonnen.
- `wlmscpfs` bleibt ein registriertes, aber in dieser Lektion nicht
  genutztes Werkzeug — es wird weiterhin in Lektion 4.7s Werkzeugleiste
  geführt.
