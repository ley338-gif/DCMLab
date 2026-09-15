<?php

namespace App\Console\Commands;

use App\Mail\ReviewReminderMail;
use App\Models\QuizReview;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * W7 (ADR 0084): eine Mail mit der Zahl faelliger Wissenskarten, in
 * einstellbarem Abstand (config('review.reminder_interval_days'),
 * Standard 3 Tage). `review_reminder_sent_at` traegt den Zeitpunkt der
 * zuletzt verschickten Erinnerung -- dadurch bekommt ein Nutzer nie mehr
 * als eine Mail pro Intervall, unabhaengig davon, wie oft dieser Befehl
 * tatsaechlich laeuft (der Scheduler ruft ihn taeglich auf, siehe
 * bootstrap/app.php). Ein Nutzer ohne faellige Karten bekommt keine Mail,
 * und `review_reminders_enabled = false` wirkt sofort (naechster Lauf
 * ueberspringt ihn).
 */
class ReviewSendReminders extends Command
{
    protected $signature = 'review:send-reminders';

    protected $description = 'Verschickt Erinnerungsmails an Nutzer mit faelligen Wissenskarten (W7)';

    public function handle(): int
    {
        $intervalDays = (int) config('review.reminder_interval_days', 3);
        $cutoff = now()->subDays($intervalDays);

        $eligibleUsers = User::query()
            ->where('review_reminders_enabled', true)
            ->where(function ($query) use ($cutoff) {
                $query->whereNull('review_reminder_sent_at')
                    ->orWhere('review_reminder_sent_at', '<=', $cutoff);
            })
            ->get();

        if ($eligibleUsers->isEmpty()) {
            $this->info('review:send-reminders — kein Nutzer faellig fuer eine Erinnerung.');

            return self::SUCCESS;
        }

        /** @var array<int, int> $dueCounts user_id => Anzahl faelliger Karten */
        $dueCounts = QuizReview::query()
            ->whereIn('user_id', $eligibleUsers->pluck('id'))
            ->where('due_at', '<=', now())
            ->selectRaw('user_id, count(*) as due_count')
            ->groupBy('user_id')
            ->pluck('due_count', 'user_id')
            ->all();

        $sent = 0;

        foreach ($eligibleUsers as $user) {
            $dueCount = (int) ($dueCounts[$user->id] ?? 0);

            if ($dueCount === 0) {
                continue;
            }

            Mail::to($user)->send(new ReviewReminderMail($user, $dueCount));
            $user->forceFill(['review_reminder_sent_at' => now()])->save();
            $sent++;
        }

        $this->info(sprintf(
            'review:send-reminders — %d von %d moeglichen Erinnerungen verschickt.',
            $sent,
            $eligibleUsers->count(),
        ));

        return self::SUCCESS;
    }
}
