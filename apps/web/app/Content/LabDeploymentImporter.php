<?php

namespace App\Content;

use App\Activities\LabActivity;
use App\Models\Activity;
use App\Models\ContentVersion;
use App\Models\Lab;
use App\Models\Lesson;
use App\Models\LessonElement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Lab-Content-Lifecycle-Audit, PR #152: bringt ein `deploy/labs/<slug>.json`-
 * Artefakt in die DB -- die einzige Stelle, die ein solches Artefakt
 * anwendet. Reine Deployment-Funktion, KEIN zweiter Authoring-Pfad neben
 * Studio: sie ruft dieselbe Validierung (`LabActivity::validate()`) und
 * denselben Live-Schreiber (`LabContentPublisher::publish()`) auf, die
 * `StudioLabController`/`ContentPublishingService` auch fuer eine echte
 * Studio-Freigabe verwenden.
 *
 * Bewusst NICHT ueber `ContentVersioningService::createDraft()` +
 * `submitForReview()` + `ContentPublishingService::publish($version,
 * $reviewer)`: dieser Dreischritt modelliert einen Autor UND einen davon
 * verschiedenen menschlichen Reviewer. Ein automatisierter Deployment-Import
 * hat keine zweite Person -- einen fiktiven zweiten Reviewer vorzutaeuschen
 * waere irrefuehrend, keine sauberere Loesung. `ContentVersioningService::
 * publishSnapshot()` (PR #152) bildet stattdessen genau das ab, was hier
 * tatsaechlich passiert: EIN automatisierter Vorgang, `created_by` UND
 * `reviewed_by` derselbe, klar benannte Deployment-Akteur.
 */
final readonly class LabDeploymentImporter
{
    public function __construct(
        private ContentVersioningService $versions,
        private ContentRepository $content,
        private LabContentPublisher $publisher,
    ) {}

    /**
     * @param  array<string, mixed>  $artifact
     *
     * @throws RuntimeException wenn das Artefakt gegen LabActivity::validate()
     *                          nicht besteht -- wirft VOR jeder Schreib-
     *                          operation, es entsteht nie eine teilweise
     *                          importierte Ressource. Der restliche Import
     *                          selbst laeuft in einer Transaktion, damit ein
     *                          unerwarteter Fehler dabei ebenfalls nichts
     *                          Halbes hinterlaesst.
     */
    public function import(array $artifact): void
    {
        $slug = (string) $artifact['slug'];
        /** @var array<string, mixed> $payload */
        $payload = $artifact['lab'];

        $issues = $this->validate($slug, $payload);

        if ($issues !== []) {
            $summary = implode('; ', array_map(fn (ContentIssue $issue): string => (string) $issue, $issues));

            throw new RuntimeException("Import von \"{$slug}\" abgebrochen: {$summary}");
        }

        DB::transaction(function () use ($slug, $payload, $artifact): void {
            $activity = Activity::query()->firstOrCreate(
                ['type' => 'lab', 'key' => $slug],
                ['status' => 'draft', 'title' => ['de' => $payload['title']]],
            );

            // Notwendiger Platzhalter: LabContentPublisher::publish() loest
            // die Lab-Zeile selbst per firstOrFail() auf -- fuer einen ganz
            // neuen Slug muss sie also schon (minimal) existieren, bevor der
            // Publisher die echten Werte hineinschreibt. `assertions: []`
            // erfuellt die NOT-NULL-Spalte, bis publish() sie ueberschreibt.
            Lab::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'status' => 'draft',
                    'difficulty' => $payload['difficulty'],
                    'points' => 0,
                    'estimated_minutes' => 0,
                    'assertions' => [],
                    'title' => ['de' => $payload['title']],
                    'scenario_title' => ['de' => ''],
                    'rich_content' => null,
                ],
            );

            $this->publisher->publish($activity, $payload);

            $this->publishVersionIfChanged($activity, $payload);

            $this->syncPlacement($activity, $artifact['placement'] ?? null);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<ContentIssue>
     */
    private function validate(string $slug, array $payload): array
    {
        // Unpersistierte Probe-Modelle: LabActivity::validate() braucht nur
        // $this->lab->slug (fuer die ContentIssue-Dateibeschriftung) und den
        // uebergebenen $payload selbst -- keine bereits gespeicherte Zeile
        // noetig, um ein brandneues Lab VOR jeder Schreiboperation zu pruefen.
        $probeActivity = new Activity(['type' => 'lab', 'key' => $slug]);
        $probeLab = new Lab(['slug' => $slug]);

        return (new LabActivity($probeActivity, $probeLab, $this->content))->validate($payload);
    }

    /**
     * Betreiber-Vorgabe: kein Versionsmuell bei inhaltlich identischem
     * erneuten Import -- nur wenn sich das Payload gegenueber der aktuellen
     * veroeffentlichten Version tatsaechlich unterscheidet (`==`, nicht
     * `===`: Schluesselreihenfolge darf keinen Unterschied machen), entsteht
     * eine neue `content_versions`-Zeile. `LabContentPublisher::publish()`
     * oben laeuft trotzdem immer -- ein reines `UPDATE` auf dieselben Werte
     * ist nebenwirkungsfrei und haelt `labs` robust gegen manuelle DB-Drift.
     *
     * @param  array<string, mixed>  $payload
     */
    private function publishVersionIfChanged(Activity $activity, array $payload): void
    {
        $current = ContentVersion::query()
            ->where('activity_id', $activity->id)
            ->where('is_current', true)
            ->first();

        if ($current !== null && $current->payload == $payload) {
            return;
        }

        $this->versions->publishSnapshot($activity, $payload, $this->deploymentActor());
    }

    /**
     * Eigentuemerschaft ueber `activity_id`, nicht ueber `lesson_id`: dieses
     * Lab darf ausschliesslich SEINE EIGENE `lesson_elements`-Zeile anfassen,
     * nie eine fremde. Beim Verschieben (Artefakt zeigt jetzt auf eine
     * andere Lesson/Position) wird dieselbe Zeile aktualisiert, nicht
     * geloescht+neu angelegt -- so bleibt zu jedem Zeitpunkt hoechstens eine
     * Zeile pro Lab-Aktivitaet uebrig, nie eine verwaiste alte. Wird
     * `placement` auf `null` gesetzt (Lab soll standalone sein), wird die
     * eigene Zeile entfernt.
     *
     * Bewusst NICHT geprueft: ob die Ziel-Lesson/-Position bereits von einem
     * ANDEREN LessonElement belegt ist (Kollisionsvermeidung/Renumbering
     * anderer Elemente) -- ausserhalb des #152-Scopes, siehe PR-Beschreibung
     * und Testabdeckung.
     *
     * @param  array{lesson_id: string, position: int}|null  $placement
     */
    private function syncPlacement(Activity $activity, ?array $placement): void
    {
        $owned = LessonElement::query()
            ->where('type', 'activity')
            ->where('activity_id', $activity->id)
            ->first();

        if ($placement === null) {
            $owned?->delete();

            return;
        }

        $lesson = Lesson::query()->where('lesson_id', $placement['lesson_id'])->firstOrFail();

        if ($owned !== null) {
            if ($owned->lesson_id !== $lesson->id || $owned->position !== $placement['position']) {
                $owned->update(['lesson_id' => $lesson->id, 'position' => $placement['position']]);
            }

            return;
        }

        LessonElement::query()->create([
            'lesson_id' => $lesson->id,
            'type' => 'activity',
            'position' => $placement['position'],
            'activity_id' => $activity->id,
        ]);
    }

    /**
     * Betreiber-Vorgabe: kein normaler Login-Nutzer nur fuer den Deployment-
     * Formalismus. Dieses Konto ist ueber sein zufaelliges, nie
     * ausgegebenes Passwort strukturell nicht interaktiv einloggbar (kein
     * Auth-Workaround -- schlicht kein Nutzer, der je ein Passwort erhaelt)
     * und über Name/E-Mail in `content_versions.created_by`/`reviewed_by`
     * eindeutig als automatisierter Deployment-Vorgang erkennbar, nicht als
     * Studio-Review einer Person. `firstOrCreate()`: idempotent, entsteht
     * genau einmal, nicht pro Import-Lauf neu.
     */
    private function deploymentActor(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'content-deployment@dcmlab.internal'],
            [
                'name' => 'Content Deployment',
                'password' => Hash::make(Str::random(64)),
                'email_verified_at' => now(),
            ],
        );
    }
}
