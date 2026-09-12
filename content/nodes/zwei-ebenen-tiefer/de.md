---
title: Zwei Ebenen tiefer
scenario_title: Patient → Study → Series → Instance in einem Testarchiv nachvollziehen
---

## Briefing

**Gerüst** (`status: draft`). Diese Node ist noch nicht spielbar.

<!-- kein-beispiel -->
```
Geplantes Szenario   Lektion 1.2: die Hierarchie Patient -> Study -> Series
                      -> Instance an einem echten Bestand nachvollziehen
                      (Abschnitt 5, Track 1)
Blockiert durch       services/engine/app/rules.py: `findscu` kennt nur die
                      Ebenen STUDY und SERIES, keine PATIENT- oder
                      INSTANCE-Ebene; jeder Archiv-Host hat hoechstens
                      einen Bestand (eine Study, eine Serie), keine echte
                      Hierarchie zum Durchklicken
Naechster Schritt      Engine um PATIENT-/INSTANCE-Ebene und mehrere
                      Studies/Serien pro Archiv erweitern (neues Feature,
                      kein Content-Problem) -- siehe docs/content-todo.md
```

## Write-up

Folgt, sobald die Node spielbar ist.
