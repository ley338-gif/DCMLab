---
title: "Datenschutz, Zugriffsprotokollierung, Aufbewahrungsfristen"
teaser: "Wer hat wann welchen Befund geöffnet — und wie lange muss die Klinik das überhaupt noch aufheben?"
objectives:
  - "Kannst benennen, welche Ereignisse in einem PACS-Umfeld typischerweise protokollpflichtig sind (Zugriff, Export, Löschung)"
  - "Kannst den Unterschied zwischen gesetzlicher Aufbewahrungsfrist und technischer Löschung einordnen"
  - "Kannst erklären, welche Rolle der IHE-ATNA-Ansatz (Audit Trail and Node Authentication) für Zugriffsprotokollierung spielt"
---

## Status dieser Lektion

**Entwurf — Gerüst aus Track-5-Scaffolding (P10.46).** Diese Lektion hat noch keinen
Fließtext. Die Gliederung unten ist der geplante Aufbau; die eigentliche
Ausarbeitung erfolgt in einer eigenen PR pro Lektion (siehe `docs/content-todo.md`).

## Geplante Gliederung

<!-- kein-beispiel -->
```
1. Warum Zugriffsprotokollierung in der Bildgebung besonders sensibel ist
   (Bilddaten sind Gesundheitsdaten besonderer Kategorie nach Art. 9 DSGVO)
2. Was typischerweise protokolliert wird: Login, Studie geöffnet, Export,
   Löschung, administrative Änderungen
3. IHE ATNA (Audit Trail and Node Authentication): Audit-Message-Format
   (RFC 3881 / DICOM Supplement), zentraler Audit-Repository-Server als
   Muster, nicht als Pflicht-Implementierung
4. Aufbewahrungsfristen: gesetzliche Mindestfristen (länderspezifisch, z. B.
   Röntgenverordnung/Strahlenschutzgesetz in Deutschland) vs. tatsächliche
   Löschpraxis — warum "nie löschen" in der Praxis oft der Standardfall ist
5. Spannungsfeld: Aufbewahrungspflicht vs. Recht auf Löschung/Widerspruch
   (DSGVO) — wie das in der Praxis aufgelöst wird
```

## Was zum Schreiben noch fehlt

- **Kein Hands-on-Beispiel mit der Toolbox geplant.** Zugriffsprotokollierung
  und Aufbewahrungsfristen sind Betriebs-/Rechtsthemen, keine DICOM-Wire-
  Level-Mechanik; die vorhandenen Tools (Orthanc, DCMTK-CLI) bilden das
  nicht ab. Denkbar wäre höchstens ein Verweis auf Orthancs eigenes
  Log/Audit-Verhalten als Illustration (zu prüfen, ob real vorhanden und
  zitierfähig) — aber kein tragendes Beispiel.
  `sandbox.required: false`, kein `lab.node`.
- Reale, aktuelle Rechtsgrundlagen (Röntgenverordnung/Strahlenschutzgesetz-
  Nachfolgeregelungen, DSGVO Art. 9/17) müssen recherchiert und korrekt
  zitiert werden — Fristen sind sensibel und dürfen nicht aus Erinnerung
  geraten werden.
- Prüfen, ob Orthanc tatsächlich ATNA/RFC-3881-konforme Audit-Logs
  unterstützt (evtl. per Plugin) — falls ja, wäre ein realer Log-Auszug ein
  legitimes, kleines Beispiel.

## Selbstcheck

*(folgt mit dem Fließtext)*
