<?php

namespace App\Console\Commands;

use App\Content\ContentFieldComparison;
use App\Content\ContentRepository;
use App\Content\QuizContent;
use App\Models\Activity;
use App\Models\ContentVersion;
use App\Models\Lesson;
use App\Models\LessonElement;
use App\Models\Node;
use App\Models\Themenfeld;
use App\Models\Track;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Liest content/ ein und aktualisiert den Index in der Datenbank
 * (Abschnitt 7): tracks, lessons, nodes. Die Prosa bleibt im Dateisystem --
 * hier landen nur Slug, Reihenfolge, Titel/Teaser und ein Hash der Quelle.
 *
 * Zusaetzlich (ADR 0072): ein `activities`-Verzeichniseintrag je Lektion,
 * Node und Track-Pruefung, damit sie ueber ActivityRegistry aufloesbar sind.
 *
 * Schutz des Studio-Stands (ADR 0122, Phase 3) -- vorher drehte jeder Lauf
 * jede Studio-Aenderung ausser `rich_content` still zurueck:
 *
 *   Regel A (versionsgebunden): hat eine Lektion/Node mindestens eine
 *     veroeffentlichte `content_versions`-Zeile, bleiben genau die Felder,
 *     die der zugehoerige Publisher schreibt, unangetastet (Lektionsfelder,
 *     Quiz, Node-Felder -- je nach Payload-Art getrennt).
 *   Regel B (nur beim Anlegen): Felder, die Studio direkt ohne Version
 *     setzt -- Track `themenfeld_id/order/level/hours/status`, Node
 *     `themenfeld_id`, Activity `sandbox`/`quiz` `track_id/order` --
 *     kommen nur beim allerersten Sync aus der Datei (wie schon bisher
 *     Lesson `track_id`/`order` und Track `title`/`teaser`).
 *
 * Weicht die Datei in einem geschuetzten Feld ab, gibt es eine Warnung mit
 * den Feldnamen. `--force-from-files` hebt beide Regeln nach Rueckfrage auf.
 * Eine frische DB ohne `content_versions` synchronisiert unveraendert.
 */
class ContentSync extends Command
{
    protected $signature = 'content:sync
        {--force-from-files : Studio-Stand bewusst mit content/ ueberschreiben (fragt nach, siehe docs/betrieb.md)}';

    protected $description = 'Aktualisiert den DB-Index (tracks, lessons, nodes, activities) aus content/ (Abschnitt 7)';

    /**
     * Genau die Felder, die LessonContentPublisher schreibt (ohne
     * rich_content, das content:sync ohnehin nie anfasst).
     */
    private const LESSON_STUDIO_FIELDS = ['title', 'teaser', 'level', 'duration_minutes', 'tools', 'requires', 'glossary_terms', 'objectives', 'objectives_count', 'sandbox', 'related_node'];

    /** QuizContentPublisher. */
    private const QUIZ_STUDIO_FIELDS = ['quiz', 'body'];

    /** NodeContentPublisher (inklusive status, den er auf published setzt). */
    private const NODE_STUDIO_FIELDS = ['title', 'scenario_title', 'difficulty', 'points', 'category', 'interaction', 'estimated_minutes', 'skills', 'related_lessons', 'hints', 'status'];

    /** StudioTrackController::update()/publish()/unpublish()/archive()/restore(). */
    private const TRACK_STUDIO_FIELDS = ['themenfeld_id', 'order', 'level', 'hours', 'status'];

    private bool $forceFromFiles = false;

    /**
     * Veroeffentlichte Versionen je Payload-Art und Activity-Key.
     *
     * @var array{lesson: array<string, list<int>>, quiz: array<string, list<int>>, node: array<string, list<int>>}
     */
    private array $studioVersions = ['lesson' => [], 'quiz' => [], 'node' => []];

