<?php

namespace App\Achievements;

/**
 * Einzige Quelle der Wahrheit fuer die Achievement-Definitionen (Auftrag
 * "Achievement-System", Abschnitt 4: nicht im Frontend hartcodieren).
 * AchievementSeeder schreibt dieses Array nach `achievement_definitions`;
 * ContentValidate prueft node.yml-Metadaten gegen slugs() -- beides rein
 * dateibasiert, ohne DB-Zugriff, damit `content:validate` weiterhin ohne
 * Datenbank laeuft.
 */
final class AchievementRegistry
{
    /**
     * @return list<array{slug: string, name: string, description: string, image: string, category: string, rarity: string|null, points: int, is_hidden: bool, sort_order: int}>
     */
    public static function all(): array
    {
        return [
            [
                'slug' => 'first-blood',
                'name' => 'First Blood',
                'description' => 'Löse deinen ersten Lab- oder Node.',
                'image' => 'first-blood.png',
                'category' => 'labs',
                'rarity' => 'common',
                'points' => 0,
                'is_hidden' => false,
                'sort_order' => 10,
            ],
            [
                'slug' => 'association-accepted',
                'name' => 'Association Accepted',
                'description' => 'Stelle deine erste erfolgreiche DICOM Association her.',
                'image' => 'association-accepted.png',
                'category' => 'dicom',
                'rarity' => 'common',
                'points' => 0,
                'is_hidden' => false,
                'sort_order' => 20,
            ],
            [
                'slug' => 'echo-heard',
                'name' => 'Echo Heard',
                'description' => 'Führe dein erstes erfolgreiches C-ECHO durch.',
                'image' => 'echo-heard.png',
                'category' => 'dicom',
                'rarity' => 'common',
                'points' => 0,
                'is_hidden' => false,
                'sort_order' => 30,
            ],
            [
                'slug' => 'store-and-forward',
                'name' => 'Store & Forward',
                'description' => 'Übertrage dein erstes DICOM-Objekt erfolgreich per C-STORE.',
                'image' => 'store-and-forward.png',
                'category' => 'dicom',
                'rarity' => 'common',
                'points' => 0,
                'is_hidden' => false,
                'sort_order' => 40,
            ],
            [
                'slug' => 'worklist-whisperer',
                'name' => 'Worklist Whisperer',
                'description' => 'Meistere deine erste Modality-Worklist-Abfrage.',
                'image' => 'worklist-whisperer.png',
                'category' => 'dicom',
                'rarity' => 'uncommon',
                'points' => 0,
                'is_hidden' => false,
                'sort_order' => 50,
            ],
            [
                'slug' => 'sandbox-starter',
                'name' => 'Sandbox Starter',
                'description' => 'Starte deine erste DCMLab-Spielwiese.',
                'image' => 'sandbox-starter.png',
                'category' => 'platform',
                'rarity' => 'common',
                'points' => 0,
                'is_hidden' => false,
                'sort_order' => 60,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_map(static fn (array $definition): string => $definition['slug'], self::all());
    }
}
