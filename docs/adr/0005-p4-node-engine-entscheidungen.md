# 0005 — Node-Engine: Sitzungsspeicher, API-Erweiterungen, Sonderfälle (P4)

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Abschnitt 5.5 gibt die Engine-API als "Vorschlag, du darfst verfeinern" vor
und verlangt Sitzungszustand "in Postgres (JSONB), nicht im Prozessspeicher
der Engine". Abschnitt 3.3 legt die Dienstgrenze fest: die Engine kennt
Nodes und Sitzungen, keine Nutzer. Mehrere Detailentscheidungen waren nötig,
um das Regelwerk aus Abschnitt 5.3 vollständig und schema-getrieben (nicht
node-spezifisch verdrahtet, siehe P6-Anforderung) umzusetzen.

## Entscheidungen

**`engine_sessions` legt die Engine selbst an, nicht Laravel.** Beide Dienste
teilen dieselbe Postgres-Instanz, aber die Engine kennt keine
Laravel-Migration und soll sie auch nicht kennen (Dienstgrenze). `init_db()`
ruft beim Start `Base.metadata.create_all()` auf — idempotent, kein
Migrationswerkzeug nötig. Folge: `php artisan migrate:fresh` (Teil von
`make seed`) räumt `engine_sessions` mit weg, weil es dieselbe Datenbank
benutzt. Das ist hingenommen, nicht übersehen — eine Node-Sitzung ist
jederzeit neu anlegbar (`POST /v1/sessions`) und trägt keinen Fortschritt,
der nicht auch in `node_attempts` (Laravel-Seite, spätere Phase) landet.
Die Spalte ist `JSONB().with_variant(JSON(), "sqlite")`, damit dieselbe
Modell-Definition in Tests (SQLite in-memory) und Produktion (Postgres)
funktioniert.

**API um Hints, Write-up und stuck-Erkennung erweitert.** Der Vorschlag in
5.5 kennt keinen Hint-Endpunkt, aber die P4-DoD verlangt "Hints mit Kosten"
und `stuck_timeout`. Da Hint-*Text* und Write-up-*Text* in `de.md` liegen
(Laravel-Domäne), kennt die Engine nur die Kosten-Buchhaltung: `POST
.../hint {hint_id}` merkt sich benutzte Hints (idempotent), `POST
.../write-up` setzt Punkte auf 0 (Fortschritt bleibt erhalten, wie in
Abschnitt 5.3 gefordert). `GET .../state` liefert zusätzlich `points` (schon
abzüglich Hint-Kosten) und `stuck: bool` (Zeit seit letztem Fortschritts-Ereignis
≥ `stuck_timeout_minutes`) — die eigentliche "Hint 1 aktiv anbieten"-UI ist
P5-Sache, die Engine liefert nur das Signal.

**Rate-Limiting für Flag-Versuche ist nicht Teil dieser Phase.** Abschnitt
5.2 verlangt "pro Nutzer und Node" limitiert — die Engine kennt aber keine
Nutzer (nur eine opake `session_id`). Eine Sitzung entspricht in der Praxis
einem Nutzerversuch an einer Node, aber eine belastbare Nutzer-Rate-Limitierung
gehört auf die Laravel-Seite, die Nutzer kennt. Wird zusammen mit P8
(Punkte/Profil) oder P5 (Node-Oberfläche, die den Flag-Endpunkt aufruft)
nachgezogen.

**`environment.files` + `assets/<datei>` statt Sonderlogik pro Node.**
`cat <datei>` liest generisch aus `content/nodes/<slug>/assets/<datei>`,
wenn `<datei>` in `environment.files` steht — keine Node-spezifische
Fallunterscheidung im Code, damit P6 (Wrong Door "ausschließlich aus dem
Schema") tatsächlich ohne Sonderfall auskommt. Fehlt die Asset-Datei
physisch, liefert `cat` einen Platzhaltertext statt eines Fehlers oder
erfundener Prosa (Abschnitt 13) — betrifft aktuell `silent-ct/notizen.txt`
und `silent-ct/conformance-pacs-archiv.txt` (siehe `docs/content-todo.md`).
`netzplan-radiologie.txt` ist dagegen echt befüllt: sein Inhalt steht
wörtlich im mitgelieferten `de.md`-Write-up und wurde von dort übernommen,
nicht erfunden.

**`storescu` auf der Workstation scheitert strukturell, nicht per
Assoziationsprüfung.** Abschnitt 5.3: "storescu von der Workstation
scheitert an fehlenden Dateien — die Bilder liegen auf der Modalität, nicht
beim Lernenden." Der Sende-Erfolgsfall existiert nur über `POST
.../action {action: "send_study"}` auf dem `modality-simulator`-Host.

**DICOM-Dump-Ausgaben (`findscu`) sind synthetisch, nicht real-DCMTK-exakt.**
Format und Inhalt orientieren sich an den in Lektion 1.1 und dem
`silent-ct`-Write-up bereits gezeigten Beispielen (Tag, VR, Wert, Keyword),
aber Study-/Series-Instance-UIDs sind deterministisch aus dem Node-Slug
generiert (`hashlib.sha1`), nicht aus echten Testdaten gelesen — es gibt
keine echte DICOM-Implementierung (Abschnitt 11), nur eine plausible
Simulation.

## Folgen

Eine neue Node mit demselben Schema (P6: Wrong Door) braucht keine
Engine-Änderung, sofern sie mit `environment.hosts`, `known_calling_aets`,
`tools`, `dataset`, `flag`, `hints` auskommt. Sollte Wrong Door doch
Engine-Code brauchen, ist das laut P6-Auftrag selbst ein Schema-Mangel und
wird dann als eigene ADR dokumentiert, nicht stillschweigend verbaut.
