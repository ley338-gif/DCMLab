# 0095 — Modulsystem: ActivitySupports um Studio-Capabilities erweitert (CMS-1)

## Status

Angenommen, 15.09.2026.

## Kontext

ADR 0094 legt den Phasenplan fuer DCMLab Studio fest. Phase CMS-1
("Module Foundation") verlangt laut `docs/studio-architecture-plan.md`
Abschnitt 5 eine Erweiterung von `ActivitySupports`
(`app/Activities/ActivitySupports.php`) um die im Studio-Auftrag
genannten Capabilities (`runtimeType`, `previewable`, `duplicatable`,
`versionable`, `reusable`), zusaetzlich zu den fuenf bereits
bestehenden Feldern (`isGraded`, `tracksCompletion`, `needsContainer`,
`authorable`, `freelyPlaceable`). Der CMS-0-Audit hatte bereits
festgehalten: `ActivityContract`/`ActivityRegistry`/`ActivitySupports`
sind schon ein gutes Modulsystem-Fundament, keine Neukonzeption noetig
-- nur additive Erweiterung.

## Entscheidung

`ActivitySupports` bekommt fuenf neue, optionale Felder mit
Default-Werten (keine Migration, kein Breaking Change fuer bestehende
Aufrufer). Die Werte je Aktivitaetstyp sind bewusst an tatsaechlich
beobachtbarem Vertragsverhalten festgemacht, nicht an Wunschzustand:

- **`versionable`**: `true` nur, wenn `serialize($draft)` den Entwurf
  tatsaechlich auswertet (Lesson, Exam, Achievement). `Quiz` und
  `Sandbox` melden `false`, obwohl beide ueber ihre jeweilige Lektion
  editierbar sind -- ihr *eigener* Vertrag hat kein Dateiziel,
  `serialize()` ignoriert `$draft` immer (Code-Read bestaetigt). `Node`
  meldet ebenfalls `false`: kein Editor legt heute einen Entwurf an,
  `NodeActivity::serialize()` ignoriert `$draft` komplett.
- **`runtimeType`**: `'container'` fuer `Node` und `Sandbox` (beide
  brauchen eine laufende Container-/Engine-Sitzung), sonst `null`.
- **`reusable`**: `true` fuer `Node` (ueber `related_lessons` aus
  mehreren Lektionen referenzierbar, keiner exklusiv zugeordnet) und
  `Achievement` (`unlock_when` bezieht sich typischerweise auf beliebige
  andere Aktivitaeten).
- **`previewable`**/**`duplicatable`**: bei allen sechs Typen `false`
  (Default) -- beide Funktionen existieren im Produkt noch nicht
  (geplant fuer CMS-10 bzw. CMS-4), keine Instanz behauptet eine
  Faehigkeit, die es noch nicht gibt.

Alle sechs bestehenden `*Activity.php`-Implementierungen sowie die
zugehoerigen sieben Unit-Tests (`tests/Unit/Activities/*`) wurden
entsprechend ergaenzt.

## Konsequenzen

- Keine Migration, keine Aenderung an `ActivityContract`,
  `ActivityRegistry` oder `ActivityType` -- reine additive Erweiterung
  eines Value Objects, wie in `docs/studio-architecture-plan.md`
  Abschnitt 5 vorgesehen.
- `Quiz`/`Sandbox`s `versionable: false` macht die in
  `docs/studio-architecture-plan.md` Abschnitt 2 (Technical Debt)
  dokumentierte Kopplung an `Lesson` jetzt maschinenlesbar, statt nur
  in Kommentaren zu stehen -- ein kuenftiger Lesson Composer (CMS-6)
  kann darauf pruefen, statt erneut zu recherchieren.
- Kein `ModuleProvider`-Registrierungsmechanismus in dieser Phase
  ergaenzt (wie im Plan vorgesehen: erst wenn ein zweiter interner
  Modultyp ansteht, um nicht gegen einen noch unklaren Vertrag zu
  entwickeln).

## Verifikation

- Alle 414 Tests (inkl. 7 erweiterte Assertions in den
  Activity-Unit-Tests), PHPStan Level 7 und `pint --test` sind gruen.
- Keine Vue-/Frontend-Aenderung, `npm run build`/`vue-tsc` nicht
  betroffen.
