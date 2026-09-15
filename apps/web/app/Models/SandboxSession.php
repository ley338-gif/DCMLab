<?php

namespace App\Models;

use Database\Factories\SandboxSessionFactory;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Durable Aufzeichnung einer Spielwiesen-Sitzung (ADR 0096, CMS-2). Der
 * eigentliche Laufzeitzustand bleibt in services/sandbox -- siehe
 * Migrationskommentar.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $activity_id
 * @property int|null $sandbox_template_id
 * @property string $runtime_provider
 * @property string|null $runtime_instance_id
 * @property string $status
 */
class SandboxSession extends Model
{
    /** @use HasFactory<SandboxSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'activity_id',
        'sandbox_template_id',
        'runtime_provider',
        'runtime_instance_id',
        'status',
        'started_at',
        'last_activity_at',
        'expires_at',
        'finished_at',
        'result',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'expires_at' => 'datetime',
            'finished_at' => 'datetime',
            'result' => AsCollection::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * @return BelongsTo<SandboxTemplate, $this>
     */
    public function sandboxTemplate(): BelongsTo
    {
        return $this->belongsTo(SandboxTemplate::class);
    }
}
