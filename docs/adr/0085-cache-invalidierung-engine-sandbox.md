# 0085 — Cache-Invalidierung bei Engine/Scenario-Engine/Sandbox

## Status

Angenommen, 15.09.2026. Loest einen Punkt aus `docs/offene-fragen.md`
("Cache-Invalidierung bei Engine/Sandbox nach einer Veroeffentlichung"),
offen seit ADR 0071/0074 (W2).

## Kontext

`ContentWriter::write()` ruft nach jedem erfolgreichen Schreiben
`CacheInvalidatorContract::invalidate()` auf; die Bindung war bisher
`NullCacheInvalidator` (No-op), weil den drei Python-Diensten ein
Endpunkt dafuer fehlte. Beim Nachsehen zeigte sich: die drei Dienste
cachen `content/` unterschiedlich stark, nicht wie im alten
Kommentar behauptet "alle drei pro Prozess":

- **`services/engine`**: nur `datasets.yml` ist gecacht
  (`content._datasets_cached`, `lru_cache(maxsize=1)`); `load_node()`
  liest `node.yml` bei jedem Aufruf frisch.
- **`services/sandbox`**: `datasets.yml` UND `worklists.yml` sind je
  eigenstaendig gecacht (`datasets_yaml._all`, `worklists_yaml._all`).
- **`services/scenario-engine`**: gar kein `lru_cache` -- jede Anfrage
  liest die Node-Definition frisch von der Platte.

## Entscheidung

**Alle drei Dienste bekommen `POST /internal/cache/clear`**, abgesichert
mit demselben `X-DCMLAB-KEY`-Header wie jeder andere interne Endpunkt
(`require_internal_key`). Fuer `scenario-engine` ist der Endpunkt ein
echter No-op (kein Cache vorhanden) -- er existiert trotzdem, damit die
Laravel-Seite alle drei Dienste EINHEITLICH aufrufen kann, ohne einen
davon als Sonderfall zu behandeln.

**`App\Content\HttpCacheInvalidator implements CacheInvalidatorContract`**
ruft alle drei Endpunkte PARALLEL auf (`Http::pool()`, 3s Timeout, 2s
Connect-Timeout) und loggt jeden Fehlschlag als Warnung, statt zu werfen
-- `ContentWriter::write()` ruft `invalidate()` erst NACH dem
erfolgreichen Schreiben auf, ein kurzzeitig veralteter Dienst ist kein
Grund, eine Veroeffentlichung rueckgaengig zu machen.

**Bindung: `HttpCacheInvalidator` ueberall, ausser in der Testumgebung.**
Ein erster Versuch, sie unbedingt zu binden (wie
`EngineClientContract`/`SandboxClientContract` es bereits tun), hat die
gesamte Pest-Suite von ~7s auf ~18s verlangsamt -- jeder Test, der eine
Content-Veroeffentlichung durchlaeuft (Quiz-/Lektions-/Prüfungs-/
Achievement-Editor-Tests), haette drei echte, in der Testumgebung nie
erreichbare HTTP-Verbindungsversuche ausgeloest. `AppServiceProvider`
bindet deshalb `NullCacheInvalidator` in `app()->environment('testing')`
und `HttpCacheInvalidator` sonst -- `HttpCacheInvalidator` selbst hat
eigene Testabdeckung ueber `Http::fake()`, es geht also keine
Testabdeckung verloren.

## Konsequenzen

- `services/engine/app/content.py`, `services/sandbox/app/datasets_yaml.py`,
  `services/sandbox/app/worklists_yaml.py`: je eine `clear_cache()`-Funktion,
  die den jeweiligen `lru_cache` leert.
- Eine frisch veroeffentlichte Lektion/ein Node/ein Datensatz erscheint in
  Engine und Sandbox jetzt sofort nach der Freigabe, nicht erst nach einem
  Neustart des jeweiligen Dienstes.

## Verifikation

- `test_cache.py` in allen drei Diensten: der Endpunkt verlangt den
  internen Schluessel (401 ohne); fuer engine/sandbox wird die
  Invalidierung end-to-end bewiesen (Datei aendern, alten Wert lesen
  [gecacht], `/internal/cache/clear` aufrufen, neuen Wert lesen); fuer
  scenario-engine nur der erfolgreiche Aufruf (kein Cache vorhanden).
- `HttpCacheInvalidatorTest` (Laravel): alle drei Endpunkte werden mit
  dem richtigen Header aufgerufen; ein nicht erreichbarer und ein mit
  Serverfehler antwortender Dienst werden geloggt, nicht geworfen.
- Alle 364 Pest-Tests (unveraendert schnell dank Testumgebungs-Bindung),
  `phpstan` (Level 7), `pint`, `npm run build`, sowie `ruff check`/`mypy`
  und die volle Testsuite in allen drei Python-Diensten sind gruen.
