<?php

namespace App\Content;

use App\Activities\ActivityContract;

/**
 * Gegenstueck zu ContentWriter (ADR 0071, W2): liest den heutigen Bestand
 * ueber ActivityContract::deserialize() als Startversion ein. Bewusst duenn
 * -- solange es `content_drafts` (W3) noch nicht gibt, hat ein Import
 * nirgends hin zu persistieren. Diese Klasse gibt dem Lesepfad schon jetzt
 * einen stabilen Namen und eine getestete Form, an die W3 anknuepft, statt
 * `ActivityContract::deserialize()` direkt verstreut aufzurufen.
 *
 * Der einmalige Bestandsimport und der spaetere, ausdrueckliche
 * Admin-Reimport (ADR 0071 "Schutz der Einbahnstraße", Punkt 3) sind
 * derselbe Aufruf hier -- die Unterscheidung "einmalig vs. Admin-Vorgang mit
 * Warnung" ist eine Frage des Aufrufers (W3), nicht dieser Klasse.
 */
final class ContentImporter
{
    /**
     * @return array<string, mixed>
     */
    public function importDraft(ActivityContract $activity): array
    {
        return $activity->deserialize();
    }

    /**
     * @param  iterable<ActivityContract>  $activities
     * @return array<string, array<string, mixed>> Entwuerfe, indiziert nach ActivityContract::key()
     */
    public function importAll(iterable $activities): array
    {
        $drafts = [];

        foreach ($activities as $activity) {
            $drafts[$activity->key()] = $this->importDraft($activity);
        }

        return $drafts;
    }
}
