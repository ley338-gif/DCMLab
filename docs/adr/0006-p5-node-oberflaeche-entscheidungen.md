# 0006 — Node-Oberfläche und Web-Terminal (P5)

Status: akzeptiert
Datum: 2026-09-14

## Kontext

Abschnitt 5.4 verlangt eine Drei-Tab-Oberfläche (Workstation, Modalität/
Konsole, Archiv), ein xterm.js-Terminal mit Befehlsvorlagen und markierten
Platzhaltern, und Abschnitt 6 verlangt, dass dieselbe Terminal-Komponente
später auch die Spielwiese bedient. Mehrere Detailentscheidungen waren
nötig, die der Auftrag nicht explizit vorgibt.

## Entscheidungen

**Kein WebSocket für die Node-Simulation.** Abschnitt 6 nennt WebSocket nur
für die Spielwiese ("Transport-Backend" unterscheidet sich). Die Engine-API
(P4) ist Request/Response pro Befehl, also schickt `EngineTerminal.vue`
einen `fetch()`-Aufruf pro Enter-Druck, nicht pro Tastenanschlag. Die
Komponente nimmt eine `onCommand`-Callback-Prop entgegen, keine
Transport-Logik selbst — das hält sie fuer P7 (Spielwiese, WebSocket)
wiederverwendbar, wie in Abschnitt 6 verlangt.

**Eigene, schlanke JSON-Endpunkte statt Inertia-Visits fuer exec/config/
action/hint/write-up/flag.** Ein Terminal-Befehl darf keinen vollen
Seitenwechsel ausloesen. `resources/js/lib/api.ts` reicht mit
`fetch()` + demselben XSRF-Cookie, das Inertia selbst nutzt (kein Sanctum,
keine zusaetzliche Session-Art noetig).

**Platzhalter-Markierung ueber die echte `environment.placeholders`-Liste,
nicht ueber eine Grossbuchstaben-Heuristik.** Ein erster Entwurf markierte
den ersten `[A-Z][A-Z0-9_]*`-Treffer im Befehl — das haette bei
`findscu -k QueryRetrieveLevel=SERIES ...` bereits bei "S" in "SERIES"
angeschlagen, lange bevor der echte Platzhalter `ZIEL_AE` kommt. Die
Komponente bekommt jetzt `environment.placeholders` als Prop und sucht
gezielt nach diesen Woertern.

**Tab springt zum naechsten Platzhalter.** `find_series` in `silent-ct`
enthaelt zwei Platzhalter (`UID_AUS_SCHRITT_1` und `ZIEL_AE`) in einer
Zeile. Ohne eine Sprungmoeglichkeit waere der zweite nur durch manuelles
Positionieren erreichbar — das Terminal hat aber bewusst keine generische
Maus-Textauswahl (kein Copy/Paste-Editor, sondern eine Kommandozeile).
Tab uebernimmt dieselbe Rolle wie in Snippet-Editoren ueblich.

**Cursor-Position wird explizit mitgefuehrt, nicht nur "ans Ende anhaengen".**
Ohne das wuerde Text nach einem ueberschriebenen Platzhalter (z. B. die
IP/Port am Zeilenende) beim Weitertippen falsch einsortiert. Pfeiltasten
links/rechts bewegen den Cursor, Backspace und Zeichen-Eingabe wirken
relativ zu ihm.

**Write-up-Punktestrafe nur "vorab" (Abschnitt 5.3 wörtlich).** Der erste
Entwurf zog bei jedem `write_up_seen` Punkte ab, auch nach dem Loesen. Der
Auftrag sagt ausdruecklich "Write-up **vorab** ansehen setzt die Node auf 0
Punkte" — nach dem Loesen ist das Ansehen straflos und automatisch (kein
Klick noetig). Die Engine unterscheidet jetzt `write_up_seen` (fuer die
Anzeige) von `write_up_seen_before_solve` (fuer die Punktestrafe); die
Laravel-Seite zeigt das Write-up automatisch, sobald `solved` **oder**
`write_up_seen` wahr ist.

**`node_attempts` ist bewusst schlank.** Abschnitt 7 listet
`hints_used`/`points`/`status`/`flag_submitted_at`/`engine_session_id` --
das ist der minimale Stand, den Laravel fuer Uebersicht/Profil (P8) braucht.
Der eigentliche Spielzustand (Hosts, Zaehler, Konfig) bleibt ausschliesslich
in der Engine; `node_attempts` wird nach jedem Hint/Write-up/Flag-Aufruf aus
dem frischen Engine-Zustand synchronisiert, nie umgekehrt.

## Ein gefundener Automatisierungs-Rand (kein Produktbug)

Bei der Browser-Verifikation hat sich gezeigt, dass manche
Browser-Automatisierungswerkzeuge synthetische "Enter"/"Type"-Ereignisse
erzeugen, die xterm.js' eigener Tastatur-Behandlung nicht als vertrauenswuerdige
Taste gilt (echte Zeichentasten wie Buchstaben, Ziffern und `-`/`.` einzeln
funktionieren; zusammengesetzte "type a string"-Aktionen und ein
bestimmtes "Return"-Mapping nicht). Das ist ein bekanntes Muster bei
canvas-basierten Terminal-Emulatoren und kein Zeichen eines
Anwendungsfehlers — der komplette Loesungsweg wurde erfolgreich manuell
nachgestellt (echte Tastendruecke fuer Buchstaben/Ziffern/Sonderzeichen,
ein gezielt dispatchtes `KeyboardEvent('keydown', {key:'Enter'})` fuer den
Zeilenabschluss) und funktioniert Ende-zu-Ende.

## Folgen

Die Terminal-Komponente ist bewusst generisch (Callback-Prop, keine
Node-spezifische Logik) und uebernimmt so unveraendert in P7. Ein neuer
Node-Typ mit anderen Platzhaltern braucht keine Aenderung an
`EngineTerminal.vue` -- nur `environment.placeholders` in `node.yml`.
