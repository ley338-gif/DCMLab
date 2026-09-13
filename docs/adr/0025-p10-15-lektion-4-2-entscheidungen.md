# 0025 — P10.15: Lektion 4.2 — echte Sandbox-Beispiele plus Node-Verweis

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Lektion 4.2 ("Verbindung steht, aber nichts kommt an") lag seit P9 als
Gerüst vor, mit `verbindung-ohne-bild` (P10.9) bereits als vollständige,
live verifizierte Lab-Node verknüpft. Wie bei Lektion 4.1 (ADR 0017)
war vorab zu prüfen, ob sich das Kernthema der Lektion — eine
Presentation-Context-Ablehnung — live in der echten Spielwiese
reproduzieren lässt, statt das anzunehmen.

## Entscheidung — ein zweiter Beleg für dieselbe Infrastrukturgrenze

**Vor dem Schreiben wurde das tatsächlich gegen einen echten
Orthanc+Toolbox-Sitzungspaar geprüft.** Ergebnis: Das aktuelle
`orthancteam/orthanc`-Image (`containers/orthanc/Dockerfile`) nimmt
jede getestete Kombination aus SOP Class und Transfer Syntax an — auch
verlustbehaftet komprimierte Objekte (JPEG Lossless,
`1.2.840.10008.1.2.4.70`) wurden anstandslos akzeptiert. Damit gilt für
die Presentation-Context-Aushandlung dieselbe Großzügigkeit, die ADR
0017 für Called/Calling AE Titles bereits dokumentiert hat — ein
zweiter, unabhängiger Beleg für dieselbe Infrastrukturgrenze der
Spielwiese, nicht nur eine Wiederholung der Vermutung.

**Ein echter, bisher ungenutzter Kniff hat trotzdem ein starkes reales
Beispiel ermöglicht:** `storescu -cx` (pynetdicom-CLI, die als
`storescu`/`echoscu`/`findscu` im Toolbox-Image installiert ist und
DCMTKs Log-Stil bewusst nachbildet) beschränkt die vorgeschlagenen
Presentation Contexts auf genau den Objekttyp der zu sendenden Datei,
statt pauschal alle bekannten SOP Classes anzubieten. Kombiniert mit
`-d` zeigt das einen sauberen, einzeiligen Vergleich zwischen Proposed
und Accepted Transfer Syntax für genau einen Context — exakt das von
der Lektion selbst geplante Beispiel ("storescu -d: Proposed und
Accepted nebeneinander"), ohne die hunderte Zeilen Rauschen, die
`storescu` ohne `-cx` erzeugt (die Datei wird sonst für dutzende
irrelevante SOP Classes gleichzeitig angeboten).

**Lösung, konsistent mit ADR 0017:** reale Beispiele für das, was die
Spielwiese wirklich zeigen kann (erfolgreiche Aushandlung via
`storescu -d -cx`, `dcmdump` der Transfer-Syntax-/SOP-Class-Felder),
ehrlicher Verweis auf die Node für die eigentliche Ablehnung. Der
Sandbox-Block ist als `<!-- kein-beispiel -->` markiert, nennt konkret,
was getestet wurde (beide Transfer-Syntax-Varianten), und verweist auf
Node "Verbindung ohne Bild" als den Ort, an dem beide realen
PS3.8-Table-9-18-Ablehnungsgründe (Result 3
"abstract-syntax-not-supported", Result 4
"transfer-syntaxes-not-supported" — beide bereits in P10.9/P10.3
verifiziert) tatsächlich durchspielbar sind.

## Manuell verifiziert (gegen den echten Stack)

- Reales synthetisches CT-Objekt erzeugt (`datasets/build/generate.py`),
  `echoscu` erfolgreich, `storescu -d -cx` mit sauberem
  Proposed/Accepted-Vergleich für CT Image Storage + Explicit VR
  Little Endian — wörtlich übernommen.
- `dcmdump +P TransferSyntaxUID +P SOPClassUID` — wörtlich übernommen.
- Getestet, dass Orthanc sowohl unkomprimiertes Explicit VR Little
  Endian als auch JPEG Lossless (`1.2.840.10008.1.2.4.70`) anstandslos
  akzeptiert — keine Presentation-Context-Ablehnung reproduzierbar.
- Lektion 4.2 im Browser: Lab-Verweis zeigt korrekt „Node „Verbindung
  ohne Bild" (medium, 15 Pkt.)", vollständiger Fließtext rendert
  fehlerfrei inkl. Tabellen und Stolperfallen.
- `content:validate`: keine neuen Verstöße (weiterhin 19
  vorbestehende); Glossarbegriffe `presentation-context`,
  `transfer-syntax`, `abstract-syntax` existierten bereits.
- Pest/Pint/PHPStan: nicht lokal ausführbar (bekanntes
  PHP-Versionsproblem, siehe `docs/content-todo.md`); CI ist hier
  maßgeblich.

## Nicht gebaut / offen (siehe `docs/content-todo.md`)

- Kein `tshark`-Mitschnitt-Beispiel, obwohl `meta.yml` das Werkzeug
  deklariert (wie schon bei Lektion 4.1, ADR 0017) — ein echter
  Paketmitschnitt bleibt ein eigener, größerer Verifikationsschritt.
- Kein `storescp`-Beispiel (ebenfalls in `meta.yml` deklariert, aber
  für diese Lektion nicht zwingend nötig — sie behandelt die
  SCU-seitige Diagnose, nicht den Aufbau eines eigenen Empfängers).
