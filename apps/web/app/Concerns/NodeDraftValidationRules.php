<?php

namespace App\Concerns;

use App\Services\ProfileService;

/**
 * Formregeln eines Node-Entwurfs, wie der Studio-Node-Editor sie erzwingt
 * (`StudioNodeController::update()`/`validateDraft()`). `content:draft-node`
 * prueft einen Datei-Entwurf mit denselben Regeln: `NodeActivity::validate()`
 * kennt z. B. die erlaubten Schwierigkeitsgrade und Skill-Kategorien nicht.
 */
trait NodeDraftValidationRules
{
    /**
     * @return array<string, string>
     */
    protected function nodeDraftRules(): array
    {
        return [
            'title' => 'required|string',
            'scenario_title' => 'required|string',
            'difficulty' => 'required|string|in:easy,medium,hard,insane',
            'points' => 'required|integer|min:0',
            'category' => 'required|string',
            'interaction' => 'required|string|in:terminal,scenario',
            'estimated_minutes' => 'required|integer|min:0',
            'skills' => 'array',
            'skills.*' => 'string|in:'.implode(',', ProfileService::SKILL_CATEGORIES),
            'related_lessons' => 'array',
            'related_lessons.*' => 'string',
            'hints' => 'array',
            'hints.*.id' => 'required|string',
            'hints.*.cost' => 'required|integer|min:0',
            // Nur die grobe Form -- die eigentliche Schema-/Inhaltspruefung
            // (RichContentValidator, Hint-ID-Konsistenz) laeuft ueber
            // activity->validate($draft) (CMS-7d.3, ADR 0118). Bewusst KEINE
            // weiteren `rich_content.*`-Regeln, siehe LessonEditorController.
            'rich_content' => 'required|array',
        ];
    }
}
