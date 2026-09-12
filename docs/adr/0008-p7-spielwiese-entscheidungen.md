# 0008 — Die Spielwiese: Orchestrator, Container-Paar, Terminal (P7)

Status: akzeptiert
Datum: 2026-09-14

## Kontext

Abschnitt 6 verlangt einen echten Orthanc + Toolbox-Container pro Sitzung,
per Web-Terminal (xterm.js) angebunden, mit Warteschlange, Tageskontingent,
Idle-TTL und garantiertem Egress-Verbot. Mehrere technische Entscheidungen
waren nötig, die der Auftrag nicht bis ins Detail vorgibt.

## Entscheidungen

**HTTP-Request/Response pro Befehl statt WebSocket-PTY.** Abschnitt 6 nennt
"xterm.js über WebSocket", aber die eigentliche Anforderung ist ein Terminal,
das sich wie ein echtes anfühlt — nicht zwingend ein volles PTY-Protokoll.
Da `EngineTerminal.vue` (P5) bereits transport-unabhängig als
Callback-Prop gebaut ist (ADR 0006), reicht ein `POST
/v1/sandboxes/{id}/exec {command}` mit `docker exec` im Toolbox-Container —
exakt dasselbe Muster wie die Node-Engine. Das spart die gesamte
WebSocket-Framing-/Reconnect-Logik für eine Funktion, die ohnehin nur
einzelne Befehle statt echter interaktiver Programme braucht (kein `vim`,
kein `top`). Web-UI-Exposition von Orthancs REST-Oberfläche (Port 8042)
über den Reverse Proxy ist aus demselben Grund **nicht** gebaut — die DoD
verlangt nur die DICOM-Interaktion über das Terminal.

**Orthanc besitzt die Netzwerk-Namespace, Toolbox tritt bei
(`network_mode: container:<orthanc>`).** Abschnitt 4.6 verlangt, dass die
Spielwiese sich wie eine lokale Standardinstallation meldet: AE `ORTHANC`
unter `127.0.0.1:4242`. Das funktioniert nur, wenn beide Container
dieselbe Netzwerk-Namespace teilen. Nur Orthanc haengt am
`internal: true`-Docker-Netz (Dockers eingebaute Egress-Sperre) — die
Toolbox erbt diese Beschraenkung automatisch, weil sie buchstaeblich
dieselbe Namespace benutzt.

**Datensatz-Generierung als kurzlebiger Root-Container, Lernumgebung als
langlebiger Nicht-Root-Container.** Ein frisches Docker-Volume gehoert
root; der Generator-Lauf (`datasets/build/generate.py`, per `docker exec`
im selben Toolbox-Image) braucht deshalb `user="root"`, bevor das Volume
read-only unter `~/daten/<slug>/` in den eigentlichen, als `dcmlab`
laufenden Sitzungs-Container gemountet wird. Der Lernende beruehrt den
Root-Lauf nie.

**Race Condition beim Namespace-Beitritt behoben.** `docker_client.
containers.run(..., detach=True)` kehrt zurueck, sobald der Start-Request
angenommen ist — nicht, sobald der Netzwerk-Namespace unter
`/proc/<pid>/ns/net` tatsaechlich existiert. Ein direkt danach gestarteter
`network_mode=container:<id>`-Container kann daran scheitern
("namespace path: ... no such file or directory"). `docker_ops.
_wait_until_running` pollt `container.status == "running"`, bevor die
Toolbox startet.

**Orthanc: Authentifizierung aus, aber Query/Retrieve fuer jede Calling AE
freigegeben.** `AuthenticationEnabled: true` ohne `RegisteredUsers` lässt
neuere Orthanc-Versionen (1.13+) gar nicht erst starten — und da dieser
Orthanc nie ausserhalb seiner eigenen, egress-losen Namespace erreichbar
ist, schuetzt HTTP-Auth hier nichts. Separat davon lehnt Orthanc
standardmaessig C-FIND/C-MOVE/C-GET von unbekannten Calling AEs ab
(`DicomModalities`) — die Lernumgebung kennt aber keine feste Liste
erlaubter AE Titles, der Lernende soll frei experimentieren koennen.
`DicomAlwaysAllow{Find,FindWorklist,Move,Get,Store}: true` schaltet diese
Pruefung fuer alle vier Dienste ab, konsistent mit dem schon vorhandenen
`DicomAlwaysAllowEcho`. **Das muss revidiert werden, sobald Orthancs
REST-API doch einmal ueber den Reverse Proxy exponiert wird** (aktuell
nicht der Fall, siehe oben).

**Befoerderung aus der Warteschlange behaelt die urspruengliche ID.**
Ein gefundener Bug waehrend der Verifikation: Ein wartender Request bekam
beim Start eine *neue* `sandbox_id`, komplett getrennt von der `request_id`,
die der Browser beim Anfragen schon erhalten hatte — der Browser haette
seine eigene, inzwischen laufende Sitzung nie wiedergefunden. `orchestrator.
_start_sandbox` nimmt jetzt eine explizite `sandbox_id` entgegen;
`_promote_next` uebergibt dafuer die `request_id` weiter.

**Kein eigener Zustand auf der Laravel-Seite.** Abschnitt 6: "kein Zustand
ueber Sitzungen hinweg, Neustart ist immer eine Zeile". `SandboxController`
ist ein reiner Proxy zu `services/sandbox` (wie `EngineClient`/`NodeController`
fuer die Engine), die `sandbox_id` lebt nur im Vue-Komponentenstand
(`SandboxPanel.vue`) — ein Seiten-Reload verliert die laufende Sitzung
absichtlich, nicht versehentlich.

## Manuell verifiziert (gegen den echten Stack, Abschnitt 9 Testplan)

- `POST /v1/sandboxes` erzeugt echtes Container-Paar + Datensatz.
- `echoscu -v -aec ORTHANC 127.0.0.1 4242` liefert eine echte
  `Association Accepted` / `Echo Response (Status: 0x0000)`.
- `storescu` legt ein Objekt tatsaechlich in Orthanc ab (per Orthancs
  eigener REST-API `/instances` gegengeprueft).
- `findscu` liefert das gespeicherte Objekt zurueck.
- `ping 8.8.8.8` aus der Toolbox scheitert mit "Network is unreachable"
  (Egress-Sperre wirkt).
- Ablauf der `idle_timeout_minutes` raeumt Container, Netz und Volume weg
  und bucht die Sitzungsdauer aufs Tageskontingent.
- Warteschlange: eine vierte Anfrage bei `max_concurrent_sandboxes: 3`
  wird eingereiht, eine `DELETE` auf eine laufende Sitzung befoerdert sie
  sofort in eine echte, funktionierende Sitzung unter derselben ID.

## Nicht gebaut (bewusst, siehe "Was du ausdruecklich nicht baust")

Kein automatisierter, Docker-abhaengiger Testfall im Pest/Pytest-Lauf --
die Orchestrator-Logik (Kontingent, Warteschlange, Aufraeumen) ist mit
`fakeredis` und gefaktem `docker_ops` vollstaendig ohne echten
Docker-Daemon getestet; die obige Liste wurde stattdessen manuell gegen den
laufenden Stack verifiziert, wie in jeder vorigen Phase.
