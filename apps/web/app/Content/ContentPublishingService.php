<?php

namespace App\Content;

use App\Activities\ActivityRegistry;
use App\Activities\ActivityType;
use App\Content\RichContent\LessonPayloadNormalizer;
use App\Content\RichContent\NodePayloadNormalizer;
use App\Models\Activity;
use App\Models\ContentVersion;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * CMS-7d.3 (ADR 0118). Schliesst eine Luecke, die beim Mapping fuer den
 * Rich-Content-Cutover gefunden wurde: `ContentVersionController::publish()`
 * rief bisher `ActivityContentApplier::apply()` (schreibt live in
 * Lesson/Node) und `ContentVersioningService::publish()` (haengt
 * `is_current` um) NACHEINANDER auf, nicht in einer Transaktion -- schlug
 * der zweite Schritt fehl, waren Live-Daten bereits geaendert, ohne dass
 * die Versionshistorie das je erfahren haette. Dieser Service kapselt
 * beides in EINER Transaktion:
 *
 *   normalize(payload)          -- Legacy-`body` -> `rich_content`, siehe
 *                                   LessonPayloadNormalizer/NodePayloadNormalizer
 *   validate(normalized)        -- ausserhalb der Transaktion: eine
 *                                   ungueltige Version ist ein normaler,
 *                                   erwarteter Ausgang (Autor bekommt
 *                                   Befunde zurueck), keine Transaktion noetig
 *   DB::transaction:
 *     ActivityContentApplier::apply()   -- schreibt Lesson/Node/Activity
 *     ContentVersioningService-Logik    -- is_current umhaengen, Version
 *                                          veroeffentlichen/neu anlegen
 *
 * `ActivityContentApplier::apply()` bleibt unveraendert bestehen (entscheidet
 * weiterhin Lesson/Quiz/Node/ContentWriter) -- nur der Aufrufzeitpunkt
 * wandert in die Transaktion.
 */
final readonly class ContentPublishingService
{
    public function __construct(
        private ActivityRegistry $registry,
        private ActivityContentApplier $applier,
        private ContentVersioningService $versions,
    ) {}

    /**
     * @return list<ContentIssue> nicht leer, wenn NICHTS uebernommen wurde
     *                            -- weder Live-Daten noch die Version
     *                            wurden dann veraendert.
     */
    public function publish(ContentVersion $version, User $reviewer): array
    {
        $activity = $version->activity;
        $normalized = $this->normalize($activity, $version->payload);
        $issues = $this->registry->resolve($activity)->validate($normalized);

        if ($issues !== []) {
            return $issues;
        }

        DB::transaction(function () use ($activity, $normalized, $version, $reviewer): void {
            $this->applyOrFail($activity, $normalized);
            $this->versions->publish($version, $reviewer);
        });

        return [];
    }

    /**
     * Ersetzt die Rolle, die `ContentVersioningService::rollback()` fuer
     * Lesson/Node nie ausgefuellt hat (reine Buchfuehrung, wendet laut
     * eigenem Klassendoc nichts an): normalisiert die historische Fassung,
     * validiert sie gegen die HEUTIGEN Regeln, wendet sie an UND schreibt
     * die neue Version -- alles in einer Transaktion. Alte, unveraendert
     * gebliebene Revisionen (auch mit `payload.body`) werden dabei nie
     * rueckwirkend umgeschrieben, nur die neu entstehende Kopie wird
     * normalisiert.
     *
     * `created_by`/`reviewed_by` sind bewusst `$performedBy`, nicht der
     * historische Autor -- wer eine Wiederherstellung ausloest, zeichnet
     * dafuer verantwortlich, nicht wer die Version urspruenglich schrieb.
     *
     * @throws RuntimeException wenn die historische Fassung gegen die
     *                          heutigen Regeln nicht mehr gueltig ist --
     *                          eine Wiederherstellung, die die Live-Daten
     *                          in einen ungueltigen Zustand versetzen wuerde,
     *                          ist kein normaler, still abzufangender Ausgang.
     */
    public function restoreVersion(ContentVersion $source, User $performedBy): ContentVersion
    {
        // Betreiber-Review vor #126: die Route ist generisch und das
        // `publish`-Gate allein erzwingt nicht, WELCHEN Status `source`
        // hat -- ohne diese Pruefung koennte ein Reviewer ueber denselben
        // Endpunkt einen `draft`/`review` direkt als neue veroeffentlichte
        // Version "wiederherstellen" und damit den normalen
        // draft -> review -> publish-Pfad umgehen. `is_current` bereits
        // wiederherzustellen waere ausserdem wirkungslos (dieselbe Version
        // ist schon aktuell) und deshalb kein sinnvoller Aufruf.
        if ($source->status !== 'published') {
            throw new RuntimeException(
                'Wiederherstellung abgebrochen -- nur eine bereits veroeffentlichte Version kann wiederhergestellt werden.',
            );
        }

        if ($source->is_current) {
            throw new RuntimeException(
                'Wiederherstellung abgebrochen -- diese Version ist bereits die aktuelle.',
            );
        }

        $activity = $source->activity;
        $normalized = $this->normalize($activity, $source->payload);
        $issues = $this->registry->resolve($activity)->validate($normalized);

        if ($issues !== []) {
            $summary = implode('; ', array_map(fn (ContentIssue $issue): string => (string) $issue, $issues));

            throw new RuntimeException(
                "Wiederherstellung abgebrochen -- die historische Version ist gegen die aktuellen Regeln nicht mehr gueltig: {$summary}",
            );
        }

        return DB::transaction(function () use ($activity, $normalized, $source, $performedBy): ContentVersion {
            $this->applyOrFail($activity, $normalized);

            ContentVersion::query()
                ->where('activity_id', $activity->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            return ContentVersion::create([
                'activity_id' => $activity->id,
                'status' => 'published',
                'payload' => $normalized,
                'is_current' => true,
                'created_by' => $performedBy->id,
                'reviewed_by' => $performedBy->id,
                'published_at' => now(),
                'restored_from_version_id' => $source->id,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function applyOrFail(Activity $activity, array $normalized): void
    {
        $issues = $this->applier->apply($activity, $normalized);

        if ($issues !== []) {
            // Kann nur passieren, wenn apply()s eigene, erneute Validierung
            // vom vorab schon bestandenen validate($normalized) abweicht --
            // ein inkonsistenter Zustand, kein normaler Ausgang. Bricht die
            // Transaktion ab, statt eine Teil-Veroeffentlichung zu riskieren.
            throw new RuntimeException(
                'ActivityContentApplier::apply() lieferte Befunde, obwohl die Vorab-Validierung bestanden hatte -- Transaktion abgebrochen.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalize(Activity $activity, array $payload): array
    {
        if ($activity->type === ActivityType::Lesson->value) {
            // Betreiber-Review vor #126: ein Legacy-`payload['body']` ist
            // nur `before` (siehe LessonPayloadNormalizer-Klassendoc) --
            // die LIVE Lesson->body liefert das fehlende `after` nach,
            // sonst wuerde ein Restore/Publish einer alten Revision diesen
            // Teil unbemerkt verlieren.
            $currentBody = Lesson::query()->where('lesson_id', $activity->key)->value('body');

            return (new LessonPayloadNormalizer)->normalize($payload, is_string($currentBody) ? $currentBody : null);
        }

        return match ($activity->type) {
            ActivityType::Node->value => (new NodePayloadNormalizer)->normalize($payload),
            default => $payload,
        };
    }
}
