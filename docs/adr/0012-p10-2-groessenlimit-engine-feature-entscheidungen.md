# 0012 — P10.2: Größenlimit beim C-STORE (Engine-Feature 2) + Node `oversized-image`

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Zweites von sieben Engine-Features aus `P10-Roadmap-DCMLab.md`: ein
Archiv soll ein zu großes Objekt beim C-STORE ablehnen können, während
kleinere Objekte durchkommen. Wie schon bei ADR 0011 gilt: die Roadmap
ist extern geliefert, kein Teil des Auftrags — wo sie unbelegte Fakten
verlangt, gilt weiterhin Abschnitt 13.

## Entscheidungen

**`storescu` direkt von einer Shell aus möglich, statt nur über die
Modalitäts-Konfiguration.** Bisher (P4–P9) lebten alle Sendeobjekte auf
einem `modality-simulator`-Host mit Config-Panel und Sende-Knopf, und
`storescu` von einer Shell schlug immer strukturell fehl ("Bilder liegen
auf der Modalität"). Für dieses Feature ist das falsch: der Lernende
soll ein Objekt gezielt per Kommandozeile senden können, um seine Größe
vorher nachzuschauen (`ls`) und das Ergebnis direkt zu sehen. Beide Wege
bleiben nebeneinander bestehen — ein Host ohne `environment.objects`
verhält sich exakt wie zuvor (siehe `test_nodes_without_objects_keep_the
_pre_p10_behaviour`).

**Realer DIMSE-Statuscode `0xA7xx` ("Refused: Out of Resources", PS3.7
Annex C), nicht der in der Roadmap genannte Status 122.** Die Roadmap
behauptet "Status ist 122, das ist 'Instance Not Stored'" — das ist kein
in PS3.7 definierter DICOM-Statuscode für C-STORE-Ablehnungen. Da es
keinen standardisierten, größenlimit-spezifischen Code gibt (das ist in
der Praxis herstellerabhängig), fällt die Wahl auf den generischen,
tatsächlich existierenden Code für Ressourcen-Erschöpfung — semantisch
passend ("das Archiv kann/will dieses Objekt aus Kapazitätsgründen nicht
annehmen") und ehrlich als Näherung gekennzeichnet, nicht als exakter
Herstellertext ausgegeben.

**Größe der Testobjekte ist real berechnet, nicht gewürfelt.** 512 × 512
Pixel × 16 Bit = 524.288 Byte reine Pixeldaten für die kleine, klassische
Schicht (dieselbe Bildgröße, die `datasets/build/generate.py` seit P7
tatsächlich erzeugt). Für "ein volles CT-Volumen" (Roadmap-Formulierung)
wird dieselbe Schicht 150-fach als ein Enhanced-Multiframe-Objekt gebündelt:
150 × 524.288 Byte = 78.643.200 Byte (≈ 75 MiB) — nahe an der von der
Roadmap genannten Hausnummer "78 MB", aber aus einer echten Rechnung
hergeleitet statt übernommen.

**`ls` zeigt echte Dateigrößen (`ls -la`-Stil).** Damit der Lernende die
Größe vor dem Sendeversuch nachschauen kann, ohne dass die Engine eine
neue, nicht-realistische Ausgabeform erfindet — `ls -la` mit
rechtsbündiger Byteangabe ist Standard-Unix-Verhalten.

**Flag ist die Größe des tatsächlich angekommenen Objekts, nicht des
abgelehnten.** Entspricht der Roadmap-Vorgabe ("Größe des empfangenen
Bildes in Bytes") und ist über `ls` bereits vor dem ersten Sendeversuch
ablesbar — die Node prüft Verständnis, nicht Rechercheaufwand.

**Node an Lektion 4.3 gehängt, nicht an eine nicht existierende
Track-2-Lektion.** Die Roadmap nennt "Track 2.2" als Bezug, aber Track 2
existiert noch nicht (eigene, spätere P10-Etappe). Lektion 4.3
("Nur manche Bilder kommen an") existiert bereits seit P9 als Gerüst und
nennt exakt dieses Thema als einen von zwei Gründen (SOP-Class-Ablehnung
ist der andere, weiterhin ungebaute). `lab.node` zeigt jetzt auf
`oversized-image`, bleibt aber `optional: true`, weil die Lektion damit
nur zur Hälfte abgedeckt ist.

## Manuell verifiziert (gegen den echten Stack)

- `ls` zeigt `524288  klein.dcm` und `78643200  gross.dcm` vor jedem
  Sendeversuch.
- `storescu ... gross.dcm` liefert `F: Store Failed, file: gross.dcm` /
  `F:   Status: 0xa700 (Refused: Out of Resources)`, Exit-Code 1, Bestand
  im Archiv bleibt bei 0.
- `storescu ... klein.dcm` liefert Exit-Code 0, Bestand wächst auf eine
  Study.
- Flag `524288` korrekt — über die echte Session/API gegen den laufenden
  Stack geprüft, 15 Punkte vergeben.
- `content:validate`: keine neuen Verstöße. `pytest`/`ruff`/`mypy` für
  `services/engine`: 62 Tests grün (7 neu für die Größenlimit-Logik, 1
  neuer Ende-zu-Ende-Test gegen den echten Node-Content).

## Nicht gebaut (bewusst, diese Phase)

Die SOP-Class-Ablehnung, der zweite in Lektion 4.3 genannte Grund, bleibt
offen — das ist ein eigenes, noch nicht begonnenes Feature (siehe
`docs/content-todo.md`).
