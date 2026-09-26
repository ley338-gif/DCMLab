# Lektions-Backlog Track 2–5

Bestandsaufnahme aller Lektionen aus Track 2–5 gegen `docs/lektions-standard.md`.
Stand: 2026-09-25. Die Referenzen sind 4.1 (erfüllt den Standard) und 3.8 (fast).
**Nur bewertet, nichts korrigiert.** Keine Lektion und keine ContentVersion wurde
angefasst.

> **Korrektur (2026-09-26):** Frühere Fassungen dieses Dokuments und ADR 0122 nannten
> bei 2.2 `daten//`. Tatsächlich steht dort `daten/<datensatz>/`. Ein Diagnoseskript
> hatte den Platzhalter per `strip_tags` verschluckt. #25 ist inhaltlich identisch mit
> dem Live-Stand und kann ohne Verlust ersetzt werden.

## Quelle und Methode

**Live-Stand statt Repo.** Studio-Änderungen sind neuer als `content/`. Die Tabelle
beruht deshalb auf einem Export der lokalen DB `dcmlab`. Die DB wurde nur gelesen.
Ablauf im `app`-Container:

1. `content/` nach `/tmp/content-live` kopieren.
2. `php artisan content:export --path=/tmp/content-live` ausführen.
3. Das Ergebnis per `docker cp` herausholen.

`content/` im Repo blieb unberührt, das bestätigt `git status`.

Der Export hat bei 7 Lektionen den Live-Stand eingesetzt (1.7, 2.2, 2.3, 3.1, 3.3,
3.4, 4.5). **2.1 ist nicht exportierbar**; dort sind Wortzahl und Prosa-Befund direkt
aus `lessons.rich_content` gelesen. Offene Versionen, Lektionsstatus und
Node-Status stammen ebenfalls rein lesend aus der DB.

**Erhebung.** Automatisch erhoben sind:

- Wörter im Fließtext ohne Quiz
- Quiz (Anzahl und Typen)
- Beweiskraft-Formulierungen (Treffer für „beweist/bewiesen/belegt/Belegt
  nicht/sagt nicht/schließt … nicht aus“)
- Normfundstellen: PS3.x-Nennungen, davon mit Tabelle, Abschnitt oder Annex
- Beispiele (`Was du daran abliest`)
- Diagnosematrix (Tabelle mit „Nicht bewiesen“/„Belegt nicht“)

Von Hand bewertet, anhand von Überschrift und erstem Absatz, ist der
Diagnose-Einstieg. Die Herkunft der Beispiele stammt aus der Git-Historie der Datei
(„echte Beispiele“ in der Commit-Nachricht). Sie ist damit **laut Commit** belegt,
nicht neu gegen die Spielwiese nachgeprüft. Die Zahlen sind ein Raster zum Sortieren,
kein Urteil über fachliche Richtigkeit. Die prüft erst die Überarbeitung selbst.

**Legende**

- Einstieg: **ja** = Ticket oder Symptom; **Auftrag** = Arbeitsauftrag statt Störung;
  **nein** = Definition oder Überblick
- Beweis: Zahl der Treffer
- Norm: *x/y* = x von y PS3-Nennungen mit Fundstelle
- Aufwand: **S** unter einem halben Tag, **M** ein bis zwei Tage, **L** mehr, meist
  mit neuen Beispielen oder neuer Struktur

## Übergreifend

- **Keine einzige veröffentlichte Lektion in Track 2–5 außer 4.1 hat ein
  Lektionsquiz.** Die Entwürfe 3.7, 4.11, 4.12 und 5.9–5.12 haben 2–3 Fragen; der
  Standard verlangt 4–5.
