<?php

namespace App\Console\Commands;

use App\Content\ContentRepository;
use App\Models\Lesson;
use App\Models\Node;
use App\Models\Themenfeld;
use App\Models\Track;
use Illuminate\Console\Command;

/**
 * Liest content/ ein und aktualisiert den Index in der Datenbank
 * (Abschnitt 7): tracks, lessons, nodes. Die Prosa bleibt im Dateisystem --
 * hier landen nur Slug, Reihenfolge, Titel/Teaser und ein Hash der Quelle.
 */
class ContentSync extends Command
{
    protected $signature = 'content:sync';

    protected $description = 'Aktualisiert den DB-Index (tracks, lessons, nodes) aus content/ (Abschnitt 7)';

    public function handle(ContentRepository $content): int
    {
        $themenfeldIds = $this->syncThemenfelder($content);
        $trackIds = $this->syncTracks($content, $themenfeldIds);
        $lessonCount = $this->syncLessons($content, $trackIds);
        $nodeCount = $this->syncNodes($content);

        $this->info(sprintf(
            'content:sync — %d Themenfelder, %d Tracks, %d Lektionen, %d Nodes synchronisiert.',
            count($themenfeldIds),
            count($trackIds),
            $lessonCount,
            $nodeCount,
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

            Lesson::updateOrCreate(
                ['lesson_id' => $id],
                [
                    'track_id' => $trackIds[$trackSlug],
                    'order' => $lesson['meta']['order'] ?? 0,
                    'level' => $lesson['meta']['level'] ?? 'einsteiger',
                    'duration_minutes' => $lesson['meta']['duration_minutes'] ?? 0,
                    'objectives_count' => $lesson['meta']['objectives_count'] ?? 0,
                    'requires' => $lesson['meta']['requires'] ?? [],
                    'tools' => $lesson['meta']['tools'] ?? [],
                    'sandbox' => $lesson['meta']['sandbox'] ?? null,
                    'lab' => $lesson['meta']['lab'] ?? null,
                    'glossary_terms' => $lesson['meta']['glossary_terms'] ?? [],
                    'tools_checked' => $lesson['meta']['tools_checked'] ?? null,
                    'status' => $lesson['meta']['status'] ?? 'draft',
                    'authors' => $lesson['meta']['authors'] ?? [],
                    'content_updated_at' => $lesson['meta']['updated'] ?? null,
                    'title' => ['de' => $lesson['frontmatter']['title'] ?? ''],
                    'teaser' => ['de' => $lesson['frontmatter']['teaser'] ?? ''],
                    'source_hash' => hash('sha256', $lesson['meta_raw'].$lesson['md_raw']),
                ],
            );

            $count++;
        }

        return $count;
    }

    private function syncNodes(ContentRepository $content): int
    {
        $count = 0;

        foreach ($content->nodes() as $slug => $node) {
            if ($node['def'] === null || $node['frontmatter'] === null) {
                $this->warn("Node {$slug}: node.yml oder de.md fehlt/unlesbar — uebersprungen.");

                continue;
            }

            Node::updateOrCreate(
                ['slug' => $slug],
                [
                    'difficulty' => $node['def']['difficulty'] ?? 'easy',
                    'points' => $node['def']['points'] ?? 0,
                    'category' => $node['def']['category'] ?? 'netzwerk',
                    'skills' => $node['def']['skills'] ?? [],
                    'related_lessons' => $node['def']['related_lessons'] ?? [],
                    'estimated_minutes' => $node['def']['estimated_minutes'] ?? 0,
                    'status' => $node['def']['status'] ?? 'draft',
                    'content_updated_at' => $node['def']['updated'] ?? null,
                    'title' => ['de' => $node['frontmatter']['title'] ?? ''],
                    'scenario_title' => ['de' => $node['frontmatter']['scenario_title'] ?? ''],
                    'source_hash' => hash('sha256', $node['def_raw'].$node['md_raw']),
                ],
            );

            $count++;
        }

        return $count;
    }
}
