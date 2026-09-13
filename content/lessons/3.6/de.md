---
title: "Specific Character Set — Umlaute und was schiefgeht"
teaser: '"Müller" wird zu "MÃ¼ller" — nicht weil DICOM keine Umlaute kann, sondern weil ein Attribut vergessen wurde, das die Kodierung erst festlegt.'
objectives:
  - SpecificCharacterSet als Attribut erklären, das die Kodierung aller Textwerte im Objekt festlegt
  - Ein Encoding-Problem von einem echten Dateninhaltsfehler unterscheiden
  - Kaputt dargestellte Umlaute auf ein fehlendes oder falsches SpecificCharacterSet zurückführen
---

## Status dieser Lektion

Gerüst (`status: draft`). Metadaten, Lernziele und die geplante Gliederung
stehen — Fließtext, Beispiele und Lab fehlen noch.

Der Fachtext wird bewusst nicht vorab erfunden: Abschnitt 13 des Auftrags
verbietet erfundene Prosa und erfundene Werkzeugausgaben. Jede Ausgabe in
dieser Lektion muss in der Spielwiese erzeugt und wörtlich übernommen werden.

## Geplante Gliederung

<!-- kein-beispiel -->
```
Aufhänger      Ein Patientenname mit Umlaut kommt im Archiv als
               Zeichensalat an, obwohl die Modalität ihn korrekt
               angezeigt hat
Erklärung      SpecificCharacterSet (0008,0005) legt fest, wie alle
               Textwerte im Objekt zu dekodieren sind -- Default ohne
               dieses Attribut ist reines ASCII (ISO_IR 6), Umlaute
               brauchen z. B. ISO_IR 100 (Latin-1) oder ISO_IR 192 (UTF-8)
Beispiele      in der Spielwiese erzeugen, wörtlich übernehmen:
                 - dcmdump eines echten Objekts mit gesetztem
                   SpecificCharacterSet und korrekt kodiertem Namen
                 - denselben Namen ohne oder mit falschem
                   SpecificCharacterSet erzeugen und die reale,
                   falsch dekodierte Ausgabe zeigen
Im Alltag      Woran man ein Encoding-Problem von einer wirklich
               falschen Eingabe unterscheidet
Stolperfallen  Zeichensalat für einen Tippfehler der MTRA halten, statt
               für ein fehlendes SpecificCharacterSet
Lab            Node fehlt noch -- "Kaputte Umlaute diagnostizieren"
Selbstcheck    3 Fragen
```

## Was zum Schreiben noch fehlt

- Alle Beispiele müssen in der Spielwiese erzeugt und wörtlich übernommen werden.
- Zu prüfen: `ct-thorax-60`s Patientenname (`MUSTER^ERIKA`) enthält
  keine Umlaute — für diese Lektion wird wahrscheinlich ein eigenes,
  kleines Testobjekt mit echtem Umlautnamen gebraucht (mit `pydicom`
  synthetisch erzeugbar, vermutlich ohne neuen Datensatz-Slug lösbar,
  aber noch nicht verifiziert).

## Selbstcheck

*Folgt mit dem Fließtext.*
