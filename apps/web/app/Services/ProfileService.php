<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\Node;
use App\Models\NodeAttempt;
use App\Models\Profile;
use App\Models\Track;
use App\Models\TrackBadge;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Punkte, Rang und Skill-Radar (Abschnitt 7). Node-Punkte und bestandene
 * Track-Pruefungen sind die beiden Punktequellen (seit P10.60 bewusst um
 * Track-Bestehen erweitert -- vorher galten nur Node-Punkte, siehe ADR
 * 0009; Lektionen selbst geben weiterhin keine Punkte).
 */
final class ProfileService
{
    // Einmalig je bestandenem Track (TrackBadge ist unique(user_id,
    // track_id)), passend zur Rang-Schwelle novice -> operator. Public,
    // damit die Ergebnisseite (P10.65) denselben Wert anzeigen kann, ohne
    // ihn zu duplizieren.
    public const TRACK_PASS_POINTS = 50;

    /**
     * Rang-Schwellen (Abschnitt 7 nennt nur die fuenf Namen, keine Punktzahlen
     * -- das ist eine umkehrbare Balance-Entscheidung, siehe ADR 0009).
     *
     * @var array<string, int>
     */
    private const RANK_THRESHOLDS = [
        'novice' => 0,
        'operator' => 50,
        'administrator' => 150,
        'architect' => 300,
        'standard_bearer' => 500,
    ];

    /**
     * Die fuenf Skill-Kategorien aus Abschnitt 7 -- immer alle fuenf im
     * Radar, auch mit 0 Punkten, damit es Luecken zeigt statt sie zu verschweigen.
     *
     * @var list<string>
     */
    public const SKILL_CATEGORIES = ['netzwerk', 'datenmodell', 'bildgebung', 'integration', 'security'];

