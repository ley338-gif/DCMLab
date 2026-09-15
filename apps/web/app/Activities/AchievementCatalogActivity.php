<?php

namespace App\Activities;

use App\Content\AchievementCatalogGenerator;
use App\Content\ContentIssue;
use App\Content\ContentRepository;
use App\Content\ContentValidator;
use App\Models\User;
use Symfony\Component\Yaml\Yaml;

/**
 * Aktivitaetsvertrag fuer den Achievement-Katalog als Ganzes (ADR 0072/0083,
 * W6.4) -- ein sechster, bewusst untypischer Aktivitaetstyp: anders als
 * Lektion/Quiz/Pruefung/Node/Spielwiese ist ein Achievement keine von
 * Lernenden abschliessbare Einheit, sondern deklaratives Metadatum
 * (`unlock_when`, ausgewertet von AchievementUnlockEvaluator). Es gibt
 * deshalb genau eine Instanz dieses Typs (`key() === 'catalog'`), die die
 * gesamte Datei `content/achievements.yml` repraesentiert; welcher
 * einzelne Eintrag bearbeitet wird, steht im Entwurf selbst (`$draft['slug']`),
 * nicht in der Aktivitaets-Identitaet -- ADR 0072 sieht genau das als
 * Erweiterungsweg vor ("ein sechster Typ registriert sich einfach
 * zusaetzlich, ohne dass dieses Enum angefasst werden muss").
 *
 * `result()`/`learnerView()` haben fuer diesen Typ keine sinnvolle Fachlogik
 * (ActivitySupports::tracksCompletion = false) -- sie erfuellen nur den
 * Vertrag.
 */
final readonly class AchievementCatalogActivity implements ActivityContract
{
    public function __construct(
        private ContentRepository $content,
    ) {}

    public function activityType(): string
    {
        return ActivityType::Achievement->value;
    }

    public function key(): string
    {
        return 'catalog';
    }

    public function supports(): ActivitySupports
    {
        return new ActivitySupports(
            isGraded: false,
            tracksCompletion: false,
            needsContainer: false,
            authorable: true,
            freelyPlaceable: false,
            // versionable: serialize($draft) wertet den Entwurf tatsaechlich
            // aus (siehe oben).
            versionable: true,
            // reusable: ein Achievement-Eintrag wird ueber `unlock_when`
            // typischerweise auf beliebige andere Aktivitaeten (Lektion,
            // Node, Pruefung, ...) angewendet -- keine exklusive 1:1-Bindung.
            reusable: true,
        );
    }

    public function learnerView(User $user): array
    {
        return ['type' => $this->activityType()];
    }

    public function authorView(): array
    {
        return [
            ['key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true],
            ['key' => 'description', 'type' => 'text', 'label' => 'Beschreibung', 'required' => true],
            ['key' => 'image', 'type' => 'text', 'label' => 'Bilddatei', 'required' => true],
            ['key' => 'category', 'type' => 'text', 'label' => 'Kategorie', 'required' => true],
            ['key' => 'rarity', 'type' => 'text', 'label' => 'Seltenheit', 'required' => false],
            ['key' => 'scope', 'type' => 'text', 'label' => 'Geltungsbereich', 'required' => false],
            ['key' => 'points', 'type' => 'number', 'label' => 'Punkte', 'required' => true],
            ['key' => 'is_hidden', 'type' => 'boolean', 'label' => 'Versteckt', 'required' => true],
            ['key' => 'sort_order', 'type' => 'number', 'label' => 'Sortierung', 'required' => true],
            ['key' => 'unlock_when', 'type' => 'json', 'label' => 'Auslösekriterium', 'required' => false],
        ];
    }

    /**
     * Mit einem Entwurf werden nicht die Befunde des gesamten Katalogs
     * ignoriert, sondern ALLE Befunde aus achievements.yml zurueckgegeben
     * (nicht nur die des bearbeiteten Slugs) -- anders als bei Lektion/
     * Pruefung mit je eigener Datei ist achievements.yml eine von allen
     * Eintraegen gemeinsam genutzte Datei, ein Slug-Filter waere eine
     * zweite, schwaechere Pruefung.
     */
    public function validate(?array $draft = null): array
    {
        $achievements = $this->content->achievements();

        if ($draft !== null) {
            $achievements = $this->syntheticAchievements($draft);
        }

        return array_values(array_filter(
            (new ContentValidator)->validate(
                themenfelder: $this->content->themenfelder(),
                tracks: $this->content->tracks(),
                achievements: $achievements,
                lessons: $this->content->lessons(),
                nodes: $this->content->nodes(),
                exams: $this->content->exams(),
                tools: $this->content->tools(),
                toolsRaw: $this->content->toolsRaw(),
                glossary: $this->content->glossary(),
                datasets: $this->content->datasets(),
                skills: $this->content->skills(),
            ),
            fn (ContentIssue $issue): bool => str_starts_with($issue->file, 'achievements.yml'),
        ));
    }

    /**
     * Ohne Entwurf identisch zum Ist-Zustand (ADR 0073). Mit Entwurf
     * ersetzt/ergaenzt `AchievementCatalogGenerator` chirurgisch genau den
     * Eintrag mit `$draft['slug']` -- jeder andere Eintrag bleibt
     * unangetastet.
     */
    public function serialize(?array $draft = null): array
    {
        $raw = $this->rawFile();

        if ($draft === null) {
            return $raw === '' ? [] : [['path' => 'achievements.yml', 'contents' => $raw]];
        }

        $slug = (string) ($draft['slug'] ?? '');
        $regenerated = AchievementCatalogGenerator::regenerateEntry($raw, $slug, $draft);

        return [['path' => 'achievements.yml', 'contents' => $regenerated]];
    }

    public function deserialize(): array
    {
        return ['achievements' => $this->content->achievements()];
    }

    public function result(User $user): ?ActivityResult
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return list<array<string, mixed>>
     */
    private function syntheticAchievements(array $draft): array
    {
        $files = $this->serialize($draft);
        $regeneratedRaw = $files[0]['contents'] ?? '';
        $parsed = Yaml::parse($regeneratedRaw) ?? [];

        return array_map(
            fn (array $achievement, int|string $index): array => $achievement + [
                '_file' => 'achievements.yml', '_raw' => $regeneratedRaw, '_index' => $index,
            ],
            $parsed,
            array_keys($parsed),
        );
    }

    private function rawFile(): string
    {
        $entries = $this->content->achievements();

        return $entries[0]['_raw'] ?? '';
    }
}
