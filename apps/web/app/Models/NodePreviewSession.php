<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ephemere Sitzung fuer die autorisierte Draft-Node-Vorschau (`NodeController::
 * previewSessionFor()`, Autorisierung ueber `NodePolicy::view()`). Strukturell
 * ein Klon von `NodeAttempt`, aber bewusst eine eigene Tabelle statt eines
 * Zusatzfeldes dort: `node_attempts` ist `unique(user_id, node_id)`, eine
 * Vorschau darf diesen einzigen Datensatz je Nutzer/Node nie belegen (siehe
 * Migration). Kein Code liest `node_preview_sessions` fuer Punkte, Profil,
 * Achievements oder das Leaderboard -- das IST die Absicherung gegen
 * verfaelschten Lernfortschritt, nicht ein zusaetzlicher Filter irgendwo.
 *
 * @property int $id
 * @property int $user_id
 * @property int $node_id
 * @property string $engine_session_id
 * @property string $status
 * @property array<int, string> $hints_used
 * @property int|null $points
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $flag_submitted_at
 */
#[Fillable([
    'user_id', 'node_id', 'engine_session_id', 'status', 'hints_used',
    'points', 'started_at', 'flag_submitted_at',
])]
class NodePreviewSession extends Model
{
    protected function casts(): array
    {
        return [
            'hints_used' => 'array',
            'started_at' => 'datetime',
            'flag_submitted_at' => 'datetime',
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
     * @return BelongsTo<Node, $this>
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
