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
 *   DB::transaction:
 *     Activity-Zeile sperren (lockForUpdate())  -- CMS-7d.4: serialisiert
 *                                                  jeden Publish/Restore
 *                                                  derselben Activity, siehe
 *                                                  isLegacyRestoreBlocked()
 *     Legacy-Kompatibilitaetspruefung
 *     normalize(payload)          -- Legacy-`body` -> `rich_content`, siehe
 *                                     LessonPayloadNormalizer/NodePayloadNormalizer
 *     validate(normalized)        -- eine ungueltige Version ist ein
 *                                     normaler, erwarteter Ausgang (Autor
 *                                     bekommt Befunde zurueck); eine reine
 *                                     Lese-/Ablehnungs-Transaktion ohne
 *                                     Schreibvorgang committet folgenlos
 *     ActivityContentApplier::apply()   -- schreibt Lesson/Node/Activity
 *     ContentVersioningService-Logik    -- is_current umhaengen, Version
 *                                          veroeffentlichen/neu anlegen
 *
 * `ActivityContentApplier::apply()` bleibt unveraendert bestehen (entscheidet
 * weiterhin Lesson/Quiz/Node/ContentWriter) -- nur der Aufrufzeitpunkt
 * wandert in die Transaktion. Seit CMS-7d.4 laeuft die gesamte Kette
 * (inklusive normalize()/validate()) innerhalb der gesperrten Transaktion,
 * nicht mehr nur apply()+Versionswechsel -- noetig, damit die neue
 * Legacy-Kompatibilitaetspruefung race-sicher gegen einen konkurrierenden
 * Publish/Restore derselben Activity ist.
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
        return DB::transaction(function () use ($version, $reviewer): array {
            // CMS-7d.4 (Betreiber-Review): die Activity-Zeile wird als
            // ALLERERSTES gesperrt -- jeder Publish/Restore dieser Activity
            // serialisiert sich dadurch gegen jeden anderen, nicht nur der
            // Legacy-Zweig unten. Ohne diesen Lock koennte ein nativer
            // Publish genau zwischen der Legacy-Pruefung und dem
            // tatsaechlichen Schreiben eines anderen Vorgangs committen --
            // dieselbe Fehlerklasse, die der atomare Publish/Restore in
            // ADR 0118 fuer Live-Daten vs. Versionshistorie schon geschlossen
            // hat, hier fuer die Legacy-Kompatibilitaetspruefung selbst.
            $activity = Activity::query()->whereKey($version->activity_id)->lockForUpdate()->firstOrFail();

            if ($this->isLegacyRestoreBlocked($activity, $version->payload)) {
                return [new ContentIssue(
                    "lessons/{$activity->key}/de.md",
                    null,
                    'Diese alte Version kann nicht mehr automatisch veroeffentlicht werden -- '
                        .'die Lektion wurde seitdem im Rich-Content-Editor veraendert. Bitte im '
                        .'Editor neu speichern und erneut einreichen.',
                )];
            }

            $normalized = $this->normalize($activity, $version->payload);
            $issues = $this->registry->resolve($activity)->validate($normalized);

            if ($issues !== []) {
                return $issues;
            }

            $this->applyOrFail($activity, $normalized);
            $this->versions->publish($version, $reviewer);

            return [];
        });
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

        return DB::transaction(function () use ($source, $performedBy): ContentVersion {
            // CMS-7d.4 (Betreiber-Review): siehe publish() -- derselbe
            // Activity-Lock, aus demselben Race-Grund. Die eigentliche
            // Legacy-Kompatibilitaetspruefung UND normalize()/validate()
            // laufen deshalb jetzt erst hier, gegen den gesperrten Zustand,
            // nicht mehr davor.
            $activity = Activity::query()->whereKey($source->activity_id)->lockForUpdate()->firstOrFail();

            if ($this->isLegacyRestoreBlocked($activity, $source->payload)) {
                throw new RuntimeException(
                    'Wiederherstellung abgebrochen -- diese historische Version stammt aus dem alten '
                        .'Inhaltsformat. Die Lektion wurde seitdem im Rich-Content-Editor veraendert und '
                        .'kann deshalb nicht mehr verlustfrei automatisch wiederhergestellt werden.',
                );
            }

            $normalized = $this->normalize($activity, $source->payload);
            $issues = $this->registry->resolve($activity)->validate($normalized);

            if ($issues !== []) {
                $summary = implode('; ', array_map(fn (ContentIssue $issue): string => (string) $issue, $issues));

                throw new RuntimeException(
                    "Wiederherstellung abgebrochen -- die historische Version ist gegen die aktuellen Regeln nicht mehr gueltig: {$summary}",
                );
            }

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
     * CMS-7d.4 (Betreiber-Review vor #126s Merge, Nachtrag): die
     * "historisches before + LIVE Lesson::body-after"-Kompatibilitaet aus
     * `normalize()` ist nur so lange eindeutig, wie sich die Lektion seit
     * dem Cutover nicht veraendert hat -- sobald `rich_content` als EIN
     * Dokument im neuen Editor bearbeitet wurde, gibt es keine belastbare
     * Grenze zwischen "before" und "after" mehr. Ein Legacy-Payload
     * (`payload.body` ohne `payload.rich_content`) darf deshalb nur
     * angewendet werden, solange fuer diese Lesson-Activity noch nie eine
     * ECHTE, im Rich-Content-Editor gespeicherte Autorenrevision
     * veroeffentlicht wurde. Node hat kein analoges "nie separat
     * versioniertes" Segment (`NodeSections::parse()` rekonstruiert
     * Briefing/Hints/Write-up immer vollstaendig aus `body`) und bekommt
     * deshalb keine Sperre.
     *
     * @param  array<string, mixed>  $payload
     */
    private function isLegacyRestoreBlocked(Activity $activity, array $payload): bool
    {
        return ! array_key_exists('rich_content', $payload)
            && $activity->type === ActivityType::Lesson->value
            && $this->hasNativeRichContentAuthoringPublish($activity);
    }

    /**
     * `true` heisst genau: "es wurde mindestens einmal eine ECHTE, im
     * Rich-Content-Editor gespeicherte Autorenrevision veroeffentlicht" --
     * monoton (bleibt `true`, sobald einmal erreicht, da jede kuenftige
     * Publish/Restore-Version seit CMS-7d.3 Phase 3 `rich_content` traegt).
     * `whereNull('restored_from_version_id')` filtert `restoreVersion()`-
     * erzeugte Versionen bewusst heraus: eine Wiederherstellung ist selbst
     * keine Autoren-Bearbeitung im neuen Editor -- sonst wuerde der ERSTE
     * Legacy-Restore jeden weiteren sofort blockieren, obwohl niemand das
     * Dokument je angefasst hat. Ein prae-7d.3-Draft, der erst NACH dem
     * Cutover veroeffentlicht wird, zaehlt schon dadurch korrekt nicht mit
     * (`ContentVersioningService::publish()` ersetzt das Payload nicht,
     * es traegt also weiterhin keinen `rich_content`-Schluessel).
     */
    private function hasNativeRichContentAuthoringPublish(Activity $activity): bool
    {
        return $activity->contentVersions()
            ->where('status', 'published')
            ->whereNull('restored_from_version_id')
            ->get()
            ->contains(fn (ContentVersion $v): bool => array_key_exists('rich_content', $v->payload));
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
