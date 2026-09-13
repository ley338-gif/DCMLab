# 0016 — P10.6: Node `mitgehoert` — drei Verhandlungsebenen nacheinander

Status: akzeptiert
Datum: 2026-09-13

## Kontext

`mitgehoert` (Lektion 1.8, "Association, Presentation Context,
Negotiation") war seit P10.3 (Feature 3, ADR 0013) technisch lösbar,
hatte aber noch keinen Fließtext. Ihr eigentliches Lernziel laut Lektion
ist, ein Association-Log lesen und erklären zu können — nicht nur einen
einzelnen Fehler zu beheben.

## Entscheidungen

**Drei unabhängige Fehler nacheinander statt nur einer.** Damit "ein Log
lesen und erklären" tatsächlich geübt wird, statt nur ein einzelner
Fix wie bei `syntax-negotiation-fails` (P10.3), kombiniert diese Node
einen falschen Host, einen falschen Port und eine falsche Transfer
Syntax — bewusst **ohne** einen falschen AE Title (den behandeln bereits
Silent CT, Wrong Door und Neue Node). Jede Korrektur deckt eine neue,
komplett andere Fehlermeldung auf, nie eine graduelle Verbesserung
derselben — das zwingt dazu, jede Meldung neu einzuordnen statt Muster zu
erraten.

**Kein neuer Engine-Code.** Alle drei Fehlerklassen (Host/Port aus
Abschnitt 5.3 seit P4, Transfer Syntax aus Feature 3/P10.3) existieren
bereits. Diese Node ist ausschließlich eine neue Kombination bestehender,
unabhängig geprüfter Mechanik — derselbe Grundsatz wie bei Wrong Door
(P6) und `patient-merge-discovery` (P10.4).

**`protokoll-vorlage.txt` als Nachschlagehilfe, nicht als Lösung.** Die
Datei ordnet Fehlertexte den drei Verhandlungsebenen (TCP, Association,
Presentation Context) zu, nennt aber keine der drei konkreten
Korrekturwerte dieser Node — sie vermittelt das Konzept, nicht das
Ergebnis.

**Reale Transfer-Syntax-UIDs, diesmal Explicit VR Little Endian
(`1.2.840.10008.1.2.1`) statt Implicit VR wie in P10.3, und JPEG Baseline
(`1.2.840.10008.1.2.4.50`) als Werkseinstellung** — beide bereits in
dieser Phase gegen `pydicom.uid` verifiziert (siehe ADR 0013), hier
lediglich wiederverwendet, um eine andere Kombination als die bereits
existierende Node zu zeigen.

## Manuell verifiziert (gegen den echten Stack)

- Alle vier Stufen (Host unbekannt → Port falsch → Transfer Syntax
  abgelehnt → akzeptiert) liefern exakt die erwarteten, einzeln
  verschiedenen Log-Zeilen.
- Bestand im Archiv bleibt bei allen drei Fehlversuchen bei 0, wächst
  erst nach der dritten Korrektur.
- Flag `1.2.840.10008.1.2.1` korrekt — über die echte Session/API gegen
  den laufenden Stack geprüft, 20 Punkte vergeben.
- Lektion 1.8 zeigt korrekt „Lab: Node „Mitgehört" (medium, 20 Pkt.)".
- `content:validate`: keine neuen Verstöße. `pytest`/`ruff`/`mypy` für
  `services/engine`: 71 Tests grün (1 neuer Ende-zu-Ende-Test, keine neue
  Logik nötig).

## Nicht gebaut (bewusst)

`halbe-sache` (1.7) und `verbindung-ohne-bild` (4.2) bleiben Gerüst —
beide sind ebenfalls durch Feature 3 technisch lösbar, aber ein weiterer
Transfer-Syntax-Node in derselben Phase hätte das Muster nur wiederholt,
ohne zusätzlichen Lernwert. Bleiben für eine spätere Phase mit jeweils
eigenem, wirklich unterscheidbarem Szenario.
