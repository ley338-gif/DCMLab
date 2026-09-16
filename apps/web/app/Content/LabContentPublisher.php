<?php

namespace App\Content;

use App\Models\Activity;
use App\Models\Lab;

/**
 * Wendet einen freigegebenen Lab-Entwurf direkt auf die DB an (CMS-8a,
 * Entscheidung C) -- Gegenstueck zu `NodeContentPublisher` fuer den
 * Lab-Aktivitaetstyp, ohne dessen Datei-Anteil: ein Lab hat kein
 * content/**-Pendant, dieser Publisher ist deshalb die EINZIGE Stelle, die
 * einen Lab-Entwurf jemals persistiert.
 */
final class LabContentPublisher
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(Activity $activity, array $payload): void
    {
        $lab = Lab::where('slug', $activity->key)->firstOrFail();

        $lab->update([
            // Wie bei Node: ein archiviertes Lab bleibt archiviert, jedes
            // andere ist nach seiner ersten echten Freigabe "published".
            'status' => $lab->status === 'archived' ? $lab->status : 'published',
            'title' => ['de' => $payload['title']],
            'scenario_title' => ['de' => $payload['scenario_title']],
            'difficulty' => $payload['difficulty'],
            'points' => $payload['points'],
            'estimated_minutes' => $payload['estimated_minutes'],
            'runtime_template' => $payload['runtime_template'] ?? null,
            'dataset' => $payload['dataset'] ?? null,
            'assertions' => $payload['assertions'] ?? [],
            'rich_content' => $payload['rich_content'],
        ]);

        // Haelt den activities-Verzeichniseintrag (Autoren-Panel,
        // Review-Queue) konsistent mit dem Lab.
        $activity->update([
            'title' => $lab->title,
            'source_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);
    }
}
