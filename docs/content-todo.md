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
graded Quiz-Karten und keine Spaced-Repetition-Wiederholung.

**Seit P10.36 geklärt:** Das vermeintlich offene Formatproblem gab es
nicht — `quiz:` war sowohl im ursprünglichen Projektauftrag
(`dcm-lab-agent-prompt.md` 4.1) als auch in `docs/content-schema.md`
bereits vollständig spezifiziert (`{id, type: single|multi|input,
answer}`). Es fehlten nur die Werte. Da jede der 27 Fragen (9
Lektionen × 3) eine Verständnisfrage zu einem in derselben Lektion
bereits real verifizierten Fakt ist, ließ sich der Antwortschlüssel
ohne Erfindung direkt aus dem jeweiligen Fließtext ableiten. Alle neun
`content/lessons/{1.0…1.8}/meta.yml` tragen jetzt ein vollständiges
`quiz:`-Feld — siehe ADR 0046. Weiterhin **nicht** gebaut: die
interaktive Karten-UI und die Spaced-Repetition-Planung selbst
(`content:sync` liest `quiz:` bisher nicht einmal in eine
Datenbankspalte ein) — das bleibt eine eigene, deutlich größere
Engineering-Slice, für die jetzt aber eine vollständige, korrekte
Content-Grundlage steht.

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

## Track 2 — acht Gerüste angelegt, Fließtext fehlt (seit P10.24)

`content/lessons/2.1` bis `2.8` existieren seit P10.24 als Gerüste
(`status: draft`): vollständige `meta.yml` (Track, Reihenfolge, Dauer,
`requires`, höchstens vier Werkzeuge aus der Registry, Glossarbegriffe,
Datensatz) und eine `de.md` mit Titel, Teaser, drei Lernzielen und der
geplanten Gliederung. Titel und Lab-Hinweise sind aus der
Curriculum-Tabelle in `konzept-lernplattform.md` (Abschnitt 5, Track 2)
abgeleitet, nicht erfunden. Drei fehlende, aber im Standard eindeutig
definierte Glossarbegriffe wurden dabei ergänzt (`c-find`, `c-get`,
`worklist`, `mpps`, `storage-commitment`) — bereits vorhandene Begriffe
wie `c-echo`, `c-store`, `c-move` folgten demselben Muster, nur für
diese Dienste fehlten die Einträge noch.

Kein Gerüst enthält Fachprosa oder Werkzeugausgaben — Abschnitt 13 des
Auftrags. Jede Lektion trägt unter „Was zum Schreiben noch fehlt" ihre
eigene Liste offener Punkte:

- **Ausgaben fehlen (2.1, 2.2, 2.3).** Jedes Beispiel muss in der
  Spielwiese erzeugt und wörtlich übernommen werden — reiner
  Schreibaufwand, keine offene Infrastrukturfrage. **2.1 ist seit
  P10.28 vollständig geschrieben** — vier echte Beispiele (erfolgreicher
  C-ECHO mit explizitem DIMSE-Status, Presentation-Context-Mitschnitt
  mit genau einer Context, `tshark`-Mitschnitt des vollständigen
  Zyklus, Transport-Fehler gegen einen geschlossenen Port). Die
  AE-Title-Großzügigkeit der Spielwiese (ADR 0017) wurde dabei in
  einer frischen Sitzung erneut real bestätigt statt nur zitiert —
  Lektion verweist ehrlich auf Node „Silent CT" für den
  Ablehnungsfall. Siehe ADR 0038. `tools` um `tshark` ergänzt. **2.2
  ist seit P10.29 ebenfalls vollständig geschrieben** — sechs echte
  Beispiele, darunter ein in dieser Slice empirisch geprüfter
  Mechanismus-Fund: mehrere Dateien in einem `storescu`-Aufruf laufen
  über **eine** Association mit mehreren `MsgID`s, nicht über mehrere
  separate Verbindungen (direkt gegen einen Loop mit Einzelaufrufen
  verglichen, nicht nur angenommen). Siehe ADR 0039. `tools` um
  `tshark` ergänzt (jetzt am Vier-Werkzeuge-Limit). **2.3 ist seit
  P10.30 ebenfalls vollständig geschrieben** — fünf echte Beispiele,
  darunter eine SERIES-Level-Abfrage, die real zwei getrennte
  `Find SCP Response`-Blöcke liefert (die reale Zwei-Serien-Struktur
  von `ct-thorax-60`, nicht nur aus `datasets.yml` übernommen). Siehe
  ADR 0040. `tools` um `tshark` ergänzt. **Damit sind alle drei
  Lektionen ohne offene Infrastrukturfrage (2.1, 2.2, 2.3)
  geschrieben.**
- **Infrastruktur bereits vorhanden (2.5, 2.6, 2.7).** Diese drei
  Lektionen (Modality Worklist, MPPS, Storage Commitment) können die
  in P10.20–P10.21 für Track 4 gebaute echte Infrastruktur direkt
  mitnutzen (Orthancs Worklists-Plugin, ADR 0030; der eigene
  `pynetdicom`-MPPS-SCP, ADR 0031; Orthancs native
  Storage-Commitment-REST-API, ADR 0031). Abgrenzung zu 4.7/4.8:
  Track 2 erklärt den Dienst selbst, Track 4 das jeweilige
  Troubleshooting-Fehlerbild. **2.5 ist seit P10.25 vollständig
  geschrieben** — kein neuer Infrastruktur-Fund nötig, wie erwartet;
  dabei aber ein neuer, echt verifizierter Fund: DCMTKs `findscu -W`
  schlägt genau eine Presentation Context vor (die Modality Worklist
  Information Model FIND), eine gewöhnliche Study-Abfrage dagegen ein
  Bündel aus 13 Contexts — und pynetdicoms `findscu` (das den echten
  DCMTK-Namen verdeckt, ADR 0025) beschränkt sich selbst mit `-W`
  **nicht** darauf, ein weiterer konkreter Beleg für die
  Shadowing-Falle an einem neuen Befehl. Siehe ADR 0035. `tools` dabei
  von `[findscu, wlmscpfs]` auf `[findscu]` reduziert — `wlmscpfs` war
  im geschriebenen Fließtext nicht nötig. **2.6 ist seit P10.26
  ebenfalls vollständig geschrieben** — ebenfalls kein neuer
  Infrastruktur-Fund; dabei aber real verifiziert, dass der bestehende
  MPPS-SCP (ADR 0031) einen dritten, gültigen Endzustand
  (`DISCONTINUED`, nicht nur `COMPLETED` wie in 4.8 gezeigt) genauso
  erfolgreich (`0x0`) annimmt — der DIMSE-Erfolgsstatus beschreibt nur
  die Zustellung, nicht den Inhalt der Meldung. Siehe ADR 0036. `tools`
  von `[pynetdicom, dcmdump]` auf `[pynetdicom, tshark]` korrigiert.
  **2.7 ist seit P10.27 ebenfalls vollständig geschrieben** — letzte
  der drei Lektionen mit vorhandener Infrastruktur, kein neuer
  Infrastruktur-Fund; dabei aber ein neuer, echt verifizierter Fund:
  ein `tshark`-Mitschnitt auf Port 4242 zeigt erstmals die
  DICOM-Ebene unter der REST-API — zwei vollständig getrennte
  Assoziationen (`N-ACTION-RQ`/`-RSP`, dann separat
  `N-EVENT-REPORT-RQ`/`-RSP`), anders als bei MPPS (2.6), wo beide
  Nachrichten dieselbe Assoziation teilen. 4.8 zeigte bisher nur die
  REST-Wrapper-Sicht, nie die zugrundeliegende Mechanik. Siehe ADR
  0037. `tools` um `tshark` ergänzt. **Damit sind alle drei Lektionen
  mit vorhandener Infrastruktur (2.5, 2.6, 2.7) geschrieben.**
