<?php

namespace App\Content;

use App\Models\Activity;
use App\Models\Node;

/**
 * Wendet einen freigegebenen Node-Entwurf direkt auf die DB an (ADR 0108,
 * CMS-6d Teil 2) -- Gegenstueck zu `LessonContentPublisher` fuer den
 * Node-Aktivitaetstyp. Anders als bei Lesson/Quiz gibt es keinen zweiten
 * Publisher fuer einen Teilbereich: Hints (nur id/cost, der Text steckt
 * bereits in `body`) sind Teil desselben Entwurfs wie jedes andere
 * Node-Feld, nicht an eine eigene content_versions-Zeile gekoppelt.
 *
 * Runtime-/Sicherheitsparameter (`environment`, `flag`) sind nie Teil des
 * Payloads (siehe `NodeActivity::authorView()`/`serialize()`) und werden
 * hier folgerichtig auch nicht angefasst -- sie bleiben datei-/system-
 * gefuehrt (ADR 0107).
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
            'body' => rtrim((string) $payload['body'], "\r\n"),
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
