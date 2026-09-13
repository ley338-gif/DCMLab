---
title: "Security: Netzsegmentierung, Legacy-Modalitäten, bekannte Angriffsflächen"
teaser: "DICOM wurde 1993 nicht für ein feindliches Netz entworfen — und viele Modalitäten laufen heute noch mit dieser Annahme."
objectives:
  - "Kannst erklären, warum DICOM-Netzwerkkommunikation (C-STORE/C-FIND/...) von Haus aus unauthentifiziert/unverschlüsselt ist"
  - "Kannst benennen, warum Netzsegmentierung (VLAN-Trennung von Modalitäten) die naheliegende Kompensationsmaßnahme ist, nicht ein Nachrüsten der Modalität selbst"
  - "Kannst mindestens zwei real dokumentierte DICOM-Angriffsflächen benennen (z. B. fehlende Authentifizierung am Port, PACS als Ziel für Ransomware)"
---

## Status dieser Lektion

**Entwurf — Gerüst aus Track-5-Scaffolding (P10.46).** Diese Lektion hat noch keinen
Fließtext. Die Gliederung unten ist der geplante Aufbau; die eigentliche
Ausarbeitung erfolgt in einer eigenen PR pro Lektion (siehe `docs/content-todo.md`).

## Geplante Gliederung

<!-- kein-beispiel -->
```
1. DICOM-Netzwerkprotokoll ohne eingebaute Sicherheit: kein TLS, keine
   Authentifizierung per Default (AE-Title ist kein Passwort, siehe
   Lektion 2.1/ADR zu Orthancs Permissivität)
2. Warum Legacy-Modalitäten (oft 10+ Jahre alte Geräte, kein Patch-Support
   mehr vom Hersteller) das eigentliche Risiko sind, nicht das Protokoll an
   sich
3. Netzsegmentierung als Standardkompensation: eigenes VLAN für Modalitäten,
   kein direkter Internetzugang, Firewall-Regeln nur zum PACS/Archiv
4. Reale, dokumentierte Vorfälle/Forschungsergebnisse zitieren (z. B.
   öffentlich erreichbare PACS-Server, gefundene ungeschützte DICOM-Ports) —
   mit echten, verifizierbaren Quellen, nicht aus dem Gedächtnis
5. Verhältnis zu DICOM TLS (Supplement 51) und warum es in der Praxis selten
   eingesetzt wird
```

## Was zum Schreiben noch fehlt

- **Kein Hands-on-Angriffsszenario in der Sandbox geplant** — das würde eine
  gezielte, gegen den Sandbox-eigenen Schutzzweck laufende Übung erfordern
  (siehe die generellen "keine Angriffstools ohne klaren Verteidigungs-
  /Lernzweck"-Leitplanken des Projekts). Realistisch und mit den
  vorhandenen Tools machbar wäre höchstens: ein reales `findscu`/`storescu`
  ohne vorherige Autorisierung gegen die eigene Sandbox-Orthanc-Instanz
  ausführen, um zu zeigen, dass es *funktioniert* (= das eigentliche
  Problem) — das wäre ein legitimer, kleiner Baustein und sollte beim
  Ausschreiben geprüft werden.
  `sandbox.required: false` vorerst, ggf. beim Ausschreiben auf `true`
  korrigieren, falls sich dieser kleine Baustein trägt.
- Reale, verifizierbare Quellen für "dokumentierte Vorfälle" müssen recherchiert
  werden (z. B. bekannte Sicherheitsforschung zu offenen PACS-Servern) — keine
  erfundenen oder ungeprüften Beispiele.
- Abgrenzung zu Lektion 5.5 (Datenschutz/Protokollierung) sauber ziehen:
  diese Lektion behandelt Netzwerk-/Infrastruktursicherheit, nicht
  Zugriffsprotokollierung.

## Selbstcheck

*(folgt mit dem Fließtext)*
