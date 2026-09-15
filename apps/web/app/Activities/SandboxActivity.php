<?php

namespace App\Activities;

use App\Models\Lesson;
use App\Models\User;

/**
 * Aktivitaetsvertrag fuer die Spielwiese einer Lektion (ADR 0072). Wie Quiz
 * haengt eine Spielwiese heute an genau einer Lektion (`lessons.sandbox`)
 * und hat keinen eigenen `activities`-Verzeichniseintrag -- Instanzen
 * entstehen ueber `new SandboxActivity($lesson)`. ADR 0072 sieht die
 * Spielwiese perspektivisch als frei platzierbar vor; das setzt eine
 * eigene Content-Struktur voraus und ist nicht Teil von W0.
 *
 * Es gibt keinen Bewertungs- oder Abschlusszustand (Laravel haelt ohnehin
 * keinen eigenen Zustand fuer die Spielwiese, siehe SandboxController) --
 * `result()` liefert deshalb immer `null`.
 */
final readonly class SandboxActivity implements ActivityContract
{
    public function __construct(
        private Lesson $lesson,
    ) {}

    public function activityType(): string
    {
        return ActivityType::Sandbox->value;
    }

    public function key(): string
    {
        return $this->lesson->lesson_id;
    }

    public function supports(): ActivitySupports
    {
        return new ActivitySupports(
            isGraded: false,
            tracksCompletion: false,
            needsContainer: true,
            authorable: true,
            freelyPlaceable: false,
            runtimeType: 'container',
            // versionable bleibt false: dieser Vertrag hat kein eigenes
            // Dateiziel, serialize() ignoriert $draft immer (siehe oben) --
            // die Spielwiesen-Konfiguration haengt am content_versions-
            // Datensatz der LEKTION (LessonActivity::serialize($draft['sandbox']),
            // LessonMetaGenerator::regenerateSandbox()). Siehe
            // docs/studio-architecture-plan.md Abschnitt 6 fuer die geplante
            // Aufloesung dieser Kopplung (SandboxTemplate/SandboxActivity/
            // SandboxSession).
        );
    }

    public function learnerView(User $user): array
    {
        return [
            'type' => $this->activityType(),
            'lesson_id' => $this->lesson->lesson_id,
            'dataset' => $this->lesson->sandbox['dataset'] ?? null,
            'note' => $this->lesson->sandbox['note'] ?? null,
        ];
    }

    public function authorView(): array
    {
        return [
            ['key' => 'dataset', 'type' => 'text', 'label' => 'Datensatz', 'required' => true],
            ['key' => 'note', 'type' => 'text', 'label' => 'Hinweis', 'required' => false],
        ];
    }

    public function validate(?array $draft = null): array
    {
        // Bewusst leer, siehe LessonActivity::validate().
        return [];
    }

    public function serialize(?array $draft = null): array
    {
        // Kein eigenes Dateiziel -- die Spielwiese ist Teil von meta.yml der
        // Lektion, siehe LessonActivity::serialize().
        return [];
    }

    public function deserialize(): array
    {
        return [
            'lesson_id' => $this->lesson->lesson_id,
            'dataset' => $this->lesson->sandbox['dataset'] ?? null,
            'note' => $this->lesson->sandbox['note'] ?? null,
        ];
    }

    public function result(User $user): ?ActivityResult
    {
        return null;
    }
}
