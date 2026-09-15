<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Erinnerungs-Abstand (W7, ADR 0084)
    |--------------------------------------------------------------------------
    |
    | Mindestabstand in Tagen zwischen zwei Erinnerungsmails an denselben
    | Nutzer -- unabhaengig davon, wie oft `review:send-reminders` tatsaechlich
    | laeuft (der Scheduler ruft es taeglich auf, siehe bootstrap/app.php).
    |
    */

    'reminder_interval_days' => (int) env('REVIEW_REMINDER_INTERVAL_DAYS', 3),

];
