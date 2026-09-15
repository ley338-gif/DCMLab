<?php

namespace Tests\Feature\Console;

use App\Mail\ReviewReminderMail;
use App\Models\Lesson;
use App\Models\QuizReview;
use App\Models\Track;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * W7 (ADR 0084): "Ein Nutzer mit fälligen Karten erhält genau eine Mail pro
 * Intervall, ein Nutzer ohne fällige Karten keine, und die Abschaltung wirkt
 * sofort" -- das Abnahmekriterium aus dem Agent-Prompt, Wort fuer Wort als
 * Tests.
 */
class ReviewSendRemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_with_due_cards_receives_exactly_one_mail(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->dueReviewFor($user);

        $this->artisan('review:send-reminders')->assertSuccessful();

        Mail::assertSent(ReviewReminderMail::class, 1);
        Mail::assertSent(ReviewReminderMail::class, fn (ReviewReminderMail $mail) => $mail->hasTo($user->email) && $mail->dueCount === 1);
        $this->assertNotNull($user->refresh()->review_reminder_sent_at);
    }

    public function test_a_user_without_due_cards_receives_no_mail(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->dueReviewFor($user, dueAt: now()->addDay());

        $this->artisan('review:send-reminders')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertNull($user->refresh()->review_reminder_sent_at);
    }

    public function test_disabling_reminders_takes_effect_immediately(): void
    {
        Mail::fake();
        $user = User::factory()->create(['review_reminders_enabled' => false]);
        $this->dueReviewFor($user);

        $this->artisan('review:send-reminders')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_a_user_already_reminded_within_the_interval_is_not_reminded_again(): void
    {
        Mail::fake();
        $user = User::factory()->create(['review_reminder_sent_at' => now()->subDay()]);
        $this->dueReviewFor($user);

        $this->artisan('review:send-reminders')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_a_user_is_reminded_again_once_the_interval_has_passed(): void
    {
        Mail::fake();
        $user = User::factory()->create(['review_reminder_sent_at' => now()->subDays(4)]);
        $this->dueReviewFor($user);

        $this->artisan('review:send-reminders')->assertSuccessful();

        Mail::assertSent(ReviewReminderMail::class, 1);
    }

    private function dueReviewFor(User $user, ?CarbonImmutable $dueAt = null): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id]);

        QuizReview::create([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'question_id' => 'q1',
            'due_at' => $dueAt ?? now()->subHour(),
        ]);
    }
}
