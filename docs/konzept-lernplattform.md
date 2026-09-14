# DCM Lab — Lernplattform für DICOM und PACS

**Konzept**

*Arbeitsstand 12.09.2026 — Curriculum, Spielmechanik, Architektur. Prototyp einer Node vorhanden (siehe `prototyp-silent-ct.md`).*

---

## 1. Warum das eine Lücke ist

Das bestehende Angebot zerfällt in drei Kategorien, und keine davon bedient den Autodidakten:

| Anbieter-Typ | Beispiele | Was fehlt |
|---|---|---|
| Verbands-/Präsenzkurse | SIIM Core Intensives, MTMI PACS Administrator Course, OFFIS DICOM-Schulungen, HL7 Austria | Termingebunden, teuer (vierstellig), Frontalunterricht, kein Wiederholen |
| Herstellerschulungen | Philips Vue PACS, RSTI | Produktspezifisch, nicht Standard-orientiert |
| Videokurse | PACS Bootcamp, Healthcare IT Solutions | Passiv, kein Feedback, kein "hab ich's wirklich verstanden"-Beweis |

**Was es nirgends gibt:** eine Umgebung, in der jemand um 22 Uhr eine kaputte Association selbst debuggt, bis sie läuft. Genau das ist der HackTheBox-Ansatz. Der Lernerfolg bei HTB kommt nicht aus dem Lernmaterial, sondern aus dem Moment, in dem etwas endlich funktioniert.

**Positionierung in einem Satz:** *Die Plattform, auf der man PACS-Administration lernt, indem man kaputte PACS repariert.*

Sekundärnutzen, der das Ganze tragfähig macht: ein nachweisbares Profil. PARCA-Zertifikate sind im deutschsprachigen Raum kaum verbreitet, ein öffentliches Plattform-Profil mit gelösten Szenarien ist für Bewerbungen und interne Argumentation bares Geld wert.

### Die Lücke sitzt tiefer als gedacht

Das Angebot ist nicht nur zu teuer oder zu unpraktisch — es setzt zu spät an. Weil Häuser selten Budget für Schulung freigeben, bekommt die Mehrheit der Leute, die PACS betreuen, *gar keinen* Einstieg. Sie bekommen ein System übergeben und lernen am Ticket.

Die Folge ist konkreter, als es klingt: Vielen ist nicht bekannt, dass es überhaupt Werkzeuge gibt. Freie, seit Jahrzehnten gepflegte DICOM-Toolkits, mit denen sich jede Verbindung selbst nachstellen ließe, existieren — sie werden nur nirgends angekündigt. Wer nie in eine Schulung kommt, erfährt auch nie von ihnen.

**Daraus folgt für das Curriculum:** Die Existenz der Werkzeuge ist selbst ein Lernziel, keine Voraussetzung. Deshalb steht Lektion 1.0 (*Die Werkzeugkiste*) vor allem anderen.

---

## 2. Der Grundsatz: Der Lernende installiert nichts

Dieselbe Gruppe, die kein Schulungsbudget bekommt, hat in aller Regel auch keine lokalen Adminrechte, kein Docker auf dem Dienstrechner und keinen Freigabeprozess, den sie mal eben durchläuft. Für einen 1st-Level-Supporter ist „installier dir erst mal eine Testumgebung" eine höhere Hürde als jede Kommandozeile.

**Alles, was zum Üben nötig ist, stellt die Plattform.** Ein Browser genügt. Wer sich danach freiwillig zu Hause etwas Eigenes aufbaut, ist willkommen — aber es ist nie Voraussetzung, und keine Lektion und keine Node setzt es voraus.

Das ist kein Komfortversprechen, sondern die Bedingung dafür, dass die Zielgruppe aus Abschnitt 1 überhaupt ankommt. Es hat Folgen für die Architektur (Abschnitt 7) und für die Kosten (Abschnitt 11) — die werden hier bewusst in Kauf genommen.

Zwei weitere Grundsätze derselben Art:

- **Nie echte Patientendaten.** Alle Testdaten sind synthetisch oder stammen aus explizit freigegebenen, anonymisierten Datensätzen.
- **Nichts, was der Lernende tut, erreicht ein fremdes System.** Die Übungsumgebung hat keinen Weg nach draußen.