- **`storescu`, `storescp`, `echoscu`, `findscu` der Spielwiese sind die pynetdicom-Programme**
  (`/usr/local/bin`, pynetdicom 3.0.4), nicht DCMTK (`/usr/bin`, 3.6.7). Das DCMTK-Programm ist
  installiert, liegt aber im `PATH` dahinter (live geprüft 2026-09-26).
  - Die Ausgaben in den Lektionen stammen deshalb von pynetdicom.
  - Die Werkzeug-Registry (`content/tools/de.yml`: `suite: dcmtk`) und Formulierungen wie
    „DCMTK-Werkzeug“ stimmen nicht.
  - Verhalten, das vom Werkzeug abhängt, ist betroffen. Beispiel: Der Exitcode von `storescu`
    bleibt bei abgelehnten Objekten `0`; siehe den Entwurf zu 2.2.
  - Jede Lektion mit diesen Werkzeugen ist bei ihrer Überarbeitung darauf zu prüfen.
- **Eine Diagnosematrix haben nur 4.1 und 3.8.**
- **Track 3 (3.1–3.6) hat keinen Abschnitt `## Stolperfallen`.** Die Lektionsstruktur
  in `content-schema.md` verlangt ihn.
- **28 der 39 Lektionen nennen den Standard gar nicht.** 3.1 und 4.9 nennen ihn ohne
  Fundstelle. Mit Fundstelle arbeiten nur 2.5, 2.7, 3.8, 4.2, 4.3, 4.8, 5.2
  (teilweise), 5.4 und 5.8.

## Tabelle

