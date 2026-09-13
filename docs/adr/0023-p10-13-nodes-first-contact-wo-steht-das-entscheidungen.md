# 0023 — P10.13: Nodes „First Contact" und „Wo steht das?" — Feature 8 (dcmftest + dcmdump-Felder)

Status: akzeptiert
Datum: 2026-09-13

## Kontext

`first-contact` (Lektion 1.1) und `wo-steht-das` (Lektion 1.3) lagen
seit P9 als Gerüst vor, beide blockiert durch denselben Engine-Fallback:
`dcmdump` auf eine lokal abgelegte Datei lieferte immer nur "keine
lokale Datei in dieser Simulation". P10.10/P10.12 haben `dcmdump`
bereits für einzelne Felder generalisiert (SOP Class, Transfer Syntax,
LossyImageCompression) — beide Nodes lassen sich jetzt mit derselben,
weiter ausgebauten Mechanik lösen, ohne ein grundlegend neues Konzept.

Beide Lektionen sind zudem **kein** Gerüst mehr: Lektion 1.1 hat bereits
einen eigenen Abschnitt **"Dein erstes Lab"**, der First Contact exakt
spezifiziert ("eine einzelne Datei ohne Endung und ohne Kontext …
Nachweisen, dass es sich um ein DICOM-Objekt handelt. Herausfinden, von
welchem Gerätetyp sie stammt. Und den Namen der Untersuchung nennen.
Du brauchst dafür genau die zwei Werkzeuge aus dieser Lektion" —
`dcmftest` und `dcmdump`). Lektion 1.3 spezifiziert **"Dein Lab"**
ebenso genau ("drei Fragen … welcher Rekonstruktionskern, welche
Schichtdicke, welches Aufnahmedatum — und keine Tag-Nummern dazu …
entscheiden, ob es sich um Standard- oder Herstellerangaben handelt").
Beide Nodes wurden nach diesen bereits im Content verankerten
Spezifikationen gebaut, nicht neu erfunden.

## Entscheidung — Feature 8: `dcmftest` + generalisierte `dcmdump`-Felder

**Neuer Befehl `dcmftest <datei>`:** reale Ausgabe `yes: <datei>` /
`no: <datei>` je nachdem, ob das Objekt in `environment.objects`
bekannt ist — in der Simulation gilt jedes deklarierte Objekt als
gültiges Format, unabhängig vom Dateinamen (real bedeutsam: die Node
gibt der Datei bewusst keine `.dcm`-Endung).

**`_exec_dcmdump` generalisiert auf eine Tabelle** (`DCMDUMP_FIELD_ORDER`)
statt einzelner `if`-Zweige: fünf weitere reale Felder —
`modality` (0008,0060), `study_description` (0008,1030),
`acquisition_date` (0008,0022), `slice_thickness` (0018,0050),
`convolution_kernel` (0018,1210) — alle per `pydicom`s Datenwörterbuch
verifiziert, alle in echter aufsteigender Tag-Reihenfolge ausgegeben.
Details: `content-schema.md` Abschnitt 6h.

**`first-contact`:** ein Objekt ohne `.dcm`-Endung, `modality: "US"`,
`study_description: "Abdomen komplett"`. Flag ist der
Untersuchungsname — genau die dritte, in Lektion 1.1 selbst benannte
Teilaufgabe.

**`wo-steht-das`:** ein Objekt mit `acquisition_date`,
`slice_thickness`, `convolution_kernel` — kein Query-Key, kein `+P`
(die Simulation kennt keine gezielte Tag-Abfrage; der volle `dcmdump`
zeigt alle drei Werte auf einmal). Die "Standard oder
Herstellerangabe?"-Frage aus der Lektion wird ehrlich beantwortet: alle
drei sind reale Standard-Tags (gerade Group-Nummer, öffentliches
DICOM-Wörterbuch) — auch `ConvolutionKernel`, dessen **Wert**
(`B60f`) herstellerspezifisch formatiert ist, während das **Tag**
selbst Standard ist. Kein erfundenes privates Gegenbeispiel-Tag, um
künstliche Ambiguität zu erzeugen — die reale Pointe ("klingt
herstellerspezifisch" ≠ "ist ein Herstellertag") ist bereits
lehrreich genug und bleibt faktentreu. Da das Schema nur einen
einzelnen `flag`-Wert pro Node erlaubt, ist das Flag `ConvolutionKernel`
(`B60f`) — die anderen beiden Werte werden im Write-up trotzdem
vollständig hergeleitet.

**`content/lessons/1.1/meta.yml` und `content/lessons/1.3/meta.yml`**:
Kommentare zum Gerüst-Status entfernt, `lab.optional` je auf `false` —
beide Nodes sind vollständig und lösen exakt die in den jeweiligen
Lektionstexten bereits spezifizierten Aufgaben.

## Manuell verifiziert

- `services/engine/tests/test_dcmftest.py` (neu, 4 Tests): `yes`/`no`
  für bekannte/unbekannte Dateien, `dcmftest` ist wie `dcmdump` nicht
  hinter `node.tools` gegated (reiner Lesebefehl).
- `services/engine/tests/test_dcmdump_ct_metadata.py` (neu, 1 Test):
  alle drei neuen CT-Metadatenfelder in echter aufsteigender
  Tag-Reihenfolge.
- `services/engine/tests/test_real_content.py`: zwei neue Tests laden
  die echten Nodes und lösen sie vollständig (inkl. Flag-Prüfung).
- Vollständige Engine-Testsuite (104 Tests), `ruff check .`: grün.
  `mypy app`: ein vorbestehender, unveränderter Fehler (fehlende
  `types-PyYAML`-Stubs).

## Nicht gebaut / offen (siehe `docs/content-todo.md`)

- `zwei-ebenen-tiefer` (1.2) bleibt offen — sie bräuchte zusätzlich
  eine echte PATIENT-/INSTANCE-Ebene im C-FIND und mehrere
  Studies/Serien pro Archiv, ein deutlich größerer Eingriff als die
  reinen `dcmdump`-Erweiterungen hier.
- `wo-steht-das`s Flag deckt nur eine der drei in der Lektion
  genannten Fragen ab (technische Schema-Grenze: ein Flag pro Node);
  die anderen beiden Werte sind im Write-up vollständig hergeleitet,
  aber nicht separat abprüfbar.
