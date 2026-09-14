# 0072 — Aktivitätsvertrag: ein Plugintyp statt Sonderfällen

## Status

Angenommen, 15.09.2026. Ergänzt ADR 0071 (Speicherort) und geht ihm in der
Umsetzungsreihenfolge voraus.

## Kontext

Das Zielbild hat sich geschärft: ein **Lightweight LMS**. Nicht Moodle
nachbauen, sondern die wenigen Muster übernehmen, die dort tragen, und den
Rest weglassen. Auslöser war die Frage, ob die Spielwiese langfristig als
Plugin oder Modul funktionieren sollte.

Die Antwort der beiden großen Systeme ist dieselbe und seit Jahren stabil:

- **Moodle** kennt Aktivitätsmodule als Plugintyp `mod`. Forum, Quiz und
  Assignment sind selbst nichts anderes als solche Plugins. Der Kern weiß
  nichts über Quizze, er fragt ein Modul über `[modname]_supports()` nur nach
  seinen Fähigkeiten: Liefert es eine Bewertung, verfolgt es Abschluss, lässt
  es sich sichern.
- **Open edX** kennt XBlocks mit einem noch klareren Vertrag: `student_view`
  für Lernende, `studio_view` für den Autoreneditor, `author_view` für die
  Vorschau, `has_score` für die Frage, ob die Komponente bewertet wird. Studio
  ist dabei nur eine zweite Laufzeitumgebung für dieselben Bausteine.

Der Befund im eigenen Bestand: **Lektion, Node und Prüfung sind heute drei
Sonderfälle.** Drei Tabellen (`lessons`, `nodes`, mit Prüfungen nur als
Dateien plus `exam_attempts`), drei Controller, drei Content-Formate, drei
Fortschrittstabellen (`lesson_progress`, `node_attempts`, `exam_attempts`).
`ProfileService`, das Skill-Radar und `AchievementService` rechnen deshalb
gegen drei Quellen statt gegen eine. ADR 0070 beschreibt dasselbe Muster
bereits für die Abzeichen und hat es auf der Leseseite notdürftig
zusammengeführt.

Die Spielwiese ist heute gar keine eigene Sache, sondern hängt an einer
Lektion (`POST lessons/{lesson}/sandbox`). Sie lässt sich nirgends sonst
platzieren.

Wenn die Autorenschicht aus ADR 0071 vor dieser Entscheidung gebaut wird,
entstehen vier Editoren als vier weitere Sonderfälle, und ein fünfter
Inhaltstyp kostet später einen Umbau statt eines Moduls.

## Entscheidung

**Es gibt genau einen Plugintyp: die Aktivität.** Lektion, Quiz, Prüfung, Node
und Spielwiese sind Aktivitätstypen über einem gemeinsamen Kern. Neue
Inhaltsarten sind neue Module, keine neuen Sonderfälle.

Der Vertrag umfasst sechs Fähigkeiten:

| Fähigkeit | Wofür |
|---|---|
| Lernendenansicht | Props für die Inertia-Seite |
| Autorenansicht | Feldschema für den Editor aus ADR 0071 |
| Validierung | eigene Regeln, beigesteuert an den `ContentValidator` |
| Serialisierung | Pfad und Inhalt der erzeugten Dateien unter `content/` |
| Deserialisierung | Gegenstück für den Importer |
| Ergebnis | Abschluss, optionale Punktzahl, belegte Skills |

Zusätzlich deklariert jedes Modul seine Merkmale, nach dem Vorbild von
Moodles `supports()`: wird bewertet, verfolgt Abschluss, braucht einen
Container, ist im Editor anlegbar, ist frei platzierbar.

### Fortschritt gemeinsam, Versuchszustand typspezifisch

Bewusst **nicht** alles in eine Tabelle. `node_attempts` trägt
`engine_session_id` und `hints_used`, `exam_attempts` trägt gezogene
Fragen-IDs, Position und Antworten. Das ist laufender Versuchszustand und
gehört dem jeweiligen Modul.

Gemeinsam wird nur das dauerhafte Ergebnis: eine Tabelle `activity_progress`
mit Abschluss, Punktzahl, Höchstpunktzahl, Zeitpunkt. Sie ist die einzige
Quelle für Punkte, Rang, Skill-Radar und Achievement-Auslöser.

