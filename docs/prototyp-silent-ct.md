# Prototyp — spielbare Node „Silent CT"

*Stand 12.09.2026. Phase 0: eine spielbare Node als Beleg, dass Konzept, Content-Schema und Lab-Format zusammenpassen.*

Veröffentlicht als Artifact (privat, teilbar): <https://claude.ai/code/artifact/d292f81c-1094-4bab-a181-3dcfd2d43dac>

---

## 1. Was der Prototyp beweisen soll

Das Konzept behauptet in Abschnitt 6, dass **Stufe A** — simulierte Labs ohne Container — für Easy/Medium ausreicht und 80 % des Lernwerts trägt. Dieser Prototyp ist der Test dieser Behauptung. Er läuft komplett im Browser, ohne Backend, ohne Container, ohne DICOM-Toolkit.

Wenn fünf Kollegen die Node lösen und dabei grinsen, ist Phase 1 gerechtfertigt.

## 2. Aufbau der Oberfläche

Drei Umgebungen als Tabs — die Trennung ist inhaltlich, nicht dekorativ: sie bildet ab, **wo** man im echten Leben Zugriff hat und wo nicht.

| Tab | Was der Lernende kann |
|---|---|
| **Workstation** `10.20.0.50` | Shell mit `echoscu`, `findscu`, `storescu`, `dcmdump`, `ping`, `ls`, `cat` — plus Befehlsvorlagen zum Anklicken |
| **CT-Konsole Raum 3** `10.20.0.30` | Vier Konfigurationsfelder ändern, Sendeauftrag auslösen, Auftragsprotokoll lesen. Keine Shell. |
| **Archiv** `10.20.0.10` | Statusseite: Port, Bestand, Zähler für akzeptierte/abgelehnte Associations. **AE Title gesperrt**, Verbindungsprotokoll nur mit Herstellerzugang. |

Links daneben: Briefing, drei gestaffelte Hints mit Punktabzug (−1 / −2 / −4), Flag-Eingabe, Write-up.

### Die zentrale Design-Entscheidung

Der AE Title des Archivs ist in der Oberfläche **nicht** sichtbar. Damit bleibt der Lösungsweg echt:

- **Weg A — nachstellen:** `echoscu` mit den Werten der CT-Konsole von der Workstation aus → `Called AE Title Not Recognized`. Dann den richtigen Namen in der eigenen Dokumentation nachschlagen.
- **Weg B — dokumentieren:** `cat netzplan-radiologie.txt` auf der Workstation listet alle AE Titles des Hauses inklusive `PACS-ARCHIV`.

Beide Wege sind legitim, beide brauchen einen Gedanken. Hätte man die Archiv-Konfiguration sichtbar gemacht, wäre die Node ein 30-Sekunden-Vergleich zweier Tabs gewesen.

### Der Einstieg ohne Kommandozeilen-Erfahrung

Ein leeres Terminal ist für die wichtigste Zielgruppe — den 1st/2nd-Level-Supporter — die falsche erste Begegnung. Er würde im echten Haus nie dcmtk öffnen; für ihn ist das Terminal *unrealistischer* als eine Oberfläche. Für den PACS-Admin ist es genau richtig.

Gelöst über **Befehlsvorlagen** unter dem Terminal: sechs Schaltflächen, die einen vollständigen Befehl in die Eingabezeile schreiben, statt ihn auszuführen. Der Platzhalter (`ZIEL_AE`, `UID_AUS_SCHRITT_1`) ist bereits markiert und wird überschrieben, dann Enter.

Drei Gründe, warum das die Node nicht entwertet:

- **Der Platzhalter ist genau die Lernaufgabe.** `ZIEL_AE` unverändert abzuschicken führt zu `Called AE Title Not Recognized` — dem Kern der Node. Die Vorlage nimmt die Syntax ab, nicht das Denken.
- **Der Befehl bleibt sichtbar.** Man liest jedes Mal `echoscu -aet … -aec … host port`. Nach drei Nodes tippt man ihn selbst, weil man ihn zwanzigmal gesehen hat.
- **Kein Punktabzug.** Die Vorlagen sind kein Hint, sondern eine andere Tür in denselben Raum.

