# 0029 — P10.19: Lektion 4.10 — tshark im Toolbox-Image, gezielte Ausnahme von "no-new-privileges"

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Lektion 4.10 ist die Abschlusslektion des Troubleshooting-Tracks
(Synthese: reproduzieren, eingrenzen, gezielt filtern) und deklariert
`tshark` als Werkzeug — wie bereits fünf frühere Lektionen (4.1, 4.2,
4.4, 4.9, 4.10), die alle live Beispiele mit `tshark` bisher auf
`<!-- kein-beispiel -->` zurückgestellt hatten. Vor dem Schreiben
dieser Lektion wurde erstmals geprüft, ob `tshark` in der Spielwiese
überhaupt lauffähig ist.

**Fund 1 — `tshark` fehlte komplett.** `containers/toolbox/Dockerfile`
installierte nie `tshark`, obwohl fünf `meta.yml`-Dateien es als
Werkzeug deklarieren. Jede frühere Zurückstellung war also korrekt
vorsichtig, aber aus dem falschen (weil nie geprüften) Grund.

**Fund 2 — nach Installation: Rechteproblem statt Softwareproblem.**
Nach Ergänzen von `tshark`/`libcap2-bin` im Dockerfile und
`setcap cap_net_raw,cap_net_admin=eip /usr/bin/dumpcap` (nötig, weil
`dumpcap` im Container als nicht-root-Nutzer `dcmlab` läuft, Abschnitt
6: keine Root-Shell für Lernende) funktionierte ein manueller
`docker run --cap-add=NET_RAW --cap-add=NET_ADMIN …` — echter
Mitschnitt mit korrekter DICOM-Dissektion. Derselbe Container **über
den echten Sandbox-Orchestrator gestartet** (`services/sandbox`)
scheiterte weiterhin mit:

```
tshark: You do not have permission to capture on device "lo".
(socket: Operation not permitted)
```

trotz bestätigtem `cap_add: [NET_RAW NET_ADMIN]` (`docker inspect`).
Ursache: `RESOURCE_LIMITS` in `services/sandbox/app/docker_ops.py`
setzt `security_opt: ["no-new-privileges"]` für beide
Session-Container. Dieses Flag blockiert gezielt den
Datei-Capability-Mechanismus (`setcap`), über den ein Nicht-root-Prozess
beim `exec` zusätzliche Capabilities erhält — unabhängig davon, dass
`cap_add` die Capability bereits im Container-Bounding-Set freigegeben
hat. Empirisch verglichen, nicht vermutet: derselbe Container, einmal
ohne, einmal mit `no-new-privileges`, identisches `cap_add` — nur die
Variante ohne das Flag funktionierte.

## Entscheidung — Ausnahme nur für die Toolbox, mit expliziter
Nutzerfreigabe

Diese Änderung berührt eine Docker-Sicherheitseinstellung
(`security_opt`) und wurde deshalb dem Nutzer vorgelegt, statt
eigenmächtig entschieden. Nutzerentscheidung: **"Ja, nur für die
Toolbox lockern (empfohlen)"** — Orthanc (der DICOM-SCP im selben
Netzwerk-Namespace) behält `no-new-privileges` unverändert; nur der
Toolbox-Container bekommt eine eigene `TOOLBOX_RESOURCE_LIMITS`
(`security_opt` entfernt, `cap_add: ["NET_RAW", "NET_ADMIN"]`
hinzugefügt) — keine root-Rechte, keine weiteren Capabilities, das
`--internal`-Netz (kein Egress) bleibt unangetastet.

**`containers/toolbox/Dockerfile`**: `tshark`, `libcap2-bin` ergänzt,
`setcap` auf `/usr/bin/dumpcap`.

**`services/sandbox/app/docker_ops.py`**: neue
`TOOLBOX_RESOURCE_LIMITS`-Konstante (abgeleitet von `RESOURCE_LIMITS`
ohne `security_opt`, mit `cap_add`); der Toolbox-`containers.run()`-Aufruf
verwendet sie anstelle von `RESOURCE_LIMITS`. Der Orthanc-Aufruf bleibt
unverändert bei `RESOURCE_LIMITS`.