### Was der Kern ausdrücklich nicht bekommt

Das Wort „lightweight" entscheidet die Zweifelsfälle:

- **Ein Plugintyp, nicht vierzig.** Moodle hat rund vierzig Plugintypen, vom
  Virenscanner bis zum Portfolio-Anbieter. Das ist zwanzig Jahre
  Institutionsgeschäft und hier nicht nachzubauen.
- **Kein Notenbuch.** Eine Aktivität meldet bestanden und optional eine
  Punktzahl. Keine Gewichtungen, keine Kategorien, keine Notenskalen.
- **Keine Capability-Matrix.** Drei Rollen aus ADR 0071, Policies darauf.
- **Keine Kursformate, keine Foren, kein Chat, kein Kalender.**
- **Keine Fristen und keine Kohorten** in dieser Runde. Die Plattform ist
  selbstgesteuert, das ist Konzept-Abschnitt 4.

## Konsequenzen

- `lessons`, `nodes` und die Prüfungsdefinitionen werden zu `activities` mit
  `type`. Die typspezifischen Nutzdaten liegen als JSONB daneben, weil sie
  ohnehin nach `content/` serialisiert werden und nicht einzeln abgefragt
  werden müssen.
- `lesson_progress`, der Ergebnisteil von `node_attempts` und der
  Ergebnisteil von `exam_attempts` wandern nach `activity_progress`. Der
  Versuchszustand bleibt, wo er ist.
- `ProfileService` und `AchievementService` rechnen danach gegen eine Quelle.
  Die Zusammenführung der Abzeichen aus ADR 0070 wird dadurch einfacher, nicht
  schwerer, und sollte erst danach erfolgen.
- Die Spielwiese wird ein Aktivitätstyp ohne Bewertung und ist damit überall
  platzierbar, nicht nur an einer Lektion.
- Die Skill-Zuordnung wandert in den Vertrag. Moodle hängt Kompetenzen an
  einzelne Aktivitäten und belegt sie über Aktivitätsabschluss; das Skill-Radar
  fällt danach als Nebenprodukt an, statt separat gerechnet zu werden.
- Der Vertrag wird so geschnitten, dass ein Modul später ein Ergebnis nach
  außen melden kann, ohne dass der Kern sich ändert. Damit bleibt der Weg zu
  LTI und cmi5 offen, ohne dass jetzt etwas davon gebaut wird.

### Verhältnis zu ADR 0071

0071 bleibt unverändert gültig: Datenbank ist die Autoren-Wahrheit, `content/`
wird erzeugt, eine Richtung. Diese ADR sagt nur, **was** dort geschrieben und
gelesen wird, nämlich Aktivitäten statt dreier Sonderformate. Das
Content-Schema in `docs/content-schema.md` ändert sich dadurch nicht, es wird
künftig von den Modulen erzeugt.

## Bewusst offen gelassen

- **LTI 1.3 als Tool Provider.** LTI Advantage umfasst Deep Linking,
  Assignment and Grade Services und Names and Role Provisioning. Damit ließe
  sich DCM Lab in ein vorhandenes Klinik-LMS einhängen, statt es zu ersetzen.
  Strategisch der interessanteste Weg, aber kein Teil dieser Runde.
- **cmi5 als Meldeformat.** cmi5 ist ein xAPI-Profil mit standardisierten
  Verben und Abschlussregeln und gilt als der empfohlene Weg für neue Inhalte.
  Passt zum Ergebnisteil des Vertrags, kommt aber später.
- **SCORM.** Verworfen. Es passt zu verpacktem, statischem Inhalt und damit
  zum Gegenteil dessen, was diese Plattform ausmacht.

## Verifikation

- Ein neuer Aktivitätstyp lässt sich hinzufügen, ohne `ProfileService`,
  `AchievementService`, das Dashboard oder die Punkterechnung anzufassen.
- Punkte, Rang, Skill-Radar und öffentliches Profil sind nach der Migration
  für einen Bestandsnutzer unverändert.
- `silent-ct` bleibt spielbar, die Prüfung zieht unverändert
  abdeckungsbalanciert, die Wiederholungskarten verhalten sich gleich.
- Die Spielwiese lässt sich aus einer Lektion **und** aus einer anderen
  Aktivität heraus starten.
