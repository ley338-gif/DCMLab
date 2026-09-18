<?php

namespace App\Content;

use App\Models\Activity;
use App\Models\Lab;
use App\Models\Lesson;
use App\Models\LessonElement;
use Illuminate\Support\Collection;

/**
 * Lab-Content-Lifecycle-Audit, PR #152: baut das deterministische
 * `deploy/labs/<slug>.json`-Artefakt aus dem aktuellen `labs`-/`activities`-/
 * `lesson_elements`-Zustand. Reiner Lesevorgang, keine Nebenwirkungen.
 *
 * Bewusst schema-fest statt generisch fuer beliebige Activity-Typen (siehe
 * PR-Beschreibung, ausdruecklich kein "Deployment-System fuer alle
 * Activity-Typen") -- nur die Felder, die `LabContentPublisher::publish()`
 * tatsaechlich aus einem Payload uebernimmt, plus die Lesson-Platzierung.
 * Keine DB-IDs, keine Zeitstempel, keine Runtime-/Nutzerdaten.
 */
final class LabDeploymentExporter
{
    public const SCHEMA_VERSION = 1;

    /**
     * @return Collection<int, Lab>
     */
    public function exportable(?string $slug = null): Collection
    {
        $query = Lab::query()->where('status', 'published')->orderBy('slug');

        if ($slug !== null) {
            $query->where('slug', $slug);
        }

        return $query->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArtifact(Lab $lab): array
    {
        $activity = Activity::query()->where('type', 'lab')->where('key', $lab->slug)->first();

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'slug' => $lab->slug,
            'lab' => [
                'title' => $lab->title['de'] ?? $lab->slug,
                'scenario_title' => $lab->scenario_title['de'] ?? '',
                'difficulty' => $lab->difficulty,
                'points' => $lab->points,
                'estimated_minutes' => $lab->estimated_minutes,
                'runtime_template' => $lab->runtime_template,
                'dataset' => $lab->dataset,
                'assertions' => $lab->assertions,
                'rich_content' => $lab->rich_content,
            ],
            'placements' => $activity === null ? [] : $this->placementsFor($activity),
        ];
    }

    /**
     * Betreiber-Korrektur: `LabActivity::supports()->reusable === true` --
     * ein Lab kann aus mehreren Lektionen heraus verlinkt sein, `first()`
     * auf die `lesson_elements`-Zeilen dieser Activity waere verlustbehaftet
     * fuer jedes Lab mit mehr als einer Verknuepfung. Nimmt deshalb ALLE
     * Zeilen dieser Activity auf, sortiert deterministisch nach `lesson_id`
     * (Natural Key) dann `position`, damit dieselbe DB-Lage immer dieselbe
     * Artefakt-Reihenfolge ergibt. Nur `lesson_id` (der stabile Natural
     * Key), niemals die numerische `lessons.id`/`activity_id` -- ein
     * Artefakt darf keine Quell-DB-IDs voraussetzen.
     *
     * @return list<array{lesson_id: string, position: int}>
     */
    private function placementsFor(Activity $activity): array
    {
        $elements = LessonElement::query()
            ->where('type', 'activity')
            ->where('activity_id', $activity->id)
            ->with('lesson:id,lesson_id')
            ->get();

        $sorted = $elements
            ->filter(fn (LessonElement $element): bool => $element->lesson !== null)
            ->map(fn (LessonElement $element): array => [
                'lesson_id' => $element->lesson->lesson_id,
                'position' => $element->position,
            ])
            ->sort(fn (array $a, array $b): int => $a['lesson_id'] <=> $b['lesson_id'] ?: $a['position'] <=> $b['position'])
            ->all();

        return array_values($sorted);
    }

    /**
     * Feste Schluesselreihenfolge (siehe `toArtifact()`) + `JSON_PRETTY_PRINT`
     * ergeben bei unveraendertem `$artifact` immer dieselbe Byte-Folge --
     * Determinismus ist damit eine Eigenschaft von `toArtifact()` selbst,
     * diese Methode fuegt nur den Zeilenumbruch am Dateiende hinzu (saubere
     * Diffs, POSIX-Konvention).
     *
     * @param  array<string, mixed>  $artifact
     */
    public function encode(array $artifact): string
    {
        return json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
    }
}
