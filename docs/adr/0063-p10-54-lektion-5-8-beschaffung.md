# 0063 — P10.54: Lektion 5.8 (Beschaffung) — Track 5 vollständig, veröffentlicht

Status: akzeptiert
Datum: 2026-09-14

## Kontext

Lektion 5.8 war seit P10.46 (ADR 0055) explizit als reine Synthese der
übrigen sieben Track-5-Lektionen geplant und sollte laut damaligem
Beschluss als letzte Lektion des Tracks geschrieben werden, da sie
konkret auf deren tatsächlichen (nicht nur geplanten) Inhalt
zurückverweist. Mit 5.1–5.7 seit P10.47–P10.53 real fertiggestellt
(ADR 0056–0062), war diese Voraussetzung erfüllt.

## Entscheidung — echte Synthese, keine erfundene Checkliste

**`content/lessons/5.8/de.md`**: vollständig neu geschrieben, auf
Basis des tatsächlichen Fließtexts von 5.1–5.7 (nicht deren
ursprünglicher Scaffold-Gliederung). Sieben Fragenkategorien, jede mit
direktem Verweis auf den realen, in der jeweiligen Lektion verifizierten
Befund:

- **Konformität** (5.2): Orthancs reales Conformance Statement, reale
  `storescu`-Verhandlung.
- **Integration** (5.1): die vier real live gezeigten
  SWF.b-Transaktionen (RAD-5/6/7/8), PIR/Coercion, XDS-I/KOS.
- **Migration** (5.3): der reale Zwei-Archiv-Test (UID-Erhalt vs.
  UID-Neuvergabe → getrennte Studien).
- **Datenschutz** (5.4): die reale `dcmodify`-Anonymisierung
  (Z-/U-Aktionscodes, Encrypted Attributes Data Set).
- **Zugriffsschutz** (5.5): der reale `/changes`-Test (Schreiben
  protokolliert, Lesen nicht) und ATNA als reale Antwort darauf.
- **Sicherheit** (5.6): die real gezeigte AE-Title-Lücke plus die
  real zitierte 2026-Studie (1.780 verwundbare Dienste).
- **Betrieb** (5.7): die drei real getesteten REST-Endpunkte
  (`/statistics`, `/system`, `/jobs`).

Zusätzlich real recherchiert statt erfunden: Eine reale, öffentlich
referenzierte Vorlage für den Beschaffungsprozess existiert — die
Deutsche Röntgengesellschaft (AGIT) hat eine PACS-Checkliste
veröffentlicht, orientiert an der IEEE-Praxis für
Software-Anforderungsspezifikationen, mit einer RFI-/RFP-Unterscheidung
(Springer/„Die Radiologie", Anforderungsdefinition und -spezifikation
für PAC-Systeme). Nur die **Existenz und Struktur** dieser Vorlage
wird zitiert, nicht ihr Inhalt nacherzählt — der Inhalt der eigenen
Fragenliste stammt vollständig aus den real verifizierten Befunden
dieses Tracks.

Ein `<!-- kein-beispiel -->`-markierter ASCII-Überblick erfüllt die
Beispielregel-Pflicht (mindestens ein Codeblock pro `de.md`) ehrlich —
diese Lektion hat bewusst keinen ausführbaren Befehl, da sie reine
Synthese ist.

**`content/lessons/5.8/meta.yml`**: `status: draft` → `fertig`,
`duration_minutes` von 10 auf 12. `sandbox.required` bleibt `false`,
`tools` bleibt `[]` — keine Werkzeuge nötig, keine `needs_sandbox:
true`-Deklaration, also erscheint auch kein „Spielwiese
starten"-Button (empirisch bestätigt, siehe unten).

**`content/tracks.yml`: `betrieb.status`** von `planned` auf
`published` gesetzt — alle acht Lektionen aus Track 5 sind jetzt
vollständig geschrieben, derselbe Maßstab wie bei Track 2 (ADR
0042/PR #44) und Track 3 (ADR 0054/PR #52).

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Isolierter Compose-Stack (`docker compose -p p10-54`, eigene Ports
  15441/16388/18098).
- `content:validate`: 0 Verstöße (41 Lektionen, 16 Nodes, 23
  Werkzeuge, 52 Glossarbegriffe) — bestätigt auch, dass der
  `<!-- kein-beispiel -->`-ASCII-Block die Beispielregel korrekt
  erfüllt.
- `content:sync`: 5 Tracks, 41 Lektionen — Track „Betrieb und
  Integration" real synchronisiert.
- Tracks-Übersicht im Browser gegen den echten Stack aufgerufen:
  „Betrieb und Integration" zeigt real „8 Lektionen · Verfügbar" statt
  „Bald verfügbar".
- Lektion 5.8 im Browser aufgerufen — rendert vollständig fehlerfrei,
  kein „Spielwiese starten"-Button (da keine Werkzeuge mit
  `needs_sandbox: true` deklariert, wie seit ADR 0058 bekannt).
- Kein Sitzungscontainer für diese Slice nötig — keine Docker-Sandbox-
  Ressourcen zu bereinigen. `dcmlab/toolbox:latest`/
  `dcmlab/orthanc:latest` unberührt (nur gelesen, nie gebaut/gelöscht).
- `datasets/build`/`services/sandbox`-Regressionssuites nicht erneut
  ausgeführt — diese Slice ändert keinen Python-/PHP-Code.

## Track 5 abgeschlossen

Mit dieser Slice sind alle acht Lektionen von Track 5 („Betrieb und
Integration") vollständig geschrieben und real verifiziert (ADR
0056–0063), der Track ist veröffentlicht. Bemerkenswert im Rückblick:
**jede einzelne** der in ADR 0055 vorsichtig als „möglicherweise kein
Hands-on möglich" markierten Lektionen (5.1, 5.2, 5.4, 5.5, 5.6, 5.7)
erwies sich bei tatsächlicher Prüfung als hands-on-fähig — nur 5.3
brauchte eine Umgebung außerhalb der Standard-Sandbox (zwei
Orthanc-Instanzen), und 5.8 blieb wie geplant reine Synthese. Dasselbe
Muster wie bereits bei Track 2 und Track 3 beobachtet: vorsichtige
Scaffold-Annahmen über fehlende Hands-on-Möglichkeiten halten der
tatsächlichen Prüfung selten stand.

## Nicht Teil dieser Slice

- Kein Node-Stub für Lektion 5.8 (`lab.node` bleibt `null`) — reine
  Synthese, kein Engine-Feature-Kandidat.
- Keine Entscheidung über die nächste Roadmap-Phase nach Track 5 —
  wird separat mit dem Nutzer geklärt.
