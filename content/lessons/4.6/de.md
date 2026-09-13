---
title: „Falscher Patient"
teaser: Das einzige Fehlerbild in diesem Track, bei dem die falsche Korrektur schlimmer ist als der Fehler selbst.
objectives:
  - Coercion durch das Archiv als Ursache erkennen, statt die Modalität zu verdächtigen
  - Die Konsequenzen einer Korrektur vor der Korrektur benennen
  - Merge und Move als verschiedene Vorgänge auseinanderhalten
---

## Zwei Registrierungen, eine Person

Ein Befund verweist auf eine Study, die unter der auf der Anforderung
notierten Patient ID nicht (oder nur unvollständig) zu finden ist — die
Person existiert im Archiv aber unter einer zweiten ID. Das ist keine
Netzwerk- oder Verbindungsstörung wie in Lektion 4.1, sondern ein Problem
im Datenbestand selbst.

```
$ findscu -S -k QueryRetrieveLevel=STUDY -k PatientID=00456 \
          -k StudyInstanceUID -k StudyDescription \
          -aec ORTHANC 127.0.0.1 4242
I: Find SCP Response: 1 - 0xFF00 (Pending)
I: (0008,1030) LO [CT Kopf nativ]                          # 1 StudyDescription
I: (0010,0020) LO [00456]                                  # 1 PatientID
I: (0020,000D) UI [1.2.826.0.1.3680043.8.498.676277684...]  # 1 StudyInstanceUID
I: Find SCP Result: 0x0000 (Success)
```
**Was du daran abliest:** Genau eine Study unter dieser ID — nichts
falsch daran, aber das sagt nichts darüber, ob es noch eine zweite
Registrierung derselben Person gibt.

```
$ findscu -S -k QueryRetrieveLevel=STUDY -k PatientName=SCHMIDT* \
          -k PatientID -k StudyInstanceUID -k StudyDescription \
          -aec ORTHANC 127.0.0.1 4242
I: Find SCP Response: 1 - 0xFF00 (Pending)
I: (0010,0010) PN [SCHMIDT^ANNA]                           # 1 PatientName
I: (0010,0020) LO [000456]                                 # 1 PatientID
I: Find SCP Response: 2 - 0xFF00 (Pending)
I: (0010,0010) PN [SCHMIDT^ANNA]                           # 1 PatientName
I: (0010,0020) LO [00456]                                  # 1 PatientID
I: Find SCP Result: 0x0000 (Success)
```
**Was du daran abliest:** Zwei Treffer, derselbe Name, zwei Patient IDs
(`000456` und `00456`) — dieselbe Person ist zweimal angelegt.

## Coercion: wenn nicht die Modalität schuld ist

Ein naheliegender erster Verdacht ist ein Tippfehler an der Modalität —
aber ein Archiv darf ankommende Patientendaten aktiv gegen seinen
eigenen Bestand abgleichen und dabei Werte überschreiben
({{term:coercion}}). DICOM sieht dafür einen eigenen, expliziten
Warnstatus im C-STORE-Antwortcode vor: `0xB000`, *"Warning: Coercion of
Data Elements"* — das Objekt wurde angenommen, aber nicht unverändert
übernommen. Wer nur die Modalität verdächtigt, obwohl das Archiv selbst
umgeschrieben hat, sucht am falschen Ende.

## Erst verstehen, dann korrigieren — nie an der Quelle

Bevor überhaupt etwas geändert wird: eine Korrektur an der falschen
Stelle vermehrt das Problem, statt es zu lösen. Deshalb läuft jeder
Korrekturversuch zuerst an einer Kopie, nie an der Originaldatei:

```
$ cp instance-0001.dcm instance-0001-KOPIE.dcm
$ dcmodify -m "PatientID=000456" instance-0001-KOPIE.dcm
$ dcmdump +P PatientName +P PatientID instance-0001-KOPIE.dcm
(0010,0010) PN [SCHMIDT^ANNA]                           #  12, 1 PatientName
(0010,0020) LO [000456]                                 #   6, 1 PatientID
```
**Was du daran abliest:** Die Kopie trägt jetzt die korrigierte ID — die
Originaldatei ist unverändert (`dcmdump` auf `instance-0001.dcm` zeigt
weiterhin `00456`). Erst wenn die Korrektur an der Kopie nachweislich
richtig ist, wird sie an der eigentlichen Quelle nachvollzogen.

**Was eine lokale Korrektur nicht erreicht:** Ist die Study inzwischen an
einen Viewer, einen Befund-Arbeitsplatz oder ein zweites Archiv verteilt
worden, tragen diese Kopien weiterhin die falsche ID — eine Korrektur an
einer Stelle korrigiert nicht automatisch alle anderen.

## Merge und Move sind nicht dasselbe

Zwei Vorgänge, die in der Praxis oft synonym benutzt werden, obwohl sie
unterschiedliche Dinge tun:

- **Merge** — zwei komplette Patientenakten (alle ihre Studies) werden zu
  einer zusammengeführt. Passend, wenn dieselbe Person tatsächlich
  zweimal komplett angelegt wurde, wie im Beispiel oben.
- **Move** — eine einzelne Study wird von einer Patientenakte in eine
  andere verschoben. Passend, wenn nur eine Untersuchung am falschen
  Patienten hängt, der Rest der jeweiligen Akte aber korrekt ist.

Ein Merge auf einen Fall angewendet, der eigentlich nur ein Move
gebraucht hätte, vermischt unbeteiligte Studies zweier verschiedener
Menschen — das ist der Kern des ersten Lernziels dieser Lektion: erst die
Diagnose, dann das passende Werkzeug.

## Im Alltag

| Schritt | Frage |
|---|---|
| 1. Suchen | Existiert dieselbe Person unter mehr als einer ID? (Name-Suche, nicht ID-Suche) |
| 2. Einordnen | Betrifft es die ganze Akte (Merge) oder nur eine Study (Move)? |
| 3. Testen | Korrektur zuerst an einer Kopie, Ergebnis mit `dcmdump` gegenprüfen |
| 4. Verteilung prüfen | Wohin wurde die Study bereits kopiert — und muss dort ebenfalls korrigiert werden? |
| 5. Erst dann | An der Quelle nachvollziehen |

## Stolperfallen

- **Die Modalität verdächtigen, obwohl das Archiv coerced hat.** Der
  Fehler kann auf der Empfängerseite entstehen, nicht nur beim Absender.
- **Direkt am Original arbeiten.** Jeder Korrekturversuch beginnt an
  einer Kopie — ausnahmslos.
- **Merge und Move verwechseln.** Ein Merge ist nicht rückgängig zu
  machen, ohne erneut Aufwand zu betreiben — die Diagnose muss vor dem
  Werkzeug stehen.

## Selbstcheck

1. Eine Study findet sich nicht unter der erwarteten Patient ID, aber
   eine Namenssuche liefert zwei Treffer. Was ist der nächste Schritt —
   und was nicht?
2. Ein C-STORE liefert Status `0xB000` zurück. Ist das ein Fehler? Was
   bedeutet er?
3. Wann brauchst du ein Merge, wann reicht ein Move?

*Wissenskarten (spaced repetition) folgen, sobald `meta.yml` ein
strukturiertes `quiz`-Feld mit Antwortschlüssel bekommt — siehe
`docs/content-todo.md`, Abschnitt P3.*

Werkzeuglage geprüft am: 2026-09-13