| Lektion | Wörter | Quiz | Einstieg | Beweis | Norm | Beispiele | Node veröffentlicht? | offene Version | Aufwand | Hauptlücken |
|---|---|---|---|---|---|---|---|---|---|---|
| 2.1 C-ECHO | 664 | nein | nein | 3 | – | 4, echt laut Commit (#35) | – | – | M | **nicht exportierbar** (Fettdruck ohne Leerzeichen vor „Fünf“, `doc.content[5]`); kürzeste Service-Lektion; Einstieg ist eine Grundsatzfrage statt eines Tickets |
| 2.2 C-STORE | 862 | nein | Auftrag | 2 | – | 6, echt laut Commit (#36) | – | **#25 review** | M | Live-Konsolenbeispiel mit Platzhalter `daten/<datensatz>/` statt `daten/ct-thorax-60/` (verstößt gegen die Beispielregel, `content-schema.md` Abschnitt 0); #25 ist inhaltsgleich mit dem Live-Stand (Leerlauf-Review); kein Quiz |
| 2.3 C-FIND | 1018 | nein | Auftrag | 0 | – | 5, echt laut Commit (#37) | – | – | M | Lab-Abschnitt aus PR #158 für Lernende unsichtbar (Datei neuer als DB); keine Beweiskraft-Aussagen; kein Quiz |
| 2.4 C-MOVE/C-GET | 1238 | nein | ja | 0 | – | 6, echt laut Commit | – | – | M | keine Beweiskraft-Aussagen (was beweist „leerer Zielordner“?); keine PS3-Fundstelle zu Move-Destination; kein Quiz |
| 2.5 MWL | 956 | nein | Auftrag | 0 | 1/1 | 5, echt laut Commit (#32) | – | – | M | Einstieg als Auftrag; keine Beweiskraft; kein Quiz |
| 2.6 MPPS | 935 | nein | nein | 0 | – | 3, echt laut Commit (#33) | – | – | M | Definitionseinstieg („Ein C-STORE überträgt Bilder — nichts weiter“); keine Beweiskraft; kein Quiz |
| 2.7 Storage Commitment | 792 | nein | Auftrag | 4 | 2/2 | 3, echt laut Commit (#34) | – | – | S–M | kein Quiz; kein Diagnosewerkzeug (N-ACTION-Erfolg ≠ Commitment-Ergebnis als Matrix) |
| 2.8 DICOMweb | 1138 | nein | Auftrag | 0 | – | 7, echt laut Commit (#39) | – | – | M | keine Beweiskraft; keine PS3.18-Fundstellen; kein Quiz |
| 3.1 IOD/Module | 1125 | nein | ja | 0 | 0/1 | 3, echt laut Commit (#47) | – | – | M | Live-Stand (#15) weicht von der Datei ab; keine Stolperfallen; PS3.3-Aussage ohne Fundstelle |
| 3.2 Pixeldaten | 988 | nein | ja | 1 | – | 4, echt laut Commit (#48) | – | – | M | keine Stolperfallen; kein Quiz; kaum Beweiskraft |
| 3.3 Window/Rescale | 868 | nein | ja | 0 | – | 3, echt laut Commit (#49) | – | – | M | **Lernziel aus #16 live verloren** (nur im Versions-Payload); keine Stolperfallen; kein Quiz |
| 3.4 Multiframe | 830 | nein | ja | 0 | – | 4, synthetisch (pydicom, #50) | – | – | M | Lab-Abschnitt aus #21 fehlt in der Datei (DB neuer); „synthetisch“ ehrlich benennen (Standard Abschnitt 4); keine Stolperfallen |
| 3.5 SR/KOS | 1235 | nein | ja | 0 | – | 3, synthetisch (pydicom, #51) | – | – | M | Konformitätsanspruch der pydicom-Objekte prüfen (Befund 3.8 R2); keine Stolperfallen; kein Quiz |
| 3.6 Character Set | 743 | nein | ja | 3 | – | 4, echt laut Commit (#52) | – | – | S–M | keine Stolperfallen; Type-1C-Aussage ohne PS3.3-Fundstelle; kein Quiz |
| 3.7 Encapsulated PDF (draft) | 821 | 3 (s/m/i) | ja | 5 | – | 1 | – | – | M | nur 3 Quizfragen; nur ein Beispiel; noch `draft` |
| 3.8 RDSR (draft, Referenz) | 3201 | 5 (3s/2m) | ja | 7 | 1/2 | 2, synthetisch, ehrlich benannt | gefiltert: **draft** | – | S | **keine `input`-Frage**; noch `draft`; verknüpfte Node ebenfalls `draft` |
| 4.2 Presentation Context | 799 | nein | ja | 1 | 1/1 | 2, echt laut Commit (#22) | verbindung-ohne-bild: ja | – | M | kein Quiz; keine Diagnosematrix; Anschluss an die Kette aus 4.1 prüfen |
| 4.3 unvollständige Study | 828 | nein | ja | 2 | 3/3 | 3, echt laut Commit (#23) | oversized-image: ja | – | S–M | kein Quiz; keine Diagnosematrix |
| 4.4 Timeout/Netzweg | 751 | nein | ja | 4 | – | 2, echt laut Commit (#24) | – | – | M | Gegenstück zu 4.1 (Timeout, Host unreachable): Beweiskraft der TCP-Meldungen mit 4.1 abgleichen; kein Quiz |
| 4.5 Dubletten | 896 | nein | ja | 4 | – | 4, echt laut Commit (#25) | – | – | M | Änderungen aus PR #158 für Lernende unsichtbar (Datei neuer als DB); kein Quiz |
| 4.6 Falscher Patient | 787 | nein | ja | 1 | – | 3, echt laut Commit (#15) | patient-merge-discovery: ja | – | M | kein Quiz; kaum Beweiskraft |
| 4.7 Worklist leer | 938 | nein | ja | 0 | – | 3, echt laut Commit (#27) | worklist-query-empty: ja | – | M | keine Beweiskraft; setzt 2.5 voraus (Abgrenzung); kein Quiz |
| 4.8 MPPS/Commitment | 1172 | nein | ja | 1 | 1/1 | 5, echt laut Commit (#28) | – | – | M | kaum Beweiskraft (N-ACTION-Erfolg ≠ Commitment-Ergebnis); kein Diagnosewerkzeug; kein Quiz |
| 4.9 TLS | 1052 | nein | ja | 0 | 0/1 | 5, echt laut Commit (#29) | – | – | M | keine Beweiskraft (was beweist ein TLS-Handshake-Fehler?); TLS-Aussagen ohne PS3.15-Fundstelle (nennt nur PS3.7); kein Quiz |
| 4.10 Systematik | 1033 | nein | ja | 0 | – | 5, echt laut Commit (#26) | – | – | M | Kette konsistent zu 4.1 halten (Standard Abschnitt 1); keine Beweiskraft; kein Quiz |
| 4.11 Retrieve (draft) | 709 | 2 (s/m) | ja | 1 | – | 2, Herkunft nicht belegt | move-destination-unknown: **draft** | – | M | nur 2 Quizfragen, keine `input`; Beispielherkunft unbelegt |
| 4.12 gesendet, unsichtbar (draft) | 653 | 2 (s/m) | ja | 3 | – | 2, Herkunft nicht belegt | stored-but-invisible: **draft** | – | M | nur 2 Quizfragen, keine `input`; Beispielherkunft unbelegt |
| 5.1 MWL+MPPS | 1293 | nein | nein | 0 | – | 4, Herkunft nicht belegt | – | – | L | Überblick statt Diagnose; keine Beweiskraft; kein Quiz |
| 5.2 Conformance Statement | 885 | nein | ja | 2 | 1/3 | 2, Herkunft nicht belegt | – | – | M | PS3.2-Aussagen nur zum Teil mit Fundstelle; kein Quiz |
| 5.3 Migration | 930 | nein | nein | 0 | – | 1, außerhalb der Spielwiese | – | – | L | Befunde nicht aus der Spielwiese (so markiert); kaum Beispiele; kein Quiz |
| 5.4 Anonymisierung | 840 | nein | Auftrag | 1 | 3/4 | 4, Herkunft nicht belegt | – | – | M | Normbezug am besten im Track, Beweiskraft fehlt; kein Quiz |
| 5.5 Zugriffsprotokoll | 760 | nein | nein | 0 | – | 3, Herkunft nicht belegt | – | – | M | kein Diagnose-Einstieg; keine Beweiskraft; kein Quiz |
| 5.6 AE-Title-Sicherheit | 749 | nein | Auftrag | 0 | – | 2, Herkunft nicht belegt | – | – | M | keine Beweiskraft (was beweist „frei erfundener Name genügt“?); kein Quiz |
| 5.7 Kennzahlen | 637 | nein | Auftrag | 0 | – | 5, Herkunft nicht belegt | – | – | M | keine Beweiskraft; kein Quiz |
| 5.8 Checkliste | 838 | nein | nein | 1 | 1/1 | 0 | – | – | L | Zusammenfassungs-Lektion ohne Beispiel; der Standard passt nur bedingt, Rolle klären |
| 5.9 Go-live (draft) | 782 | 2 (m/s) | ja | 0 | – | 3, Herkunft nicht belegt | modality-go-live: **draft** | – | M | nur 2 Quizfragen; keine Beweiskraft trotz Thema „C-ECHO grün ≠ fertig“ |
| 5.10 AE-Registry (draft) | 526 | 2 (m/s) | ja | 1 | – | 0 | – | – | L | kein einziges Beispiel; nur 2 Quizfragen |
| 5.11 DR/Restore (draft) | 661 | 2 (s/m) | ja | 0 | – | 0 | restore-or-retrieve: **draft** | – | L | kein Beispiel; nur 2 Quizfragen |
| 5.12 Routing (draft) | 610 | 2 (s/m) | ja | 0 | – | 0 | – | – | L | kein Beispiel (3× `kein-beispiel`); nur 2 Quizfragen |

## Vorab: Sofortmaßnahmen außerhalb der Qualitätsarbeit

Diese Befunde aus ADR 0122 blockieren einen sauberen Export. Sie sind klein (S) und
sollten **vor** der Überarbeitung der betroffenen Lektion erledigt werden, jeweils in
Studio:

1. **2.1**: das fehlende Leerzeichen nach „Was du daran abliest:“ ergänzen. Danach
   ist 2.1 exportierbar.
2. **2.2**: den Platzhalter `daten/<datensatz>/` im Konsolenbeispiel durch `daten/ct-thorax-60/` ersetzen und die offene Version **#25
   (review)** entscheiden (veröffentlichen oder verwerfen), bevor eine neue Fassung
   entsteht. `createDraft()` würde sie sonst superseden (ADR 0121).
3. **1.7, 2.3, 4.5**: den neueren Dateistand aus PR #158 in Studio veröffentlichen,
   sonst bleibt er für Lernende unsichtbar, und ein Export würde ihn zurückdrehen.
4. **3.3**: entscheiden, ob das Lernziel aus #16 wieder live gehen soll.
5. **3.4**: den Lab-Abschnitt aus #21 per `make content-export` ins Repo holen.

## Reihenfolge: Track 2 → 4 → 3 → 5

**Track 2 zuerst.** Die Service-Lektionen sind die meistvorausgesetzten des ganzen
Bestands (`requires` über alle Lektionen gezählt):

| Lektion | benötigt von | darunter Track-4-Lektionen |
|---|---|---|
| 2.1 | 5 | – |
| 2.2 | 5 | 4.12 |
| 2.5 | 5 | – |
| 2.3 | 4 | – |
| 2.4 | – | 4.11 |
| 2.6 | 3 | – |

Wer 4.x oder 5.x liest, trifft zuerst auf sie. Eine Diagnose-Lektion in Track 4 kann
nur dann auf Grundlagenwiederholung verzichten (Standard Abschnitt 1), wenn die
`requires`-Lektion die Grundlage wirklich trägt.

**Danach Track 4.** Er ist dem Referenzmuster am nächsten: Fast alle Lektionen
beginnen schon mit einem Ticket, und 4.2, 4.3, 4.6 und 4.7 haben veröffentlichte
Nodes. Pro Lektion fehlen vor allem Quiz, Diagnosematrix und
Beweiskraft-Präzisierung (meist M). Innerhalb des Tracks gilt:

1. **4.10** zuerst, weil 4.11 und 4.12 sie voraussetzen und sie die Kette aus 4.1
   für den ganzen Track trägt.
2. Dann **4.4** als TCP-Gegenstück zu 4.1.
3. Dann **4.2**, weil 4.1 ausdrücklich auf sie abgrenzt.

**Dann Track 3.** 3.1 wird von 4 Lektionen vorausgesetzt, zuerst 3.1, dann 3.2 und
3.5. Dazu kommt der fehlende Stolperfallen-Abschnitt in 3.1–3.6. 3.8 braucht nur
eine `input`-Frage und die Veröffentlichung (S).

**Zuletzt Track 5.** Hier ist die Lücke am größten: vier Lektionen ohne
Diagnose-Einstieg und vier Entwürfe ohne ein einziges Beispiel, meist L. Die
Lektionen bauen aber auf Track 2 auf und profitieren von dessen Überarbeitung.
Innerhalb des Tracks zuerst 5.9, weil 5.10 und 5.12 sie voraussetzen.

### Top 5

1. **2.2 C-STORE**: von 5 Lektionen vorausgesetzt, auch von 4.12. Ein sichtbarer
   Fehler im Live-Beispiel und die offene Version #25 machen die Lektion zur
   dringendsten.
2. **2.1 C-ECHO**: von 5 Lektionen vorausgesetzt und die kürzeste Service-Lektion
   (664 Wörter). Sie ist nicht exportierbar und der direkte Vorläufer des
   4.1-Musters (C-ECHO-Beweiskraft).
3. **2.3 C-FIND**: von 4 Lektionen vorausgesetzt, ohne jede Beweiskraft-Aussage. Der
   Stand aus PR #158 ist für Lernende unsichtbar.
4. **2.5 Modality Worklist**: von 5 Lektionen vorausgesetzt, darunter 5.1 und 5.9.
   Sie ist die Grundlage für 4.7, keine Beweiskraft.
5. **4.10 Systematik**: trägt die Diagnosekette für Track 4 und wird von 4.11/4.12
   vorausgesetzt. Hier zahlt sich der Standard (Kette, Beweiskraft) am stärksten
   aus.

## Datei-Entwurf nach Studio übernehmen: `content:draft` (umgesetzt)

**Problem.** Ein in einer Datei geschriebener Lektionsentwurf, etwa eine
Agent-Überarbeitung, lässt sich heute nur durch Abtippen im Rich-Content-Editor als
Studio-Entwurf anlegen. Für ein Backlog dieser Größe ist das eine echte Bremse.

**Umgesetzt** nach Freigabe am 2026-09-26. Aufruf:
`php artisan content:draft <lesson> --from=<de.md> [--meta=<meta.yml>] --author=<id|email> [--part=lesson|quiz] [--supersede]`.
Die Skizze ist unten unverändert, die Abweichungen stehen danach.

**Skizze.** Ein Befehl `php artisan content:draft {lesson} --from=<datei>`:

1. Liest `de.md` (optional auch `meta.yml`) aus `--from`, nicht aus `content/`.
2. Baut das Payload wie der Lesson-Editor:
   - Metadaten aus Frontmatter und `meta.yml`
   - Prosa über `LessonPayloadNormalizer`/`MarkdownToRichContentConverter`
     (`before` + `after`, ohne `## Quiz`)
   - Quiz als eigene Version mit `quiz`-Payload, wie der Quiz-Editor
3. Validiert es mit `LessonActivity::validate()` und bricht bei Befunden ab.
4. Legt es über `ContentVersioningService::createDraft()` mit einem angegebenen
   Autor als **Entwurf** an. Dabei greift die Supersession aus ADR 0121: Ein offener
   Entwurf oder Review derselben Lektion wird `superseded`. Deshalb fragt der Befehl
   nach, wenn es einen offenen gibt, und nennt dessen Nummer.
5. **Veröffentlicht nie**, reicht nicht einmal zur Review ein. Beides bleibt in
   Studio, bei Menschen mit der jeweiligen Rolle.

**Risiken.**

- *Wird die Datei so wieder ein zweiter Schreibpfad?* Nein, solange der Befehl nur
  Entwürfe erzeugt und `published` ausschließlich über Studio-Review und danach
  `make content-export` erreichbar bleibt. Die Datei ist Eingabe für einen Entwurf,
  nie Wahrheit. `content:sync` bleibt davon unberührt.
- *Versehentliches Überschreiben eines fremden Entwurfs* durch die Supersession. Das
  fängt die Rückfrage mit Versionsnummer ab.
- *Konverterlücken*: `callout`, `dicom_tag_table` und `dicom_dump` sind aus Markdown
  nicht erzeugbar (ADR 0114/0122). Wer sie will, setzt sie danach im Editor.
- *Autorschaft*: `created_by` muss ein echter Nutzer sein, kein Systemkonto, sonst
  verliert „Reviewer ≠ Autor“ seine Aussagekraft.

**Abweichungen von der Skizze in der Umsetzung:**

- **`--part=lesson|quiz`**: Lektionsfelder und Quiz hängen an derselben
  Lesson-Activity, und ADR 0121 erlaubt pro Activity nur einen offenen Entwurf. Ein
  zweiter `createDraft()` würde den ersten sofort superseden. Weichen beide Teile
  ab, bricht der Befehl mit Erklärung ab. Man legt sie nacheinander an und
  veröffentlicht dazwischen.
- **Kein Entwurf ohne Änderung**: Ist die Datei inhaltlich gleich dem Live-Stand,
  entsteht keine Version. So entsteht kein Leerlauf-Review wie #25.
- **`--author` ist Pflicht** und muss die Lektion bearbeiten dürfen
  (`ActivityPolicy::update`).
- **Supersession nur bewusst**: Die offenen Versionen werden genannt. Ohne
  Interaktion bricht der Befehl ab, außer mit `--supersede`.
