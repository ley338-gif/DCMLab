# 0030 — P10.20: Lektion 4.7 — echter Modality-Worklist-Dienst in der Spielwiese

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Lektion 4.7 ("Worklist ist leer") lag seit P9 als Gerüst vor. Der
zugehörige Node `worklist-query-empty` ist seit P10.11 vollständig
(ADR 0021), aber die Spielwiese selbst hatte laut `docs/content-todo.md`
(Fund aus P10.11) keinen Worklist-Dienst — `wlmscpfs` steht zwar in der
Werkzeug-Registry, aber weder im Toolbox-Image noch als eigener
Container vorgesehen. Das wurde bisher als eigenständiges
Infrastrukturthema zurückgestellt ("neuer Container oder neuer Dienst
im Toolbox-Image plus Eintrag in `datasets.yml`").

**Fund vor dem Schreiben:** Orthanc (`orthancteam/orthanc`, das Image
dieses Projekts) bringt das Worklists-Plugin (`libOrthancWorklists.so`)
bereits mit — es muss nur per Konfiguration aktiviert werden
(`"Worklists": {"Enable": true, "Database": "<Pfad>"}`). Damit
entfällt der ursprünglich angenommene Aufwand eines zusätzlichen
Containers oder Diensts vollständig: Orthanc selbst kann in der
Spielwiese den Worklist-C-FIND beantworten, genau wie es Bild-C-FIND
und C-STORE bereits tut.

Beim ersten Test wurde ein Bedienfehler aufgedeckt und korrigiert: Wird
das Orthanc-Image mit einem expliziten Kommando (`/etc/orthanc` statt
des Standard-`CMD` `/tmp/orthanc.json`) gestartet, überspringt der
`docker-entrypoint.sh` effektiv die von `generateConfiguration.py`
erzeugte, mit Plugin-Defaults angereicherte Konfigurationsdatei — das
eigene `orthanc.json` wird zwar gelesen, aber ohne `Plugins`-Eintrag,
sodass keine Plugin-`.so`-Datei geladen wird und die
Worklist-Presentation-Context vom SCU abgelehnt wird. Mit dem
Standard-`CMD` (wie es `containers/orthanc/Dockerfile` unverändert
verwendet) tritt der Fehler nicht auf.

## Entscheidung — Orthancs eigenes Worklists-Plugin, ein fester,
real generierter Auftrag pro Sitzung

**`containers/orthanc/orthanc.json`**: `"Worklists": {"Enable": true,
"Database": "/worklists"}` ergänzt.

**`datasets/build/generate.py`**: von einem einzelnen CLI-Modus auf
zwei Subcommands umgestellt — `ct` (unverändertes Verhalten, vorher
ohne Subcommand) und neu `worklist` (`generate_worklist()`/
`build_worklist_item()`, erzeugt einen echten
Modality-Worklist-Datensatz, SOP-Klasse
`1.2.840.10008.5.1.4.31`, via pydicom). `docker_ops.py`s
`_run_generator`-Aufruf entsprechend um das `ct`-Subcommand ergänzt.

**`content/worklists.yml`** (neu, parallel zu `content/datasets.yml`):
ein fester Eintrag `ct-thorax-worklist` (Patient, Accession Number,
Modality `CT`, Scheduled Station AE Title `CT01`, ...). Datum/Uhrzeit
sind bewusst nicht Teil dieser Datei — sie werden bei jedem
Sitzungsstart auf "heute" gesetzt (Abschnitt 6: kein Zustand über
Sitzungen hinweg).

**`services/sandbox/app/worklists_yaml.py`** (neu): liest
`content/worklists.yml`, parallel zu `datasets_yaml.py`.

**`services/sandbox/app/docker_ops.py`**: `build_session()` legt pro
Sitzung zusätzlich ein Docker-Volume `<network>-worklists` an, befüllt
es über einen kurzlebigen Toolbox-Container-Lauf
(`_run_worklist_generator`, analog zu `_run_generator`) mit dem realen
Worklist-Eintrag aus `content/worklists.yml` plus dem aktuellen Datum,
und mountet es read-only in den Orthanc-Container unter `/worklists`.
`teardown_session()` entfernt dieses Volume wieder — verifiziert über
die echte "Spielwiese beenden"-Aktion, nicht nur angenommen.

Kein Wahlfeld für Lernende: jede Sitzung bekommt genau diesen einen,
fest definierten (aber real, mit aktuellem Datum generierten) Auftrag
— analog zu den festen `RESOURCE_LIMITS`.

**`content/lessons/4.7/de.md`**: vollständig neu geschrieben, mit
echten Beispielen — `findscu -W` mit passendem Modality-Filter (echter
Treffer aus dem generierten Auftrag), derselbe Aufruf mit
nicht-passendem Filter (`Modality=MR`, echt leeres Ergebnis — dieselbe
`Find SCP Result: 0x0000 (Success)`-Zeile ohne jede Antwort davor,
keine Fehlermeldung), und ein echter `tshark`-Mitschnitt der
zugrundeliegenden C-FIND-Assoziation. `wlmscpfs` bleibt als Werkzeug
deklariert (real im Toolbox-Image vorhanden, `dcmtk`-Paket) und wird
konzeptionell erklärt (die Software, die diese Rolle in echten Häusern
übernimmt), aber nicht live vorgeführt — kein Widerspruch zur
Validierungsregel, die nur die *umgekehrte* Richtung prüft (benutzt,
aber nicht deklariert).

Die Lektion benennt explizit die Vereinfachung der Spielwiese: Orthanc
übernimmt hier zusätzlich die Worklist-Rolle, die in echten Häusern
fast immer ein separates RIS/Broker-System übernimmt — das ändert
nichts an der Mechanik der Abfrage selbst (dieselbe Art C-FIND), wird
aber nicht verschwiegen.

**`content/lessons/4.7/meta.yml`**: `status: draft` → `fertig`,
Kommentar zum Lab aktualisiert, `tools_checked`/`updated` auf
`2026-09-13`.

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Ad-hoc-Test (isolierter `docker run` mit Orthanc + generiertem
  `.wl`-File): Fehlkonfiguration (`CMD` überschrieben) reproduzierbar
  zum Scheitern gebracht, dann mit Standard-`CMD` korrigiert — Plugin
  lädt (`Registering plugin 'orthanc-worklists'`), Datenbankordner wird
  gelesen (`The database of worklists will be read from folder:
  /worklists`).
- `findscu -W` gegen den isolierten Testcontainer: korrekt gematchter
  Datensatz mit allen erwarteten Feldern.
- `datasets/build`: 5 pytest (inkl. neuem Test für
  `generate_worklist`), `ruff check .`, `mypy generate.py` — alle
  grün.
- `services/sandbox`: 14 pytest (unverändert, `build_session` wird in
  `test_orchestrator.py` gemockt), `ruff check .`, `mypy app` — alle
  grün.
- Beide Images (`dcmlab/toolbox`, `dcmlab/orthanc`) neu gebaut; voller
  Compose-Stack hochgefahren, `content:sync` ausgeführt (Hinweis:
  `content:build` erzeugt nur Flag-Hashes, `content:sync` synchronisiert
  Tracks/Lektionen/Nodes in die DB — beide Schritte nötig nach
  `migrate:fresh`).
- **Echter End-to-End-Test über die Produkt-Oberfläche:** eingeloggt,
  Lektion 4.7 geöffnet (rendert jetzt vollständig, kein
  "Gerüst"-Hinweis mehr), "Spielwiese starten" geklickt — echtes
  Container-Paar plus Worklist-Volume vom Orchestrator erzeugt.
- Orthanc-Logs des echten Sitzungscontainers bestätigen
  Plugin-Registrierung und `/worklists` als gelesenen Ordner.
- Im echten Toolbox-Container derselben Sitzung: `findscu -W` mit
  `Modality=CT` liefert den echten, für diese Sitzung generierten
  Auftrag (Patient `MUSTER^ERIKA`, Accession `4711-0001`, Station
  `CT01`); derselbe Aufruf mit `Modality=MR` liefert `Success` ohne
  jede Antwortzeile — der in der Lektion beschriebene Kontrast, live
  reproduziert.
- Echter `tshark -i lo -f "tcp port 4242" -Y dicom`-Mitschnitt im
  selben Container während der Abfrage: C-FIND-RQ/-RSP inklusive
  separater `-DATA`-Pakete, wie in der Lektion abgedruckt.
- "Spielwiese beenden" über die echte Oberfläche geklickt — bestätigt
  per `docker ps -a`/`docker volume ls`, dass Container, Netz **und**
  das neue Worklist-Volume vollständig entfernt wurden (kein manueller
  Cleanup-Bypass für diesen Verifikationslauf).
- `content:validate`: 19 vorbestehende Verstöße, keine neuen.
- Alle Docker-Ressourcen dieser Slice (Compose-Stack, geteilte
  Test-Images, Sitzungscontainer/-Netz/-Volumes) nach Abschluss
  vollständig entfernt.

## Nicht gebaut / offen (siehe `docs/content-todo.md`)

- `wlmscpfs` selbst läuft in keiner Lektion tatsächlich als eigener
  SCP-Prozess in der Spielwiese (nur konzeptionell erklärt) — dafür
  müsste die Toolbox ebenfalls Zugriff auf einen Worklist-Ordner
  bekommen und einen zweiten DICOM-Port exponieren. Kein erkennbarer
  pädagogischer Zusatznutzen gegenüber der jetzigen Lösung (Orthanc
  beantwortet denselben Dienst bereits real), deshalb zurückgestellt.
- Der Worklist-Eintrag ist pro Sitzung fest (`ct-thorax-worklist`,
  keine Lernenden-Auswahl) — für zukünftige Lektionen mit anderen
  Szenarien (z. B. mehrere gleichzeitige Aufträge, ein Auftrag mit
  falschem AE Title) müsste `content/worklists.yml` und die API
  (`SandboxController`/`SandboxClient`) um einen Slug-Parameter
  erweitert werden, analog zu `dataset_slug`.
