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
     * @throws RuntimeException wenn die Artefakt-STRUKTUR selbst nicht dem
     *                          erwarteten Schema entspricht (fehlende/falsch
     *                          typisierte Top-Level-Keys, unbekannte
     *                          `schema_version`), ODER wenn das Artefakt
     *                          gegen LabActivity::validate() nicht besteht --
     *                          in beiden Faellen wirft dies VOR jeder
     *                          Schreiboperation, es entsteht nie eine
     *                          teilweise importierte Ressource. Der
     *                          restliche Import selbst laeuft in einer
     *                          Transaktion, damit ein unerwarteter Fehler
     *                          dabei ebenfalls nichts Halbes hinterlaesst.
     */
    public function import(array $artifact): void
    {
        $this->assertStructurallyValid($artifact);

        $slug = (string) $artifact['slug'];
        /** @var array<string, mixed> $payload */
        $payload = $artifact['lab'];
        /** @var list<array{lesson_id: string, position: int}> $placements */
        $placements = $artifact['placements'];

        $issues = $this->validate($slug, $payload);

        if ($issues !== []) {
            $summary = implode('; ', array_map(fn (ContentIssue $issue): string => (string) $issue, $issues));

            throw new RuntimeException("Import von \"{$slug}\" abgebrochen: {$summary}");
        }

        DB::transaction(function () use ($slug, $payload, $placements): void {
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

            $this->syncPlacements($activity, $placements);
        });
    }

    /**
     * Betreiber-Vorgabe: die Top-Level-Struktur explizit pruefen, BEVOR
     * irgendein Schluessel gelesen wird -- ein syntaktisch gueltiges, aber
     * strukturell falsches JSON darf nie mit einer PHP-Warnung/einem
     * TypeError (fehlender Array-Schluessel, falscher Typ) den gesamten
     * `labs:import`-Lauf zum Absturz bringen, sondern immer mit genau dieser
     * verstaendlichen RuntimeException abbrechen. Jede einzelne Pruefung
     * steht bewusst VOR dem naechsten Zugriff auf denselben Schluessel.
     *
     * @param  array<string, mixed>  $artifact
     */
    private function assertStructurallyValid(array $artifact): void
    {
        if (! array_key_exists('schema_version', $artifact)) {
            throw new RuntimeException('Ungueltiges Lab-Artefakt: "schema_version" fehlt.');
        }

        if (! is_int($artifact['schema_version']) || $artifact['schema_version'] !== LabDeploymentExporter::SCHEMA_VERSION) {
            $given = is_scalar($artifact['schema_version']) ? (string) $artifact['schema_version'] : gettype($artifact['schema_version']);

            throw new RuntimeException(
                "Nicht unterstuetzte Lab-Artifact-Version: {$given} (unterstuetzt: ".LabDeploymentExporter::SCHEMA_VERSION.')',
            );
        }

        if (! array_key_exists('slug', $artifact)
            || ! is_string($artifact['slug'])
            || $artifact['slug'] === ''
            || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $artifact['slug'])) {
            throw new RuntimeException('Ungueltiges Lab-Artefakt: "slug" fehlt oder ist kein gueltiger Slug.');
        }

        if (! array_key_exists('lab', $artifact) || ! is_array($artifact['lab'])) {
            throw new RuntimeException('Ungueltiges Lab-Artefakt: "lab" fehlt oder ist kein Objekt.');
        }

        if (! array_key_exists('placements', $artifact) || ! is_array($artifact['placements']) || ! array_is_list($artifact['placements'])) {
            throw new RuntimeException('Ungueltiges Lab-Artefakt: "placements" fehlt oder ist keine Liste.');
        }

        foreach ($artifact['placements'] as $index => $placement) {
            if (! is_array($placement)
                || ! array_key_exists('lesson_id', $placement) || ! is_string($placement['lesson_id']) || $placement['lesson_id'] === ''
                || ! array_key_exists('position', $placement) || ! is_int($placement['position'])) {
                throw new RuntimeException(
                    "Ungueltiges Lab-Artefakt: placements[{$index}] hat nicht die erwartete Struktur (lesson_id: string, position: int).",
                );
            }
        }
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
     * `LabActivity::supports()->reusable === true` -- ein Lab kann aus
     * mehreren Lektionen heraus verlinkt sein, `placements` ist deshalb seit
     * der Betreiber-Korrektur eine Liste, kein Singular mehr. Eigentuemer-
     * schaft ueber `activity_id`, nicht ueber `lesson_id`: dieses Lab darf
     * ausschliesslich SEINE EIGENEN `lesson_elements`-Zeilen anfassen, nie
     * eine fremde. Diff nach Ziel-`lesson_id` (der numerischen `lessons.id`,
     * nicht dem Natural Key): eine im Artefakt weiterhin vorhandene
     * Verknuepfung wird an Ort und Stelle aktualisiert (nur bei
     * tatsaechlicher Aenderung der Position), eine nicht mehr enthaltene
     * wird geloescht, eine neu hinzugekommene angelegt -- so entsteht nie
     * eine verwaiste alte Zeile und nie ein Duplikat bei wiederholtem
     * Import. Eine leere Liste bedeutet: Lab wird (wieder) standalone.
     *
     * Bewusst NICHT geprueft: ob die Ziel-Lesson/-Position bereits von einem
     * ANDEREN LessonElement belegt ist (Kollisionsvermeidung/Renumbering
     * anderer Elemente) -- ausserhalb des #152-Scopes, siehe PR-Beschreibung
     * und Testabdeckung.
     *
     * @param  list<array{lesson_id: string, position: int}>  $placements
     */
    private function syncPlacements(Activity $activity, array $placements): void
    {
        $owned = LessonElement::query()
            ->where('type', 'activity')
            ->where('activity_id', $activity->id)
            ->get()
            ->keyBy('lesson_id');

        $desiredLessonIds = [];

        foreach ($placements as $placement) {
            $lesson = Lesson::query()->where('lesson_id', $placement['lesson_id'])->firstOrFail();
            $desiredLessonIds[$lesson->id] = true;

            $existing = $owned->get($lesson->id);

            if ($existing !== null) {
                if ($existing->position !== $placement['position']) {
                    $existing->update(['position' => $placement['position']]);
                }

                continue;
            }

            LessonElement::query()->create([
                'lesson_id' => $lesson->id,
                'type' => 'activity',
                'position' => $placement['position'],
                'activity_id' => $activity->id,
            ]);
        }

        foreach ($owned as $lessonId => $element) {
            if (! array_key_exists($lessonId, $desiredLessonIds)) {
                $element->delete();
            }
        }
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
