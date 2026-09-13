# 0013 — P10.3: Transfer-Syntax-Aushandlung (Engine-Feature 3) + Node `syntax-negotiation-fails`

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Drittes von sieben Engine-Features aus `P10-Roadmap-DCMLab.md`: eine
Association soll zustande kommen können, während die eigentliche
Bildübertragung an einer nicht gemeinsam unterstützten Transfer Syntax
scheitert — unabhängig von Host, Port und AE-Title, die bereits stimmen
können. Wie bei ADR 0011/0012 gilt: die Roadmap ist extern geliefert,
kein Teil des Auftrags.

## Entscheidungen

**Neues, editierbares Konfigurationsfeld `transfer_syntax` statt neuer
CLI-Flags für `storescu`.** Die Roadmap deutet dcmtk-Kommandozeilenflags
an (`-xe`, Ähnliches), ohne sie eindeutig zu belegen — echte
DCMTK-Flags für Transfer-Syntax-Vorschläge sind uneinheitlich dokumentiert
und ließen sich nicht mit Sicherheit verifizieren. Statt eine nicht
verifizierte CLI-Syntax zu erfinden, nutzt dieses Feature den bereits
bewährten, generischen Mechanismus aus P4–P9: ein zusätzliches Feld im
`config`-Dict einer `modality-simulator`-Node wird automatisch editierbar
angezeigt (`set_config` und die Oberfläche kennen keine feste Feldliste)
— keine einzige Zeile Frontend-Code war für das neue Feld nötig.

**Prüfreihenfolge: Host/Port/AE-Title zuerst, Transfer Syntax danach.**
Entspricht der realen DICOM-Reihenfolge (erst kommt die Association
zustande, dann werden Presentation Contexts pro Abstract Syntax
verhandelt) und der bestehenden Priorität aus Abschnitt 5.3. Ein Node mit
sowohl falscher AE als auch falscher Transfer Syntax zeigt zuerst den
AE-Fehler — bewusst, damit der Lernende einen Fehler nach dem anderen
löst statt zwei Ursachen gleichzeitig zu suchen.

**Reale Transfer-Syntax-UIDs, verifiziert gegen pydicom, nicht aus dem
Gedächtnis übernommen.** Alle in Node und Tests verwendeten UIDs (u. a.
Implicit VR Little Endian `1.2.840.10008.1.2`, JPEG 2000
`1.2.840.10008.1.2.4.91`) wurden vor Verwendung gegen `pydicom.uid`
geprüft (PS3.5 Annex A, Transfer Syntax Registry) — dieselbe Quelle, die
`datasets/build/generate.py` seit P7 für echte Objekte nutzt.

**Realer Ablehnungsgrund `transfer-syntaxes-not-supported` (PS3.8 Table
9-18, Result-Wert 4), keine erfundene Fehlermeldung.** Die Meldung
"No Acceptable Presentation Contexts" ist als Text keine wörtliche
DCMTK-Ausgabe, die verifiziert werden konnte — der zugrundeliegende
Ablehnungsgrund (Result-Code 4 im Presentation-Context-Verhandlungsfeld)
ist dagegen ein realer, im Standard definierter Wert und wird als solcher
gekennzeichnet ausgegeben, konsistent mit dem bereits in P4 etablierten
Stil (`Called AE Title Not Recognized`, ebenfalls PS3.8).

**Node an die bereits existierenden Lektionen 1.7 und 1.8 gehängt.** Die
Roadmap nennt einen nicht existierenden "Track 2.1/2.2"-Bezug. Lektion
1.7 ("Transfer Syntax und Kompression") und 1.8 ("Association,
Presentation Context, Negotiation") behandeln beide Themen bereits als
veröffentlichter Track-1-Content — ein direkterer, bereits vorhandener
Bezug als jede Track-2-Referenz.

## Manuell verifiziert (gegen den echten Stack)

- Sendeversuch mit Werkseinstellung (JPEG 2000) liefert
  `F: No Acceptable Presentation Contexts` /
  `F:   Presentation Context Result: 4 (transfer-syntaxes-not-supported)`,
  Bestand bleibt bei 0.
- Nach Korrektur auf `1.2.840.10008.1.2` (Implicit VR Little Endian):
  Association akzeptiert, 1 von 1 Objekten übertragen, Bestand wächst.
- Das neue Konfigurationsfeld `transfer_syntax` erscheint automatisch im
  Konfigurationspanel der Oberfläche, ohne Frontend-Änderung.
- Flag `1.2.840.10008.1.2` korrekt — über die echte Session/API gegen den
  laufenden Stack geprüft, 15 Punkte vergeben.
- `content:validate`: keine neuen Verstöße. `pytest`/`ruff`/`mypy` für
  `services/engine`: 67 Tests grün (4 neu für die Transfer-Syntax-Logik,
  1 neuer Ende-zu-Ende-Test gegen den echten Node-Content).

## Nebeneffekt, noch nicht genutzt

`halbe-sache` (1.7), `mitgehoert` (1.8) und `verbindung-ohne-bild` (4.2)
waren blockiert, weil die Engine keine Presentation-Context-Aushandlung
kannte. Diese Blockade ist jetzt technisch aufgehoben — die drei bleiben
trotzdem als Gerüst stehen, bis ihr jeweiliger Fließtext geschrieben ist
(kein Content erfunden, nur weil die Engine es jetzt könnte). Siehe
`docs/content-todo.md`.

## Nicht gebaut (bewusst, diese Phase)

Multiframe-Generator, Worklist-Query, Patient-Merge/Study-Split — die
verbleibenden vier Engine-Features aus der Roadmap.