    public function profileFor(User $user): Profile
    {
        return Profile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'public_slug' => $this->generateUniqueSlug(),
                'rank' => 'novice',
                'skill_vector' => array_fill_keys(self::SKILL_CATEGORIES, 0),
            ],
        );
    }

    public function totalPoints(User $user): int
    {
        $nodePoints = (int) NodeAttempt::query()
            ->where('user_id', $user->id)
            ->where('status', 'solved')
            ->sum('points');

        $trackPoints = TrackBadge::query()->where('user_id', $user->id)->count() * self::TRACK_PASS_POINTS;

        return $nodePoints + $trackPoints;
    }

    /**
     * Nach jedem geloesten Flag aufgerufen (Abschnitt 10, P8-DoD): Rang und
     * Skill-Radar neu berechnen, First Blood pruefen.
     */
    public function recomputeAfterSolve(User $user, Node $node): void
    {
        $profile = $this->profileFor($user);

        $solved = NodeAttempt::query()
            ->where('user_id', $user->id)
            ->where('status', 'solved')
            ->with('node')
            ->get();

        $skillVector = array_fill_keys(self::SKILL_CATEGORIES, 0);
        $totalPoints = 0;

        foreach ($solved as $attempt) {
            $points = $attempt->points ?? 0;
            $totalPoints += $points;

            foreach ($attempt->node->skills as $skill) {
                if (array_key_exists($skill, $skillVector)) {
                    $skillVector[$skill] += $points;
                }
            }
        }

        $profile->rank = $this->rankFor($this->totalPoints($user));
        $profile->points = $this->totalPoints($user);
        $profile->skill_vector = $skillVector;
        $profile->save();

        $this->maybeAwardFirstBlood($user, $node);
    }

    /**
     * Nach Abschluss einer Track-Abschlusspruefung aufgerufen (P10.60):
     * vergibt bei Bestehen einmalig ein TrackBadge (die zweite
     * Punktequelle, siehe Klassendoc) und speist richtig beantwortete
     * Fragen additiv ins Skill-Radar ein -- dieselbe additive Logik wie bei
     * Node-`skills` in recomputeAfterSolve(), keine Straf-/Abzugsmechanik
     * fuer falsche Antworten (die gibt es sonst nirgends im Code).
     *
     * @param  list<array{correct: bool, tags: list<string>}>  $answeredQuestions
     * @return bool ob dieser Aufruf das TrackBadge neu vergeben hat (nicht nur bestanden, sondern zum ersten Mal)
     */
    public function recomputeAfterExamAttempt(User $user, Track $track, bool $passed, array $answeredQuestions): bool
    {
        $badgeNewlyAwarded = false;

        if ($passed) {
            $badge = TrackBadge::firstOrCreate(
                ['user_id' => $user->id, 'track_id' => $track->id],
                ['awarded_at' => now()],
            );
            $badgeNewlyAwarded = $badge->wasRecentlyCreated;
        }

        $profile = $this->profileFor($user);
        $skillVector = $profile->skill_vector;

        foreach ($answeredQuestions as $question) {
            if (! $question['correct']) {
                continue;
            }

            foreach (array_slice($question['tags'], 0, 2) as $tag) {
                if (array_key_exists($tag, $skillVector)) {
                    $skillVector[$tag] += 1;
                }
            }
        }

        $profile->skill_vector = $skillVector;
        $profile->points = $this->totalPoints($user);
        $profile->rank = $this->rankFor($profile->points);
        $profile->save();

        return $badgeNewlyAwarded;
    }

    /**
     * Fasst first_blood-Achievements (global, pro Node, ADR 0009) und
     * TrackBadges (pro Nutzer, pro Track, ADR 0066) zu einer nach
     * `awarded_at` sortierten Liste zusammen -- die eine gemeinsame
     * Lesestelle fuer "welche Abzeichen hat dieser Nutzer", die Dashboard
     * und oeffentliches Profil gleichermassen nutzen (ADR 0069). Die
     * beiden Herkunfts-Tabellen, ihre Unique-Constraints und ihre
     * getrennte Vergabe-Logik bleiben unangetastet.
     *
     * @return list<array{kind: string, node_title: ?string, track_title_key: ?string, awarded_at: CarbonImmutable}>
     */
    public function achievementsFor(User $user): array
    {
        $firstBloods = Achievement::query()
            ->where('user_id', $user->id)
            ->where('type', 'first_blood')
            ->with('node')
            ->get()
            ->map(fn (Achievement $achievement) => [
                'kind' => 'first_blood',
                'node_title' => $achievement->node?->title['de'] ?? $achievement->node?->slug,
                'track_title_key' => null,
                'awarded_at' => $achievement->awarded_at,
            ]);

        $trackBadges = TrackBadge::query()
            ->where('user_id', $user->id)
            ->with('track')
            ->get()
            ->map(fn (TrackBadge $badge) => [
                'kind' => 'track_passed',
                'node_title' => null,
                'track_title_key' => $badge->track?->title_key,
                'awarded_at' => $badge->awarded_at,
            ]);

        $entries = [...$firstBloods->all(), ...$trackBadges->all()];

        usort($entries, fn (array $a, array $b) => $b['awarded_at'] <=> $a['awarded_at']);

        return $entries;
    }

    /**
     * Nutzer, die sich fuer die Bestenliste entschieden haben (Opt-in ist
     * per Default aus), sortiert nach Punkten.
     *
     * @return Collection<int, Profile>
     */
    public function leaderboard(): Collection
    {
        return Profile::query()
            ->where('leaderboard_opt_in', true)
            ->orderByDesc('points')
            ->with('user')
            ->get();
    }

    private function rankFor(int $points): string
    {
        $rank = 'novice';

        foreach (self::RANK_THRESHOLDS as $candidate => $threshold) {
            if ($points >= $threshold) {
                $rank = $candidate;
            }
        }

        return $rank;
    }

    private function maybeAwardFirstBlood(User $user, Node $node): void
    {
        $alreadyAwarded = Achievement::query()
            ->where('node_id', $node->id)
            ->where('type', 'first_blood')
            ->exists();

        if ($alreadyAwarded) {
            return;
        }

        // Race-sicher: der Unique-Index (node_id, type) laesst bei
        // gleichzeitigen Loesungen nur den ersten Insert durch.
        try {
            Achievement::create([
                'user_id' => $user->id,
                'node_id' => $node->id,
                'type' => 'first_blood',
                'awarded_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Ein anderer Nutzer war zwischen der exists()-Pruefung und
            // diesem Insert schneller -- kein Fehler, nur kein First Blood.
        }
    }

    private function generateUniqueSlug(): string
    {
        do {
            $slug = Str::lower(Str::random(10));
        } while (Profile::where('public_slug', $slug)->exists());

        return $slug;
    }
}