    public function handle(ContentRepository $content): int
    {
        $this->forceFromFiles = (bool) $this->option('force-from-files');

        if ($this->forceFromFiles && ! $this->confirm(
            'content/ ueberschreibt damit jeden in Studio veroeffentlichten Stand (Metadaten, Quiz, Node-Felder, Track-Einstellungen). Wirklich fortfahren?',
            false,
        )) {
            $this->warn('Abgebrochen -- nichts synchronisiert.');

            return self::FAILURE;
        }

        $this->studioVersions = $this->loadStudioVersions();

        $themenfeldIds = $this->syncThemenfelder($content);
        $trackIds = $this->syncTracks($content, $themenfeldIds);
        $lessonCount = $this->syncLessons($content, $trackIds);
        $nodeCount = $this->syncNodes($content, $themenfeldIds);
        $examCount = $this->syncExamActivities($content, $trackIds);
        $this->syncAchievementCatalogActivity($content);
        // Erst nach syncNodes(): eine Lab-Referenz braucht die
        // `type=node`-Activity des verlinkten Node, siehe Klassendoc dort.
        $elementCount = $this->syncLessonElements($content);

        $this->info(sprintf(
            'content:sync — %d Themenfelder, %d Tracks, %d Lektionen, %d Nodes, %d Pruefungen, %d neue Lesson-Elemente synchronisiert.',
            count($themenfeldIds),
            count($trackIds),
            $lessonCount,
            $nodeCount,
            $examCount,
            $elementCount,
        ));

        return self::SUCCESS;
    }

    /**
     * Welche Lektionen/Nodes einen in Studio veroeffentlichten Stand haben
     * -- getrennt nach Payload-Art, derselbe Diskriminator wie
     * `ActivityContentApplier` (`quiz`-Schluessel = Quiz-Entwurf).
     * Wiederherstellungen zaehlen mit: auch sie setzen Live-Daten.
     *
     * @return array{lesson: array<string, list<int>>, quiz: array<string, list<int>>, node: array<string, list<int>>}
     */
    private function loadStudioVersions(): array
    {
        $versions = ['lesson' => [], 'quiz' => [], 'node' => []];

        if ($this->forceFromFiles) {
            return $versions;
        }

        $published = ContentVersion::query()
            ->where('status', 'published')
            ->whereHas('activity', fn ($query) => $query->whereIn('type', ['lesson', 'node']))
            ->with('activity:id,type,key')
            ->orderBy('id')
            ->get();

        foreach ($published as $version) {
            $activity = $version->activity;
            $kind = $activity->type === 'node' ? 'node' : (array_key_exists('quiz', $version->payload) ? 'quiz' : 'lesson');
            $versions[$kind][$activity->key][] = $version->id;
        }

        return $versions;
    }

    /**
     * Entfernt die geschuetzten Felder aus `$attributes` und warnt, falls
     * die Datei in einem davon tatsaechlich etwas anderes sagt als die DB --
     * nie bei rein formalen Unterschieden (ContentFieldComparison).
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function withoutStudioFields(array $attributes, Model $existing, array $fields, string $resource, string $reason): array
    {
        $differing = [];

        foreach ($fields as $field) {
            if (! array_key_exists($field, $attributes)) {
                continue;
            }

            if (! self::sameFieldValue($field, $attributes[$field], $existing->getAttribute($field))) {
                $differing[] = $field;
            }

            unset($attributes[$field]);
        }

        if ($differing !== []) {
            $this->warn(
                "{$resource}: {$reason} -- abweichende Dateiwerte fuer ".implode(', ', $differing).' nicht uebernommen. '
                .'Studio-Stand nach content/ holen: content:export; bewusst zuruecksetzen: content:sync --force-from-files.',
            );
        }

        return $attributes;
    }

    private static function sameFieldValue(string $field, mixed $fromFile, mixed $inDb): bool
    {
        return match ($field) {
            'sandbox' => ContentFieldComparison::same(ContentFieldComparison::sandbox($fromFile), ContentFieldComparison::sandbox($inDb)),
            'related_node' => ContentFieldComparison::same(ContentFieldComparison::relatedNode($fromFile), ContentFieldComparison::relatedNode($inDb)),
            'body' => str_replace("\r\n", "\n", (string) $fromFile) === str_replace("\r\n", "\n", (string) $inDb),
            default => ContentFieldComparison::same($fromFile, $inDb),
        };
    }

    /**
     * @param  list<int>  $versionIds
     */
    private static function versionReason(array $versionIds): string
    {
        return 'in Studio veroeffentlicht (ContentVersion #'.implode(', #', $versionIds).')';
    }

