<?php

namespace App\Services;

use App\Models\ActivityProgress;
use App\Models\ExamAttempt;
use App\Models\Node;
use App\Models\NodeAttempt;
use App\Models\Profile;
use App\Models\Track;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Punkte, Rang und Skill-Radar (Abschnitt 7). Node-Punkte, bestandene
 * Track-Pruefungen und geloeste Labs (CMS-8d) sind die drei Punktequellen
 * (seit P10.60 bewusst um Track-Bestehen erweitert -- vorher galten nur
 * Node-Punkte, siehe ADR 0009; Lektionen selbst geben weiterhin keine
 * Punkte).
 */
final class ProfileService
{
    // Einmalig je bestandenem Track (gezaehlt ueber den fruehesten
    // bestandenen ExamAttempt je Track, nicht mehrfach bei erneutem
    // Bestehen), passend zur Rang-Schwelle novice -> operator. Public,
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

        $passedTracks = ExamAttempt::query()
            ->where('user_id', $user->id)
            ->where('passed', true)
            ->distinct('track_id')
            ->count('track_id');

        // CMS-8d: aus activity_progress gelesen, NICHT live aus Lab::points
        // summiert -- activity_progress.score ist ein einmalig zum
        // Abschlusszeitpunkt eingefrorener Wert (wie bei Node/Exam), eine
        // spaetere Punkte-Aenderung am Lab im Studio-Editor darf bereits
        // erzielte Erfolge nicht rueckwirkend umwerten.
        $labPoints = (int) ActivityProgress::query()
            ->whereHas('activity', fn ($query) => $query->where('type', 'lab'))
            ->where('user_id', $user->id)
            ->where('completed', true)
            ->sum('score');

        return $nodePoints + $passedTracks * self::TRACK_PASS_POINTS + $labPoints;
    }

    /**
     * Nach jedem geloesten Flag aufgerufen (Abschnitt 10, P8-DoD): Rang und
     * Skill-Radar neu berechnen. Die "Trailblazer"-Vergabe (globaler
     * Wettlauf um die Erstloesung einer Node, frueher first_blood/ADR 0009)
     * laeuft seit ADR 0090b deklarativ ueber AchievementUnlockEvaluator,
     * ausgeloest vom Aufrufer via ActivityProgressRecorder::record(), nicht
     * mehr hier.
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
    }

    /**
     * Nach Abschluss einer Track-Abschlusspruefung aufgerufen (P10.60):
     * speist richtig beantwortete Fragen additiv ins Skill-Radar ein --
     * dieselbe additive Logik wie bei Node-`skills` in
     * recomputeAfterSolve(), keine Straf-/Abzugsmechanik fuer falsche
     * Antworten (die gibt es sonst nirgends im Code). Die Track-Badge-
     * Vergabe (deklaratives Achievement "track-<slug>") laeuft seit ADR
     * 0090b ueber AchievementUnlockEvaluator, ausgeloest vom Aufrufer via
     * ActivityProgressRecorder::record() -- totalPoints() zaehlt bestandene
     * Tracks direkt aus ExamAttempt, ist also unabhaengig davon bereits
     * korrekt, sobald $passed hier verarbeitet wurde.
     *
     * @param  list<array{correct: bool, tags: list<string>}>  $answeredQuestions
     */
    public function recomputeAfterExamAttempt(User $user, Track $track, bool $passed, array $answeredQuestions): void
    {
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
    }

    /**
     * Nach dem Loesen eines Labs aufgerufen (CMS-8d) -- analog
     * recomputeAfterSolve(), aber ohne Skill-Vektor (Lab hat kein
     * Node-artiges Skill-Konzept). MUSS NACH `ActivityProgressRecorder::
     * record('lab', ...)` aufgerufen werden, nicht davor: anders als bei
     * Node liest `totalPoints()` den Lab-Anteil aus `activity_progress`
     * (siehe oben), das `record()` erst gerade schreibt -- die umgekehrte
     * Reihenfolge wuerde hier einen veralteten Punktestand berechnen.
     */
    public function recomputeAfterLabSolve(User $user): void
    {
        $profile = $this->profileFor($user);
        $profile->points = $this->totalPoints($user);
        $profile->rank = $this->rankFor($profile->points);
        $profile->save();
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

    private function generateUniqueSlug(): string
    {
        do {
            $slug = Str::lower(Str::random(10));
        } while (Profile::where('public_slug', $slug)->exists());

        return $slug;
    }
}
