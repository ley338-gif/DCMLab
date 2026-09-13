# 0039 — P10.29: Lektion 2.2 (C-STORE) — Dienst-Blickwinkel

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Lektion 2.2 ("C-STORE: Bilder senden und empfangen") lag seit P10.24
als Gerüst vor, ohne offene Infrastrukturfrage — nur Fließtext fehlte.

**Abgrenzung zu Lektion 1.0:** 1.0 stellt `storescu`/`storescp` als
Werkzeuge vor. 2.2 vertieft den Dienst selbst: die Beziehung zwischen
Association und einzelnen Store-Requests, den vollständigen
Statusraum (Success/Warning/Failure), und die Empfängerrolle als
eigenständiges Programm.

**In dieser Slice real verifizierter Mechanismus-Fund:** Mehrere
Dateien in einem einzigen `storescu`-Aufruf laufen über **eine**
Association mit mehreren, fortlaufend nummerierten `MsgID`s — nicht
über mehrere separate Verbindungen. Verifiziert durch direkten
Vergleich: ein `for`-Loop mit drei separaten `storescu`-Aufrufen
öffnet drei eigene Associationen, derselbe Aufruf mit drei Dateien als
Argumente öffnet genau eine. Dieser Unterschied war vor der
Verifikation nicht mit Sicherheit bekannt und wurde bewusst empirisch
geprüft, nicht angenommen.

## Entscheidung — Fließtext mit sechs echten Beispielen

**`content/lessons/2.2/de.md`**: vollständig neu geschrieben.
- Ein echter Einzeldatei-Versand mit `Status: 0x0000`.
- Ein echter Mehrdatei-Versand in einem Aufruf — zeigt die
  Ein-Association-viele-`MsgID`s-Mechanik direkt.
- Ein echter Ordner-Versand (60 Dateien, `ct-thorax-60`) mit
  Exitcode-Beweis (still, wie jedes DCMTK-Werkzeug bei Erfolg).
- Ein echter `tshark`-Mitschnitt eines einzelnen C-STORE-Zyklus
  (Objekt als eigenes `P-DATA`-Paket, vom Dissector als „CT Image
  Storage" erkannt).
- Ein echter `storescp`-Empfang mit anschließender `dcmdump`-Prüfung
  der tatsächlich angekommenen Datei (nach SOP Instance UID benannt,
  nicht nach dem Originaldateinamen).
- Statuscode-Übersicht (Success/Warning/Failure) mit Verweis auf
  Lektion 4.6 (Coercion, `0xB000`) und 4.2/4.3 (Ablehnung) statt
  Duplizierung bereits real dokumentierter Fälle.

**`content/lessons/2.2/meta.yml`**: `tools` von
`[storescu, storescp, dcmdump]` auf
`[storescu, storescp, dcmdump, tshark]` ergänzt (genau am
Vier-Werkzeuge-Limit). `status: draft` → `fertig`.

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Alle sechs Beispiele in einem echten, vom Orchestrator erzeugten
  Sitzungscontainer erzeugt — Session über die echte Produkt-
  Oberfläche gestartet.
- Ein-Association-vs-mehrere-Associationen-Unterschied direkt
  gegengeprüft (Loop mit drei Einzelaufrufen vs. ein Aufruf mit drei
  Dateien).
- Realer Bestand am Archiv nach dem Ordner-Versand per REST-API
  geprüft (60 Instanzen — passend zur Datensatzgröße).
- Der reale `storescp`-Log und die tatsächlich empfangene, nach SOP
  Instance UID benannte Datei per `dcmdump` verifiziert.
- `content:validate`: 0 Verstöße (27 Lektionen, 35 Glossarbegriffe).
- `datasets/build` und `services/sandbox`: pytest erneut ausgeführt
  (unverändert von dieser Slice betroffen) — beide grün.
- Lektion 2.2 im Browser gegen den echten Stack aufgerufen — rendert
  vollständig fehlerfrei.
- Sitzung über die echte Oberfläche sauber beendet (`docker ps -a`
  bestätigt vollständige Entfernung).
- Alle Docker-Ressourcen dieser Slice nach Abschluss vollständig
  entfernt.

## Nicht Teil dieser Slice

- Kein Node-Stub für Lektion 2.2 (`lab.node` bleibt `null`).
- Kein echter Failure-Status demonstriert — dieselbe
  Orthanc-Großzügigkeit (ADR 0017/0025/0026) verhindert eine echte
  Ablehnung an dieser Stelle; die Lektion verweist stattdessen auf die
  bereits real dokumentierten Fälle in 4.2/4.3, statt sie zu
  duplizieren oder eine Ablehnung zu erfinden.
- Track 2 offen: 2.3 (reiner Schreibaufwand), 2.4 und 2.8 (neue
  Infrastruktur nötig) — siehe `docs/content-todo.md`.