---

## 3. Zielgruppen

| Persona | Vorwissen | Braucht | Einstiegspunkt |
|---|---|---|---|
| **Der Quereinsteiger** — IT-Admin, bekommt PACS "mit dazu" | Netzwerk ja, Medizin nein | Grundvokabular, Angst nehmen | Track 1, Lektion 1.0 |
| **Der Supporter** — 1st/2nd Level, nimmt Tickets an | Kennt Symptome, nicht Ursachen | Fehlerbilder → Ursache | Lektion 1.0, dann Track 4 und Labs |
| **Der MTR/MTRA mit IT-Affinität** | Modalitätenseite stark | Was passiert hinter dem Send-Knopf | Track 1 + 2 |
| **Der erfahrene PACS-Admin** | Viel Praxis, Lücken im Standard | Tiefe: SR, Hanging Protocols, IHE, DICOMweb | Track 5 + Hard-Labs |
| **Der Student / Interessierte** | Theorie, keine Praxis | Überhaupt mal Hand anlegen | Track 1 + Spielwiese |

Die Persona "Supporter" ist die wichtigste für den Anfang: größte Gruppe, größter Leidensdruck, klarste Erfolgsmomente.

**Offene Frage zur Lab-Form:** Der Supporter und die MTRA arbeiten im Alltag nicht auf der Kommandozeile. Der Prototyp löst das über Befehlsvorlagen zum Anklicken; ob das reicht oder ob es einen zweiten, klickbaren Werkzeugmodus braucht, entscheidet die Testrunde (siehe `prototyp-silent-ct.md`, Abschnitt 8).

---

## 4. Didaktisches Modell

Jede Einheit folgt demselben Dreischritt — bewusst kurz gehalten, damit es in eine Mittagspause passt:

```
  KONZEPT (5-8 Min. Lesen)  →  LAB (15-30 Min. Hands-on)  →  CHALLENGE (Flag)
  "Was ist ein SCU/SCP?"       "Baue eine Association auf"   "Wie lautet der
                                                             Called AE Title?"
```

**Konzept** ist kurz und ohne Standard-Prosa. Nicht "Der Service Class User initiiert die Association", sondern: *Wer anruft, ist SCU. Wer den Hörer abnimmt, ist SCP. Ein CT ist beim Bilderschicken SCU, beim Worklist-Abfragen ebenfalls SCU — die Rolle hängt am Service, nicht am Gerät.*

Jede Erklärung wird mit einem laufenden Beispiel belegt: Befehl, echte Ausgabe, eine Zeile Leseanleitung. Die verbindliche Form steht in `content-schema.md`, Abschnitt 0.

**Lab** ist eine echte Umgebung mit echten DICOM-Tools. Kein Multiple Choice.

**Challenge** ist ein Flag: ein Wert, den man nur kennt, wenn man es wirklich gelöst hat. Bei DICOM ist das elegant lösbar — der Flag steht in einem privaten Tag des Bildes, das man erst empfangen kann, wenn die Verbindung steht.

Zusätzlich: **Quiz-Karten** nach Spaced-Repetition für das reine Faktenwissen (VR-Typen, Standard-Ports, häufige Tags). Nicht gamifiziert, nur als Trainingsschleife.

---

## 5. Curriculum

### Track 1 — Fundamente *(Einsteiger, ca. 7 Std.)*

| # | Lektion | Lab |
|---|---|---|
| 1.0 | **Die Werkzeugkiste** — was es gibt, was es kostet, wofür man es nimmt | Spielwiese: erste vier Befehle |
| 1.1 | Was DICOM eigentlich ist: Dateiformat **und** Netzwerkprotokoll | Eine .dcm-Datei öffnen und zerlegen |
| 1.2 | Patient → Study → Series → Instance: das Datenmodell | Hierarchie in einem Testarchiv nachvollziehen |
| 1.3 | Tags, Groups, Elements, VR, Value Multiplicity | Tags per Tool auslesen, Wert finden |
| 1.4 | UIDs: warum alles eine Nummer hat, Root-UIDs, Eindeutigkeit | Zwei Studies unterscheiden, die gleich aussehen |
| 1.5 | SCU und SCP — Rollen, nicht Geräte | C-ECHO in beide Richtungen |
| 1.6 | AE Title, Host, Port: das Adress-Trio | Node konfigurieren bis C-ECHO grün |
| 1.7 | Transfer Syntax und Kompression | Dieselbe Serie in drei Syntaxen vergleichen |
| 1.8 | Association, Presentation Context, Negotiation | Association-Log lesen und erklären |