Zusätzlich fängt die Engine unbrauchbare UIDs ab: Wer `StudyInstanceUID=UID_AUS_SCHRITT_1` stehen lässt, bekommt nicht „0 Antworten", sondern den Hinweis, dass VR `UI` nur Ziffern und Punkte erlaubt.

Bewusst **nicht** gebaut: ein zweiter, klickbarer Werkzeugmodus ohne Terminal. Das wäre die größere Lösung für dieselbe Frage — sie bleibt offen, bis die Testrunde zeigt, ob die Vorlagen reichen.

## 3. Was auf der Workstation liegt

Drei Dateien, alle mit Funktion:

- `netzplan-radiologie.txt` — die eigene Dokumentation. Enthält die Lösung, wenn man weiß, wonach man sucht. (Nebenbei die Werbung für Healthcare Node Registry: gepflegte eigene Doku schlägt jeden Herstellerzugang.)
- `notizen.txt` — das Wartungsprotokoll. Zeigt, dass die Konfiguration aus einem Backup von 02/2024 zurückgeschrieben wurde und die Abnahme CT Raum 3 ausgelassen hat.
- `conformance-pacs-archiv.txt` — Auszug aus dem Conformance Statement. Nennt beide Ablehnungsgründe wörtlich und den Satz „Die Prüfung erfolgt zeichengenau". Der AE Title steht bewusst **nicht** drin — genau wie in echten Conformance Statements.

## 4. Regelwerk der Lab-Engine

Das ist die eigentliche Ausbeute: die Regeln, nach denen die simulierte Umgebung antwortet. Sie sind der Vorgriff auf `node.yml` und lassen sich direkt in die spätere Engine übernehmen.

### Association-Prüfung (gilt für jedes Werkzeug)

In dieser Reihenfolge:

1. Host unbekannt → `TCP Initialization Error: Connection timed out`
2. Host bekannt, aber nicht `10.20.0.10:104` → `Connection refused`
3. Called AE ≠ `PACS-ARCHIV` → `Rejected Permanent, Source: Service User, Reason: Called AE Title Not Recognized`, Ablehnungszähler +1
4. Calling AE nicht in der Liste konfigurierter Nodes → `Calling AE Title Not Recognized`, Ablehnungszähler +1
5. sonst akzeptiert, Zähler für akzeptierte Associations +1

Die Reihenfolge ist didaktisch wichtig: Sie zwingt zur Stufenprüfung *Netzwerk → Transport → Identität*, die Lektion 4.10 als Systematik lehrt.

### Werkzeugverhalten

- **Alle dcmtk-Werkzeuge sind bei Erfolg still.** `echoscu -v` zeigt die Association-Schritte, `echo $?` gibt den Exitcode. Das ist der erste Stolperstein für Einsteiger und gehört dazu.
- **Default-Werte sind gesetzt** wie in dcmtk: `-aet ECHOSCU`, `-aec ANY-SCP`. Wer `echoscu 10.20.0.10 104` ohne Parameter tippt, bekommt sofort eine Ablehnung — ein Lernmoment, kein Fehler der Umgebung.
- **`findscu`** kennt Study Root und Patient Root, `-k` mit Schlüsselwort (`PatientID`) **und** Tag (`0010,0020`), Wildcards `*` und `?`. Antworten werden im dcmtk-Format ausgegeben, inklusive VR, Längenangabe und Keyword.
- **Abfrage auf SERIES-Ebene ohne `StudyInstanceUID`** wird mit `0xa900 Identifier does not match SOP Class` abgewiesen. Das ist die Stelle, an der die Node auf Lektion 2.3 vorgreift — und der Grund, warum der Flag in zwei Schritten geholt werden muss.
- **`storescu`** von der Workstation scheitert an fehlenden Dateien. Die Bilder liegen auf der Modalität, nicht bei dir. Auch das ist Realität.

### Rückkopplung

