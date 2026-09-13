---
title: "Anonymisierung, Pseudonymisierung, Forschungsdaten"
teaser: "Ein geschwärzter Name reicht nicht — DICOM-Objekte tragen an über zwanzig Stellen Patientenbezug, oft ungefragt."
objectives:
  - "Kannst Anonymisierung von Pseudonymisierung unterscheiden (Rückführbarkeit als Kriterium)"
  - "Kannst mindestens drei DICOM-Attribute benennen, die über die offensichtlichen Patient-Tags hinaus Patientenbezug tragen können (z. B. eingebrannte Texte in Pixeldaten, private Tags, UIDs als indirekter Bezug)"
  - "Kannst einordnen, warum eine vollständige DICOM-Anonymisierung mehr ist als das Löschen von PatientName/PatientID"
---

## Status dieser Lektion

**Entwurf — Gerüst aus Track-5-Scaffolding (P10.46).** Diese Lektion hat noch keinen
Fließtext. Die Gliederung unten ist der geplante Aufbau; die eigentliche
Ausarbeitung erfolgt in einer eigenen PR pro Lektion (siehe `docs/content-todo.md`).

## Geplante Gliederung

<!-- kein-beispiel -->
```
1. Begriffe: Anonymisierung (nicht rückführbar) vs. Pseudonymisierung
   (rückführbar über einen geschützt gehaltenen Schlüssel) — warum das für
   Forschungsdaten (DSGVO Art. 89, Zweckbindung) einen Unterschied macht
2. Offensichtlicher Patientenbezug: PatientName, PatientID, PatientBirthDate,
   OtherPatientIDs, ...
3. Weniger offensichtlicher Bezug: ReferringPhysicianName, InstitutionName,
   private/herstellerspezifische Tags, UIDs (die selbst indirekt identifizierend
   sein können, wenn sie z. B. eine Fallnummer kodieren), eingebrannte Texte in
   Pixeldaten (Secondary-Capture-Screenshots mit sichtbarem Namen im Bild)
4. DICOM PS3.15 Annex E (Basic Application Level Confidentiality Profile) als
   Referenzstandard, nicht als selbst erfundene Liste
5. Praktischer Check: an einem realen, in der Sandbox erzeugten Objekt via
   dcmdump prüfen, welche Tags nach einem naiven "PatientName löschen" noch
   übrig bleiben
```

## Was zum Schreiben noch fehlt

- **Möglicher echter Hands-on-Baustein, muss beim Ausschreiben geprüft
  werden**: DCMTK bringt keinen dedizierten Anonymizer-Befehl in der
  aktuellen Toolbox mit (kurzer Grep im Repo/Container hat keinen gefunden),
  aber `dcmodify` (bereits als Tool registriert, siehe 3.1–3.3) kann genutzt
  werden, um Tags gezielt zu entfernen/überschreiben und den Unterschied
  vorher/nachher real per `dcmdump` zu zeigen. Falls das beim Ausschreiben
  trägt, wird `sandbox.required` auf `true` korrigiert und ein `dataset`
  ergänzt — für das Scaffolding bleibt es vorsichtig bei `false`.
- Eingebrannte Pixeldaten-Texte (z. B. bei Secondary Capture) sind mit der
  Toolbox nicht realistisch prüfbar (kein OCR/Bildviewer) — das bleibt
  voraussichtlich konzeptionell.
- Reale Rechtsgrundlage (DSGVO Art. 89, ggf. nationale Forschungsklauseln)
  muss korrekt und aktuell recherchiert werden, keine Paraphrase aus
  Erinnerung.

## Selbstcheck

*(folgt mit dem Fließtext)*
