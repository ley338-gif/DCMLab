# 0106 — Studio Lesson Composer: Drag & Drop (CMS-6c)

## Status

Angenommen, 15.09.2026.

## Kontext

ADR 0105 (CMS-6b) baute `lesson_elements` als vollstaendigen vertikalen
Slice inklusive Learner-Renderer, aber `/studio/lessons/{lesson}` blieb
bewusst rein lesend. CMS-6c ergaenzt genau das fehlende Stueck: Drag &
Drop auf demselben Modell, wie vom Betreiber vorgegeben ("CMS-6c kann
dann direkt der Studio-Composer mit Drag & Drop werden").

## Entscheidung

**Neue Route** `PATCH /studio/lessons/{lesson}/reorder`: nimmt die
**vollstaendige** neue Reihenfolge als Liste von `lesson_elements.id`
entgegen (nicht nur zwei vertauschte Eintraege) -- ein Drag-&-Drop-
Browser kennt ohnehin schon die komplette neue Liste, das erspart
fehleranfaellige Positions-Arithmetik im Backend. Validierung: die Liste
muss exakt so viele Eintraege haben wie die Lektion Elemente hat, jede
ID muss eindeutig sein und zu einem `lesson_elements`-Datensatz DIESER
Lektion gehoeren (`in:<echte IDs>`) -- ein Element einer fremden Lektion
kann so nicht eingeschleust werden.

**Berechtigung**: dieselbe Regel wie der Lektions-Editor selbst
(`Gate::authorize('update', $activity)` auf die `type=lesson`-Activity)
-- Reviewer/Administrator immer, ein Autor nur fuer Lektionen, denen er
zugewiesen ist. Bewusst NICHT die breitere `studio.access`-Regel des
lesenden Blicks: wer den Text einer Lektion nicht bearbeiten darf, soll
auch ihre Reihenfolge nicht aendern koennen.

**Frontend**: natives HTML5 Drag & Drop (`draggable`, `@dragstart`,
`@dragover.prevent`, `@drop`) statt einer neuen Abhaengigkeit
(vuedraggable/SortableJS o. Ae.) -- fuer eine einzelne vertikale Liste
reicht das, und es fuehrt keine neue Bibliothek fuer eine einzige Seite
ein. Optimistisches lokales Umsortieren, dann `router.patch()` im
Hintergrund (`preserveScroll`/`preserveState`). `can_manage` (vom
Server berechnet, dieselbe Regel wie oben) blendet Drag-Griffe aus und
deaktiviert `draggable`, wenn der Betrachter nicht berechtigt ist --
nur Ansehen bleibt fuer jede Nicht-Lernende-Rolle offen, wie in ADR 0105.

## Konsequenzen

- Kein neues NPM-Paket.
- Native HTML5-Drag-Events lassen sich nicht zuverlaessig durch
  synthetische Maus-Events in gaengigen Browser-Automatisierungswerk-
  zeugen ausloesen -- die eigentliche Drag-Interaktion wurde deshalb
  nicht Ende-zu-Ende im Browser nachgestellt, wohl aber die Seite
  (Rendering, Berechtigungen) und der komplette Reorder-Endpunkt
  (Autorisierung, Validierung, Persistenz) automatisiert getestet.
- **Bewusst nicht Teil dieser ADR**: "+ Element hinzufuegen"/"+ Activity
  hinzufuegen" (neue Elemente anlegen, nicht nur bestehende umsortieren)
  -- das setzt Entscheidungen voraus (z. B. koennen mehrere Sandboxes je
  Lektion existieren?), die noch nicht getroffen sind, und ist kein Teil
  des vom Betreiber vorgegebenen CMS-6c-Umfangs.

## Verifikation

- Alle 488 Tests (4 neu: `can_manage`-Konsistenz mit dem Lektions-Editor,
  Reviewer kann umsortieren, nicht zugewiesener Autor darf nicht, ein
  Element einer fremden Lektion wird abgelehnt), PHPStan Level 7 und
  `pint --test` sind gruen.
- `vue-tsc --noEmit`, `npm run check`, `npm run build` erfolgreich.