- **Ursprünglich als „neue Infrastruktur nötig" eingeschätzt (2.4,
  2.8).** 2.4 (C-MOVE vs. C-GET) sollte für ein *echtes*
  Drei-Parteien-C-MOVE angeblich einen zweiten Storage-Endpunkt in der
  Spielwiese brauchen. **2.4 ist seit P10.31 vollständig geschrieben —
  diese Annahme war falsch, wie schon bei Worklist/MPPS/Storage
  Commitment (P10.20/P10.21):** Ein zweiter, vom Lernenden selbst
  gestarteter `storescp`-Prozess im selben Toolbox-Container, mit
  eigenem AE Title, plus dynamischer Registrierung bei Orthanc
  (`PUT /modalities/<name>`, derselbe Mechanismus wie bei Storage
  Commitment), genügt bereits für ein echtes, standardkonformes
  Drei-Parteien-C-MOVE — verifiziert per `tshark`: zwei getrennte
  Associationen (SCU→Quelle, Quelle→Ziel), real angekommene Dateien am
  Ziel. Nebenfund: Orthancs C-MOVE-Handler verlangt zwingend die exakte
  `StudyInstanceUID` als Identifier (ein `PatientID`-only-C-MOVE
  scheitert mit einem klaren Fehler), anders als C-FIND. Siehe ADR
  0041. `tools` um `tshark` ergänzt (jetzt am Vier-Werkzeuge-Limit).
  **2.8 (DICOMweb) ist seit P10.32 ebenfalls vollständig geschrieben —
  dieselbe Annahme war auch hier nur zur Hälfte richtig:** Kein neuer
  Storage-Endpunkt, keine neue Infrastruktur nötig — Orthancs
  DICOMweb-Plugin liegt bereits im Image, aktiviert wird es exakt wie
  das Worklists-Plugin (ADR 0030) über eine einzige Konfigurationszeile
  (`"DicomWeb": {"Enable": true}`). Damit laufen QIDO-RS, WADO-RS und
  STOW-RS sofort real gegen dieselbe Ablage wie DIMSE (per `findscu`
  auf Bild- und Studienebene bestätigt: DIMSE-gesendete und
  STOW-RS-hochgeladene Instanzen landen im selben Index). `curl` war
  bereits seit P10.21 im Toolbox-Image installiert, aber tatsächlich
  noch nicht in `content/tools/de.yml` registriert — jetzt nachgeholt.
  Nebenfund: STOW-RS verlangt eine präzise `multipart/related`-Kodierung;
  ein naiver Body ohne `boundary=`-Angabe scheitert nicht still, sondern
  mit einer echten `415 Unsupported Media Type`-Antwort. Die neue
  Registrierung von `curl` als Werkzeug deckte drei bereits fertige
  Lektionen (2.4, 2.7, 4.8) auf, die `curl` in Beispielen verwenden,
  ohne es zu deklarieren — 2.7 und 4.8 hatten Platz unter dem
  Vier-Werkzeuge-Limit, bei 2.4 wurden zwei Befehle zu einer Zeile
  zusammengefasst, um das Limit zu halten. Siehe ADR 0042. **Damit sind
  alle acht Lektionen aus Track 2 vollständig geschrieben.**

`content:validate` meldet für die acht neuen Lektionen nichts.

## Track 3 — vollständig geschrieben (seit P10.45)

`content/lessons/3.1` bis `3.6` existieren seit P10.39 als Gerüste
(`status: draft`): vollständige `meta.yml` und eine `de.md` mit Titel,
Teaser, drei Lernzielen und geplanter Gliederung. Titel und Lab-Hinweise
sind aus der Curriculum-Tabelle (`konzept-lernplattform.md` Abschnitt 5,
Track 3) übernommen, nicht erfunden. Kein Gerüst enthält Fachprosa oder
Werkzeugausgaben (Abschnitt 13).

Anders als bei Track 2, wo sich am Ende jede „braucht neue
Infrastruktur"-Annahme als falsch herausstellte, sind hier von
vornherein drei echte, noch ungeprüfte Infrastrukturfragen markiert,
weil `content/datasets.yml` aktuell ausschließlich klassische
Single-Frame-CT-Objekte kennt:

- **3.4** (Multiframe/Enhanced IODs) braucht wahrscheinlich ein echtes
  Enhanced-CT-Testobjekt — deckt sich mit dem Roadmap-Punkt
  „Multiframe-Generator" (siehe P10-Roadmap-Tabelle oben, Status
  weiterhin `offen`).
- **3.5** (Structured Reports/Presentation States/Key Objects) braucht
  wahrscheinlich ein echtes SR-/GSPS-/KOS-Testobjekt.
- **3.6** (Specific Character Set) braucht ein Testobjekt mit echtem
  Umlaut-Patientennamen — vermutlich ohne neuen Datensatz-Slug lösbar.

Alle drei noch nicht empirisch geprüft — wird beim Schreiben der
jeweiligen Lektion getan, nicht vorab angenommen (dieselbe Disziplin
wie in jeder Track-2-Slice). 3.1–3.3 verwenden vorläufig `ct-thorax-60`
als plausiblen, aber ebenfalls noch nicht bestätigten Platzhalter.
Siehe ADR 0048.

