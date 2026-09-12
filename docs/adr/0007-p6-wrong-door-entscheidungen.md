# 0007 — Wrong Door: zweite Node als Schema-Test (P6)

Status: akzeptiert
Datum: 2026-09-14

## Kontext

Abschnitt 10 verlangt für P6 eine zweite Node "ausschließlich aus dem
Schema erzeugt, ohne eine Zeile Sonderlogik" — dasselbe Muster wie
`silent-ct`, diesmal mit dem eingebauten Fehler auf der *Calling*- statt
der *Called*-Seite. Die DoD sagt ausdrücklich: Falls das Engine-Code
gebraucht hätte, wäre das ein Schema-Mangel und würde als ADR dokumentiert,
nicht als Sonderfall verbaut.

## Ergebnis

**Kein Engine-Code geändert.** `services/engine/app/rules.py` und
`app/content.py` sind byteidentisch zum Stand nach P5. `check_association`
prüft bereits in der vorgeschriebenen Reihenfolge (Host → Port → Called AE
→ Calling AE → akzeptiert, Abschnitt 5.3) — Regel 4 (*Calling AE Title Not
Recognized*) war seit P4 vollständig implementiert und getestet, nur
`silent-ct` hatte bisher nie einen Fall geliefert, der sie auslöst. Wrong
Door braucht nur eine andere Kombination aus YAML-Werten:

- `known_calling_aets` enthält `MR_2`, nicht die Konsole selbst.
- Die Konsole (`mr-console`) ist mit `local_ae: MR-2` (Bindestrich)
  konfiguriert — derselbe Ein-Zeichen-Tippfehler-Kniff wie `silent-ct`
  (dort: `PACS-ARCHIV` vs. `PACS_ARCHIV`), nur auf dem umgekehrten Feld.
- `remote_ae` ist von Anfang an korrekt (`PACS-ARCHIV`) — die Association
  scheitert also nicht an Stufe 3, sondern an Stufe 4.

`services/engine/tests/test_real_content.py` belegt das direkt: derselbe
Testcode (`check_association`, `set_config`, `trigger_action`,
`check_flag`) läuft unverändert gegen `content/nodes/wrong-door/node.yml`
und `content/nodes/silent-ct/node.yml` — nur die übergebenen Werte
(AE-Titel, Hostnamen) unterscheiden sich. Läuft dieser Test rot, ist das
ein Hinweis auf einen Schema-Mangel, keinen fehlenden Sonderfall.

**Ebenso schema-getrieben auf der Laravel-/Vue-Seite.** `NodeController`
und `Nodes/Show.vue` (P5) klassifizieren Hosts über ihre Rolle
(`role: shell`, `config_editable`, `services`), nie über einen Hostnamen —
`wrong-door`s Konsole heißt `mr-console` statt `ct-console` und
funktioniert ohne jede Codeänderung.

## Content-Entscheidungen (nicht Engine-Entscheidungen)

**Eigener Datensatz-Eintrag (`ct-thorax-1-slice`) statt eines neuen
Studiennamens.** Abschnitt 4.6 legt genau eine Beispielpatientin und -studie
mit zwei Serien fest (`Thorax 1.0 B70f`, `Thorax 5.0 B31f`). `silent-ct`
nutzt bereits die zweite Serie als Flag; ein zweiter, unabhängiger Flag für
Wrong Door braucht die erste. Der neue `datasets.yml`-Eintrag isoliert nur
diese eine, bereits kanonisch feststehende Serie — keine neue Prosa, keine
neuen Fachfakten.

**`de.md` wurde geschrieben, nicht als Gerüst belassen** — anders als sonst
in diesem Projekt (siehe `docs/content-todo.md`), weil P6 die Node selbst
als benanntes Phasen-Ergebnis verlangt, nicht als Lücke in vorhandenem
Content. Jede technische Behauptung darin (Fehlermeldungen, Verhalten von
`echoscu`/`findscu`, Ablauf) ist entweder wörtlich das verifizierte
Engine-Verhalten (gegen den laufenden Stack nachgestellt, siehe Test-Plan
im PR) oder eine direkte, unveränderte Ableitung aus dem eigenen
`node.yml` (die Geräteliste in `assets/geraeteliste-radiologie.txt` ist
`known_calling_aets` als Tabelle). Die einzige freie Erfindung ist die
Rahmenhandlung ("zweites MR-Gerät, zwei Wochen in Betrieb") — dieselbe Art
Rahmung, die auch `silent-ct` trägt, ohne eigene prüfbare Fachbehauptung.

## Folgen

Eine dritte Node nach demselben Muster (z. B. auf einer anderen
Assoziationsstufe) bräuchte ebenfalls keinen Engine-Code — nur neue
`node.yml`/`de.md`/Datensatz-Werte.