### Track 2 — Die Services *(Aufbau, ca. 8 Std.)*

| # | Lektion | Lab |
|---|---|---|
| 2.1 | C-ECHO: der Ping, der keiner ist | — |
| 2.2 | C-STORE: Bilder senden und empfangen | Eine Serie von A nach B schicken |
| 2.3 | C-FIND: Query-Level, Matching-Keys, Wildcards | Studie anhand von Bruchstücken finden |
| 2.4 | C-MOVE vs. C-GET: warum C-MOVE drei Beteiligte hat | Retrieve an eine dritte Node |
| 2.5 | Modality Worklist (MWL): das meistunterschätzte Thema | Worklist-Query aus Modalitätssicht bauen |
| 2.6 | MPPS: Status-Rückmeldung der Modalität | MPPS-Ablauf mitlesen |
| 2.7 | Storage Commitment: "hast du's wirklich?" | Commitment-Zyklus durchspielen |
| 2.8 | DICOMweb: WADO-RS, QIDO-RS, STOW-RS | Dieselbe Abfrage per DIMSE und per REST |

### Track 3 — Das Bild selbst *(Aufbau, ca. 5 Std.)*

| # | Lektion | Lab |
|---|---|---|
| 3.1 | IOD und Module: woraus ein CT-Bild besteht | Pflichtattribute prüfen |
| 3.2 | Pixeldaten, Photometric Interpretation, Bits Allocated | Falsch dargestelltes Bild reparieren |
| 3.3 | Window Center/Width, Rescale Slope/Intercept, LUTs | Warum das Bild "schwarz" ist |
| 3.4 | Multiframe, Enhanced IODs | Klassisch vs. Enhanced vergleichen |
| 3.5 | Structured Reports, Presentation States, Key Objects | Einen SR lesen |
| 3.6 | Specific Character Set — Umlaute und was schiefgeht | Kaputte Umlaute diagnostizieren |

### Track 4 — Troubleshooting *(das Herzstück, ca. 10 Std.)*

Aufgebaut als Fehlerbild → Hypothesen → Diagnoseweg → Fix. Jede Lektion ist direkt ein Lab.

| # | Fehlerbild | Lernziel |
|---|---|---|
| 4.1 | "Association rejected" | Called/Calling AE Title, Konfigurationsabgleich |
| 4.2 | "Verbindung steht, aber nichts kommt an" | Presentation Context / keine gemeinsame Transfer Syntax |
| 4.3 | "Nur manche Bilder kommen an" | SOP Class nicht akzeptiert, Größenlimits |
| 4.4 | "Timeout" | Netzwerk, Firewall, 104 vs. 11112, MTU, Idle Timeouts |
| 4.5 | "Studie ist gesplittet / doppelt" | Study Instance UID, Fehlverhalten der Modalität |
| 4.6 | "Falscher Patient" | Patient ID, Coercion, Merge/Move, Konsequenzen |
| 4.7 | "Worklist ist leer" | Query-Keys, Modality-Filter, Zeitfenster, HIS-Anbindung |
| 4.8 | "Bilder da, aber Befund geht nicht raus" | MPPS/Commitment/Statuskette |
| 4.9 | "Nach TLS-Aktivierung geht nichts mehr" | Zertifikate, Cipher, Legacy-Geräte |
| 4.10 | Systematik: Logs, Wireshark-Filter, Reproduzieren | Vorgehen statt Raten |

### Track 5 — Betrieb und Integration *(Fortgeschritten, ca. 8 Std.)*