**3.1 ist seit P10.40 vollständig geschrieben** — `ct-thorax-60`
erwies sich für ein modulweises Durchgehen als ausreichend, kein neuer
Datensatz nötig. Dabei ein neuer, echt verifizierter Fund: Orthanc
lehnt ein Objekt mit fehlendem Type-1-Attribut (`StudyInstanceUID`,
per `dcmodify -e` entfernt) real mit `0xA700 (Failure)` ab und nennt
den Grund im eigenen Log im Klartext („required tags … are missing")
— ein fehlendes Type-2-Attribut (`PatientID`) dagegen wird anstandslos
mit `0x0000 (Success)` angenommen. Anders als die wiederholt
dokumentierte Großzügigkeit bei AE-Title/Presentation-Context/SOP-Class
(ADR 0008/0025) prüft Orthanc die Hierarchie-Pflichtfelder also
tatsächlich streng. `tools` von `[]` auf `[dcmdump, dcmodify,
storescu]` gesetzt, neuer Glossarbegriff `iod`. Siehe ADR 0049.

**3.2 ist seit P10.41 ebenfalls vollständig geschrieben** — kein
Viewer im Werkzeugkasten bestätigt (nicht nur vermutet): Ein
MONOCHROME1/2-Effekt lässt sich nur auf Tag-Ebene zeigen, die Lektion
sagt das jetzt ehrlich. `img2dcm` (bereits mit `lesson: "3.2"`
registriert) liefert dafür echtes, nicht-triviales Material: ein von
Hand gebautes BMP wird zu einem echten RGB-Objekt, dessen
Pixeldaten-Bytes exakt den eingegebenen Werten entsprechen. Dabei ein
Nebenfund, der 3.1 präzisiert: `PhotometricInterpretation` ist ebenfalls
Type 1, wird von Orthanc beim Fehlen aber trotzdem anstandslos
angenommen — Orthancs strenge Prüfung aus 3.1 gilt also gezielt für die
Hierarchie-UIDs, nicht pauschal für jedes Type-1-Attribut jedes Moduls.
`tools` von `[]` auf `[dcmdump, img2dcm, dcmodify, storescu]` gesetzt
(am Vier-Werkzeuge-Limit), neuer Glossarbegriff
`photometric-interpretation`. Siehe ADR 0050.

**3.3 ist seit P10.42 ebenfalls vollständig geschrieben** —
`ct-thorax-60` trägt tatsächlich keine `RescaleSlope`/`RescaleIntercept`/
`WindowCenter`/`WindowWidth`-Attribute (real per `dcmdump` bestätigt,
leere Ausgabe). Bewusst **kein** Generator-Zusatz: Lektion 3.1 zeigt
bereits einen vollständigen, echten `dcmdump` desselben Testobjekts —
eine Generator-Änderung hätte diese bereits veröffentlichte, reale
Ausgabe rückwirkend ungültig gemacht. Stattdessen zeigt 3.3 echt, dass
die Attribute fehlen, und ergänzt sie live per `dcmodify -i` mit echten
CT-Praxis-Konventionswerten (`RescaleIntercept -1024`/`RescaleSlope 1`,
`WindowCenter 40`/`WindowWidth 400`). Der „schwarzes Bild"-Effekt wird
über eine echte, als `<!-- kein-beispiel -->` markierte Rechnung
(Rohwert `0` → `HU -1024` → weit unterhalb des Weichteilfensters)
gezeigt, nicht über ein tatsächliches Bild (weiterhin kein Viewer im
Werkzeugkasten). `tools` von `[]` auf `[dcmdump, dcmodify]` gesetzt,
neuer Glossarbegriff `hounsfield-unit`. Siehe ADR 0051.

**3.4 ist seit P10.43 ebenfalls vollständig geschrieben — die
Infrastrukturfrage ist geklärt, ohne den Generator zu ändern.** Ein
echtes Enhanced-CT-Testobjekt (`pydicom`s externe Testdaten,
`eCT_Supplemental.dcm`) existiert zwar, lädt aber per Netzwerk nach —
die Spielwiese verbindet die Toolbox mit einem `internal=True`-Netz
ohne Egress, das scheidet also aus. Stattdessen liefert `pydicom`s
**mitgelieferte** Paket-Testdaten (Teil der pip-Installation, kein
Netzwerk nötig, mit `docker run --network none` bestätigt) ein echtes,
RLE-komprimiertes 2-Frame-Objekt (`SC_rgb_rle_2frame.dcm`, Secondary
Capture, pydicoms bekannte fiktive Sherlock-Holmes-Testdaten). Damit
real gezeigt: Orthanc zählt die Datei als eine Instance
(`NumberOfStudyRelatedInstances 1`), obwohl real zwei Frames darin
stecken — und eine echte, leere Abfrage der Functional-Groups-Sequenzen
am selben Objekt dient als Gegenprobe zur Enhanced-IOD-Erklärung (kein
echtes Enhanced-Objekt mit gefüllten Sequenzen verfügbar, aber die
SOP-Class-UIDs und die Struktur bleiben real belegt). `dcmdrle` neu in
`content/tools/de.yml` registriert. `tools` von `[]` auf `[dcmdump,
dcmdrle, storescu, findscu]` gesetzt, neuer Glossarbegriff
`enhanced-iod`. Siehe ADR 0052.

**3.5 ist seit P10.44 ebenfalls vollständig geschrieben — auch hier
kein Netzwerk- oder Generator-Bedarf.** Weder `pydicom`s noch
`pynetdicom`s Paket-Testdaten enthalten ein echtes SR-/GSPS-/
KOS-Beispiel. Anders als beim Enhanced-CT-Fall (3.4/ADR 0052) erwies
sich ein minimales, aber echtes Basic Text SR und ein KOS von Hand mit
`pydicom` gebaut als gut machbar (deutlich einfachere Pflichtstruktur
als Enhanced-Functional-Groups) — beide referenzieren eine real
generierte `ct-thorax-60`-Instanz, bestehen `dcmftest` und werden von
Orthanc real angenommen. Damit gezeigt: `ModalitiesInStudy CT\KO\SR`,
`NumberOfStudyRelatedInstances 3` in derselben Study. Presentation
States (GSPS) bewusst **nicht** live gebaut — die dafür nötigen
zusätzlichen Pflichtmodule wären unverhältnismäßig für den Ertrag
dieser einen Lektion; Lernziel 2 verlangt nur die Einordnung als
Verweis-Objekt, kein Live-Beispiel. `tools` von `[]` auf `[dcmftest,
dcmdump, storescu, findscu]` gesetzt, drei neue Glossarbegriffe
(`structured-report`, `key-object-selection`, `presentation-state`).
Siehe ADR 0053.

**3.6 ist seit P10.45 ebenfalls vollständig geschrieben — damit ist
Track 3 komplett.** Wie erwartet kein neues IOD nötig, aber ein
reichhaltigerer echter Befund als angenommen: Ein Hex-Vergleich zeigt,
dass ein Objekt mit und eines ohne `SpecificCharacterSet` **identische**
Rohbytes für den Umlautnamen tragen (`pydicom` kodiert unabhängig von
der Deklaration) — die Lücke zeigt sich nicht als Zeichensalat, sondern
als uneinheitliche Werkzeugreaktion: `dcm2json` verweigert die Datei
ohne Deklaration mit einer echten, klaren Fehlermeldung, `storescu`
nimmt beide Objekte an, und Orthancs eigene DICOMweb-API dekodiert
beide korrekt und normalisiert sie beim Ausgeben still auf UTF-8. Drei
echte, unterschiedliche Reaktionen auf dieselbe Lücke. `tools` von
`[]` auf `[dcmdump, dcm2json, storescu, curl]` gesetzt, neuer
Glossarbegriff `specific-character-set`. Siehe ADR 0054.

**`content/tracks.yml: bild.status`** von `planned` auf `published`
gesetzt — alle sechs Lektionen aus Track 3 sind jetzt vollständig
geschrieben, derselbe Maßstab wie bei Track 2 (ADR 0042).

`content:validate` meldet für die sechs neuen Lektionen nichts.

## Track 5 — acht Gerüste angelegt, Fließtext fehlt (seit P10.46)

`content/lessons/5.1` bis `5.8` existieren seit P10.46 als Gerüste
(`status: draft`): vollständige `meta.yml` und eine `de.md` mit Titel,
Teaser, drei Lernzielen und geplanter Gliederung. Titel sind aus der
Curriculum-Tabelle (`konzept-lernplattform.md` Abschnitt 5, Track 5)
übernommen, nicht erfunden. Kein Gerüst enthält Fachprosa oder
Werkzeugausgaben (Abschnitt 13).

Anders als Track 1–4 hat Track 5 laut Curriculum-Tabelle keine
Lab-Hinweis-Spalte — konsequenterweise setzen alle acht Gerüste
erstmals `sandbox: {required: false}` **ohne** `dataset`-Schlüssel und
`lab.node: null`. Vor dem ersten Gerüst wurde geprüft (Code-Lektüre von
`ContentValidate::checkDatasetReference()` und `checkLessonStructure()`)
und anschließend **empirisch bestätigt** (`content:validate` — 0
Verstöße bei 41 Lektionen), dass dieses Schema-Shape ohne Vorbild
tatsächlich validator-tolerant ist. Siehe ADR 0055.

Grund: IHE-Profile (5.1), Conformance Statements (5.2), Migration
(5.3), Datenschutz/Protokollierung (5.5), Security (5.6) und
Beschaffung (5.8) sind Betriebs-/Prozess-/Rechtsthemen, die sich mit
der vorhandenen Toolbox (Orthanc + DCMTK-CLI) nicht sinnvoll als
Hands-on-Übung abbilden lassen, ohne Beispiele zu erfinden. Zwei
Kandidaten für einen echten, kleinen Hands-on-Baustein sind als zu
prüfende offene Punkte markiert, nicht vorab angenommen:

- **5.4** (Anonymisierung/Pseudonymisierung): `dcmodify`-basierte
  Tag-Entfernung an einem real generierten Objekt, Vorher/Nachher per
  `dcmdump` — noch nicht verifiziert.
- **5.7** (Monitoring): Orthancs echter REST-Endpunkt `/statistics`
  bzw. `/changes` (per `curl`, bereits als Tool registriert seit
  Lektion 2.8) — noch nicht verifiziert, unklar ob die Sandbox
  aussagekräftige Werte ohne echte Produktionslast liefert.

Beide bleiben vorerst bei `sandbox.required: false`; falls sich der
Baustein beim Ausschreiben trägt, wird das in der jeweiligen
Lektions-PR nachträglich auf `true` korrigiert (inkl. `dataset`, falls
nötig). **5.8** (Beschaffung) ist zusätzlich als reine Synthese der
übrigen sieben Lektionen markiert und sollte als letzte Lektion des
Tracks geschrieben werden, da sie inhaltlich auf deren tatsächlichem
(nicht nur geplantem) Inhalt aufbaut.

`content/tracks.yml: betrieb.status` bleibt `planned`, bis alle acht
Lektionen tatsächlich geschrieben sind — derselbe Maßstab wie bei
Track 2 (ADR 0042/PR #44) und Track 3 (ADR 0054/PR #52).

`content:validate` meldet für die acht neuen Lektionen nichts.

**5.1 ist seit P10.47 vollständig geschrieben — die Scaffold-Annahme
„kein Hands-on möglich" hat sich als falsch herausgestellt.** Vor dem
Schreiben real recherchiert (offizielles IHE Radiology Technical
Framework Supplement „Scheduled Workflow.b" Rev. 1.7, per `pdftotext`
aus dem Original-PDF extrahiert, nicht aus dem Gedächtnis
rekonstruiert): Scheduled Workflow besteht exakt aus den DICOM-Diensten,
die diese Plattform in Lektion 2.5 (Modality Worklist) und 2.6/4.8
(MPPS) bereits real zeigt, nur mit festen IHE-Transaktionsnummern
(RAD-5 Query Modality Worklist, RAD-8 Modality Images Stored, RAD-6/
RAD-7 MPPS In Progress/Completed). Die Lektion zeigt jetzt alle vier
Transaktionen live in einer zusammenhängenden Sequenz gegen dieselbe
Patientin (`MUSTER^ERIKA`) — echte `findscu -W`, echter `storescu`,
echte MPPS-`N-CREATE`/`N-SET`-Nachrichten (identischer Aufbau wie
Lektion 4.8), gefolgt von einer echten `findscu -S`-Kontrolle. PIR und
XDS-I bleiben konzeptionell, aber mit echten Querverweisen statt neuen
Beispielen: PIR über den bereits real verifizierten
Coercion-Mechanismus aus Lektion 4.6, XDS-I über den real recherchierten
Fund, dass sein geteiltes Manifest technisch ein Key Object Selection
Document ist (dieselbe SOP-Klasse wie in Lektion 3.5 von Hand gebaut).
`sandbox.required` von `false` auf `true` korrigiert (Dataset
`ct-thorax-60`), `tools` von `[]` auf `[findscu, storescu, pynetdicom]`
gesetzt, drei neue Glossarbegriffe (`scheduled-workflow`,
`patient-information-reconciliation`, `xds-i`). Siehe ADR 0056.

**Nebenfund aus P10.47 (kein Content-Bezug, sicherheitsrelevant):**
Beim Aufräumen dieser Slice wurden neun bereits verwaiste
`dcmlab-sandbox-<uuid>`-Container samt Volumes aus früheren Slices
dieser Roadmap-Sitzung gefunden und entfernt — sie waren nie über die
reguläre „Spielwiese beenden"-Aktion sauber abgebaut worden. Vor dem
Entfernen wurde geprüft, dass keine dieser Sitzungen zur echten,
laufenden Live-Umgebung des Nutzers gehörte (kein aktueller
Valkey-Quota-Schlüssel, keine Aktivität in `infra-sandbox-1`s Logs der
letzten 30 Minuten) — die `infra-*`-Container und die geteilten
`dcmlab/*:latest`-Images blieben unangetastet. Für künftige Slices
festgehalten: verwaiste `dcmlab-sandbox-*`-Ressourcen vor dem Entfernen
grundsätzlich gegen die Live-Aktivität von `infra-sandbox-1` prüfen,
nicht ungeprüft per Namensfilter löschen (dieselbe Vorsicht wie beim
Docker-Image-Nebenfund aus ADR 0048).

**5.2 ist seit P10.48 vollständig geschrieben — die Zitierfrage aus dem
Scaffold ist geklärt.** Recherchiert statt angenommen: Orthanc (das
Archiv dieser gesamten Plattform) veröffentlicht sein DICOM Conformance
Statement offen im eigenen Quellcode-Repository — echt zitierbar, kein
anonymisiertes/nachgebautes Beispiel nötig. Die Lektion zitiert daraus
(Store-SCP-SOP-Klassen, Transfer-Syntaxen, die reale Aussage „Orthanc
does not support extended negotiation") und prüft die dort behauptete
Präferenz für `LittleEndianExplicitTransferSyntax` **live gegen das
tatsächliche Verhandlungsergebnis** von `storescu -d`: Context 41
(`CTImageStorage`/`LittleEndianExplicit`) wird real akzeptiert, der
C-STORE läuft real über genau diesen Context. Dabei ein real
aufgetretener Stolperstein dokumentiert statt stillschweigend
korrigiert: Der erste `storescu`-Aufruf ohne expliziten Pfad traf das
`pynetdicom`-Skript gleichen Namens statt des echten DCMTK-Tools
(derselbe Namenskonflikt wie in ADR 0025). `sandbox.required` von
`false` auf `true` korrigiert (Dataset `ct-thorax-60`), `tools` von
`[]` auf `[storescu]` gesetzt, neuer Glossarbegriff
`conformance-statement`. Siehe ADR 0057.

**5.3 ist seit P10.49 vollständig geschrieben — der in ADR 0055
vorgeschlagene Hands-on-Baustein wurde real getestet und trägt.**
Zwei isolierte, `:test`-getaggte Orthanc-Instanzen auf einem eigenen
Docker-Netz (außerhalb der Standard-Sandbox, die pro Sitzung nur ein
Archiv bereitstellt) zeigten real: Eine Migration per
DICOM-Netzwerktransfer (`storescu`, REST-Export, `storescu` erneut)
lässt `StudyInstanceUID`/`SeriesInstanceUID`/`SOPInstanceUID` und
Pixeldaten byte-identisch — sogar Orthancs eigene, deterministisch
abgeleitete interne Instanz-ID stimmt auf beiden Archiven überein.
Einzige real gemessene Änderung: die File-Meta-Implementierungssignatur
(`ImplementationClassUID`/`-VersionName`, `PYDICOM 3.0.2` →
`OFFIS_DCMTK_370`). Eine zweite, real durchgeführte „kaputte Migration"
(UID-Neuvergabe beim Import) erzeugte am Ziel-Archiv real zwei
getrennte, unverbundene Studien für denselben Patienten — ohne
Fehlercode. Ein dritter geplanter Test (Private-Tag-Überleben)
scheiterte an `dcmodify`-Syntaxproblemen und wurde nicht
weiterverfolgt; die entsprechende Aussage bleibt in der Lektion
ausdrücklich als unbelegt markiert, nicht stillschweigend als getestet
dargestellt. `tools` von `[]` auf `[storescu, dcmdump]` gesetzt,
`sandbox.required` bleibt `false` (die *vollständige* Zwei-Archiv-
Demonstration ist in der Standard-Sandbox nicht nachstellbar).

**Wichtiger Nebenfund aus P10.49 (betrifft die gesamte Track-5-
Designprämisse aus ADR 0055):** `sandbox.required` in `meta.yml` wird
im gesamten `apps/web/app`-Code an keiner Stelle gelesen — es ist ein
rein dokumentierendes Feld ohne Validierungs- oder UI-Wirkung. Der
„Spielwiese starten"-Button erscheint stattdessen ausschließlich dann,
wenn irgendein in `tools` deklariertes Werkzeug in
`content/tools/de.yml` `needs_sandbox: true` trägt
(`LessonController::toolbarData()`). Für 5.1/5.2 änderte dieser Fund im
Ergebnis nichts (ihre Werkzeuge `findscu`/`storescu`/`pynetdicom` tragen
ohnehin `needs_sandbox: true`) — aber die ursprüngliche
Scaffold-Prämisse „`sandbox.required: false` verhindert den
Sandbox-Button" (ADR 0055) war unzutreffend. Siehe ADR 0058.

**5.4 ist seit P10.50 vollständig geschrieben — der in ADR 0055 als zu
prüfen markierte Hands-on-Baustein trägt.** Real per `curl` aus der
aktuellen DICOM-PS3.15-Annex-E abgerufen (nicht aus dem Gedächtnis
paraphrasiert): sechs standardisierte Aktionscodes (D/Z/X/K/C/U) mit
konkreten, zitierten Beispielen (`PatientName`→Z, `PatientBirthDate`→Z,
`StudyInstanceUID`→U). Der U-Code verbindet sich direkt mit Lektion
3.1s Fund zur Type-1-Durchsetzung: `StudyInstanceUID` darf nicht
geleert werden, sondern muss durch eine neue, gültige UID ersetzt
werden. Live in der echten Spielwiese angewendet: `dcmodify` setzt
real `PatientName` auf Länge Null und ersetzt `StudyInstanceUID` durch
eine neue UID, ein anschließender `storescu` bestätigt real, dass
Orthanc das de-identifizierte Objekt annimmt (`0x0000 Success`) — der
empirische Beleg für die U-Aktion. DICOMs eigener,
standardisierter Pseudonymisierungs-Mechanismus (Encrypted Attributes
Data Set, `0400,0550`) real recherchiert und benannt, aber nicht live
gebaut (unverhältnismäßiger Kryptografie-Aufwand). Ein geplanter
dritter Test (privates Tag über `dcmodify` einfügen) scheiterte an
VR-Mehrdeutigkeit und wurde nicht weiterverfolgt — dafür enthält die
Lektion auch keine ungeprüfte Behauptung zu privaten Tags. `tools` von
`[]` auf `[dcmdump, dcmodify, storescu]` gesetzt, `sandbox.required`
von `false` auf `true` korrigiert (Dataset `ct-thorax-60`), zwei neue
Glossarbegriffe (`de-identification`, `encrypted-attributes`). Siehe
ADR 0059.

**5.5 ist seit P10.51 vollständig geschrieben — ein realer Test
widerlegte die naive Annahme "das Archiv protokolliert Zugriffe".**
Real per `WebSearch` bestätigt: Orthanc hat kein eingebautes
Zugriffsprotokoll, die Projektdokumentation selbst empfiehlt einen
vorgeschalteten Reverse-Proxy dafür. Was Orthanc real bietet
(`/changes`) wurde live getestet: nach einem echten `storescu` wuchs
das Protokoll real um vier Einträge (`NewInstance`/`NewSeries`/
`NewStudy`/`NewPatient`, echte Zeitstempel/Sequenznummern) — nach
einem anschließenden echten Datei-Download blieb es unverändert. Der
zentrale, live demonstrierte Fund: `/changes` ist ein
Änderungsprotokoll, kein Zugriffsprotokoll, und trägt ohnehin keine
Nutzeridentität (konsistent mit `AuthenticationEnabled: false` in
dieser Sandbox). IHE ATNA als reale, standardisierte Antwort auf genau
diese Lücke benannt (konzeptionell). Reale, aktuelle Rechtsgrundlagen
recherchiert statt aus dem Gedächtnis zitiert: § 127 StrlSchV (10
Jahre Röntgenuntersuchungen, 30 Jahre Röntgenbehandlungen, Minderjährige
bis 28. Lebensjahr) und Art. 17 Abs. 3 DSGVO (Aufbewahrungspflicht als
Löschungsausnahme). `tools` von `[]` auf `[curl, storescu]` gesetzt,
`sandbox.required` von `false` auf `true` korrigiert (Dataset
`ct-thorax-60`), neuer Glossarbegriff `atna`. Siehe ADR 0060.

**5.6 ist seit P10.52 vollständig geschrieben — der Hands-on-Baustein
ist keine neue Übung, sondern die explizite Benennung eines bereits
seit ADR 0008 real dokumentierten Verhaltens.** Live real gezeigt:
`echoscu -aet PYNETDICOM -aec ANY-SCP` (frei erfundene Titel) wird von
Orthanc real akzeptiert, ein anschließender `findscu` mit denselben
Titeln liefert real einen echten (fiktiven) Patientennamen zurück —
ohne jede Anmeldung. Statt erfundener Vorfälle wurde eine aktuelle,
reale Studie recherchiert und per `pdftotext` aus dem Original-PDF
zitiert: „Measuring Healthcare Data Leaks and Security Flaws at
Internet Scale" (Brüggemann et al., Fraunhofer SIT/FH Münster/ATHENE,
arXiv 2607.04965v2, Juli 2026) — misst real dieselbe Methode im
öffentlichen Internet: 1.903 erfolgreiche Associations, 93,54 % davon
mit ungeschütztem Datenzugriff, macht 1.780 real gefundene verwundbare
DICOM-Dienste. Die Studie nennt auch die Gegenprobe (3.355 von 3.777
Verbindungsversuchen scheiterten real an einer AE-Title-Prüfung),
ehrlich mit übernommen statt unterschlagen. Netzsegmentierung und
DICOM-TLS (Querverweis auf Lektion 4.9s bereits real verifizierten
Befund) als reale Kompensationen erklärt. `tools` von `[]` auf
`[echoscu, findscu, storescu]` gesetzt, `sandbox.required` von `false`
auf `true` korrigiert (Dataset `ct-thorax-60`), neuer Glossarbegriff
`netzsegmentierung`. Siehe ADR 0061.

**5.7 ist seit P10.53 vollständig geschrieben — alle drei geprüften
REST-Endpunkte tragen, die Sorge um fehlende Aussagekraft ohne
Produktionslast hat sich nicht bestätigt.** Live real getestet:
`/statistics` wächst real von 0 auf 60 Instanzen/50.760 Bytes nach dem
Senden des Datensatzes; `/system` liefert echte Versions-/
Bibliotheksdaten (verbindet sich mit Lektion 5.6s Studienfund zu
Software-Clustern); `/jobs` zeigt real erst eine leere Liste (gültiger
Ruhezustand, Parallele zu Lektion 2.5), dann nach einem real via `POST
.../anonymize` ausgelösten Job (Lektion 5.4, 60 Instanzen) echte
Erfolgs-/Laufzeitkennzahlen (`EffectiveRuntime`, `Progress`, `State`,
`FailedInstancesCount`). Klare Abgrenzung zu Lektion 5.5 gezogen: alle
drei Endpunkte sind technisches Monitoring, keiner sagt etwas über
Zugriffsverhalten. `tools` von `[]` auf `[curl, storescu]` gesetzt,
`sandbox.required` von `false` auf `true` korrigiert (Dataset
`ct-thorax-60`). Siehe ADR 0062. **Damit sind sieben der acht
Track-5-Lektionen geschrieben — nur 5.8 (Beschaffung, geplant als
reine Synthese) steht noch aus.**

**5.8 ist seit P10.54 vollständig geschrieben — damit ist Track 5
komplett.** Wie geplant reine Synthese, keine neuen technischen
Befunde: sieben Fragenkategorien, jede mit direktem Verweis auf den
real verifizierten Befund der jeweiligen Quelllektion (5.1–5.7).
Zusätzlich real recherchiert statt erfunden: die Deutsche
Röntgengesellschaft (AGIT) hat eine reale, öffentlich referenzierte
PACS-Beschaffungs-Checkliste veröffentlicht (orientiert an der
IEEE-Praxis für Anforderungsspezifikationen, RFI-/RFP-Unterscheidung)
— nur ihre Existenz/Struktur zitiert, ihr Inhalt nicht nacherzählt.
`content/tracks.yml: bild.status`-Muster wiederholt:
**`betrieb.status`** von `planned` auf `published` gesetzt — alle acht
Lektionen aus Track 5 sind jetzt vollständig geschrieben, real gegen
den Stack verifiziert (Tracks-Übersicht zeigt „8 Lektionen ·
Verfügbar"). Siehe ADR 0063.

**Rückblick auf Track 5:** Jede einzelne der in ADR 0055 vorsichtig als
„möglicherweise kein Hands-on möglich" markierten Lektionen (5.1, 5.2,
5.4, 5.5, 5.6, 5.7) erwies sich bei tatsächlicher Prüfung als
hands-on-fähig — nur 5.3 brauchte eine Umgebung außerhalb der
Standard-Sandbox (zwei Orthanc-Instanzen), 5.8 blieb wie geplant reine
Synthese. Dasselbe Muster wie bei Track 2 (P10.24–P10.32) und Track 3
(P10.39–P10.45) beobachtet: vorsichtige Scaffold-Annahmen über
fehlende Hands-on-Möglichkeiten halten der tatsächlichen Prüfung
selten stand.

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
| Multiframe-Generator | ✅ P10.43 (kein neuer Generator nötig, siehe ADR 0052) | — (Track 3, kein Node-Bezug) |
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

**Alle sieben Roadmap-Engine-Features sind abgearbeitet.** Der
zunächst als offen geführte Multiframe-Generator erwies sich beim
tatsächlichen Schreiben von Lektion 3.4 (P10.43) als unnötig — die
bereits real vorhandenen, mitgelieferten `pydicom`-Testdaten
(`SC_rgb_rle_2frame.dcm`) genügten, siehe ADR 0052. **Keine
unblockierten, aber ungeschriebenen Node-Stubs mehr bekannt** — die
ursprüngliche Sieben-Node-Liste (siehe oben, Abschnitt "Track 1 —
Engine-Limits") ist mit P10.14 vollständig abgearbeitet.

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
gemergten Lektionen 4.1, 4.2, 4.4 (sowie 1.8) wurden zunächst **nicht**
rückwirkend um echte `tshark`-Beispiele ergänzt — nur der neue Fund
dokumentiert.

**Seit P10.34 aufgegriffen und geklärt:** Lektion 1.8 zeigte tatsächlich
einen echten Bug — einen `tshark`-Mitschnitt mit erfundenen IP-Adressen
und PDU-Typ-Werten, ohne `<!-- kein-beispiel -->`-Markierung (ein
Abschnitt-13-Verstoß, den `content:validate` nicht erkennen konnte, da
der Validator nur die Existenz einer Erklärung prüft, nicht deren
Wahrheitsgehalt). Jetzt durch einen echten, im Sitzungscontainer
aufgezeichneten Mitschnitt ersetzt (`-T fields`-Extraktion des rohen
`dicom.pdu.type`, echte Werte `0x01/0x02/0x04/0x04/0x05/0x06` für einen
erfolgreichen C-ECHO-Zyklus), ergänzt um einen ehrlich markierten
`<!-- kein-beispiel -->`-Block für die beiden real definierten, aber
nicht live erzeugbaren Ablehnungs-PDU-Typen (`0x03`, `0x07`). 4.1, 4.2
und 4.4 zeigten `tshark` dagegen in ihrem Fließtext gar nicht — bei der
Prüfung fiel zusätzlich auf, dass auch `storescu` (4.1, 4.4) und
`storescp` (4.2) nie im Text vorkamen, obwohl deklariert. `tools:` in
allen drei `meta.yml`-Dateien auf die tatsächlich gezeigten Werkzeuge
reduziert (4.1: nur `echoscu`; 4.2: `storescu`, `dcmdump`; 4.4: nur
`echoscu`) — kein neuer tshark-Beispielbedarf, da das eigentliche Thema
dieser drei Lektionen strukturell nicht live erzeugbar ist (ADR
0008/0025) und ein Mitschnitt der erfolgreichen Gegenprobe keinen neuen
Erkenntniswert gegenüber den vorhandenen Beispielen geliefert hätte.
`content/lessons/4.10` bereits mit echten `tshark`-Beispielen — kein
Nachbesserungsbedarf. Siehe ADR 0044.

**Seit P10.35 zwei weitere Fälle desselben Musters gefunden und
behoben:** `content:validate`s `checkToolInverse` erkennt nur benutzte,
aber nicht deklarierte Werkzeuge — nie die Umkehrung (deklariert, aber
nie gezeigt). Gezielt geprüft: 4.3 deklarierte zusätzlich `storescp`
und `dcmdump`, ohne sie im Fließtext zu benutzen — auf `[storescu,
findscu]` reduziert. 4.7 deklarierte noch `wlmscpfs`, ein
Überbleibsel von vor P10.20 (seit Orthancs Worklists-Plugin aktiv ist,
ADR 0030, beantwortet Orthanc selbst die Worklist-Anfrage — dieselbe
Korrektur wurde für die Schwesterlektion 2.5 bereits in P10.25 gemacht,
hier aber übersehen) — auf `[findscu, tshark]` reduziert. Siehe ADR
0045. Keine weiteren Fälle dieses Musters bekannt.

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

**Track 2:** seit P10.32 vollständig geschrieben (alle acht Lektionen,
siehe oben). **Track 3:** seit P10.45 vollständig geschrieben (alle
sechs Lektionen, siehe oben), Track veröffentlicht. **Track 5:** seit
P10.54 vollständig geschrieben (alle acht Lektionen, siehe oben),
Track veröffentlicht.

**Zehn vorbestehende mypy-Fehler in `services/sandbox/tests/
test_orchestrator.py` (in ADR 0042 vermerkt, hier nachgetragen):**
seit P10.33 behoben — fehlende Typannotationen ergänzt, `str | None`
mit `assert ... is not None` an der Quelle verengt statt an jeder
Aufrufstelle. Siehe ADR 0043. Reine Typkorrektur, kein
Content-Bezug.

**Lokale Umgebung (seit P10.8 beobachtet, kein Content-Problem):** Die
lokale PHP-Installation (`8.4.0`) erfüllt `composer.lock`s Anforderung
`>= 8.4.1` nicht mehr — betrifft `main` unverändert, nicht nur diesen
Branch. Pest/Pint/PHPStan lassen sich dadurch aktuell nicht direkt lokal
ausführen; `content:validate`/`content:build` liefen stattdessen im
echten `app`-Container (Docker-Image, eigenes PHP) desselben isolierten
Verifikations-Stacks. CI (`shivammathur/setup-php@v2`) ist davon nicht
betroffen und bleibt die maßgebliche Prüfung für PHP-Tests.

**Quiz-Feature (P10.59, kein Content-Problem, aber `content:validate`
betroffen):** Die seit P10.36 in `content/lessons/1.0`–`1.8/meta.yml`
vorhandenen `quiz:`-Daten wurden bisher von keinem Code gelesen. Seit
P10.59 liest `ContentValidate::checkQuizStructure()` sie real (jede
`quiz[].id` braucht eine passende `**qN — ...**`-Überschrift in `de.md`,
Antwortindizes müssen innerhalb der geparsten Optionsanzahl liegen) —
real gegen alle neun bestehenden Quiz-Lektionen geprüft, 0 neue
Verstöße. Das eigentliche Feature (interaktives Beantworten,
Spaced-Repetition-Wiederholung, eigene Review-Seite) ist Anwendungscode
(`app/Services/QuizSchedulerService.php`,
`app/Content/QuizContent.php`, `app/Http/Controllers/{Quiz,Review}
Controller.php`), kein Content — siehe ADR 0065 für Details und
Verifikation.

**Track-Abschlussprüfung Fundamente (P10.60):** neuer Content unter
`content/exams/fundamente/{exam.yml,de.md}`, 40 Fragen (16 single, 9
multi, 9 truefalse, 6 input), je 4 aus den Lektionen 1.0–1.8 sowie 4
`cross`-Fragen. Kein neuer Content-Absatz musste ergänzt werden — jede
Frage ließ sich aus dem bereits vorhandenen Lektionstext belegen (die 27
bestehenden Lektions-Quiz-Karten sind darin aufgegangen, teils mit
anderem Fragetyp als im Original, siehe ADR 0066, weil der Prüfungspool
eine eigene Typmischungsvorgabe hat — der geprüfte Fakt blieb dabei
unverändert). Neu ist außerdem `content/skills.yml` (kontrolliertes
Vokabular für `tags`, deckungsgleich mit `ProfileService::
SKILL_CATEGORIES`) — reiner Validierungs-Baustein, kein Lektionsinhalt.
`content:validate` prüft die neue `exam.yml`/`de.md`-Struktur seit
P10.60 real (`ContentValidate::checkExamStructure()`), siehe ADR 0066
für alle Regeln und die vier bewussten Architekturentscheidungen
(neuer Fragetyp `truefalse`, neue Punktequelle "Track bestanden", neue
Anker-Slug-Klasse `HeadingSlug`, neues `skills.yml`).

**Track-Abschlussprüfung Die Services (P10.61):** neuer Content unter
`content/exams/services/{exam.yml,de.md}`, 38 Fragen (15 single, 9 multi,
9 truefalse, 5 input), je 4 aus den Lektionen 2.1–2.8 sowie 6
`cross`-Fragen (u. a. die vom P10-Prompt genannte Kette
MWL → MPPS → C-STORE → Storage Commitment und DICOMweb als Gegenstück zu
DIMSE). Anders als Track 1 hatte keine der acht Track-2-Lektionen zum
Zeitpunkt dieser Prüfung `quiz:`-Daten in ihrer `meta.yml` (das Feld
fehlt in allen acht Dateien) — der gesamte Pool ist deshalb neu
geschrieben, nicht aus vorhandenen Lektionskarten übernommen; jede Frage
bleibt trotzdem an echten Fakten und Stolperfallen aus dem jeweiligen
Lektionstext verankert. Keine neuen Architekturentscheidungen nötig —
Schema, Validator, Backend und Frontend aus ADR 0066 sind trackneutral
und wurden unverändert wiederverwendet. Reale Verifikation: `content:
validate` grün, echter Browser-E2E im isolierten Compose-Stack (22/22
Fragen richtig, 100 %, bestanden, Ergebnisseite korrekt).

**Track-Abschlussprüfung Das Bild selbst (P10.62):** neuer Content unter
`content/exams/bild/{exam.yml,de.md}`, 30 Fragen (12 single, 7 multi,
7 truefalse, 4 input), je 4 aus den Lektionen 3.1–3.6 sowie 6
`cross`-Fragen (u. a. die beiden unabhängigen Umrechnungsschritte
Photometric Interpretation/Fensterung, das Type-System aus 3.1/3.2, und
die Instance-vs-Frame-Zählfalle aus 3.4/3.5). Wie Track 2 hatte keine
der sechs Track-3-Lektionen `quiz:`-Daten in ihrer `meta.yml` — der Pool
ist komplett neu geschrieben, bleibt aber an echten Fakten und
Stolperfallen aus dem jeweiligen Lektionstext verankert. Ein Nebenfund
beim Schreiben: die Überschrift „Was ein {{term:enhanced-iod}}
tatsächlich anders macht" (3.4) enthält die `{{term:x}}`-Syntax wörtlich
im Rohtext, wodurch `HeadingSlug` sie mit in den Anker-Slug einrechnet
(`was-ein-term-enhanced-iod-tatsaechlich-anders-macht`) — kein Bug,
`MarkdownRenderer` und `ContentValidate` berechnen beide denselben Slug
aus demselben Rohtext (siehe ADR 0066), nur optisch ungewöhnlich; für
künftige Lektionen ist es einfacher, `{{term:x}}` nicht in
Überschriften zu verwenden, auf die ein Rückverweis zeigen soll. Keine
neuen Architekturentscheidungen. Reale Verifikation: `content:validate`
grün, echter Browser-E2E im isolierten Compose-Stack (16/16 Fragen
richtig, 100 %, bestanden).

**Track-Abschlussprüfung Betrieb und Integration (P10.63):** neuer
Content unter `content/exams/betrieb/{exam.yml,de.md}`, 38 Fragen (15
single, 9 multi, 9 truefalse, 5 input), je 4 aus den Lektionen 5.1–5.8
sowie 6 `cross`-Fragen (u. a. De-Identifikation vs. Zugriffsprotokoll
als unabhängige Datenschutzmaßnahmen, Conformance Statement vs.
tatsächliche AE-Title-Sicherheit, und die Verbindung zwischen 5.6s
realer Sicherheitslücke und 5.8s Beschaffungs-Checkliste). Wie Track 2/3
hatte keine der acht Track-5-Lektionen `quiz:`-Daten in ihrer
`meta.yml` — der Pool ist komplett neu geschrieben, bleibt aber an
echten Fakten, Zahlen und Stolperfallen aus dem jeweiligen Lektionstext
verankert (u. a. die real zitierte Fraunhofer/FH-Münster-Studie aus 5.6
und § 127 StrlSchV aus 5.5). Lektion 5.8 ist reine Synthese ohne neuen
Stoff — ihre vier Pool-Fragen prüfen die Checklisten-Struktur selbst
(RFI/RFP-Phase, Verweis auf reale Befunde der vorigen sieben Lektionen),
keine erfundenen Zusatzfakten. Keine neuen Architekturentscheidungen.
Reale Verifikation: `content:validate` grün, echter Browser-E2E im
isolierten Compose-Stack (20/20 Fragen richtig, 100 %, bestanden).

Damit haben vier der fünf Tracks eine Abschlussprüfung (Fundamente,
Die Services, Das Bild selbst, Betrieb und Integration). Offen: Track 4
(Troubleshooting) — laut Auftrag der Sonderfall, bei dem praktisch alle
Fragen `cross` sind, weil jede Störung mehrere Lektionen berührt; die
Mindestquote von 4 Fragen je Lektion muss dafür in `ContentValidate`
gelockert werden, siehe P10-Prompt Abschnitt „Anschluss: die übrigen
Tracks".

## CI

**Seit P10.55 blockierend.** `content:validate` läuft in der
CI-Pipeline (`content`-Job) und meldet seit P10.23 (0 Verstöße nach
Behebung aller 19 damaligen Fundstellen) durchgehend keine Verstöße
mehr — bestätigt in jeder einzelnen Content-Slice seither (zuletzt
P10.54: 41 Lektionen, 52 Glossarbegriffe, 0 Verstöße). Das ursprünglich
vorgesehene `continue-on-error: true` in `.github/workflows/ci.yml`
wurde entsprechend entfernt: `content:validate` ist jetzt ein
blockierender Check wie jeder andere CI-Job, nicht mehr informativ.
