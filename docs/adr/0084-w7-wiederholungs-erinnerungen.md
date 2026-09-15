# 0084 — W7: Wiederholungs-Erinnerungen

## Status

Angenommen, 15.09.2026. Letzte Arbeitsphase aus
`dcm-lab-lms-agent-prompt.md` Abschnitt 5, im Anschluss an ADR 0083 (W6.4).

## Kontext

Das DoD aus Abschnitt 5: "Ein Nutzer mit fälligen Karten erhält genau eine
Mail pro Intervall, ein Nutzer ohne fällige Karten keine, und die
Abschaltung wirkt sofort." Der Wiederholungsplaner (`QuizReview.due_at`,
SM-2-artig) existierte bereits seit früher (`ReviewController`,
`QuizSchedulerService`) -- was fehlte, war ausschließlich der
Mail-Versand.

**Eine echte Betriebslücke, entdeckt beim Verdrahten des Scheduler-Laufs:**
Dieses Deployment hat aktuell **keinen Scheduler- oder Queue-Worker-Prozess**
-- der `app`-Container in `infra/docker-compose.yml` führt ausschließlich
`php-fpm` aus (`Dockerfile`, `CMD ["php-fpm"]`), und `QUEUE_CONNECTION=redis`
ist konfiguriert, aber nichts konsumiert diese Queue. Ein zweiter Container
auf demselben Image würde zusätzlich zwei bestehende, bisher folgenlose
Annahmen von `docker/entrypoint.sh` sichtbar machen: (1) `APP_KEY` wird
NICHT zentral gesetzt, sondern beim ersten Start pro Container lokal
generiert (`.env.docker` lässt `APP_KEY` absichtlich leer, siehe dortiger
Kommentar) -- ein zweiter Container bekäme einen ANDEREN Schlüssel als
`app`; (2) der Entrypoint leert und befüllt bei jedem Start das geteilte
`web-public`-Volume neu -- zwei gleichzeitig startende Container auf
diesem Volume liefen sich in die Quere. Beides ist heute folgenlos, weil
nur ein einziger Container dieses Image ausführt.

## Entscheidung

**Die Anwendungslogik ist vollständig und unabhängig von der
Scheduler-Frage lauffähig** -- `review:send-reminders` kann jederzeit von
Hand oder über einen beliebigen externen Mechanismus aufgerufen werden:

- **Migration** ergänzt `users.review_reminders_enabled` (Standard: an)
  und `users.review_reminder_sent_at`.
- **`App\Console\Commands\ReviewSendReminders`** (`review:send-reminders`):
  wählt Nutzer mit `review_reminders_enabled = true`, deren letzte
  Erinnerung mindestens `config('review.reminder_interval_days')`
  (Standard 3 Tage) zurückliegt oder noch nie stattfand; verschickt nur an
  die mit tatsächlich fälligen `QuizReview`-Zeilen (`due_at <= now()`);
  setzt `review_reminder_sent_at` nach jedem Versand. Der
  Mindestabstand wird gegen den GESPEICHERTEN Zeitpunkt geprüft, nicht
  gegen einen festen Wochentag -- "genau eine Mail pro Intervall" gilt
  deshalb unabhängig davon, wie oft der Befehl tatsächlich läuft.
- **`App\Mail\ReviewReminderMail`**: bewusst KEIN `ShouldQueue` (kein
  Worker verfügbar, siehe Kontext) -- Versand synchron im Befehl selbst,
  unproblematisch für einen taeglichen Batch-Lauf ohne Request-Kontext.
- **`bootstrap/app.php`**: `withSchedule()` registriert
  `review:send-reminders` als taeglich -- reine Anwendungskonfiguration,
  ohne Risiko, unabhängig davon, ob/wie sie ausgeführt wird.
- **Abschaltbar im Profil** (`settings/profile`, Checkbox neben
  Name/E-Mail): `ProfileUpdateRequest` validiert das Feld zusätzlich zu
  den mit der Registrierung geteilten `profileRules()` (dort bewusst
  NICHT ergänzt, ein neues Konto braucht diese Einstellung nicht).

**Bewusst nicht Teil dieser Änderung: das Deployment tatsächlich einen
Scheduler ausführen zu lassen.** Einen zweiten Container auf demselben
Image zu starten (`php artisan schedule:work`) wäre der naheliegende
Reflex, würde aber ungeprüft in die zwei oben beschriebenen,
`entrypoint.sh`-bedingten Fallen laufen (unterschiedlicher `APP_KEY` je
Container, Race auf dem `web-public`-Volume) -- beides Fragen, die eine
bewusste Entscheidung des Betreibers verdienen (`APP_KEY` zentral setzen?
`entrypoint.sh` um eine "nur App-Container"-Bedingung erweitern? Eigener,
schlankerer Entrypoint für einen Scheduler-Container?), keine, die diese
Änderung nebenbei treffen sollte. Siehe `docs/offene-fragen.md`.

## Konsequenzen

- `resources/js/pages/settings/Profile.vue`: eine zusätzliche Checkbox,
  demselben Muster wie `leaderboard_opt_in` in `PublicProfile.vue`
  folgend (`default-checked` + natives Formular).
- `config/review.php`: `reminder_interval_days` (Standard 3,
  `REVIEW_REMINDER_INTERVAL_DAYS`).
- Ohne einen laufenden Scheduler verschickt `review:send-reminders`
  nichts von selbst -- der Befehl muss bis zur Betreiberentscheidung von
  Hand oder über einen externen Mechanismus (Host-Cron +
  `docker compose exec app php artisan review:send-reminders`) angestoßen
  werden.

## Verifikation

- `ReviewSendRemindersTest`: deckt das DoD wörtlich ab -- ein Nutzer mit
  fälligen Karten bekommt genau eine Mail; ein Nutzer ohne fällige Karten
  keine; `review_reminders_enabled = false` verhindert den Versand auch
  bei fälligen Karten; ein Nutzer, der innerhalb des Intervalls schon
  erinnert wurde, bekommt keine zweite Mail; nach Ablauf des Intervalls
  wieder eine.
- `ProfileUpdateTest`: die Einstellung ist standardmäßig an und über
  `PATCH settings/profile` abschaltbar, ohne die bestehenden
  Namen/E-Mail-Tests zu beeinflussen (das Feld ist optional in der
  Validierung, damit ein Formular ohne dieses Feld -- wie der
  Registrierungs-Weg über `profileRules()` -- unverändert funktioniert).
- Alle 362 Tests, `phpstan analyse` (Level 7), `pint --test`,
  `npm run check` und `npm run build` sind gruen.

Damit sind alle Arbeitsphasen W0–W7 aus `dcm-lab-lms-agent-prompt.md`
abgeschlossen.
