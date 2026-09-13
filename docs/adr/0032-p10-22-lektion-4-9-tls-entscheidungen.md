# 0032 — P10.22: Lektion 4.9 — echter DICOM-TLS-Endpunkt in der Toolbox

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Lektion 4.9 ("Nach TLS-Aktivierung geht nichts mehr") lag seit P9 als
Gerüst vor. Ihr eigener "Was zum Schreiben noch fehlt"-Hinweis nannte
den Aufwand: "Die Spielwiese hat bisher keinen TLS-Endpunkt ... das
muss aber im Container eingerichtet und mit Testzertifikaten bestückt
sein."

**Erster Fund: Orthanc kann DICOM-TLS aktivieren, aber nicht für diese
Spielwiese verwenden.** Ein isolierter Test (`DicomTlsEnabled: true`,
`DicomTlsCertificate`/`-PrivateKey`/`-TrustedCertificates`, echtes
selbstsigniertes Testzertifikat) bestätigte, dass Orthanc DICOM-TLS
technisch beherrscht — ein echter TLS-Handshake (TLSv1.3) fand statt.
**Aber:** Orthanc kennt nur einen einzigen `DicomPort` — mit aktiviertem
TLS weist derselbe Port anschließend **jede unverschlüsselte
Verbindung ab**, verifiziert mit einem plain `echoscu` gegen denselben
Port ("Peer aborted Association"). Da alle anderen, bereits gemergten
Track-4-Lektionen (und Track 1) denselben Orthanc-Container über
plaintext DICOM ansprechen, hätte ein global aktiviertes
`DicomTlsEnabled` jede andere reale Lektion in dieser Spielwiese
gebrochen.

**Entscheidung:** Die TLS-Gegenstelle dieser Lektion ist ein eigener
DCMTK-`storescp` im Toolbox-Container selbst — unabhängig von Orthanc,
keine Änderung an `containers/orthanc/`.

**Zweiter Fund (Wiederholung eines bekannten Musters aus ADR 0025):**
`echoscu`/`storescu` sind in dieser Spielwiese pip-installierte
`pynetdicom`-Skripte, die die echten DCMTK-Programme im `$PATH`
verdecken. `pynetdicom`s CLI kennt DCMTK-spezifische TLS-Flags wie
`+tla`/`+cf` nicht (`error: argument port: invalid int value: '+cf'`)
— für diese Lektion ist der volle Pfad zu `/usr/bin/echoscu`/
`/usr/bin/storescu` deshalb unumgänglich, nicht nur eine Stilfrage.

**Dritter Fund (real, nicht vermutet):** DCMTKs Standard-TLS-Verhalten
prüft die Vertrauenskette (`+cf`), aber **nicht** den Zertifikatsnamen
gegen die Zieladresse. Ein Testzertifikat mit `CN=dcmlab-spielwiese-tls`
wurde erfolgreich für eine Verbindung zu `127.0.0.1` akzeptiert — anders
als beim TLS-Verhalten von Browsern (HTTPS-Hostname-Pinning). Dieser
Kontrast ist ein zentraler, für die Lektion nützlicher Fund, kein
Nebenprodukt.

## Entscheidung — eigener TLS-`storescp` in der Toolbox, kein
Orthanc-Change

**`containers/toolbox/Dockerfile`**: `openssl` ergänzt (bereits in der
`orthanc`-Basis vorhanden, aber nicht in der `debian:bookworm-slim`-Basis
der Toolbox); ein neuer `RUN`-Schritt erzeugt beim Image-Build ein
selbstsigniertes Testzertifikat (`openssl req -x509 ...`,
`CN=dcmlab-spielwiese-tls`, 10 Jahre Gültigkeit) unter
`/opt/tools/tls/server-{cert,key}.pem`, world-readable (`chmod 644`)
für den späteren `USER dcmlab`. Kein Geheimnis mit echtem Wert wird
damit ins Repository aufgenommen — der private Schlüssel entsteht neu
bei jedem Image-Build, wird nie committet.

Kein Orchestrator-Wiring nötig (wie schon bei MPPS, ADR 0031): der
TLS-`storescp` läuft nur, wenn ein Lernender ihn selbst im
Sandbox-Terminal startet — kein bei Sitzungsstart vorbereitetes
Datenobjekt wie bei der Worklist (ADR 0030).

