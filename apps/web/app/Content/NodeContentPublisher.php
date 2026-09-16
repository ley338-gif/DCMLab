<?php

namespace App\Content;

use App\Models\Activity;
use App\Models\Node;

/**
 * Wendet einen freigegebenen Node-Entwurf direkt auf die DB an (ADR 0108,
 * CMS-6d Teil 2) -- Gegenstueck zu `LessonContentPublisher` fuer den
 * Node-Aktivitaetstyp. Anders als bei Lesson/Quiz gibt es keinen zweiten
 * Publisher fuer einen Teilbereich: Hints (id/cost, der Text steckt seit
 * CMS-7d.3 in `rich_content.hints`) sind Teil desselben Entwurfs wie jedes
 * andere Node-Feld, nicht an eine eigene content_versions-Zeile gekoppelt.
 *
 * Runtime-/Sicherheitsparameter (`environment`, `flag`) sind nie Teil des
 * Payloads (siehe `NodeActivity::authorView()`/`serialize()`) und werden
 * hier folgerichtig auch nicht angefasst -- sie bleiben datei-/system-
 * gefuehrt (ADR 0107).
 *
 * Seit CMS-7d.3 (ADR 0118) schreibt dieser Publisher `rich_content` (den
 * `node_content`-Umschlag, ADR 0115), nicht mehr `body` -- der Aufrufer
 * (`ContentPublishingService`) hat das Payload vorher immer schon
 * normalisiert (`NodePayloadNormalizer`). `body` wird bewusst NICHT mehr
 * aus dem Entwurf neu erzeugt, bleibt aber als Spalte unangetastet stehen
 * (Legacy-Fallback, siehe `NodeController::show()`).
 */
final class NodeContentPublisher
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(Activity $activity, array $payload): void
    {
        $node = Node::where('slug', $activity->key)->firstOrFail();

        $node->update([
            // Eine archivierte Node bleibt archiviert -- eine Freigabe holt
            // sie nicht heimlich zurueck (dafuer gibt es
            // StudioNodeController::restore()); jede andere Node ist nach
            // ihrer ersten echten Freigabe "published", nicht mehr laenger
            // "draft" (die Studio-Statusanzeige haette sonst nach einer
            // vollstaendigen Freigabe weiterhin "Entwurf" gezeigt).
            'status' => $node->status === 'archived' ? $node->status : 'published',
            'title' => ['de' => $payload['title']],
            'scenario_title' => ['de' => $payload['scenario_title']],
            'difficulty' => $payload['difficulty'],
            'points' => $payload['points'],
            'category' => $payload['category'],
            'interaction' => $payload['interaction'],
            'estimated_minutes' => $payload['estimated_minutes'],
            'skills' => $payload['skills'] ?? [],
            'related_lessons' => $payload['related_lessons'] ?? [],
            'hints' => $payload['hints'] ?? [],
            'rich_content' => $payload['rich_content'],
        ]);

        // Haelt den activities-Verzeichniseintrag (Autoren-Panel,
        // Review-Queue) konsistent mit der Node -- vorher Aufgabe von
        // content:sync, das dieser Pfad jetzt bewusst nicht mehr aufruft.
        $activity->update([
            'title' => $node->title,
            'source_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);
    }
}