Die Zähler auf der Archiv-Statusseite reagieren live: Jede abgelehnte Association — auch die des Lernenden selbst, auch jeder Wiederholungsversuch der CT-Konsole — erhöht die Ablehnungszahl. Das ist das diagnostische Signal, das im echten Betrieb den Unterschied macht: *Die Verbindung kommt an und wird abgewiesen* ist eine völlig andere Aussage als *es kommt gar nichts an*.

Der Bestand (Studies / Series / Instances) springt erst nach erfolgreichem C-STORE von 0 auf 1 / 1 / 3.

### Punktevergabe

10 Punkte, minus 1 / 2 / 4 je genutztem Hint. Write-up vorab ansehen setzt auf 0. Nach zehn Minuten ohne Hint bietet die Seite von sich aus Hint 1 an — die im Konzept festgelegte Anti-Frust-Regel, für den Prototyp von 20 auf 10 Minuten gezogen.

## 5. Rückmeldung der Tester

Am Ende — nach gelöstem Flag oder nach Ansehen des Write-ups — erzeugt die Seite eine kurze Zusammenfassung zum Kopieren: Zeit, genutzte Hints, Punkte, plus zwei offene Zeilen („Festgehangen bin ich bei" / „Nochmal so eine Aufgabe?"). Der Tester füllt sie aus und schickt den Text zurück.

Bewusst keine geteilte Ergebnisliste in der Seite: Eine Seite mit gemeinsamer Datenablage ist auf die eigene Organisation beschränkt und lässt sich nicht offen teilen — Kollegen im Haus könnten den Link dann nicht öffnen. Für fünf Tester ist Kopieren-und-Zurückschicken die richtige Abwägung. Ab Phase 1 hat die Plattform eigene Konten, und die Frage stellt sich nicht mehr.

Der Ablauf der Testrunde steht in `claude/testrunde-phase-0.md`. **Zusätzlich zu beobachten:** Werden die Befehlsvorlagen benutzt oder frei getippt? Wer sie ignoriert und trotzdem durchkommt, gehört zur PACS-Admin-Persona; wer ohne sie stecken bleibt, beantwortet die offene Frage nach dem klickbaren Werkzeugmodus.

## 6. Was der Prototyp bewusst nicht kann

- **Kein echtes DICOM.** Es gibt keinen Stack, keine PDUs, keine Bytes. Jede Antwort ist vorgedacht. Wer einen Weg geht, den die Regeln nicht kennen, bekommt eine Fehlermeldung statt eines Erlebnisses — genau die Schwäche, die im Konzept unter „Stufe A / Nachteil" steht. Bei dieser Node fällt es nicht auf; bei einer Hard-Node würde es das.
- **Kein Fortschritt über Neuladen hinweg.** Reload setzt zurück. Für 15 Minuten Spielzeit vertretbar, für die Plattform nicht.
- **Kein Konto, kein Rang, kein Skill-Radar.** Das ist Phase 1.
- **Ein Zeichen Unterschied ist das gesamte Rätsel.** Bewusst so: Easy heißt Easy. Die Übertragbarkeit steckt im Write-up, nicht in der Schwierigkeit.

## 7. Nächste sinnvolle Schritte

1. **Die Node an fünf Kollegen geben** — Ablauf und Erfolgskriterium in `claude/testrunde-phase-0.md`. Erst danach weiterbauen.
2. Bei positivem Ergebnis: Das Regelwerk aus Abschnitt 4 in ein echtes `node.yml` überführen und die zweite Node (*Wrong Door*, dasselbe Muster auf der Calling-Seite) daraus generieren — der Test, ob das Schema trägt.
3. Erst dann Laravel, Konten und Punktestand.

## 8. Offene Frage aus dieser Runde

**Für welche Persona wird die Lab-Form gebaut?** Die Befehlsvorlagen sind die kleine Antwort. Die große — ein klickbarer Werkzeugmodus neben dem Terminal, umschaltbar pro Node — würde das Curriculum für Supporter und MTRA öffnen und den Aufwand pro Node etwa verdoppeln. Diese Entscheidung sollte nicht am Schreibtisch fallen, sondern anhand der Testrunde.
