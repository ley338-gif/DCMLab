# 0121 — Content-Version-Supersession: pro Aktivität höchstens ein offener Entwurf

Status: akzeptiert
Datum: 2026-09-22

## Kontext

Betreiber-Fund während PR #178 (Lesson 4.1): Ein Reviewer-Entwurf
(`ContentVersion` #22) wurde zur Review eingereicht, dann aber inhaltlich
korrigiert und als neuer Entwurf (#23) erneut eingereicht — #22 blieb dabei
unverändert im Status `review` liegen. `ContentVersioningService::publish()`
veröffentlicht ausschließlich die übergebene Version; keine andere `draft`-
oder `review`-Version derselben Aktivität wird dabei ungültig. Ein Reviewer,
der versehentlich #22 statt #23 öffnet (z. B. über einen älteren Link oder
weil die Review-Queue `.unique('activity_id')` nur zur Anzeige, nicht zur
Absicherung nutzt), hätte die längst überholte Fassung live schalten können
— nach der Veröffentlichung von #23 sogar rückwirkend.

Dieselbe Lücke betraf `createDraft()`: Jeder der fünf Editoren
(Lesson/Node/Quiz/Exam/Achievement) ruft `storeDraft()`/`update()` beliebig
oft auf, ohne einen vorher offenen Entwurf zu invalidieren — zwei
Speichervorgänge hinterließen zwei parallele `draft`-Versionen derselben
Aktivität.

## Entscheidung

Pro Aktivität bleibt ab sofort höchstens eine `draft`- oder `review`-Version
gleichzeitig aktiv:

- **`createDraft()`**: sperrt die `Activity`-Zeile, setzt jede vorhandene
  `draft`-/`review`-Version derselben Aktivität atomar auf `superseded`,
  erzeugt erst danach den neuen Entwurf.
- **`publish()`**: sperrt die `Activity`-Zeile, setzt in derselben
  Transaktion, in der die ausgewählte Version veröffentlicht wird, jede
  *andere* `draft`-/`review`-Version derselben Aktivität auf `superseded`.
  Das bereinigt auch bereits vor diesem Fix entstandene Altfälle wie #22/#23.

`superseded` ist ein weiterer freier Wert der bestehenden `status`-Spalte
(`VARCHAR`, kein DB-Constraint) — keine Migration nötig. Veröffentlichte
Versionen und Versionen anderer Aktivitäten bleiben unangetastet.

**Keine neuen Guards nötig, um eine `superseded`-Version vor erneuter
Einreichung/Veröffentlichung zu schützen**: `submitForReview()` verlangt
bereits Status `draft`, `publish()` bereits Status `review` — `superseded`
erfüllt keine der beiden Bedingungen und wird von den bestehenden
`RuntimeException`-Prüfungen automatisch abgewiesen.

**Keine Änderung an den fünf Editor-Controllern, der Review-Queue oder dem
Self-Approval-/Rollen-Gate nötig**: Alle fünf lesen `pendingVersion` über
`whereIn('status', ['draft', 'review'])` — eine `superseded`-Version taucht
dort nie auf. Die Review-Queue filtert bereits auf `status = 'review'`. Das
`publish`-Gate (`ActivityPolicy::publish()`) bleibt unverändert rollenbasiert
(Reviewer/Administrator); es gab und gibt keine Prüfung auf
`created_by !== reviewed_by` im Anwendungscode selbst — "Self-Approval"
war in den vorangegangenen PRs eine Arbeitsweise des ausführenden Agents,
keine bestehende Code-Invariante, die dieser Fix hätte verändern können.

Einzige zusätzliche Änderung: `Studio/Nodes/Edit.vue` (die einzige Stelle,
die die volle Versionshistorie inkl. beliebiger Status anzeigt) bekommt ein
deutsches Label (`Ersetzt`) für `superseded` — die anderen vier Editoren
zeigen nur `pending_version.status`, der `superseded` nie annehmen kann, und
die bestehende `statusLabels[status] ?? status`-Fallback-Logik in der Node-
Historie wäre ohnehin nicht abgestürzt.

## Bewusst nicht in diesem Fix

`ContentPublishingService::restoreVersion()` supersediert offene
`draft`/`review`-Versionen derselben Aktivität nicht — ein Restore erzeugt
direkt eine neue veröffentlichte Version, ohne über
`ContentVersioningService::publish()` zu laufen. Theoretisch könnte danach
ein noch offener, alter Entwurf denselben Nach-Restore-Zustand überschreiben.
Dieser Fix bleibt bewusst eng auf die beiden vom Betreiber benannten
Operationen (`createDraft`, `publish`) begrenzt; eine Erweiterung von
`restoreVersion()` wäre ein eigener, separat zu bewertender Schnitt.

## Manuell/automatisiert verifiziert

- Neue Unit-Tests (`ContentVersioningServiceTest`): neuer Draft supersediert
  älteren Draft bzw. ältere Review-Version derselben Aktivität; published
  Versionen und Versionen anderer Aktivitäten bleiben unangetastet;
  Publish supersediert alle anderen offenen Versionen derselben Aktivität,
  lässt andere Aktivitäten unberührt; eine `superseded`-Version kann weder
  eingereicht noch veröffentlicht werden.
- Neuer Feature-Test (`ContentPublishingServiceTest`): reproduziert die
  PR-#178-Situation 1:1 über den vollen `ContentPublishingService`-Pfad
  (zwei parallele `review`-Versionen, Veröffentlichung der korrigierten
  supersediert die veraltete, ein späterer Versuch, die veraltete doch noch
  zu veröffentlichen, schlägt fehl und ändert die Lesson nicht).
- Neuer End-to-End-Test (`LessonEditorControllerTest`): zwei echte
  `storeDraft`-HTTP-Aufrufe hintereinander, Editor (`pending_version`) und
  Review-Queue zeigen danach nur noch den neueren Entwurf, beide
  Freigabe-Endpunkte lehnen den überholten Entwurf ab.
- Volle Suite (Pest 1037/1037, inkl. aller bestehenden Draft→Review→
  Published- und Self-Approval-/Rollen-Tests unverändert grün), Pint,
  PHPStan Level (Projekt-Standard) — alle clean.
