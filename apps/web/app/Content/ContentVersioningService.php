<?php

namespace App\Content;

use App\Models\Activity;
use App\Models\ContentVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Verwaltet den Lebenszyklus einer Aktivitaet ueber `content_versions`
 * (ADR 0071, W3): draft -> review -> published, sowie Rollback auf die
 * vorherige veroeffentlichte Version. Ersetzt die Git-Historie als
 * Aenderungsverlauf.
 *
 * Bewusst getrennt von ContentWriter (W2): eine Version zu veroeffentlichen
 * oder zurueckzusetzen ist hier reine Buchfuehrung ueber `content_versions`.
 * Das tatsaechliche Zurueckschreiben eines `payload` nach content/ setzt
 * voraus, dass ActivityContract::serialize() aus einem gespeicherten
 * payload rendert statt aus dem Ist-Zustand zu lesen (ADR 0073) -- das
 * liefert erst der Autoren-Editor (W6). Siehe ADR 0075 und
 * docs/offene-fragen.md.
 */
final class ContentVersioningService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function createDraft(Activity $activity, array $payload, User $author): ContentVersion
    {
        return ContentVersion::create([
            'activity_id' => $activity->id,
            'status' => 'draft',
            'payload' => $payload,
            'is_current' => false,
            'created_by' => $author->id,
        ]);
    }

    public function submitForReview(ContentVersion $version): ContentVersion
    {
        if ($version->status !== 'draft') {
            throw new RuntimeException('Nur ein Entwurf kann zur Pruefung eingereicht werden.');
        }

        $version->status = 'review';
        $version->save();

        return $version;
    }

    public function publish(ContentVersion $version, User $reviewer): ContentVersion
    {
        if ($version->status !== 'review') {
            throw new RuntimeException('Nur eine Version im Review-Status kann veroeffentlicht werden.');
        }

        DB::transaction(function () use ($version, $reviewer): void {
            ContentVersion::query()
                ->where('activity_id', $version->activity_id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $version->status = 'published';
            $version->is_current = true;
            $version->published_at = now();
            $version->reviewed_by = $reviewer->id;
            $version->save();
        });

        return $version->refresh();
    }

    /**
     * Setzt die aktuell gueltige Version auf die zuletzt davor
     * veroeffentlichte zurueck -- als NEUE, eigene Version mit demselben
     * `payload` (ADR 0102, CMS-5b): eine einmal veroeffentlichte Version
     * wird nie erneut mutiert (weder ihr `payload` noch ihr `status`), nur
     * `is_current` bewegt sich als reiner Zeiger auf die jeweils aktive
     * Version -- das aendert keine Historie rueckwirkend. Wirft, wenn es
     * keine aktuelle oder keine vorherige veroeffentlichte Version gibt.
     *
     * Bewusst weiterhin reine Buchfuehrung (siehe Klassendoc): das
     * tatsaechliche Zurueckschreiben des wiederhergestellten `payload` auf
     * die Lektion/Datei ist Aufgabe von ActivityContentApplier, sobald ein
     * Aufrufer (z. B. eine kuenftige Studio-"Wiederherstellen"-Aktion) beide
     * Schritte kombiniert -- siehe docs/offene-fragen.md.
     */
    public function rollback(Activity $activity, User $performedBy): ContentVersion
    {
        $current = $activity->contentVersions()->where('is_current', true)->first();

        if ($current === null) {
            throw new RuntimeException('Keine aktuelle veroeffentlichte Version zum Zuruecksetzen.');
        }

        $previous = $activity->contentVersions()
            ->where('status', 'published')
            ->where('id', '!=', $current->id)
            ->orderByDesc('published_at')
            ->first();

        if ($previous === null) {
            throw new RuntimeException('Keine vorherige veroeffentlichte Version vorhanden.');
        }

        return DB::transaction(function () use ($current, $previous, $performedBy): ContentVersion {
            $current->is_current = false;
            $current->save();

            return ContentVersion::create([
                'activity_id' => $previous->activity_id,
                'status' => 'published',
                'payload' => $previous->payload,
                'is_current' => true,
                'created_by' => $previous->created_by,
                'reviewed_by' => $performedBy->id,
                'published_at' => now(),
            ]);
        });
    }

    /**
     * Aktivitaeten ohne jede Versionshistorie sind Bestandscontent von vor
     * W3 und bleiben sichtbar, wie sie es vorher waren (ADR 0075) -- diese
     * Methode veraendert deshalb fuer keine heute existierende Aktivitaet
     * das Ergebnis, bis ein Editor (W6) echte Versionen anlegt.
     */
    public function isPublished(Activity $activity): bool
    {
        if (! $activity->contentVersions()->exists()) {
            return true;
        }

        return $activity->contentVersions()
            ->where('is_current', true)
            ->where('status', 'published')
            ->exists();
    }
}
