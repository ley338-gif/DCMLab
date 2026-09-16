<?php

namespace App\Console\Commands;

use App\Content\ContentRepository;
use App\Models\Activity;
use App\Models\Lesson;
use App\Models\LessonElement;
use App\Models\Node;
use App\Models\Themenfeld;
use App\Models\Track;
use Illuminate\Console\Command;

/**
 * Liest content/ ein und aktualisiert den Index in der Datenbank
 * (Abschnitt 7): tracks, lessons, nodes. Die Prosa bleibt im Dateisystem --
 * hier landen nur Slug, Reihenfolge, Titel/Teaser und ein Hash der Quelle.
 *
 * Zusaetzlich (ADR 0072): ein `activities`-Verzeichniseintrag je Lektion,
 * Node und Track-Pruefung, damit sie ueber ActivityRegistry aufloesbar sind.
 */
class ContentSync extends Command
{
    protected $signature = 'content:sync';

    protected $description = 'Aktualisiert den DB-Index (tracks, lessons, nodes, activities) aus content/ (Abschnitt 7)';

    public function handle(ContentRepository $content): int
    {
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

            $model = Track::updateOrCreate(
                ['slug' => $track['slug']],
                [
                    'themenfeld_id' => $themenfeldIds[$themenfeldSlug],
                    'order' => $track['order'],
                    'title_key' => $track['title_key'],
                    'level' => $track['level'],
                    'hours' => $track['hours'],
                    'status' => $track['status'],
                ],
            );

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

            Lesson::updateOrCreate(
                ['lesson_id' => $id],
                [
                    'track_id' => $trackIds[$trackSlug],
                    'order' => $order,
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
                ],
            );

            Activity::updateOrCreate(
                ['type' => 'lesson', 'key' => $id],
                [
                    'track_id' => $trackIds[$trackSlug],
                    'order' => $order,
                    'status' => $status,
                    'legacy_authors' => $legacyAuthors,
                    'title' => $title,
                    'teaser' => $teaser,
                    'source_hash' => $sourceHash,
                ],
            );

            // ADR 0096 (CMS-2b): eine Spielwiese bekommt nur dann einen
            // eigenen activities-Verzeichniseintrag, wenn die Lektion
            // tatsaechlich eine hat (sandbox.dataset gesetzt) -- anders als
            // bei Lesson/Node/Exam gibt es hier keine 1:1-Entsprechung.
            // Wird eine Spielwiese spaeter aus einer Lektion entfernt, bleibt
            // ihr Activity-Eintrag bestehen (content:sync loescht nie, siehe
            // docs/offene-fragen.md).
            if (($lesson['meta']['sandbox']['dataset'] ?? null) !== null) {
                Activity::updateOrCreate(
                    ['type' => 'sandbox', 'key' => $id],
                    [
                        'track_id' => $trackIds[$trackSlug],
                        'order' => $order,
                        'status' => $status,
                        'title' => $title,
                        'source_hash' => $sourceHash,
                    ],
                );
            }

            // ADR 0104/0105 (CMS-6a/CMS-6b): analog zur Spielwiese oben --
            // nur wenn die Lektion tatsaechlich ein Quiz hat (quiz:-Block in
            // meta.yml nicht leer).
            if (($lesson['meta']['quiz'] ?? []) !== []) {
                Activity::updateOrCreate(
                    ['type' => 'quiz', 'key' => $id],
                    [
                        'track_id' => $trackIds[$trackSlug],
                        'order' => $order,
                        'status' => $status,
                        'title' => $title,
                        'source_hash' => $sourceHash,
                    ],
                );
            }

            $count++;
        }

        return $count;
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

            Node::updateOrCreate(
                ['slug' => $slug],
                [
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
                ],
            );

            // Nodes tragen anders als Lektionen/Tracks kein eigenes
            // `order`-Feld (ihre Reihenfolge ergibt sich in NodeController
            // aus Themenfeld/Kategorie/Schwierigkeit/Slug) und kein
            // `authors`-Feld -- beides bleibt hier auf dem Standardwert.
            Activity::updateOrCreate(
                ['type' => 'node', 'key' => $slug],
                [
                    'track_id' => null,
                    'status' => $status,
                    'title' => $title,
                    'source_hash' => $sourceHash,
                ],
            );

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
}