| # | Lektion |
|---|---|
| 5.1 | IHE-Profile: SWF, PIR, XDS-I — was der Name verspricht |
| 5.2 | Conformance Statements lesen und daraus Aussagen ableiten |
| 5.3 | Migration und Archivwechsel: Fallstricke |
| 5.4 | Anonymisierung, Pseudonymisierung, Forschungsdaten |
| 5.5 | Datenschutz, Zugriffsprotokollierung, Aufbewahrungsfristen |
| 5.6 | Security: Netzsegmentierung, Legacy-Modalitäten, bekannte Angriffsflächen |
| 5.7 | Monitoring und Betriebsführung: was man messen sollte |
| 5.8 | Beschaffung: die richtigen Fragen an den Hersteller |

**Gesamtumfang:** ~38 Lernstunden, 41 Lektionen, gut 30 davon mit Lab.

---

## 6. Spielmechanik

Übernommen wird von HackTheBox das, was funktioniert — nicht die Ästhetik.

### Die Szenarien ("Nodes")

Statt "Boxes" heißen die Aufgaben **Nodes** (passt zur DICOM-Welt). Jede Node ist eine laufende, absichtlich kaputte oder unvollständige Umgebung.

| Element | Umsetzung |
|---|---|
| **Schwierigkeit** | Easy / Medium / Hard / Insane |
| **Flag** | Wert aus einem privaten Tag im Zielbild, oder Hash aus Query-Ergebnis |
| **Punkte** | 10 / 25 / 50 / 100, Abzug bei Hint-Nutzung |
| **Hints** | Gestaffelt (3 Stufen), kosten Punkte — verhindert Frustabbruch |
| **Write-up** | Nach Lösung freigeschaltet, andere Lösungswege sichtbar |
| **First Blood** | Bonus für die erste Lösung einer neuen Node |

### Beispiel-Node "Silent CT" *(Easy)*

> Das CT in Raum 3 sendet seit dem Wartungsfenster keine Bilder mehr ins Archiv. Der Techniker sagt, am Gerät sei alles unverändert. Du hast Zugriff auf das Archiv und auf den Modalitäten-Simulator. Bring die Bilder rein und nenne die Series Description der Serie, die ankommt.

Tatsächliche Ursache: Der Called AE Title unterscheidet sich um ein Zeichen — das Archiv läuft mit Bindestrich, die Konsole sendet mit Unterstrich. Lernziel 4.1. Lösungszeit ~15 Minuten. Der Aha-Moment sitzt für immer.

Diese Node ist als spielbarer Prototyp umgesetzt. Details und das vollständige Regelwerk der Lab-Engine: `prototyp-silent-ct.md`.

### Progression

- **Ränge**: Novice → Operator → Administrator → Architect → Standard Bearer
- **Skill-Profil**: Radar-Chart über Netzwerk / Datenmodell / Bildgebung / Integration / Security — zeigt Lücken statt nur Punkte
- **Badges** für abgeschlossene Tracks und Themenserien
- **Saisons** (quartalsweise) mit neuen Nodes und eigener Wertung
- **Öffentliches Profil** mit Permalink, exportierbar als PDF-Nachweis

### Bewusst *nicht* übernommen

Kein Rage-Quit-Niveau. HTB darf frustrieren, weil Security-Leute das als Sport betreiben. Ein PACS-Admin lernt nebenbei im Arbeitsalltag — jede Node muss einen erkennbaren Lösungsweg haben, und nach 20 Minuten Feststecken bietet die Plattform aktiv einen Hint an.

Kein reiner Wettbewerbsdruck. Leaderboard optional und abschaltbar; Standard ist Fortschritt gegen sich selbst.

---

## 7. Lab-Architektur — der kritische Punkt

Aus dem Grundsatz in Abschnitt 2 folgt: Es braucht **zwei** Arten von Umgebung, nicht eine. Sie unterscheiden sich darin, ob der Weg vorher bekannt ist.

### Typ 1 — Nodes: simuliert

Eine Node hat ein Szenario, einen eingebauten Fehler und einen bekannten Lösungsweg. Das ist skriptbar:

- Der Nutzer tippt `echoscu -aec ARCHIVE 10.20.0.10 104`
- Die Engine wertet den Befehl gegen eine YAML-Definition aus und liefert eine vorbereitete, realistische Antwort samt Log
- Zustand pro Nutzer und Node in der Datenbank (was hat er geändert, was funktioniert dadurch)

