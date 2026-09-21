<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Lab;
use App\Models\LabAttempt;
use App\Models\Lesson;
use App\Models\LessonElement;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * IA-Luecke aus dem Terminologie-Audit geschlossen: Herausforderungen
 * hatten mit /de/nodes bereits eine eigene Uebersicht, Labs nicht --
 * `/de/labs` ist wie `/de/nodes` oeffentlich sichtbar (siehe
 * NodeControllerTest), ein Klick auf ein Lab fuehrt Gaeste ueber die
 * bestehende auth-Middleware auf labs/{lab} zum Login.
 */
class LabsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_view_the_labs_index(): void
    {
        Lab::factory()->create(['slug' => 'c-echo-connectivity', 'title' => ['de' => 'C-ECHO Connectivity Lab']]);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);

        $this->get('/de/labs')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Labs/Index')
                ->where('labs', fn (Collection $labs) => $labs->firstWhere('slug', 'c-echo-connectivity')['status'] === 'not_started'),
            );
    }

    public function test_only_published_labs_are_shown(): void
    {
        Lab::factory()->create(['slug' => 'published-lab', 'status' => 'published']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'published-lab']);
        Lab::factory()->create(['slug' => 'draft-lab', 'status' => 'draft']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'draft-lab']);

        $this->get('/de/labs')->assertInertia(fn ($page) => $page
            ->component('Labs/Index')
            ->where('labs', fn (Collection $labs) => $labs->pluck('slug')->all() === ['published-lab']),
        );
    }

    public function test_index_reflects_the_authenticated_users_attempt_status(): void
    {
        Lab::factory()->create(['slug' => 'started-lab']);
        $startedActivity = Activity::factory()->create(['type' => 'lab', 'key' => 'started-lab']);
        Lab::factory()->create(['slug' => 'solved-lab']);
        $solvedActivity = Activity::factory()->create(['type' => 'lab', 'key' => 'solved-lab']);
        Lab::factory()->create(['slug' => 'untouched-lab']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'untouched-lab']);

        $user = User::factory()->create();
        LabAttempt::create(['user_id' => $user->id, 'activity_id' => $startedActivity->id, 'status' => 'started', 'started_at' => now()]);
        LabAttempt::create(['user_id' => $user->id, 'activity_id' => $solvedActivity->id, 'status' => 'solved', 'started_at' => now(), 'completed_at' => now()]);

        $this->actingAs($user)->get('/de/labs')->assertInertia(fn ($page) => $page
            ->component('Labs/Index')
            ->where('labs', fn (Collection $labs) => $labs->firstWhere('slug', 'started-lab')['status'] === 'in_progress'
                && $labs->firstWhere('slug', 'solved-lab')['status'] === 'solved'
                && $labs->firstWhere('slug', 'untouched-lab')['status'] === 'not_started'),
        );
    }

    public function test_empty_state_when_no_labs_exist(): void
    {
        $this->get('/de/labs')->assertInertia(fn ($page) => $page
            ->component('Labs/Index')
            ->where('labs', []),
        );
    }

    public function test_the_labs_index_route_is_named_and_resolvable_from_the_dashboard(): void
    {
        $this->assertSame('/de/labs', route('labs.index', absolute: false));
    }

    /**
     * Published Content Boundary Hardening: ein veroeffentlichtes Lab kann
     * ueber ein `LessonElement` an eine Lesson gehaengt sein, die selbst
     * nicht (mehr) veroeffentlicht ist -- der Katalog darf dann weder die
     * `lesson_id` noch den daraus abgeleiteten `track_slug` preisgeben. Fuer
     * einen Gast gilt ausschliesslich der Status (LessonPolicy::view()
     * erwartet einen echten Nutzer und wird fuer ihn nie aufgerufen).
     */
    public function test_a_guest_sees_no_lesson_link_for_a_lab_attached_to_a_draft_lesson(): void
    {
        $track = Track::factory()->create(['slug' => 'fundamente']);
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'lesson_id' => '1.0', 'status' => 'draft']);
        Lab::factory()->create(['slug' => 'echo-lab']);
        $labActivity = Activity::factory()->create(['type' => 'lab', 'key' => 'echo-lab']);
        LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'activity', 'activity_id' => $labActivity->id, 'position' => 0]);

        $this->get('/de/labs')->assertInertia(fn ($page) => $page
            ->where('labs', fn (Collection $labs) => $labs->firstWhere('slug', 'echo-lab')['lesson_id'] === null
                && $labs->firstWhere('slug', 'echo-lab')['track_slug'] === null),
        );
    }

    /**
     * Gegenprobe zu oben fuer einen normal angemeldeten Lernenden --
     * dieselbe Abschirmung, kein Unterschied zum Gast.
     */
    public function test_a_learner_sees_no_lesson_link_for_a_lab_attached_to_a_draft_lesson(): void
    {
        $track = Track::factory()->create(['slug' => 'fundamente']);
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'lesson_id' => '1.0', 'status' => 'draft']);
        Lab::factory()->create(['slug' => 'echo-lab']);
        $labActivity = Activity::factory()->create(['type' => 'lab', 'key' => 'echo-lab']);
        LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'activity', 'activity_id' => $labActivity->id, 'position' => 0]);

        $learner = User::factory()->create();

        $this->actingAs($learner)->get('/de/labs')->assertInertia(fn ($page) => $page
            ->where('labs', fn (Collection $labs) => $labs->firstWhere('slug', 'echo-lab')['lesson_id'] === null
                && $labs->firstWhere('slug', 'echo-lab')['track_slug'] === null),
        );
    }

    /**
     * Ein zugewiesener Autor, Reviewer oder Administrator darf dieselbe
     * Draft-Lesson-Verknuepfung sehen -- entschieden ausschliesslich durch
     * `LessonPolicy::view()` (hier: Reviewer-Rolle, `ActivityPolicy::
     * update()` erlaubt jedem Reviewer/Administrator unabhaengig von
     * `authorUsers()`), keine neue Rolle/Permission.
     */
    public function test_an_authorized_reviewer_sees_the_lesson_link_for_a_draft_lesson(): void
    {
        $track = Track::factory()->create(['slug' => 'fundamente']);
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'lesson_id' => '1.0', 'status' => 'draft']);
        Lab::factory()->create(['slug' => 'echo-lab']);
        $labActivity = Activity::factory()->create(['type' => 'lab', 'key' => 'echo-lab']);
        LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'activity', 'activity_id' => $labActivity->id, 'position' => 0]);
        Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);

        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)->get('/de/labs')->assertInertia(fn ($page) => $page
            ->where('labs', fn (Collection $labs) => $labs->firstWhere('slug', 'echo-lab')['lesson_id'] === '1.0'
                && $labs->firstWhere('slug', 'echo-lab')['track_slug'] === 'fundamente'),
        );
    }

    /**
     * Beweist, dass die Sichtbarkeit echt nutzergebunden ist (`Gate::
     * forUser($user)`), nicht ein globaler/geteilter Zustand: ein
     * unzugewiesener Autor sieht dieselbe Draft-Verknuepfung nicht, obwohl
     * ein Administrator (immer erlaubt, `ActivityPolicy::update()`) sie im
     * unmittelbar folgenden Request sehr wohl sieht.
     */
    public function test_an_unassigned_author_does_not_see_the_lesson_link_even_though_an_administrator_would(): void
    {
        $track = Track::factory()->create(['slug' => 'fundamente']);
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'lesson_id' => '1.0', 'status' => 'draft']);
        Lab::factory()->create(['slug' => 'echo-lab']);
        $labActivity = Activity::factory()->create(['type' => 'lab', 'key' => 'echo-lab']);
        LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'activity', 'activity_id' => $labActivity->id, 'position' => 0]);
        Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);

        $unassignedAuthor = User::factory()->author()->create();
        $admin = User::factory()->administrator()->create();

        $this->actingAs($unassignedAuthor)->get('/de/labs')->assertInertia(fn ($page) => $page
            ->where('labs', fn (Collection $labs) => $labs->firstWhere('slug', 'echo-lab')['lesson_id'] === null),
        );

        $this->actingAs($admin)->get('/de/labs')->assertInertia(fn ($page) => $page
            ->where('labs', fn (Collection $labs) => $labs->firstWhere('slug', 'echo-lab')['lesson_id'] === '1.0'),
        );
    }
}
