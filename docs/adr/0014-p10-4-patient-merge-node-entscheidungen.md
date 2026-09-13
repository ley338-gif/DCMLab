# 0014 — P10.4: Node `patient-merge-discovery` — keine neue Engine-Logik nötig

Status: akzeptiert
Datum: 2026-09-13

## Kontext

`P10-Roadmap-DCMLab.md` listet "Patient-Merge / Study-Split" als eigenes,
noch zu bauendes Engine-Feature (Abschnitt III.3.6) für die Node
`patient-merge-discovery` (Abschnitt IV, Node 4). Vor der Umsetzung wurde
geprüft, ob das wirklich neuen Engine-Code braucht.

## Entscheidung

**Kein neuer Engine-Code — der `records`-Mechanismus aus Feature 1
(ADR 0011) deckt das Szenario bereits vollständig ab.** Ein Archiv-Host
kann seit P10.1 beliebig viele vordefinierte Studies (`records`) tragen,
gegen die `findscu` mit echten DICOM-Wildcards matcht. "Ein Patient ist
zweimal angelegt, nur eine der beiden Registrierungen trägt eine echte
Study" ist strukturell nichts anderes als zwei `records`-Einträge mit
unterschiedlicher `patient_id`, aber gleichem `patient_name` — dieselbe
Mechanik, die schon `c-find-mismatch` (P10.1) nutzt, nur mit zwei
Datensätzen statt einem. Ein zusätzliches, roadmap-vorgeschlagenes
`PatientIDCoercion`/`StudySplit`-Modul hätte hier keine neue Fähigkeit
eingeführt, nur bestehende Logik verdoppelt.

Das ist dieselbe Disziplin wie bei Wrong Door (P6, "ausschließlich aus
dem Schema erzeugt") und bei `zwei-ebenen-tiefer`/`zwillinge` (in
`docs/content-todo.md` als durch Feature 1 bereits technisch lösbar
vermerkt) — wo eine bestehende, generische Fähigkeit ausreicht, wird sie
wiederverwendet statt eine zweite, redundante Fähigkeit zu bauen.

**Suchweg über `PatientName`, nicht über eine geratene zweite
`PatientID`.** Die Node zwingt zur Erkenntnis, dass eine ID, die
existiert, nicht automatisch vollständig ist — und dass der Name (der
beim Doppel-Anlegen typischerweise gleich bleibt) der zuverlässigere
Suchweg ist, wenn eine ID ins Leere läuft. Realistischere Variante des
bereits in `c-find-mismatch` geübten "Wildcard deckt den echten Wert auf".

**Flag ist die korrekte Patient ID, wie von der Roadmap für diese Node
vorgegeben** ("Finde heraus, unter welcher Patient ID das Bild wirklich
ist") — hier `000123`, die einzige der beiden IDs mit einer echten Study.

## Manuell verifiziert (gegen den echten Stack)

- Suche mit der (korrekten, aber unvollständig registrierten) Patient ID
  aus der Überweisung liefert einen Treffer ohne Study-Angaben.
- Suche über `PatientName=WEBER*` liefert beide Registrierungen (2
  Treffer) — nur eine davon mit `StudyInstanceUID`/`StudyDescription`.
- Flag `000123` korrekt, `00123` (die aus der Überweisung) falsch — über
  die echte Session/API gegen den laufenden Stack geprüft, 18 Punkte
  vergeben.
- `content:validate`: keine neuen Verstöße. `pytest`/`ruff`/`mypy` für
  `services/engine`: 68 Tests grün (1 neuer Ende-zu-Ende-Test gegen den
  echten Node-Content, keine neuen Unit-Tests nötig, da keine neue Logik
  entstand).

## Nicht gebaut (bewusst)

Ein aktiver "Merge"-Vorgang (ein Archiv, das zwei Patient-Datensätze
serverseitig zu einem verschmilzt, wie es die Roadmap mit
`PatientIDCoercion.merge()` andeutet) ist eine andere, deutlich größere
Fähigkeit — sie würde eine schreibende Admin-Operation simulieren, die
bisher nirgends in der Engine existiert (P4–P10 sind rein lesend/prüfend
aus Lernendensicht). Das bleibt offen, falls ein künftiger Node genau das
lehren soll.
