<?php

namespace App\Models;

use Database\Factories\LabFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * DB-nativer Index fuer den Lab-Activity-Typ (CMS-8a, Entscheidung C) --
 * anders als `Node` gibt es kein content/labs/**-Dateipendant, ein Lab
 * existiert von Anfang an nur in dieser Tabelle. `runtime_template`/
 * `dataset` sind Slugs (SandboxTemplate/content/datasets.yml), `assertions`
 * eine geschlossene Liste deklarativer Objekte (CMS-8c/8e).
 *
 * @property int $id
 * @property string $slug
 * @property string $difficulty
 * @property int $points
 * @property int $estimated_minutes
 * @property string|null $runtime_template
 * @property string|null $dataset
 * @property list<array<string, mixed>> $assertions
 * @property string $status
 * @property array<string, string> $title
 * @property array<string, string> $scenario_title
 * @property array<string, mixed>|null $rich_content
 */
#[Fillable([
    'slug', 'difficulty', 'points', 'estimated_minutes', 'runtime_template', 'dataset',
    'assertions', 'status', 'title', 'scenario_title', 'rich_content',
])]
class Lab extends Model
{
    /** @use HasFactory<LabFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return [
            'assertions' => 'array',
            'title' => 'array',
            'scenario_title' => 'array',
            'rich_content' => 'array',
        ];
    }
}