**Vorteil:** Keine Container, keine Ressourcenkosten, beliebig skalierbar. Eine Node ist eine YAML-Datei, kein Compose-Stack. Neue Nodes kosten Schreibarbeit, nicht Infrastruktur.

**Grenze:** Nur vorgedachte Wege funktionieren. Für Easy und Medium ist das ausreichend — der Prototyp belegt das für eine Easy-Node.

### Typ 2 — Die Spielwiese: echter Container

Die freie Übungsumgebung neben jeder Lektion. Hier tippt der Lernende, was er will — und genau das kann eine Simulation nicht bedienen. Deshalb ist sie echt:

| | |
|---|---|
| **Was läuft** | Ein Orthanc als Archiv, daneben ein Container mit den DCMTK-Werkzeugen, Python mit `pydicom`/`pynetdicom` und synthetischen Testdaten |
| **Wie man drankommt** | Web-Terminal (xterm.js über WebSocket), direkt neben dem Lektionstext |
| **Wie es sich meldet** | AE Title `ORTHANC`, `127.0.0.1`, Port `4242`, Weboberfläche auf `8042` — bewusst wie eine lokale Standardinstallation, damit jedes Beispiel aus den Lektionen auch später zu Hause noch stimmt |
| **Lebensdauer** | 60 Minuten ohne Aktivität, dann wird aufgeräumt. Kein Zustand über Sitzungen hinweg — Neustart ist immer eine Zeile |
| **Isolation** | Eigenes Netz je Sitzung, kein Ausgang ins Internet, kein Weg zu anderen Sitzungen, harte CPU- und Speichergrenzen |
| **Verfügbarkeit** | Nur für angemeldete Nutzer, ein Container-Paar je gleichzeitiger Sitzung, bei Lastspitzen Warteschlange statt unbegrenztem Hochfahren |

Das ist der Teil, der im ursprünglichen Konzept erst für Phase 3 vorgesehen war. Er rückt vor, weil ohne ihn der Grundsatz aus Abschnitt 2 nicht einzuhalten ist. Die Gegenrechnung steht in Abschnitt 11.

### Der Übergang zwischen beiden

Das Node-Schema kennt bereits `engine: simulated | container`. Einzelne Nodes — typischerweise Hard und Insane — lassen sich später auf echte Container heben, ohne dass sich am Content-Format etwas ändert. Die Spielwiese ist dafür die Vorarbeit: Wer sie betreibt, hat die Orchestrierung schon gebaut.

### Testdaten

Ausschließlich synthetisch erzeugt (`pydicom` / dcm4che-Toolkit) oder aus explizit freigegebenen, anonymisierten Public-Datasets. Nie echte Patientendaten, auch nicht anonymisiert aus dem eigenen Haus — Herkunftsnachweis wäre nicht führbar, und es braucht nur einen Burned-in-Annotation-Ausrutscher.

---

## 8. Technischer Aufbau

### Stack

| Schicht | Wahl | Begründung |
|---|---|---|
| Backend | **Laravel 13** | Auth, Rollen, Queues, Mail, Admin fertig; du kennst es aus HNR |
| Frontend | **Vue 3 + Inertia** | Keine getrennte API nötig, kein doppeltes Routing |
| DB | **PostgreSQL** | JSONB für Node-Definitionen und Fortschritt |
| Node-Engine | **Python-Service (FastAPI)** | Wertet Befehle gegen die YAML-Definitionen aus |
| Spielwiese | **Container-Orchestrierung** | Orthanc + Werkzeug-Container je Sitzung, kurzlebig |
| Terminal | **xterm.js** über WebSocket | Eine Komponente für beide Umgebungstypen |
| Deployment | **Ein docker-compose** | App, DB, Engine, Orchestrator, Reverse Proxy |

Die Trennung Laravel/Python ist die einzige Stelle mit zwei Sprachen — sie ist es wert, weil DICOM in Python besser bedienbar ist als in PHP, und weil die Engine ohnehin eigenständig sein muss.

Das Web-Terminal ist absichtlich dieselbe Komponente für Nodes und Spielwiese. Für den Lernenden soll kein Unterschied spürbar sein, ob er gerade gegen eine Simulation oder gegen einen echten Orthanc tippt.