**`content/lessons/4.10/de.md`**: vollständig neu, mit echten
Mitschnitten — Verbositätsvergleich `storescu`/`storescu -v`/
`storescu -d -cx`, ein echter `tshark -i lo -f "tcp port 4242" -Y dicom`-
Mitschnitt (A-ASSOCIATE, P-DATA C-STORE-RQ/RSP, A-RELEASE) und ein
`tshark -r mitschnitt.pcap -Y "dicom && tcp.stream eq 1"`-Beispiel zum
Eingrenzen eines Mitschnitts mit mehreren Associations. Kein
`<!-- kein-beispiel -->` nötig.

**`content/lessons/4.10/meta.yml`**: Kommentar zu "kein Node" auf
diese ADR verwiesen; `tools_checked`/`updated` auf `2026-09-13`.

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- `docker build` des geänderten Toolbox-Images; manueller
  `docker run --cap-add=NET_RAW --cap-add=NET_ADMIN` (ohne
  `no-new-privileges`) — echter Mitschnitt, korrekte DICOM-Dissektion.
- Derselbe Container über den *unveränderten* Orchestrator gestartet —
  reproduzierbar mit "Operation not permitted" fehlgeschlagen, trotz
  bestätigtem `cap_add`.
- Nach dem Code-Fix: `services/sandbox`-Image neu gebaut, Service im
  Compose-Stack neu erstellt (`--force-recreate`).
- **Echter End-to-End-Test über die Produkt-Oberfläche:** im Browser
  eingeloggt, Lektion 4.10 geöffnet, "Spielwiese starten" geklickt —
  ein echtes Container-Paar
  (`dcmlab-sandbox-<uuid>-toolbox`/`-orthanc`) wurde vom Orchestrator
  erzeugt.
- `docker inspect` des erzeugten Toolbox-Containers bestätigt:
  `CapAdd: [NET_RAW NET_ADMIN]`, `SecurityOpt: []` (leer — kein
  `no-new-privileges` mehr).
- In genau diesem Container (nicht in einem manuellen Testcontainer):
  `tshark -i lo -f "tcp port 4242" -Y dicom` parallel zu
  `echoscu -v -aec ORTHANC 127.0.0.1 4242` gestartet — 6 Pakete
  aufgezeichnet, korrekt dissektiert (A-ASSOCIATE request/accept,
  P-DATA C-ECHO-RQ/RSP, A-RELEASE request/response). Damit ist der
  Fix nicht nur isoliert, sondern durch den echten Lernpfad bestätigt.
- Datensatz-Dateiname im selben Container geprüft
  (`/home/dcmlab/daten/ct-thorax-60/instance-0001.dcm`) — stimmt mit
  dem in `de.md` verwendeten Dateinamen überein.
- "Spielwiese beenden" geklickt — Orchestrator hat beide Container,
  Netz und Volume korrekt abgebaut (per `docker ps -a` bestätigt: keine
  Reste).
- `services/sandbox`: `pytest` (14 bestanden), `ruff check .` (clean),
  `mypy app` (clean) — nach dem Code-Fix erneut ausgeführt, keine
  Regression.
- `content:validate`: keine neuen Verstöße erwartet (im App-Container
  auszuführen, siehe bekannte lokale PHP-Versionseinschränkung).
- Alle Docker-Ressourcen dieser Slice (Compose-Stack, geteilte
  `dcmlab/orthanc:latest`/`dcmlab/toolbox:latest`-Images,
  Session-Container/-Netz/-Volume) nach Abschluss vollständig entfernt.

## Nicht gebaut / offen (siehe `docs/content-todo.md`)

- Die fünf bereits gemergten Lektionen (4.1, 4.2, 4.4, 4.9 sowie 1.8),
  die `tshark`-Beispiele bisher auf `<!-- kein-beispiel -->`
  zurückgestellt hatten, wurden **nicht** rückwirkend bearbeitet — nur
  der neue Befund dokumentiert. Ob sich das lohnt, ist eine separate
  Entscheidung (Aufwand vs. Nutzen pro Lektion).
- Kein Node-Stub für Lektion 4.10 (`lab.node: null`) — die Lektion ist
  bewusst eine Synthese ohne neues Fehlerbild, kein Kandidat für einen
  eigenen Node.
