<?php

namespace App\Models;

use Database\Factories\SandboxTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine freigegebene Sandbox-Laufzeitumgebung (ADR 0096, CMS-2). Nur
 * Reviewer duerfen Zeilen anlegen/freigeben (SandboxTemplatePolicy) --
 * `SandboxController` waehlt ausschliesslich unter `status = published`.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property string $runtime_provider
 * @property string $status
 */
class SandboxTemplate extends Model
{
    /** @use HasFactory<SandboxTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'runtime_provider',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