### Datenmodell (Kern)

```
users ──< enrollments >── tracks ──< lessons
  │                                    │
  ├──< lesson_progress ────────────────┘
  ├──< node_attempts >── nodes ──< node_hints
  │        (status, flag_submitted_at, hints_used, points)
  ├──< sandbox_sessions (container_id, started_at, expires_at)
  ├──< achievements
  └──< profile (rank, skill_vector, public_slug)
```

### Content-Format

Lektionen als Markdown mit Frontmatter im Repo, nicht in der Datenbank. Versionierung in Git, Pull Requests für Korrekturen, Community-Beiträge möglich, kein CMS zu bauen. Nodes analog als YAML. Das verbindliche Schema steht in `content-schema.md`.

---

## 9. Sprache und Mehrsprachigkeit

**Entscheidung: Deutsch zuerst, Englisch später.** Die Struktur ist aber von Anfang an mehrsprachig angelegt — Nachrüsten ist teuer, Vorbereiten kostet fast nichts.

### Die inhaltliche Grundregel: Fachbegriffe werden nicht übersetzt

| Wird übersetzt | Bleibt englisch — immer |
|---|---|
| Erklärung, Beispiel, Fehlerbeschreibung, Hint | Association, Called/Calling AE Title, Presentation Context, Transfer Syntax, Study Instance UID, Storage Commitment, Worklist |

Grund: Logs, Konfigurationsmasken und Conformance Statements sind englisch. Wer „Verbindungsaufbau-Kontext" lernt, findet in keinem Log etwas wieder. Dasselbe gilt für Befehle und Werkzeugausgaben — die werden nie übersetzt, nur die Leseanleitung darunter.

### Technisch drei getrennte Ebenen

| Ebene | Umsetzung | Aufwand bei Sprache Nr. 2 |
|---|---|---|
| **UI-Strings** | Laravel lang-Files (`de.json`, später `en.json`), keine hardcodierten Strings ab Tag 1 | gering |
| **Lernstoff** | `content/lessons/1.5/de.md`, später `en.md`, Fallback auf `de` | das ist die eigentliche Arbeit |
| **Nodes / Labs** | Befehle, Logs, Flags sprachneutral; nur Briefing und Hints pro Sprache | gering |

Die Labs sind der teure Teil der Entwicklung und bleiben bei einer Übersetzung komplett unangetastet. Übersetzt wird nur Prosa.

### Konkret für Phase 1

- Locale `de` als Standard, Sprachumschalter im Code vorhanden, im UI ausgeblendet
- URL-Schema `/de/...` von Anfang an — spart später Redirect-Chaos und SEO-Verluste
- Alle übersetzbaren Felder als JSONB `{"de": "..."}`, nicht als flache Spalte
- Datums-, Zahlen- und Zeitformate über die Locale

---

## 10. Name und Recht

**Arbeitstitel: DCM Lab.**

`DCM` ist die gängige Dateiendung und wird generisch verwendet — im Gegensatz zu DICOM®, das als Marke der NEMA gehalten wird.

Zwei Einschränkungen:

- Drei Buchstaben sind häufig belegt. Vor Domainkauf und Logo gehört eine Markenrecherche auf die konkrete Wortkombination in den relevanten Nizza-Klassen (Schulung, Software) gemacht — DPMA für Deutschland, TMview/EUIPO für die EU. Das ist keine Rechtsberatung, sondern der übliche Sorgfaltsschritt.
- Im Fließtext wird DICOM® genannt werden müssen — beschreibende Nutzung, zulässig, solange die Plattform nicht wie ein offizielles NEMA-Angebot auftritt. Ein Satz im Impressum erledigt das.

Weitere Namenskandidaten: *dcmlab.de*, *DCM Arena*, *DCM Dojo*, *dcm.school*, *DCM Node*.

**Weitere rechtliche Punkte:**

