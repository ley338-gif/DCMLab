<?php

namespace App\Achievements;

use App\Content\ContentRepository;

/**
 * Duenne Fassade ueber content/achievements.yml (Abschnitt 12), analog zu
 * App\Models\{Track,Lesson,Node} -- die einzige Quelle der Wahrheit ist die
 * YAML-Datei, nicht diese Klasse. AchievementSeeder schreibt all() nach
 * `achievement_definitions`; ContentValidate prueft node.yml-Metadaten
 * gegen slugs() -- beides rein dateibasiert, ohne DB-Zugriff, damit
 * `content:validate` weiterhin ohne Datenbank laeuft.
 */
final class AchievementRegistry
{
    private const FIELDS = [
        'slug', 'name', 'description', 'image', 'category', 'rarity', 'points', 'is_hidden', 'sort_order',
    ];

    /**
     * @return array<int, array<string, mixed>> Felder wie in content/achievements.yml (Abschnitt 12): slug, name, description, image, category, rarity, points, is_hidden, sort_order
     */
    public static function all(): array
    {
        // Bewusst eine eigene ContentRepository-Instanz statt der
        // Container-Bindung: Achievements sind plattformweite Metadaten,
        // kein Content-unter-Test -- Tests, die ContentRepository auf ein
        // Lektions-/Node-Fixture umbiegen, sollen achievements.yml trotzdem
        // aus dem echten content/-Verzeichnis lesen.
        $repository = new ContentRepository(config('content.path'));

        return array_map(
            static fn (array $entry): array => array_intersect_key($entry, array_flip(self::FIELDS)),
            $repository->achievements(),
        );
    }

    /**
     * @return array<int, string>
     */
    public static function slugs(): array
    {
        return array_map(static fn (array $definition): string => (string) $definition['slug'], self::all());
    }
}