**`content/lessons/4.9/de.md`**: vollständig neu geschrieben, mit
echten Beispielen — ein erfolgreicher `echoscu`/`storescu`-Aufruf über
anonyme TLS (`+tla +cf`) gegen den eigenen `storescp`, derselbe Aufruf
ohne vertrauenswürdiges Zertifikat (echte Ablehnung mit reinem
TLS-Fehlercode `000b:0886`, keine DICOM-Statusmeldung), der Fund zur
fehlenden Namensprüfung, und ein echter `tshark`-Mitschnitt, der ab dem
`Client Hello` nur noch `Application Data` zeigt (kein DICOM-PDU-Inhalt
mehr sichtbar). Zwei der ursprünglich drei geplanten Ursachenklassen
(abgelaufenes Zertifikat, Cipher-/Protokollversions-Konflikt) sind
ehrlich als nicht reproduzierbar markiert — ein rückdatiertes
Zertifikat ließ sich mit den verfügbaren `openssl`-Optionen in dieser
Umgebung nicht direkt erzeugen (`-not_before`/`-not_after` von `openssl
req` in dieser Version nicht unterstützt, Systemzeit im Container ohne
`CAP_SYS_TIME` nicht verstellbar), ein echter Cipher-Konflikt wäre nur
durch künstlich erzwungene, nicht tatsächlich beobachtete
Inkompatibilität simulierbar gewesen.

**`content/lessons/4.9/meta.yml`**: `status: draft` → `fertig`,
Kommentar zum fehlenden Node um den Hinweis auf den neuen
TLS-Endpunkt ergänzt, `tools_checked`/`updated` auf `2026-09-13`.

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Isolierter Vortest: Orthanc mit `DicomTlsEnabled` — echter
  TLS-Handshake bestätigt (TLSv1.3, per `openssl s_client`), aber
  plain DICOM gegen denselben Port danach nachweislich abgelehnt —
  Grund für die Entscheidung gegen eine Orthanc-Änderung.
- Isolierter Test des TLS-`storescp`-Ansatzes: `+tla +cf` erfolgreich
  (`echoscu`, dann `storescu` mit echtem Dateitransfer, Datei kam beim
  SCP an), ohne `+cf` echte Ablehnung (`certificate verify failed`),
  Namensprüfung nachweislich nicht erzwungen.
- `datasets/build` und `services/sandbox`: pytest/ruff/mypy erneut
  ausgeführt (unverändert von dieser Slice betroffen) — alle grün.
- **Echter End-to-End-Test über die Produkt-Oberfläche:** eingeloggt,
  Lektion 4.9 geöffnet (rendert vollständig, kein "Gerüst"-Hinweis
  mehr), "Spielwiese starten" geklickt — echtes, vom Orchestrator
  erzeugtes Container-Paar.
- Im echten Sitzungscontainer: TLS-`storescp` gestartet, erfolgreicher
  `echoscu`/`storescu` über TLS, echte Ablehnung ohne Vertrauensanker,
  echter `tshark`-Mitschnitt — alle Ausgaben stammen aus diesem einen,
  echten Sitzungscontainer und stimmen mit dem isolierten Vortest
  überein.
- "Spielwiese beenden" über die echte Oberfläche geklickt — Container
  vollständig entfernt.
- `content:validate`: 19 vorbestehende Verstöße, keine neuen.
- Alle Docker-Ressourcen dieser Slice (Compose-Stack, geteilte
  Test-Images, Sitzungscontainer/-Netz) nach Abschluss vollständig
  entfernt.

## Nicht gebaut / offen (siehe `docs/content-todo.md`)

- Kein Node-Stub für Lektion 4.9 (`lab.node` bleibt `null`).
- Zwei der ursprünglich drei geplanten Ursachenklassen (abgelaufenes
  Zertifikat, Cipher-/Protokollversions-Konflikt) bleiben ohne echtes
  Live-Beispiel — siehe Begründung oben. Eine künftige Iteration könnte
  z. B. `libfaketime` in der Toolbox ergänzen, um ein echtes
  abgelaufenes Zertifikat reproduzierbar zu erzeugen; dafür gab es in
  diesem Slice keinen ausreichenden pädagogischen Zusatznutzen
  gegenüber dem bereits gezeigten Trust-Fehler.
- Mit Track 4 vollständig geschrieben (4.1–4.10, siehe
  `docs/content-todo.md`), sind Track 2 und Track 3 die nächsten
  offenen, noch nicht begonnenen Content-Baustellen der Roadmap.