- Standardtexte dürfen nicht gespiegelt werden — verlinken, nicht kopieren.
- Bei öffentlichem Betrieb: Impressum, Datenschutzerklärung, AV-Verträge falls Häuser ihre Mitarbeiter schulen lassen.
- Die Spielwiese führt Nutzercode aus. Das gehört in die Nutzungsbedingungen (kein Missbrauch als Rechenkapazität) und in die technischen Grenzen aus Abschnitt 7.
- Bei Nutzung im eigenen Haus als Schulungsinstrument: Abstimmung mit Datenschutz und Personalrat frühzeitig.

---

## 11. Umsetzung in Schritten

| Phase | Inhalt | Ziel |
|---|---|---|
| **0 — Validierung** *(2 Wochen)* | Eine spielbare Node ohne Konto und ohne Installation, an 5 Kollegen geben | Trägt das Format? |
| **1 — MVP** *(3-4 Monate)* | Laravel-App mit Konten, Track 1 + 4, 10 simulierte Nodes, **Spielwiese als Container**, Punkte und Ränge | Nutzbar, teilbar, ohne dass jemand etwas installiert |
| **2 — Breite** | Tracks 2, 3, 5; 30 Nodes; Skill-Profil; öffentliche Profile | Vollständiges Curriculum |
| **3 — Tiefe** | Einzelne Nodes auf echte Container heben, Saisons, Write-ups, Community-Nodes | Eigendynamik |
| **4 — Optional** | Team-Accounts für Häuser, Nachweise, Zertifikatspfad | Tragfähigkeit |

Phase 1 ist gegenüber dem früheren Stand um etwa einen Monat länger geworden — das ist der Preis der Spielwiese. Dafür entfällt die gesamte Kategorie „Nutzer kommt nicht rein, weil er nichts installieren darf".

Phase 0 bleibt der wichtigste Schritt. Wenn fünf Kollegen die Node "Silent CT" lösen und dabei grinsen, ist alles Weitere gerechtfertigt. Ablauf und Erfolgskriterium: `testrunde-phase-0.md`.

---

## 12. Offene Punkte

1. ~~Sprache~~ — **entschieden:** Deutsch zuerst, mehrsprachige Struktur von Beginn an (Abschnitt 9).
2. ~~Wer setzt die Übungsumgebung auf~~ — **entschieden:** die Plattform, als echter Container (Abschnitt 2 und 7).
3. **Betriebskosten der Spielwiese** — der einzige Posten, der mit der Nutzerzahl mitwächst. Zu klären, bevor Phase 1 anläuft: Was kostet ein Container-Paar pro aktiver Stunde, wie viele gleichzeitige Sitzungen sind realistisch, und ab welcher Zahl braucht es eine Begrenzung (Kontingent pro Nutzer und Tag). Solange das nicht durchgerechnet ist, bleibt die Spielwiese das größte finanzielle Risiko des Projekts.
4. **Offen oder geschlossen** — Open Source wie die anderen Projekte, oder Content als eigentliches Asset schützen? Denkbar: Plattform offen, Nodes und Lektionen unter restriktiverer Lizenz.
5. **Betriebsmodell** — Dieselbe Tatsache, die den Bedarf erzeugt — Häuser geben kein Geld für Schulung aus —, macht Häuser auch zu schlechten Kunden. Der zahlende Teil ist eher die Einzelperson, die sich damit bewerben oder intern argumentieren will. Team-Accounts (Phase 4) bleiben möglich, taugen aber nicht als Startannahme. Zusammen mit Punkt 3 ist das die entscheidende Rechnung: laufende Kosten pro Nutzer gegen Zahlungsbereitschaft einer Einzelperson.
6. **Lab-Form für nicht-technische Personas** — Befehlsvorlagen (umgesetzt) oder zusätzlich ein klickbarer Werkzeugmodus? Entscheidung anhand der Testrunde.
7. **Content-Tempo** — 41 Lektionen sind viel Schreibarbeit. Realistisch 2-3 Lektionen pro Woche nebenberuflich; damit dauert Phase 2 ein knappes Jahr.
8. **Mehr als DICOM** — siehe Abschnitt 13. Nicht vor Phase 2 entscheiden, aber im Datenmodell nicht verbauen.

---

## 13. Ausblick: mehr als DICOM

