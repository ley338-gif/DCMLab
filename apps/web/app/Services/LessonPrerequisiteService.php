<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Setzt `requires` durch (ADR 0071, W5) -- aber weich: `requires` ist laut
 * `docs/content-schema.md` Abschnitt 2 eine fachliche Empfehlung ("sollten
 * vorher gelesen sein"), kein Zugriffsschutz ("Eine Lektion ohne `requires`
 * darf quer eingestiegen werden"). Diese Klasse berechnet deshalb nur,
 * welche Voraussetzungen offen sind, damit die Oberflaeche sie anzeigen und
 * als "gesperrt" markieren kann -- sie verhindert nie den Zugriff selbst.
 */
final class LessonPrerequisiteService
{
    /**
     * @return list<string> lesson_id-Strings, die dieser Nutzer abgeschlossen hat
     */
    private function completedLessonIds(User $user): array
    {
        return array_values(
            Lesson::query()
                ->whereHas('progress', fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->where('status', 'completed'),
                )
                ->pluck('lesson_id')
                ->all(),
        );
    }

    /**
     * @return list<array{lesson_id: string, title: string}> offene Voraussetzungen dieser einen Lektion
     */
    public function unmetFor(User $user, Lesson $lesson): array
    {
        return $this->unmetForMany($user, collect([$lesson]))[$lesson->lesson_id] ?? [];
    }

    /**
     * @param  Collection<int, Lesson>  $lessons
     * @return array<string, list<array{lesson_id: string, title: string}>> offene Voraussetzungen je lesson_id, nur Eintraege mit mindestens einer offenen Voraussetzung
     */
    public function unmetForMany(User $user, Collection $lessons): array
    {
        $completed = $this->completedLessonIds($user);

        $requiredIds = $lessons->flatMap(fn (Lesson $lesson) => $lesson->requires)->unique()->values();

        if ($requiredIds->isEmpty()) {
            return [];
        }

        $titlesById = Lesson::query()
            ->whereIn('lesson_id', $requiredIds)
            ->get()
            ->keyBy('lesson_id');

        $result = [];

        foreach ($lessons as $lesson) {
            $unmetIds = array_values(array_diff($lesson->requires, $completed));

            if ($unmetIds === []) {
                continue;
            }

            $result[$lesson->lesson_id] = array_values(collect($unmetIds)
                ->map(fn (string $id) => [
                    'lesson_id' => $id,
                    'title' => $titlesById->get($id)?->title['de'] ?? $id,
                ])
                ->all());
        }

        return $result;
    }
}
