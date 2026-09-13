# 0018 — P10.8: Lektion 4.6 — echte Sandbox-Belege statt Node-Reskin

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Lektion 4.6 ("Falscher Patient") lag seit P9 nur als Gerüst vor, mit
`lab.node: patient-merge-discovery` bereits verknüpft (P10.4 lieferte
diese Node). Anders als Lektion 4.1 (ADR 0017) betrifft das Thema
dieser Lektion — dieselbe Person unter zwei Patient IDs registriert —
ausschließlich Dateninhalte, nicht AE-Title-Durchsetzung. Es war
deshalb vorab zu prüfen, ob sich das (anders als bei 4.1) tatsächlich
live in der echten Spielwiese reproduzieren lässt, statt das
anzunehmen.

## Entscheidung

**Vor dem Schreiben wurde der komplette Ablauf live gegen einen echten
Orthanc+Toolbox-Sitzungspaar erzeugt** (dieselben Images wie in P7/P10.7,
isoliertes `--internal`-Netz, danach vollständig entsorgt):

1. Zwei echte, synthetische DICOM-Dateien via `datasets/build/generate.py`
   erzeugt — Patientin „SCHMIDT^ANNA", einmal mit `--patient-id 00456`,
   einmal mit `--patient-id 000456`, dieselbe Study/Series-Beschreibung.
2. Beide via echtem `storescu -v -aec ORTHANC 127.0.0.1 4242` gesendet.
3. `findscu` nach `PatientID=00456` — ein Treffer.
4. `findscu` nach `PatientName=SCHMIDT*` — zwei Treffer, zwei
   verschiedene Patient IDs, derselbe Name: das Kernbeleg-Bild der
   Lektion.
5. Korrektur-Workflow demonstriert: `cp` auf eine Kopie, `dcmodify -m
   "PatientID=000456"` nur auf der Kopie, `dcmdump` zeigt Kopie
   korrigiert / Original unverändert.

**Das Thema ist voll reproduzierbar** — anders als 4.1 gibt es hier
keine `DicomAlwaysAllow*`-Blockade, weil nichts an der
Verbindungsebene geprüft wird. Die Lektion enthält deshalb
ausschließlich echte, im Sandbox-Lauf erzeugte Transkripte, keinen
einzigen `<!-- kein-beispiel -->`-Block.

**`0xB000` ("Warning: Coercion of Data Elements") wird als Konzept
erklärt, nicht live erzeugt.** Die drei echten Sandbox-Befehle oben
zeigen zwei getrennte, korrekt gespeicherte Registrierungen — sie lösen
keine Coercion durch Orthanc aus (dafür müsste ein bereits im Archiv
vorhandener Patient mit widersprüchlichen Daten überschrieben werden,
was einen eigenen, aufwendigeren Testaufbau bräuchte). Der Status
selbst ist ein realer, in PS3.7 definierter Code und wird als solcher
zitiert, nicht als beobachtetes Sandbox-Ergebnis dargestellt.

**Neuer Glossarbegriff `coercion`** (`content/glossary/de.yml`) —
bisher nicht definiert, obwohl die Lektion den Begriff braucht. Der
Text stützt sich ausschließlich auf den realen Statuscode 0xB000.

**Kein neues Engine-Feature nötig** — dieselbe Konsistenz-Linie wie
P10.4/P10.5 (ADR 0014, 0015): Die Lektion nutzt nur reale
Sandbox-Werkzeuge, keine simulierte Node-Umgebung.

**`lab.optional` bleibt `true`.** Wie in ADR 0014 vermerkt, deckt die
Node den Doppelanlage-Fall vollständig ab, nicht aber den aktiven
Merge/Move-Vorgang selbst — die Lektion bleibt eigenständig lehrreich
auch ohne die Node abzuschließen.

## Manuell verifiziert (gegen den echten Stack)

- Alle Sandbox-Transkripte (zwei `storescu`-Sends, `findscu` nach ID,
  `findscu` nach Namens-Wildcard, `dcmodify`-auf-Kopie-Workflow) live
  gegen einen frisch gebauten Orthanc+Toolbox erzeugt, wörtlich
  übernommen; danach Container, Netz und Images vollständig entfernt.
- `content:build`: 0 neue Hashes nötig (keine Node geändert),
  bestehende Warnungen zu anderen Nodes unverändert.
- `content:validate`: weiterhin 19 Verstöße, keiner davon in
  `lessons/4.6` oder `glossary/de.yml` — keine neuen Verstöße.
- Lektion 4.6 im Browser (isolierter `infra-p10-8`-Stack, frisch
  registrierter Nutzer): Werkzeugleiste, Vorher/Danach-Verweise,
  Lab-Verweis auf „Der Patient existiert zweimal" (medium, 18 Pkt.)
  und das Glossar-Tooltip für „Coercion" rendern korrekt; Node-Seite
  `patient-merge-discovery` lädt unter derselben Session fehlerfrei.
- Pest/Pint/PHPStan lokal nicht ausführbar (Composer-Lock verlangt
  PHP ≥ 8.4.1, lokal `8.4.0` installiert — ein Umgebungsproblem,
  bereits im unveränderten `main`-Checkout vorhanden, nicht durch
  diese Änderung verursacht). CI (`shivammathur/setup-php@v2`, aktuelle
  8.4-Patch-Version) ist hier die maßgebliche Prüfung.

## Nicht gebaut / offen (siehe `docs/content-todo.md`)

- Aktive Coercion durch das Archiv (Status `0xB000`) selbst live zu
  erzeugen, bräuchte einen Testaufbau mit bereits abweichenden
  Bestandsdaten — als möglicher, deutlich aufwendigerer Folgeschritt
  vermerkt, aber nicht Voraussetzung für diese Lektion.
- Ein tatsächlicher Merge/Move-Vorgang (serverseitig) ist in keinem
  Teil des Systems modelliert — die Lektion beschreibt ihn konzeptionell.
