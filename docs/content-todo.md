# Content-Lücken

Von `content:validate` gefundene, echte Lücken im vorhandenen Content, die
**nicht** mechanisch von der Plattform gefixt wurden — jede Behebung wäre
entweder eine redaktionelle Entscheidung oder hätte Prosa erfunden
(Abschnitt 13 des Auftrags: „Wo du Prosa für Lektionen oder Nodes erfinden
müsstest: tu es nicht"). Diese Datei ist die Ablage dafür.

Stand: 12.09.2026, nach Einführung von `content:validate` (P1). Aktueller
Befund: `CONTENT_PATH=../../content php artisan content:validate`.

## Was schon automatisch gefixt wurde

Rein technische, nicht-redaktionelle Lücken wurden direkt behoben:

- `content/datasets.yml` fehlte komplett — angelegt mit den Datensätzen, auf
  die Lektionen und die Node `silent-ct` bereits verweisen (`ct-thorax-60`,
  `ct-thorax-3-slices`), basierend auf den in Abschnitt 4.6 des Auftrags
  bereits festgelegten Referenzwerten.
- Fünf reine ASCII-Diagramme ohne jeden Befehl waren nicht mit
  `<!-- kein-beispiel -->` markiert (1.0, 1.2, 1.4, 1.6, 1.8) — nachgetragen,
  keine Textänderung.
- `nodes/silent-ct/node.yml: related_lessons` verwies auf `"4.1"`, eine noch
  nicht existierende Lektion — Referenz entfernt (Kommentar verweist hierher).
  Der Hinweis auf Lektion 4.1 steht weiterhin in der Prosa unter
  „Verwandte Inhalte" in `nodes/silent-ct/de.md`.

## Behoben in P10.23 — alle 19 Verstöße aus `content:validate`

Die zwei unten dokumentierten Kategorien (19 Fundstellen, Stand
12.09.2026) sind seit P10.23 behoben, jeweils als reine Formsache ohne
neu erfundenen Fachinhalt — siehe ADR 0033:

**Beispielregel, Grenzfälle des `**Was du daran abliest:**`-Musters (15
Fundstellen):** In `lessons/1.0/de.md` (5), `lessons/1.3/de.md` (1),
`nodes/silent-ct/de.md` (8, gemeinsam mit Node Wrong Door in P6
angelegt) und `nodes/wrong-door/de.md` (4) hatte jeder betroffene
Codeblock bereits eine vollständige, korrekte Erklärung als freie Prosa
— nur die feste Marker-Phrase `**Was du daran abliest:**` fehlte davor.
Nachgetragen, ohne den Inhalt der Erklärungen zu ändern.

**Werkzeuge-Grenze (1 Fundstelle):** `lessons/1.7/de.md` benutzte
`storescu` in einem Beispiel, obwohl `meta.yml: tools` bereits vier
Einträge hatte (Limit aus Abschnitt 1 von `content-schema.md`).
Entscheidung: Das Beispiel (eine `storescu`-Ablehnung wegen fehlendem
Presentation Context) duplizierte ohnehin, was Lektion 4.2 seit P10.15
bereits mit einem echten Mitschnitt ausführlich behandelt (`tools:
[storescu, storescp, dcmdump, tshark]`) — der Codeblock wurde durch
einen kurzen Verweis auf 4.2 ersetzt, die vier ursprünglich deklarierten
Werkzeuge (`dcmdump`, `dcmconv`, `dcmcjpeg`, `dcmdjpeg`) sind alle für
den Kernstoff dieser Lektion unverzichtbar und blieben unverändert.

## P3 — Quiz-Karten ohne strukturierte Antworten

Abschnitt 4.1 des Auftrags skizziert `meta.yml: quiz` als strukturiertes
Array mit Fragen, Antwortoptionen und einer korrekten Antwort, aus dem sich
graded Spaced-Repetition-Karten bauen ließen. Der tatsächliche Content
(`content/lessons/*/meta.yml`) hat kein solches Feld — die Quiz-Fragen samt
Antwortoptionen stehen ausschließlich als freie Prosa unter der
Markdown-Überschrift `## Quiz` in `de.md`, ohne maschinenlesbaren
Antwortschlüssel.

P3 rendert diese Prosa unverändert als Teil des normalen Lektionstexts
(korrekt lesbar, wie von der DoD gefordert), baut aber **keine** interaktiven,
graded Quiz-Karten und keine Spaced-Repetition-Wiederholung. Eine
Antwort-Auswertung zu implementieren hätte bedeutet, die korrekten Antworten
selbst zu erfinden — das verbietet Abschnitt 13 ausdrücklich. Sobald die
Quiz-Fragen ein strukturiertes `quiz:`-Feld mit echtem Antwortschlüssel
bekommen (redaktionelle Entscheidung, kein Plattform-Bug), kann die
interaktive Karten-Funktion nachgezogen werden.

## P4 — Zwei Dateien der Node "silent-ct" ohne Textinhalt

`content/nodes/silent-ct/node.yml: environment.files` listet drei Dateien,
die der Lernende in der simulierten Shell per `cat` lesen kann:
`netzplan-radiologie.txt`, `notizen.txt`, `conformance-pacs-archiv.txt`.

Nur `netzplan-radiologie.txt` hat echten Inhalt (`content/nodes/silent-ct/
assets/netzplan-radiologie.txt`) — wörtlich aus dem mitgelieferten
`de.md`-Write-up übernommen (Abschnitt "4. Vergleichen"), keine eigene
Prosa. Für `notizen.txt` (Wartungsprotokoll, Konfig aus Backup 02/2024) und
`conformance-pacs-archiv.txt` (nennt beide Ablehnungsgründe, nicht den AE
Title) gibt es keine im Auftrag oder in `de.md` vorgegebene Textfassung —
sie zu erfinden wäre Abschnitt-13-Verstoß, da beide Dateien konkrete,
prüfbare Fakten des Szenarios enthalten müssten (welche Ablehnungsgründe
genau, welche Konfigurationswerte im Backup standen).

Die Node bleibt ohne diese beiden Dateien vollständig lösbar (Hint h3 nennt
die Lösung bereits direkt), die Engine liefert für sie einen expliziten
Platzhaltertext statt eines Fehlers oder erfundener Prosa (siehe ADR 0005).
Redaktionelle Entscheidung nötig: Text für beide Dateien schreiben (macht
die Node reichhaltiger, ändert aber nicht ihre Lösbarkeit) oder aus
`environment.files` entfernen.

## Track 4 — zehn Gerüste angelegt, Fließtext fehlt

`content/lessons/4.1` bis `4.10` existieren jetzt als Gerüste
(`status: draft`): vollständige `meta.yml` (Track, Reihenfolge, Dauer,
`requires`, höchstens vier Werkzeuge aus der Registry, Glossarbegriffe,
Datensatz) und eine `de.md` mit Titel, Teaser, drei Lernzielen und der
geplanten Gliederung. Titel und Lernziele sind aus der Curriculum-Tabelle in
`konzept-lernplattform.md` (Abschnitt 5, Track 4) abgeleitet, nicht erfunden.

Kein Gerüst enthält Fachprosa oder Werkzeugausgaben — Abschnitt 13 des
Auftrags. Jede Lektion trägt unter „Was zum Schreiben noch fehlt" ihre
eigene Liste offener Punkte. Quer durch alle zehn sind das drei Muster:

- **Ausgaben fehlen.** Jedes Beispiel muss in der Spielwiese erzeugt und
  wörtlich übernommen werden. Das ist der Hauptteil der Arbeit.
- **Die Spielwiese kann das Szenario noch nicht.** Betraf ursprünglich 4.7
  (kein Worklist-Dienst), 4.8 (kein MPPS-, kein
  Storage-Commitment-Gegenpart) und 4.9 (kein TLS-Endpunkt). **4.7 ist seit
  P10.20 keine Ausnahme mehr:** Orthanc (das in diesem Projekt verwendete
  Image) bringt ein Worklists-Plugin bereits mit — es musste nur per
  Konfiguration aktiviert werden, kein neuer Container nötig (siehe ADR
  0030 und den neuen Abschnitt unten). **4.8 ist seit P10.21 ebenfalls
  keine Ausnahme mehr:** Orthanc beherrscht Storage Commitment nativ
  (REST-API, kein Plugin nötig); für MPPS, das Orthanc tatsächlich nicht
  unterstützt, wurde ein eigener, echter `pynetdicom`-SCP in die Toolbox
  aufgenommen (siehe ADR 0031). **4.9 ist seit P10.22 ebenfalls keine
  Ausnahme mehr:** ein eigener, echter DCMTK-`storescp` mit TLS läuft
  direkt in der Toolbox, unabhängig von Orthanc (dessen einziger
  `DicomPort` sich nicht ohne alle anderen Lektionen zu brechen auf TLS
  umstellen ließe — siehe ADR 0032). Damit sind alle zehn
  Track-4-Lektionen geschrieben.
- **Datensätze fehlen.** 4.3 braucht einen Datensatz mit gemischten SOP
  Classes, 4.5 zwei Studies, die gleich aussehen und verschiedene Study
  Instance UIDs tragen (Lektion 1.4 nennt dieses Lab bereits). Beide wären
  neue Einträge in `datasets.yml` samt Erzeugung in `datasets/build/`.

`content:validate` meldet für die zehn neuen Lektionen nichts. (Der
ursprüngliche Hinweis zu `lab.node: null` in allen zehn ist mit P9
überholt — 4.2 zeigt jetzt auf die Node `verbindung-ohne-bild`, siehe
unten.)

## P9 — Node-Definitionen: eine neue spielbare Node, sieben Gerüste

Track 1 lag mit einem echten Defekt vor: sechs der acht Lektionen nach 1.0
(1.1, 1.2, 1.3, 1.4, 1.7, 1.8) verwiesen bereits im mitgelieferten
Content-Paket auf `lab.node`-Slugs, die es nie gab (`first-contact`,
`zwei-ebenen-tiefer`, `wo-steht-das`, `zwillinge`, `halbe-sache`,
`mitgehoert`) — mit `optional: false`. `LessonController` blendet eine
fehlende Node zwar bereits stillschweigend aus (kein Absturz, keine
sichtbare Lücke), aber das Feld log damit eine falsche Tatsache: „diese
Node ist Pflicht" für eine Node, die nicht existiert.

**Node `neue-node` (Lektion 1.6, „AE Title, Host, Port: das Adress-Trio")
ist jetzt vollständig und spielbar.** Sie ist ausschließlich aus dem
bestehenden Schema erzeugt (wie Wrong Door in P6, keine Engine-Änderung):
ein neu installierter MR-Scanner mit Werkseinstellungen in allen drei
Adressfeldern (`remote_host`, `remote_port`, `remote_ae`), die der
Lernende nacheinander korrigieren muss — jede Korrektur deckt exakt die
nächste Stufe der Association-Prüfung aus Abschnitt 5.3 auf. Verifiziert
gegen den echten Stack (siehe ADR 0010).

**Die anderen sechs benannten Slugs plus ein neuer Track-4-Slug
(`verbindung-ohne-bild`, Lektion 4.2) existieren jetzt als echte, aber
absichtlich leere Gerüste** (`node.yml` ohne `environment`, `de.md` mit
einer klar markierten „Gerüst"-Sektion statt Prosa) — dieselbe Diszplin wie
bei den Track-4-Lektionen oben. Jedes Gerüst nennt seine konkrete
technische Blockade in seiner eigenen `de.md`:

| Node | Lektion | Blockiert durch |
|---|---|---|
| ~~`first-contact`~~ | 1.1 | ✅ P10.13 fertig, siehe unten — nicht mehr blockiert |
| ~~`zwei-ebenen-tiefer`~~ | 1.2 | ✅ P10.14 fertig, siehe unten — nicht mehr blockiert |
| ~~`wo-steht-das`~~ | 1.3 | ✅ P10.13 fertig, siehe unten — nicht mehr blockiert |
| ~~`zwillinge`~~ | 1.4 | ✅ P10.5 fertig, siehe unten — nicht mehr blockiert |
| ~~`halbe-sache`~~ | 1.7 | ✅ P10.12 fertig, siehe unten — nicht mehr blockiert |
| ~~`mitgehoert`~~ | 1.8 | ✅ P10.6 fertig, siehe unten — nicht mehr blockiert |
| ~~`verbindung-ohne-bild`~~ | 4.2 | ✅ P10.9 fertig, siehe unten — nicht mehr blockiert |

Diese sieben Lücken sind **Engine-Features, keine Content-Lücken** — anders
als bei Track 4 oben ist hier nicht Fließtext das Fehlende, sondern eine
Fähigkeit der simulierten Engine selbst (`services/engine/app/rules.py`
kennt ausschließlich Host/Port/Called-AE/Calling-AE als Prüfstufen). Sobald
eine dieser Fähigkeiten gebaut ist, kann das jeweilige Gerüst mit echtem
Szenario, Hints und Write-up gefüllt werden, exakt wie bei `neue-node`.

**Hardening (Stand P10.14):** Alle ursprünglich sieben betroffenen
Lektionen (1.1, 1.2, 1.3, 1.4, 1.7, 1.8, 4.2) sind inzwischen auf
`lab.optional: false` umgestellt — jede dieser sieben Nodes ist
vollständig. Damit ist diese komplette Liste abgearbeitet.

## P10 — Fortschritt gegen `P10-Roadmap-DCMLab.md`

Laufende Liste, damit der Stand jederzeit aus dieser Datei ablesbar ist,
nicht nur aus Commit-Historie. Die Roadmap selbst ist ein extern
geliefertes Dokument (kein Teil des ursprünglichen Auftrags) — wo sie
unbelegte Fakten verlangt, gilt weiterhin Abschnitt 13.

**Engine-Features (sieben laut Roadmap Abschnitt III):**

| Feature | Status | Node(s) freigeschaltet |
|---|---|---|
| C-FIND-Matching (Query-Level, Wildcards) | ✅ P10.1 | `c-find-mismatch` (neu) |
| Größenlimit (C-STORE) | ✅ P10.2 | `oversized-image` (neu) |
| Transfer-Syntax-Aushandlung | ✅ P10.3 | `syntax-negotiation-fails` (neu) |
| Multiframe-Generator | offen | — (Track 3) |
| Worklist-Query | ✅ P10.11 (ADR 0021, `content-schema.md` 6f) | `worklist-query-empty` (neu) |
| Patient-Merge / Study-Split | ✅ P10.4 (kein neuer Code nötig, siehe ADR 0014) | `patient-merge-discovery` (neu) |

**Zusätzlich, über die sieben Roadmap-Features hinaus:**

| Feature | Status | Node(s) freigeschaltet |
|---|---|---|
| Abstract-Syntax-(SOP-Class-)Ablehnung (Sendeauftrag) | ✅ P10.9 (ADR 0019, `content-schema.md` 6d) | `verbindung-ohne-bild` (neu) |
| Abstract-Syntax-Ablehnung pro Objekt (direktes `storescu`) | ✅ P10.10 (ADR 0020, `content-schema.md` 6e) | `teiltransfer` (neu) |
| `dcmdump`-Objektmetadaten (Transfer Syntax, LossyImageCompression) | ✅ P10.12 (ADR 0022, `content-schema.md` 6g) | `halbe-sache` |
| `dcmftest` + weitere `dcmdump`-Felder (Modality, StudyDescription, CT-Metadaten) | ✅ P10.13 (ADR 0023, `content-schema.md` 6h) | `first-contact`, `wo-steht-das` |
| `dcmdump`-Hierarchiefelder (PatientID, StudyInstanceUID, SeriesInstanceUID) | ✅ P10.14 (ADR 0024, `content-schema.md` 6i) | `zwei-ebenen-tiefer` |

**Nebeneffekte:**

- `zwillinge` war blockiert, weil ein Archiv-Host nur einen Bestand
  kannte. Mit `records` (ADR 0011, `content-schema.md` Abschnitt 6a)
  kann ein Archiv jetzt mehrere Studies vorhalten. **`zwillinge` ist
  seit P10.5 fertig** (ADR 0015, inkl. eines neuen realen Feldes
  `AccessionNumber`). `zwei-ebenen-tiefer` brauchte am Ende gar keine
  C-FIND-/Archiv-Erweiterung — ihr eigener, bereits im Fließtext
  spezifizierter Lab-Auftrag ("Dein Lab") ist eine reine lokale
  Zählaufgabe über mehrere Objekte; **seit P10.14 fertig** (ADR 0024,
  neues, kleines Feature 9: drei weitere `dcmdump`-Hierarchiefelder).
- `halbe-sache` (1.7), `mitgehoert` (1.8) und `verbindung-ohne-bild`
  (4.2) waren blockiert, weil die Engine keine Presentation-Context-
  Aushandlung kannte. Mit Feature 3 (ADR 0013, `content-schema.md`
  Abschnitt 6c) ist das technisch lösbar. **`mitgehoert` ist seit P10.6
  fertig** (ADR 0016: drei unabhängige Fehler — Host, Port, Transfer
  Syntax — nacheinander, damit das eigentliche Lernziel "ein Log lesen
  und erklären" geübt wird, nicht nur ein einzelner Fix). **`verbindung-
  ohne-bild` ist seit P10.9 fertig** (ADR 0019: neues Feature 4 —
  Abstract-Syntax-/SOP-Class-Ablehnung, die bislang fehlende zweite
  Hälfte von Feature 3, da ein Presentation Context Abstract Syntax und
  Transfer Syntax unabhängig voneinander aushandelt; hier lehnt das
  Archiv ein per Software-Update umgestelltes Enhanced-CT-Gerät ab,
  obwohl C-ECHO grün bleibt). **`halbe-sache` ist seit P10.12 fertig**
  (ADR 0022) — allerdings nicht über Feature 3, sondern über ein neues,
  kleineres Feature 7 (`dcmdump`-Objektmetadaten): die Node braucht gar
  keine Presentation-Context-Aushandlung, sondern prüft nur, ob das
  Pflichtfeld `LossyImageCompression` bei einer verlustbehaftet
  komprimierten Datei tatsächlich gesetzt ist.
- `first-contact` (1.1) und `wo-steht-das` (1.3) waren blockiert, weil
  `dcmdump` auf eine lokale Datei nur einen Platzhaltertext lieferte.
  **Beide sind seit P10.13 fertig** (ADR 0023, neues Feature 8:
  `dcmftest` + fünf weitere reale `dcmdump`-Felder) — beide Lektionen
  hatten dafür bereits eigene, präzise Lab-Spezifikationen im Fließtext
  ("Dein erstes Lab" / "Dein Lab"), die die Nodes jetzt genau einlösen.

**Nodes (vier laut Roadmap Abschnitt IV, alle fertig):**

| Node | Status |
|---|---|
| `c-find-mismatch` | ✅ P10.1, vollständig und live verifiziert |
| `oversized-image` | ✅ P10.2, vollständig und live verifiziert (deckt nur die Größenlimit-Hälfte von Lektion 4.3 ab — die SOP-Class-Hälfte deckt seit P10.10 `teiltransfer` ab, das Schema erlaubt aber nur einen `lab.node` pro Lektion) |
| `syntax-negotiation-fails` | ✅ P10.3, vollständig und live verifiziert |
| `patient-merge-discovery` | ✅ P10.4, vollständig und live verifiziert (Lektion 4.6 seit P10.8 ebenfalls vollständig — aktives serverseitiges Merge fehlt weiterhin, siehe unten) |

**Weitere, in der Roadmap an anderer Stelle namentlich genannte Nodes
(Abschnitt III.3.5/V), inzwischen ebenfalls fertig:** `worklist-query-empty`
(✅ P10.11, Lektion 4.7).

**Zusätzlich, über die Roadmap hinaus:** `zwillinge` (✅ P10.5),
`mitgehoert` (✅ P10.6), `verbindung-ohne-bild` (✅ P10.9),
`teiltransfer` (✅ P10.10), `halbe-sache` (✅ P10.12),
`first-contact` und `wo-steht-das` (beide ✅ P10.13) sowie
`zwei-ebenen-tiefer` (✅ P10.14) — keine Roadmap-Nodes, aber durch
Feature 1 bzw. Feature 3/4/5/7/8/9 unblockierte Stubs.

Verbleibend von den sieben Roadmap-Engine-Features: nur noch der
Multiframe-Generator (für Track 3). **Keine unblockierten, aber
ungeschriebenen Node-Stubs mehr bekannt** — die ursprüngliche
Sieben-Node-Liste (siehe oben, Abschnitt "Track 1 — Engine-Limits")
ist mit P10.14 vollständig abgearbeitet.

**Schema-Lücke aus P10.10, weiterhin offen:** `lessons/<id>/meta.yml`
erlaubt aktuell nur einen einzelnen `lab.node`-Wert. Lektion 4.3
bräuchte zwei (`oversized-image` für das Größenlimit, `teiltransfer`
für die SOP-Class-Ablehnung), um beide gleichrangig zu behandeln.
**Seit P10.16 mit der dokumentierten Übergangslösung gelöst:**
`lab.node` bleibt `oversized-image`, `teiltransfer` wird im Fließtext
nur namentlich genannt, ohne toolbar-seitige Verknüpfung (siehe ADR
0026). Ein `lab.nodes`-Array bliebe die sauberere, aber aufwendigere
Lösung (PHP-Controller + Vue-Komponente betroffen) — nicht gebaut, da
die Übergangslösung ausreicht.

**Infrastruktur-Lücke aus P10.11, geschlossen in P10.20:** Lektion
4.7s eigener Fließtext brauchte laut ihrer geplanten Gliederung echte
Sandbox-Beispiele für die Modality Worklist — die Spielwiese (P7,
`containers/toolbox`) stellte bislang keinen Worklist-Dienst und
keinen Worklist-Testdatensatz bereit. Der ursprünglich angenommene
Aufwand (neuer Container oder neuer Dienst im Toolbox-Image) hat sich
beim tatsächlichen Bauen als unnötig erwiesen: Orthanc selbst
(`orthancteam/orthanc`) bringt das Worklists-Plugin bereits mit, es
musste nur aktiviert werden (`containers/orthanc/orthanc.json`).
`datasets/build/generate.py` erzeugt seit P10.20 zusätzlich echte
Worklist-Einträge (Subcommand `worklist`, neuer Eintrag in
`content/worklists.yml`), die der Sandbox-Orchestrator pro Sitzung
frisch generiert und in Orthanc einhängt. Lektion 4.7 ist damit
vollständig geschrieben, siehe ADR 0030 — die zugehörige Node
(`worklist-query-empty`) war davon unberührt, da Nodes die simulierte
Engine nutzen, nicht die Spielwiese.

**Track-4-Vervollständigung: begonnen.** Lektion 4.1 ("Association
rejected") ist seit P10.7 vollständig geschrieben (echte
Sandbox-Beispiele, kein Node-Text kopiert — siehe ADR 0017) und zeigt
korrekt auf Node `silent-ct` als Lab. **Lektion 4.6 ("Falscher
Patient") ist seit P10.8 ebenfalls vollständig geschrieben** — anders
als 4.1 ist ihr Thema (Patient zweimal registriert) voll live
reproduzierbar, keine `<!-- kein-beispiel -->`-Blöcke nötig (siehe ADR
0018); neuer Glossarbegriff `coercion` ergänzt. **Lektion 4.2
("Verbindung steht, aber nichts kommt an") ist seit P10.15 ebenfalls
vollständig geschrieben** — ihr Thema (Presentation-Context-Ablehnung)
ist wie bei 4.1 in der aktuellen Spielwiese nicht live reproduzierbar
(zweiter, unabhängiger Beleg für denselben Infrastruktur-Fund, siehe
ADR 0025); reale Beispiele decken die erfolgreiche Aushandlung ab
(`storescu -d -cx` zeigt Proposed/Accepted sauber nebeneinander), die
eigentliche Ablehnung verweist ehrlich auf Node `verbindung-ohne-bild`.
**Lektion 4.3 ("Nur manche Bilder kommen an") ist seit P10.16
ebenfalls vollständig geschrieben** — dritter, unabhängiger Beleg für
denselben Infrastruktur-Fund (weder SOP-Class- noch Größenlimit-
Ablehnung live reproduzierbar, siehe ADR 0026); ein echtes drittes
Fehlerbild blieb aber reproduzierbar und ist der eigentliche Kern der
Lektion geworden: ein real unvollständig gesendeter `ct-thorax-60`-
Datensatz (59 von 60 Dateien), nachgewiesen über das reale
`findscu`-Feld `NumberOfStudyRelatedInstances`. Löst zugleich die
Ein-`lab.node`-Schema-Lücke aus P10.10 pragmatisch (`teiltransfer` nur
im Fließtext genannt). **Lektion 4.4 ("Timeout") ist seit P10.17
ebenfalls vollständig geschrieben** — zwei der vier geplanten
Ursachenklassen sind real reproduzierbar (geschlossener Port: `TCP
Initialisation Error: Connection refused`; offener Port ohne
DICOM-Dienst: `Unknown PDU type received`), die anderen beiden
(unbeantwortete Association, Idle-Timeout/MTU während eines laufenden
Transfers) sind strukturell nicht in einer einzelnen Docker-Umgebung
herstellbar — anders begründet als die Orthanc-Großzügigkeit aus ADR
0017/0025/0026, aber ebenfalls ehrlich als `<!-- kein-beispiel -->`
markiert (ADR 0027). Kein Lab nötig — kein passender Node-Stub
vorhanden, `lab.node` bleibt `null`. Nebenbei einen YAML-Fehler in der
Objectives-Frontmatter gefunden und behoben (unquotierter Doppelpunkt
führte zu einer sichtbar falsch gerenderten Zuordnung statt Klartext).
**Lektion 4.5 ("Studie ist gesplittet / doppelt") ist seit P10.18
ebenfalls vollständig geschrieben** — wie 4.6 vollständig real, kein
`<!-- kein-beispiel -->`-Block nötig: Split (zwei Studies, frische
UIDs) und Dublette (dieselbe Study zweimal gesendet) wurden beide real
erzeugt und zeigen am Archiv fundamental unterschiedliches Verhalten
(zwei `findscu`-Responses vs. unveränderte Instance-Zahl), siehe ADR
0028. Kein Lab nötig — Node „Zwillinge" (1.4) behandelt ein verwandtes,
aber anderes Problem und wird nur verlinkt. **Lektion 4.10
("Systematik: Logs, Wireshark-Filter, Reproduzieren"), die
Abschlusslektion des Tracks, ist seit P10.19 ebenfalls vollständig
geschrieben** — vollständig real, kein `<!-- kein-beispiel -->`-Block
nötig: ein Verbositätsvergleich `storescu`/`storescu -v`/
`storescu -d -cx` sowie ein echter `tshark`-Mitschnitt mit korrekter
DICOM-Dissektion (A-ASSOCIATE, P-DATA, A-RELEASE) und ein
`tcp.stream eq N`-Beispiel zum Eingrenzen eines Mitschnitts mit
mehreren Associations. Dabei wurde ein bisher unbekannter
Infrastruktur-Fund gemacht und behoben, siehe ADR 0029 und den neuen
Abschnitt unten. Kein Lab nötig — kein passender Node-Stub,
`lab.node` bleibt `null`. **Lektion 4.7 ("Worklist ist leer") ist seit
P10.20 ebenfalls vollständig geschrieben** — vollständig real, kein
`<!-- kein-beispiel -->`-Block nötig: ein `findscu -W` mit passendem
Modality-Filter liefert den für die Sitzung real generierten
Worklist-Auftrag, derselbe Aufruf mit nicht-passendem Filter liefert
ein echtes leeres Ergebnis (dieselbe `Find SCP Result: 0x0000
(Success)`-Zeile, nur ohne jede Antwort davor — genau die
Ununterscheidbarkeit, um die es in der Lektion geht), dazu ein echter
`tshark`-Mitschnitt der zugrundeliegenden C-FIND-Assoziation. Dafür
musste die Spielwiese erstmals einen echten Worklist-Dienst bekommen —
Orthancs eigenes Worklists-Plugin, siehe ADR 0030 und den neuen
Abschnitt unten. **Lektion 4.8 ("Bilder da, aber Befund geht nicht
raus") ist seit P10.21 ebenfalls vollständig geschrieben** —
vollständig real, kein `<!-- kein-beispiel -->`-Block nötig: ein
echter MPPS-`N-CREATE`/`N-SET`-Austausch gegen einen eigenen,
neu geschriebenen `pynetdicom`-SCP (`mppsscp.py`), dazu ein echter
`tshark`-Mitschnitt der Assoziation; für Storage Commitment (das
Orthanc nativ beherrscht, kein neuer Dienst nötig) sowohl ein
erfolgreicher Fall (echtes, gespeichertes Objekt) als auch eine echte
Ablehnung (`FailureReason 274` für eine nie gespeicherte SOP Instance
UID) — siehe ADR 0031. Kein Lab nötig — kein passender Node-Stub
vorhanden, `lab.node` bleibt `null`. **Lektion 4.9 ("Nach
TLS-Aktivierung geht nichts mehr") ist seit P10.22 ebenfalls
vollständig geschrieben** — ein eigener, echter DCMTK-`storescp` mit
TLS läuft in der Toolbox (unabhängig von Orthanc, siehe ADR 0032):
echter erfolgreicher `echoscu`/`storescu`-Aufruf über anonyme TLS,
echte Ablehnung ohne vertrauenswürdiges Zertifikat (reiner
TLS-Fehlercode, keine DICOM-Statusmeldung), ein realer Fund zur
fehlenden Namensprüfung bei DCMTK-TLS, und ein echter
`tshark`-Mitschnitt, der ab dem Handshake nur noch verschlüsselte
`Application Data` zeigt. Zwei der drei ursprünglich geplanten
Ursachenklassen (abgelaufenes Zertifikat, Cipher-Konflikt) sind ehrlich
als nicht reproduzierbar markiert. **Damit sind alle zehn
Track-4-Lektionen (4.1–4.10) vollständig geschrieben.**

**Neue Spielwiese-Fähigkeit aus P10.20: ein echter
Modality-Worklist-Dienst.** Ursprünglich (P10.11) als eigenständiges
Infrastrukturthema mit angenommenem Aufwand (neuer Container oder
Dienst im Toolbox-Image) zurückgestellt — beim tatsächlichen Bauen
zeigte sich, dass Orthanc (`orthancteam/orthanc`) das
Worklists-Plugin bereits mitbringt und nur per Konfiguration aktiviert
werden musste. `datasets/build/generate.py` hat dafür einen neuen
Subcommand `worklist` bekommen (parallel zu `ct`), `content/worklists.yml`
definiert den Auftrag (analog zu `content/datasets.yml`), und der
Sandbox-Orchestrator generiert pro Sitzung einen frischen,
real gültigen Worklist-Eintrag mit aktuellem Datum und mountet ihn in
Orthanc ein. Details und vollständige Verifikation (inklusive der
echten "Spielwiese beenden"-Aktion, die auch das neue Volume korrekt
entfernt) in ADR 0030.

**Neue Spielwiese-Fähigkeit aus P10.19: `tshark` ist jetzt echt
nutzbar.** Fünf `meta.yml`-Dateien (1.8, 4.1, 4.2, 4.4, 4.10)
deklarieren `tshark` als Werkzeug; keine davon konnte es bisher live
zeigen. Der Grund war zweistufig: (1) `tshark` war in
`containers/toolbox/Dockerfile` schlicht nie installiert — jede
frühere Zurückstellung auf `<!-- kein-beispiel -->` war also korrekt
vorsichtig, aber aus einem nie geprüften Grund; (2) nach Installation
verhinderte `security_opt: "no-new-privileges"`
(`services/sandbox/app/docker_ops.py`) weiterhin echte Mitschnitte,
weil `dumpcap` als Nicht-root-Nutzer auf eine Datei-Capability
(`setcap`) angewiesen ist, die genau dieses Flag blockiert — selbst mit
korrekt gesetztem `cap_add: [NET_RAW, NET_ADMIN]`. Mit expliziter
Nutzerfreigabe wurde `no-new-privileges` **ausschließlich für den
Toolbox-Container** aufgehoben (Orthanc unverändert, keine weiteren
Rechte, weiterhin kein Egress) — Details und vollständige
Verifikation über den echten Orchestrator in ADR 0029. Die bereits
gemergten Lektionen 4.1, 4.2, 4.4 (sowie 1.8) wurden **nicht**
rückwirkend um echte `tshark`-Beispiele ergänzt — nur der neue Fund
dokumentiert; ob sich eine nachträgliche Überarbeitung lohnt, ist eine
eigene, spätere Entscheidung.

**Wichtiger Infrastruktur-Fund aus P10.7:** Die Spielwiese (Orthanc,
P7) akzeptiert wegen `DicomAlwaysAllowEcho`/`DicomAlwaysAllowStore`/etc.
(`containers/orthanc/orthanc.json`, ADR 0008) **jeden** Called/Calling
AE Title — verifiziert mit `echoscu -aet BELIEBIGER-NAME -aec
FALSCHER-NAME 127.0.0.1 4242`, erfolgreich angenommen. Das heißt: Eine
AE-Title-basierte Association-Ablehnung lässt sich in der aktuellen
Spielwiese **nicht** live erzeugen — nur in den simulierten Nodes. Das
betrifft nicht nur Lektion 4.1, sondern jede künftige Lektion, die genau
diesen Fehler in der Spielwiese demonstrieren will (potenziell Track 2,
C-ECHO/C-STORE-Grundlagen). Redaktionelle/technische Entscheidung nötig:
entweder Lektionen verweisen für diesen Fehlertyp konsequent auf Nodes
(wie in 4.1 gelöst), oder die Spielwiese bekommt optional eine
strengere Konfiguration (eigene Aufgabe, nicht Teil von P10.7).
**P10.15 bestätigt dieselbe Großzügigkeit für Presentation-Context-
Ablehnungen:** Orthanc nimmt in diesem Aufbau auch verlustbehaftet
komprimierte Objekte (JPEG Lossless) anstandslos an — keine
SOP-Class- oder Transfer-Syntax-Ablehnung live erzeugbar (ADR 0025).
Die Redaktionsentscheidung von 4.1 gilt damit für mindestens zwei
unabhängige Fehlerklassen und dürfte weitere Track-4-Lektionen
betreffen, die noch nicht geschrieben sind (z. B. 4.9, TLS-Fehler).
**P10.16 bestätigt es ein drittes Mal:** ein echtes ~400-MB-Objekt und
ein echtes Secondary-Capture-Objekt wurden beide anstandslos
gespeichert — weder Größenlimit noch SOP-Class-Ablehnung sind live
erzeugbar. Diese Grenze betrifft damit inzwischen drei unabhängige
Fehlerklassen (AE-Title, Presentation Context, Objektgröße/SOP-Class)
und ist kein Einzelfall mehr, sondern ein wiederkehrendes Muster für
jede Lektion, die eine serverseitige Ablehnung zeigen will.

**Track 2, Track 3:** noch nicht begonnen.

**Lokale Umgebung (seit P10.8 beobachtet, kein Content-Problem):** Die
lokale PHP-Installation (`8.4.0`) erfüllt `composer.lock`s Anforderung
`>= 8.4.1` nicht mehr — betrifft `main` unverändert, nicht nur diesen
Branch. Pest/Pint/PHPStan lassen sich dadurch aktuell nicht direkt lokal
ausführen; `content:validate`/`content:build` liefen stattdessen im
echten `app`-Container (Docker-Image, eigenes PHP) desselben isolierten
Verifikations-Stacks. CI (`shivammathur/setup-php@v2`) ist davon nicht
betroffen und bleibt die maßgebliche Prüfung für PHP-Tests.

## CI

`content:validate` läuft in der CI-Pipeline (`content`-Job), aber mit
`continue-on-error: true`, solange diese Liste nicht leer ist. Sobald die
obigen Punkte entschieden und behoben sind, das `continue-on-error` in
`.github/workflows/ci.yml` entfernen.