    /**
     * @return array<string, int> Themenfeld-Slug => DB-ID
     */
    private function syncThemenfelder(ContentRepository $content): array
    {
        $ids = [];

        foreach ($content->themenfelder() as $themenfeld) {
            $model = Themenfeld::updateOrCreate(
                ['slug' => $themenfeld['slug']],
                [
                    'order' => $themenfeld['order'],
                    'title_key' => $themenfeld['title_key'],
                    'status' => $themenfeld['status'],
                ],
            );

            $ids[$themenfeld['slug']] = $model->id;
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $themenfeldIds
     * @return array<string, int> Track-Slug => DB-ID
     */
    private function syncTracks(ContentRepository $content, array $themenfeldIds): array
    {
        $ids = [];

        foreach ($content->tracks() as $track) {
            $themenfeldSlug = $track['themenfeld'] ?? null;

            if (! isset($themenfeldIds[$themenfeldSlug])) {
                $this->warn("Track {$track['slug']}: unbekanntes Themenfeld \"{$themenfeldSlug}\" — uebersprungen.");

                continue;
            }

            $attributes = [
                'themenfeld_id' => $themenfeldIds[$themenfeldSlug],
                'order' => $track['order'],
                'title_key' => $track['title_key'],
                'level' => $track['level'],
                'hours' => $track['hours'],
                'status' => $track['status'],
            ];

            // ADR 0122, Regel B: StudioTrackController setzt diese Felder
            // direkt -- nach dem Anlegen gilt die DB (wie title/teaser).
            $existing = Track::query()->where('slug', $track['slug'])->first();

            if ($existing !== null && ! $this->forceFromFiles) {
                $attributes = $this->withoutStudioFields($attributes, $existing, self::TRACK_STUDIO_FIELDS, "Track {$track['slug']}", 'Einstellungen werden in Studio gepflegt');
            }

            $model = Track::updateOrCreate(['slug' => $track['slug']], $attributes);

            $ids[$track['slug']] = $model->id;
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $trackIds
     */
    private function syncLessons(ContentRepository $content, array $trackIds): int
    {
        $count = 0;

        foreach ($content->lessons() as $id => $lesson) {
            if ($lesson['meta'] === null || $lesson['frontmatter'] === null) {
                $this->warn("Lektion {$id}: meta.yml oder de.md fehlt/unlesbar — uebersprungen.");

                continue;
            }

            $trackSlug = $lesson['meta']['track'] ?? null;

            if (! isset($trackIds[$trackSlug])) {
                $this->warn("Lektion {$id}: unbekannter Track \"{$trackSlug}\" — uebersprungen.");

                continue;
            }

            $status = $lesson['meta']['status'] ?? 'draft';
            // Freitext aus dem Bestand (z. B. "ley338"), nie auf ein
            // Nutzerkonto aufgeloest (docs/offene-fragen.md) -- landet nur in
            // legacy_authors, nie in der echten activity_authors-Pivot.
            $legacyAuthors = $lesson['meta']['authors'] ?? [];
            $title = ['de' => $lesson['frontmatter']['title'] ?? ''];
            $teaser = ['de' => $lesson['frontmatter']['teaser'] ?? ''];
            $order = $lesson['meta']['order'] ?? 0;
            $sourceHash = hash('sha256', $lesson['meta_raw'].$lesson['md_raw']);

            // Studio-Lessons-Umbau (Source-of-Truth-Cutover): track_id/order
            // werden nur beim ALLERERSTEN Sync einer Lesson aus der Datei
            // gesetzt. Sobald die Zeile existiert, ist Studio/DB fuer diese
            // beiden Felder authoritativ (StudioTrackController::
            // moveLesson()/reorderLessons()) -- ein erneuter content:sync
            // darf eine dort vorgenommene Verschiebung/Umsortierung nicht
            // stillschweigend rueckgaengig machen. Dieselbe Schutzidee wie
            // Track::title/teaser (syncTracks() oben laesst diese Felder
            // grundsaetzlich unangetastet), hier nur nachtraeglich statt von
            // Anfang an, weil track_id/order anders als title/teaser beim
            // ersten Sync ueberhaupt erst einen Wert bekommen muessen -- es
            // gibt (noch) keinen rein-Studio-seitigen Lesson-Anlageweg.
            $existingLesson = Lesson::query()->where('lesson_id', $id)->first();

            // CMS-7d.3 (ADR 0118) hat den Lernpfad auf `rich_content` als
            // kanonische PROSA-Quelle umgestellt -- `LearnerViewBuilder`
            // rendert die Prosa nur noch aus `body`, wenn `rich_content`
            // NULL ist. Das betrifft NICHT die Metadaten-Spalten weiter
            // unten (title/objectives/tools/requires/... werden immer aus
            // der Datei geschrieben, unabhaengig vom Cutover-Status) und
            // NICHT den Quiz-Abschnitt (`quiz_raw` kommt laut ADR 0118
            // bewusst immer aus `body`, auch nach dem Cutover). Ein Vergleich
            // gegen den vollen `source_hash` (meta.yml + de.md) waere daher
            // sowohl zu breit (warnt bei reinen Metadaten-/Quiz-Aenderungen,
            // die sehr wohl wirksam werden) als auch am eigentlichen Risiko
            // vorbei -- verglichen wird deshalb ausschliesslich der
            // Prosa-Anteil von `body` (vor einem etwaigen `## Quiz`-
            // Abschnitt plus der Fusstext danach, siehe `QuizContent::
            // splitBody()`), zwischen dem zuletzt synchronisierten Stand und
            // der aktuellen Datei.
            if ($existingLesson !== null
                && $existingLesson->rich_content !== null
                && self::proseOf($existingLesson->body) !== self::proseOf($lesson['body'] ?? null)) {
                $this->warn(
                    "Lektion {$id}: Die Prosa dieser Lektion wird aus rich_content gerendert. ".
                    'Änderungen am Prosa-Teil von de.md werden nicht übernommen; strukturierte '.
                    'Metadaten (meta.yml) und der Quiz-Abschnitt bleiben weiterhin datei-/sync-geführt.',
                );
            }

            $lessonAttributes = [
                'level' => $lesson['meta']['level'] ?? 'einsteiger',
                'duration_minutes' => $lesson['meta']['duration_minutes'] ?? 0,
                'objectives_count' => $lesson['meta']['objectives_count'] ?? 0,
                'requires' => $lesson['meta']['requires'] ?? [],
                'tools' => $lesson['meta']['tools'] ?? [],
                'sandbox' => $lesson['meta']['sandbox'] ?? null,
                'related_node' => $lesson['meta']['related_node'] ?? null,
                'glossary_terms' => $lesson['meta']['glossary_terms'] ?? [],
                'tools_checked' => $lesson['meta']['tools_checked'] ?? null,
                'status' => $status,
                'legacy_authors' => $legacyAuthors,
                'content_updated_at' => $lesson['meta']['updated'] ?? null,
                'title' => $title,
                'teaser' => $teaser,
                // ADR 0101 (CMS-5a): dieselbe Datei, die frontmatter fuer
                // title/teaser liefert, hat auch body/objectives -- kein
                // zusaetzlicher Lesevorgang, nur zwei weitere Spalten.
                'body' => $lesson['body'] ?? null,
                'objectives' => $lesson['frontmatter']['objectives'] ?? [],
                // ADR 0104 (CMS-6a): dieselbe Datei wie objectives/body,
                // nur der quiz:-Block aus meta.yml statt der Frontmatter.
                'quiz' => $lesson['meta']['quiz'] ?? [],
                'source_hash' => $sourceHash,
            ];

            $activityAttributes = [
                'status' => $status,
                'legacy_authors' => $legacyAuthors,
                'title' => $title,
                'teaser' => $teaser,
                'source_hash' => $sourceHash,
            ];

            if ($existingLesson === null) {
                $lessonAttributes['track_id'] = $trackIds[$trackSlug];
                $lessonAttributes['order'] = $order;
                $activityAttributes['track_id'] = $trackIds[$trackSlug];
                $activityAttributes['order'] = $order;
            }

            // ADR 0122, Regel A: in Studio veroeffentlichte Felder gewinnen.
            if ($existingLesson !== null && ! $this->forceFromFiles) {
                if (isset($this->studioVersions['lesson'][$id])) {
                    $lessonAttributes = $this->withoutStudioFields($lessonAttributes, $existingLesson, self::LESSON_STUDIO_FIELDS, "Lektion {$id}", self::versionReason($this->studioVersions['lesson'][$id]));
                    unset($activityAttributes['title'], $activityAttributes['teaser']);
                    $title = $existingLesson->title;
                }

                if (isset($this->studioVersions['quiz'][$id])) {
                    $lessonAttributes = $this->withoutStudioFields($lessonAttributes, $existingLesson, self::QUIZ_STUDIO_FIELDS, "Lektion {$id} (Quiz)", self::versionReason($this->studioVersions['quiz'][$id]));
                }
            }

            Lesson::updateOrCreate(['lesson_id' => $id], $lessonAttributes);
            Activity::updateOrCreate(['type' => 'lesson', 'key' => $id], $activityAttributes);

            // ADR 0096 (CMS-2b): eine Spielwiese bekommt nur dann einen
            // eigenen activities-Verzeichniseintrag, wenn die Lektion
            // tatsaechlich eine hat (sandbox.dataset gesetzt) -- anders als
            // bei Lesson/Node/Exam gibt es hier keine 1:1-Entsprechung.
            // Wird eine Spielwiese spaeter aus einer Lektion entfernt, bleibt
            // ihr Activity-Eintrag bestehen (content:sync loescht nie, siehe
            // docs/offene-fragen.md).
            if (($lesson['meta']['sandbox']['dataset'] ?? null) !== null) {
                $this->syncLessonChildActivity('sandbox', $id, $trackIds[$trackSlug], $order, $status, $title, $sourceHash);
            }

            // ADR 0104/0105 (CMS-6a/CMS-6b): analog zur Spielwiese oben --
            // nur wenn die Lektion tatsaechlich ein Quiz hat (quiz:-Block in
            // meta.yml nicht leer).
            if (($lesson['meta']['quiz'] ?? []) !== []) {
                $this->syncLessonChildActivity('quiz', $id, $trackIds[$trackSlug], $order, $status, $title, $sourceHash);
            }

            $count++;
        }

        return $count;
    }

    /**
     * Spielwiesen-/Quiz-Eintrag einer Lektion. `track_id`/`order` nur beim
     * Anlegen (ADR 0122, Regel B) -- vorher ueberschrieb jeder Lauf sie mit
     * dem Dateistand, obwohl Studio die Lektion selbst (und deren eigenen
     * Activity-Eintrag) laengst verschoben haben konnte.
     *
     * @param  array<string, string>  $title
     */
    private function syncLessonChildActivity(string $type, string $key, int $trackId, int $order, string $status, array $title, string $sourceHash): void
    {
        $attributes = ['status' => $status, 'title' => $title, 'source_hash' => $sourceHash];

        if ($this->forceFromFiles || ! Activity::query()->where('type', $type)->where('key', $key)->exists()) {
            $attributes['track_id'] = $trackId;
            $attributes['order'] = $order;
        }

        Activity::updateOrCreate(['type' => $type, 'key' => $key], $attributes);
    }

    /**
     * Backfuellt die geordnete Elementsequenz einer Lektion (ADR 0105,
     * CMS-6b) -- lesson_elements kennt "content:sync" bewusst nicht: diese
     * Methode legt nur FEHLENDE kanonische Slots an (Content, Sandbox, Lab,
     * Quiz, in dieser Reihenfolge, nur wenn das jeweilige Element
     * existiert) und ruehrt NIE eine bereits vorhandene Zeile an -- eine
     * spaeter in Studio (CMS-6c) per Drag & Drop geaenderte Reihenfolge
     * bleibt so ueber jeden weiteren Sync-Lauf hinweg erhalten. Laeuft erst
     * NACH syncNodes(), weil eine Lab-Referenz die `type=node`-Activity des
     * verlinkten Node braucht, die syncLessons() allein noch nicht anlegt.
     */
    private function syncLessonElements(ContentRepository $content): int
    {
        $count = 0;

        foreach ($content->lessons() as $id => $lessonData) {
            $lesson = Lesson::where('lesson_id', $id)->first();

            if ($lesson === null) {
                continue;
            }

            $slots = [['type' => 'content', 'activity_id' => null]];

            $sandboxActivityId = Activity::query()->where('type', 'sandbox')->where('key', $id)->value('id');
            if ($sandboxActivityId !== null) {
                $slots[] = ['type' => 'activity', 'activity_id' => $sandboxActivityId];
            }

            $relatedNodeSlug = $lessonData['meta']['related_node']['node'] ?? null;
            if ($relatedNodeSlug !== null) {
                $nodeActivityId = Activity::query()->where('type', 'node')->where('key', $relatedNodeSlug)->value('id');
                if ($nodeActivityId !== null) {
                    $slots[] = ['type' => 'activity', 'activity_id' => $nodeActivityId];
                }
            }

            $quizActivityId = Activity::query()->where('type', 'quiz')->where('key', $id)->value('id');
            if ($quizActivityId !== null) {
                $slots[] = ['type' => 'activity', 'activity_id' => $quizActivityId];
            }

            foreach ($slots as $slot) {
                $alreadyExists = LessonElement::query()
                    ->where('lesson_id', $lesson->id)
                    ->where('type', $slot['type'])
                    ->where('activity_id', $slot['activity_id'])
                    ->exists();

                if ($alreadyExists) {
                    continue;
                }

                $nextPosition = (LessonElement::query()->where('lesson_id', $lesson->id)->max('position') ?? -1) + 1;

                LessonElement::create([
                    'lesson_id' => $lesson->id,
                    'type' => $slot['type'],
                    'position' => $nextPosition,
                    'activity_id' => $slot['activity_id'],
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  array<string, int>  $themenfeldIds
     */
    private function syncNodes(ContentRepository $content, array $themenfeldIds): int
    {
        $count = 0;

        foreach ($content->nodes() as $slug => $node) {
            if ($node['def'] === null || $node['frontmatter'] === null) {
                $this->warn("Node {$slug}: node.yml oder de.md fehlt/unlesbar — uebersprungen.");

                continue;
            }

            $themenfeldSlug = $node['def']['themenfeld'] ?? 'dicom';

            if (! isset($themenfeldIds[$themenfeldSlug])) {
                $this->warn("Node {$slug}: unbekanntes Themenfeld \"{$themenfeldSlug}\" — uebersprungen.");

                continue;
            }

            $status = $node['def']['status'] ?? 'draft';
            $title = ['de' => $node['frontmatter']['title'] ?? ''];
            $sourceHash = hash('sha256', $node['def_raw'].$node['md_raw']);

            // Node-Gegenstueck zur Lesson-Warnung oben -- derselbe Cutover
            // (ADR 0118) gilt fuer Nodes identisch (NodeContentPublisher
            // schreibt `rich_content`, nicht mehr `body`; NodeController::
            // show() bevorzugt `rich_content`). Nodes haben keinen
            // Quiz-Sonderfall, aber `node.yml`-Metadaten (difficulty/
            // points/interaction/skills/related_lessons/hints[*].cost/...)
            // bleiben genau wie bei Lessons immer datei-/sync-gefuehrt --
            // verglichen wird deshalb ausschliesslich `body` (Briefing/
            // Hints/Write-up aus de.md), nicht der volle `source_hash`
            // (der auch node.yml einschliesst).
            $existingNode = Node::query()->where('slug', $slug)->first();

            if ($existingNode !== null
                && $existingNode->rich_content !== null
                && $existingNode->body !== ($node['body'] ?? null)) {
                $this->warn(
                    "Node {$slug}: Die Prosa dieser Node wird aus rich_content gerendert. ".
                    'Änderungen am Prosa-Teil von de.md (Briefing/Hints/Write-up) werden nicht '.
                    'übernommen; strukturierte Metadaten (node.yml) bleiben weiterhin datei-/sync-geführt.',
                );
            }

            $nodeAttributes = [
                'difficulty' => $node['def']['difficulty'] ?? 'easy',
                'points' => $node['def']['points'] ?? 0,
                'category' => $node['def']['category'] ?? 'netzwerk',
                'themenfeld_id' => $themenfeldIds[$themenfeldSlug],
                'interaction' => $node['def']['interaction'] ?? 'terminal',
                'skills' => $node['def']['skills'] ?? [],
                'related_lessons' => $node['def']['related_lessons'] ?? [],
                'estimated_minutes' => $node['def']['estimated_minutes'] ?? 0,
                'status' => $status,
                'content_updated_at' => $node['def']['updated'] ?? null,
                'title' => $title,
                'scenario_title' => ['de' => $node['frontmatter']['scenario_title'] ?? ''],
                // ADR 0107 (CMS-6d): dieselbe Datei wie title/
                // scenario_title, nur der vollstaendige Markdown-Body
                // (Briefing/Hints/Write-up) bzw. der hints:-Block aus
                // node.yml.
                'body' => $node['body'] ?? null,
                'hints' => $node['def']['hints'] ?? [],
                'source_hash' => $sourceHash,
            ];

            // Nodes tragen anders als Lektionen/Tracks kein eigenes
            // `order`-Feld (ihre Reihenfolge ergibt sich in NodeController
            // aus Themenfeld/Kategorie/Schwierigkeit/Slug) und kein
            // `authors`-Feld -- beides bleibt hier auf dem Standardwert.
            $activityAttributes = [
                'track_id' => null,
                'status' => $status,
                'title' => $title,
                'source_hash' => $sourceHash,
            ];

            if ($existingNode !== null && ! $this->forceFromFiles) {
                // ADR 0122, Regel B: StudioNodeController::updateThemenfeld().
                $nodeAttributes = $this->withoutStudioFields($nodeAttributes, $existingNode, ['themenfeld_id'], "Node {$slug}", 'Themenfeld wird in Studio gepflegt');

                // ADR 0122, Regel A. Ohne veroeffentlichte Version bleibt
                // `status` datei-gefuehrt (offene Betreiberfrage 1) -- eine
                // Archivierung einer nie veroeffentlichten Node nimmt ein
                // Sync deshalb weiterhin zurueck.
                if (isset($this->studioVersions['node'][$slug])) {
                    $nodeAttributes = $this->withoutStudioFields($nodeAttributes, $existingNode, self::NODE_STUDIO_FIELDS, "Node {$slug}", self::versionReason($this->studioVersions['node'][$slug]));
                    unset($activityAttributes['title'], $activityAttributes['status']);
                }
            }

            Node::updateOrCreate(['slug' => $slug], $nodeAttributes);
            Activity::updateOrCreate(['type' => 'node', 'key' => $slug], $activityAttributes);

            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, int>  $trackIds
     */
    private function syncExamActivities(ContentRepository $content, array $trackIds): int
    {
        $count = 0;

        foreach ($content->exams() as $trackSlug => $exam) {
            if ($exam['meta'] === null || $exam['frontmatter'] === null) {
                $this->warn("Pruefung {$trackSlug}: exam.yml oder de.md fehlt/unlesbar — uebersprungen.");

                continue;
            }

            if (! isset($trackIds[$trackSlug])) {
                $this->warn("Pruefung {$trackSlug}: unbekannter Track \"{$trackSlug}\" — uebersprungen.");

                continue;
            }

            // Pruefungen kennen heute keinen eigenen Entwurfsstatus --
            // exam.yml hat kein `status`-Feld, jede vorhandene Pruefung ist
            // fuer Lernende erreichbar (ExamController: "kein harter
            // Zugangsschutz").
            Activity::updateOrCreate(
                ['type' => 'exam', 'key' => $trackSlug],
                [
                    'track_id' => $trackIds[$trackSlug],
                    'status' => 'published',
                    'title' => ['de' => $exam['frontmatter']['title'] ?? ''],
                    'source_hash' => hash('sha256', $exam['meta_raw'].$exam['md_raw']),
                ],
            );

            $count++;
        }

        return $count;
    }

    /**
     * Der Achievement-Katalog (ADR 0072/0083, W6.4) kennt nur eine
     * Instanz -- anders als Lektion/Node/Pruefung ist ein Achievement kein
     * abschliessbares Fachobjekt, sondern deklaratives Metadatum in einer
     * gemeinsamen Datei. `key === 'catalog'` traegt keinen `track_id`.
     */
    private function syncAchievementCatalogActivity(ContentRepository $content): void
    {
        $achievements = $content->achievements();
        $raw = $achievements[0]['_raw'] ?? '';

        Activity::updateOrCreate(
            ['type' => 'achievement', 'key' => 'catalog'],
            [
                'status' => 'published',
                'title' => ['de' => 'Achievements'],
                'source_hash' => hash('sha256', $raw),
            ],
        );
    }

    /**
     * Reine Prosa eines Lesson-`body` (vor einem etwaigen `## Quiz`-
     * Abschnitt plus der Fusstext danach) -- dieselbe Extraktion wie
     * `LessonPayloadNormalizer`/`LearnerViewBuilder`, hier nur zum
     * Vergleichen zweier Body-Staende genutzt (rich_content-Warnung oben),
     * nie zum Schreiben. `null` bleibt `null`, damit "kein Body" nicht mit
     * "leere Prosa" verwechselt wird.
     */
    private static function proseOf(?string $body): ?string
    {
        if ($body === null) {
            return null;
        }

        $split = QuizContent::splitBody($body);

        return trim($split['after'] !== '' ? $split['before']."\n\n".$split['after'] : $split['before']);
    }
}
