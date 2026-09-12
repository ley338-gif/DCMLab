# 0004 — Locale-Routing und Frontend-Übersetzungsmechanismus (P2)

Status: akzeptiert
Datum: 2026-09-12

## Kontext

Abschnitt 8 verlangt `/de/`-Routing von Anfang an und keine hartkodierten
UI-Strings, alles in `lang/de.json`. Die Laravel-Backend-Seite hat dafür
bereits eine eingebaute Lösung (`__()`/`Lang::get()` gegen JSON-Dateien,
vom Starter-Kit selbst schon für zwei Flash-Meldungen benutzt). Für Vue gibt
es keinen eingebauten Mechanismus.

## Entscheidungen

**Kein zusätzliches i18n-Paket.** Statt `laravel-vue-i18n` o. ä. teilt
`HandleInertiaRequests` den Inhalt von `lang/de.json` als `translations`-Prop,
und `resources/js/lib/trans.ts` löst Strings dagegen auf — Aufrufparameter
ist zugleich Nachschlage-Key und Fallback, genau wie Laravels `__()`. Ein
Aufruf sieht in beiden Sprachen (PHP wie Vue) gleich aus:
`trans('Log in')` / `__('Log in')`. Vermeidet eine zusätzliche Abhängigkeit
für eine einzige JSON-Datei.

**`Route::prefix('de')` statt `{locale}`-Parameter.** Nur eine Sprache ist
aktuell live (Abschnitt 8: Umschalter im Code vorbereitet, im UI
ausgeblendet). Ein fixer Prefix reicht dafür und ist die einfachere,
umkehrbare Wahl — ein `{locale}`-Parameter mit Middleware-Validierung lohnt
sich erst, wenn eine zweite Sprache tatsächlich kommt. `config/fortify.php:
'prefix' => 'de'` deckt alle Fortify-eigenen Routen automatisch mit ab.
Die `.well-known/passkey-endpoints`-Route bleibt bewusst unprefixed (Web-
Standardpfad).

**`defineOptions({layout: {...}})` ist für `trans()` ungeeignet.** Diese
Vue-Compiler-Macro wertet ihr Objekt beim Laden des Seiten-Moduls aus —
das passiert, bevor Inertia die aktuellen Page-Props (inklusive
`translations`) gesetzt hat, weshalb `trans()` dort immer auf die
Fallback-Sprache zurückfiel. Der Fix ist Inertias eigener Mechanismus dafür:
`setLayoutProps()` in einem `watchEffect`, der beim Komponenten-Setup läuft
und reaktiv bleibt (dasselbe Muster, das der Starter-Kit selbst schon in
`TwoFactorChallenge.vue` benutzt).

**`APP_FALLBACK_LOCALE=en` statt `de`.** Rückt von der P0-Entscheidung ab.
Ohne eigene `lang/de/validation.php` (etc.) hätte ein fehlender Schlüssel
sonst den rohen Übersetzungsschlüssel angezeigt, nicht englischen Text.
`lang/de/{validation,auth,passwords}.php` decken die tatsächlich benutzten
Regeln vollständig ab; `en` bleibt nur als Sicherheitsnetz für Schlüssel, die
aus zukünftigen Fortify-Features noch dazukommen.

**Umfang der Übersetzung: sichtbarer Text, nicht jedes Attribut.** Abschnitt
8 verlangt keine hartkodierten *sichtbaren* Strings. `sr-only`-Text und
`aria-label`s in den mitgelieferten shadcn-vue-UI-Primitiven
(`resources/js/components/ui/*`, z. B. "Toggle sidebar") wurden bewusst
nicht angefasst — das ist generierte Bibliothekskomponente, kein
App-eigener Inhalt, und für Screenreader-nutzende Personen ohnehin nicht
sprachlich prominent genug, um das Risiko einer Bibliotheks-Änderung zu
rechtfertigen. Alle sichtbaren Strings in Auth-, Settings- und
Tracks-Seiten sowie die Passwort-Reset-/E-Mail-Bestätigungs-Mails sind
übersetzt.

**Tracks-Übersicht ersetzt die Standard-Willkommensseite.** `Welcome.vue`
(Laravel-Marketing-Seite) ist entfernt, `/de` (Routenname `home`) rendert
jetzt `TrackController::index` -- oeffentlich erreichbar (kein `auth`), mit
eigenem, layoutlosen Kopfbereich (Login/Registrieren fuer Gaeste, Dashboard-
Link fuer angemeldete Nutzer), da `AppSidebarLayout` einen angemeldeten
Nutzer voraussetzt. `NavFooter.vue` (Laravel-Repo-/Doku-Links) ist mit
entfernt, da nach dem Wegfall der Platzhalter-Links ungenutzt.

## Ein gefundener Bug (nicht Teil der eigentlichen i18n-Entscheidung)

Der Caddy/php-fpm-Shared-Volume-Mechanismus aus P0 (ADR 0001) befüllte
`public/` nur beim allerersten Start, wenn das Volume leer war. Nach dem
Entfernen von `Welcome.vue` und Hinzufügen von `Tracks/Index.vue` zeigte
Vites Manifest deshalb noch auf die alte Seite -- ein Redeploy mit
geänderten Page-Komponenten hätte in Produktion denselben Fehler
reproduziert. `docker/entrypoint.sh` räumt das Volume jetzt bei jedem
Start und befüllt es neu.

## Folgen

Neue Strings kommen in `lang/de.json` (Vue + Laravel-`__()`) bzw.
`lang/de/{validation,auth,passwords}.php` (Framework-Validierung). Eine
zweite Sprache würde `lang/en.json` + `lang/en/*.php` + einen im UI
sichtbaren Umschalter brauchen, der `Route::prefix('de')` durch einen
`{locale}`-Parameter ersetzt -- keine der beiden Änderungen betrifft den
bestehenden Content oder das Node-Schema.
