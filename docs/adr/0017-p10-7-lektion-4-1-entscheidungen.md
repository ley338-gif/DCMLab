# 0017 — P10.7: Lektion 4.1 — echte Sandbox-Beispiele statt Node-Fiktion

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Lektion 4.1 ("Association rejected") lag seit P9 nur als Gerüst vor.
Silent CT, Wrong Door und Neue Node decken ihr Thema (Called/Calling
AE Title, Adress-Trio) bereits vollständig und live verifiziert ab —
naheliegend wäre gewesen, deren Write-ups direkt in die Lektion zu
kopieren. `content-schema.md` Abschnitt 8 verbietet das aber
ausdrücklich: **"Die beiden Welten werden nicht vermischt: Lektionen
üben in der Spielwiese, Nodes spielen im fiktiven Klinikum."**
Lektionsbeispiele müssen also real gegen die Spielwiese (Orthanc,
AE `ORTHANC`, `127.0.0.1:4242`) laufen, nicht gegen die simulierte
Node-Umgebung.

## Entscheidung — und ein echter Infrastruktur-Fund

**Vor dem Schreiben wurde das tatsächlich gegen einen echten
Orthanc+Toolbox-Sitzungspaar geprüft** (dieselben Images wie in P7),
nicht angenommen. Ergebnis, das die gesamte Lektion umgeworfen hat:

```
$ echoscu -v -aet BELIEBIGER-NAME -aec FALSCHER-NAME 127.0.0.1 4242
I: Requesting Association
I: Association Accepted
...
```

**Die Spielwiese nimmt jeden Called/Calling AE Title an.**
`containers/orthanc/orthanc.json` setzt `DicomAlwaysAllowEcho: true`
(und die verwandten `DicomAlwaysAllow*`-Optionen) — eine bewusste
P7-Entscheidung (ADR 0008), damit Lernende nicht an einer
Modalitätenliste scheitern. Das heißt aber auch: **Lektion 4.1s
Kernthema — genau die Fehlermeldung, um die sich die ganze Lektion
dreht — lässt sich in der aktuellen Spielwiese nicht live erzeugen.**

**Lösung: reale Beispiele für das, was die Spielwiese wirklich kann
(TCP-Ebene), ehrlicher Verweis auf die Nodes für den Rest.** Erfolgreicher
Echo, `Connection refused` (falscher Port) und `Network is unreachable`
(falscher Host) wurden alle live gegen die echte Spielwiese erzeugt und
wörtlich übernommen — inklusive der britischen Schreibweise
"Initialisation" und der genauen `Errno`-Formate, die DCMTK tatsächlich
ausgibt (nicht die vereinfachte Simulation aus `rules.py`). Für die
Called/Calling-AE-Ablehnung selbst: die Lektion nennt die realen,
PS3.8-definierten Reject-Reason-Texte als Konzept, markiert den
Sandbox-Block als `<!-- kein-beispiel -->` mit einer expliziten Erklärung
der Blockade, und verweist auf Silent CT / Wrong Door als den Ort, an dem
genau das erlebbar ist — die vom Schema selbst vorgesehene
Arbeitsteilung zwischen Lektion und Lab.

**`silent-ct/node.yml: related_lessons` bekommt `"4.1"` zurück.** Die
Referenz war seit P1 mit dem Kommentar "folgt, sobald Track 4
geschrieben ist" vermerkt — jetzt eingelöst. `lab.node` von Lektion 4.1
zeigt auf `silent-ct` (die am vollständigsten dokumentierte, zuerst
gebaute Node zu diesem Thema), `optional: false`, da die Node fertig ist.

## Manuell verifiziert (gegen den echten Stack)

- Alle drei Sandbox-Beispiele (Erfolg, falscher Port, falscher Host)
  live gegen einen frisch gebauten Orthanc+Toolbox erzeugt, wörtlich
  übernommen (siehe oben).
- Getestet, dass `DicomAlwaysAllowEcho` tatsächlich jeden AE Title
  akzeptiert (drei Varianten: falscher `-aec`, falscher `-aet`, beide
  gleichzeitig falsch — alle erfolgreich).
- Lektion 4.1 im Browser: Lab-Verweis zeigt korrekt „Node „Silent CT"
  (easy, 10 Pkt.)", Link funktioniert.
- `content:validate`: keine neuen Verstöße (Glossarbegriffe
  `called-ae-title`, `calling-ae-title`, `association` existierten
  bereits; `objectives_count: 3` stimmt mit den drei Frontmatter-Zielen).
- Pest, Pint, PHPStan Level 7: unverändert grün (keine PHP-Änderung
  in dieser Phase).

## Nicht gebaut / offen (siehe `docs/content-todo.md`)

- Kein `tshark`-Mitschnitt-Beispiel, obwohl `meta.yml` das Werkzeug
  deklariert (aus dem ursprünglichen Gerüst übernommen) — ein echter
  Paketmitschnitt einer abgelehnten Association wäre ein eigener,
  größerer Verifikationsschritt.
- Die grundsätzliche Frage, ob die Spielwiese künftig auch
  AE-Title-Ablehnungen demonstrieren soll (z. B. durch eine optionale,
  strengere Orthanc-Konfiguration), bleibt offen — sie betrifft nicht
  nur diese Lektion, sondern potenziell jede künftige Track-2-Lektion
  zu C-ECHO/C-STORE-Fehlkonfiguration.