Eine Idee, die über den bisherigen Rahmen hinausgeht: DCM Lab nicht als abgeschlossenes Ein-Themen-Projekt zu betreiben, sondern als Kern einer breiteren Healthcare-IT-Lernplattform — mit weiteren Themenfeldern (HL7/FHIR, IHE-Integrationen, Medizinprodukte-Software, Datenschutz im Klinikbetrieb, ...) und mit externen Dozenten, die eigene Kurse im selben spielerischen Format anbieten.

Das ist kein Feature, sondern eine zweite Achse im Produkt. Festgehalten hier, damit sie im Datenmodell nicht versehentlich verbaut wird — nicht, weil sie für Phase 1 oder 2 ansteht.

### Warum das nicht "einfach draufsatteln" ist

Das bestehende Konzept ist an genau einer Stelle eng an DICOM gebunden, und das ist eine bewusste Entscheidung gewesen, keine Nachlässigkeit: **Content lebt im Git-Repo** (`content/`), nicht in der Datenbank. Lektionen sind Markdown mit Frontmatter, Nodes sind YAML, Korrekturen laufen über Pull Requests (Abschnitt 8). Das ist billig, versioniert und passt zu einem Autor, der Git kann.

Ein externer Dozent — die Zielgruppe für "eigene Kurse anbieten" — wird nicht per Pull Request Content schreiben. Er braucht:

- einen **Autoren-Modus im Browser** (Lektionstext, Lab-Definition, Flags/Challenges anlegen, ohne Git)
- ein **Rollenmodell**, das über Lernende hinausgeht: Dozent (eigene Kurse verwalten), evtl. Kurs-Reviewer/Moderator
- eine **Engine, die nicht DICOM-fest ist**: `services/engine` wertet heute DICOM-Befehle gegen YAML aus. Ein FHIR-Kurs oder ein Datenschutz-Kurs braucht andere Auswertungslogik — die Engine muss zum Plugin werden (pro Themenfeld ein Auswertungsmodul), nicht zur Kernannahme
- eine **Trennung Plattform vs. Kurs** im Datenmodell: `tracks`/`nodes` gehören heute implizit "der Plattform"; für Mehrdozentenbetrieb brauchen sie einen Besitzer (`course.owner_id` o.ä.) und eigene Sichtbarkeits-/Freigabe-Regeln

Keiner dieser Punkte ist in Phase 1/2 nötig. Aber jeder ist teuer nachzurüsten, wenn das Datenmodell erst mal "ein Autor, ein Thema, Content-in-Git" fest annimmt — ähnlich wie die Mehrsprachigkeit in Abschnitt 9, nur eine Nummer größer.

### Was das für jetzige Entscheidungen bedeutet

Nicht: jetzt schon Multi-Tenancy bauen. Sondern: an den paar Stellen, wo es fast nichts kostet, die Tür offenhalten.

- **Track/Node-Schema** (`content-schema.md`) so benennen, dass "Themenfeld" und "Track" begrifflich getrennt bleiben — DICOM ist ein Themenfeld mit fünf Tracks, nicht die Plattform selbst
- **`services/engine`** als eigenständigen Dienst mit klarer Schnittstelle behandeln (ist er heute schon, siehe Abschnitt 8) statt Auswertungslogik ins Laravel-Backend zu ziehen — das hält den Weg zu "pro Themenfeld ein Engine-Modul" offen
- Keine UI-Strings oder Modelle so benennen, dass "DICOM" oder "Node" fest im Kern verdrahtet ist, wo "Kurs" oder "Challenge" der eigentlich gemeinte Begriff ist

### Betriebsmodell-Frage, die mitläuft

Wenn Dozenten eigene Kurse anbieten, kommt zwangsläufig die Frage nach Erlösbeteiligung, Qualitätssicherung (wer prüft, ob ein Kurs taugt?) und Marke (bleibt "DCM Lab" der Name, oder wird die Plattform umbenannt und DCM Lab einer von mehreren Kursen?) dazu. Das sind Entscheidungen für den Zeitpunkt, an dem Phase 2 abgeschlossen ist und sich zeigt, ob das Einzelthema DICOM überhaupt genug Zugkraft hat — vorher lohnt die Detailplanung nicht.

---

*Nächster Schritt: Testrunde Phase 0 mit der Node „Silent CT" — Ablauf in `testrunde-phase-0.md`.*
