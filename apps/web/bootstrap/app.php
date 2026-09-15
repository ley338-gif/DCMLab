<?php

use App\Console\Commands\ReviewSendReminders;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // W7 (ADR 0084): der eigentliche "genau eine Mail pro Intervall"-Schutz
        // liegt im Befehl selbst (review_reminder_sent_at), nicht hier --
        // taeglich reicht, um jeden konfigurierten Intervall (Standard 3 Tage)
        // zuverlaessig zu treffen. Ausgefuehrt vom scheduler-Container
        // (`php artisan schedule:work`), siehe infra/docker-compose.yml.
        $schedule->command(ReviewSendReminders::class)->daily();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Eigene Fehlerseiten statt der Standard-Laravel-Seiten (Abschnitt 10,
        // P9: "Fehlerseiten"). Nur fuer die Statuscodes, die ein Lernender
        // tatsaechlich zu sehen bekommen kann -- 401 landet ohnehin im
        // Login-Redirect, deshalb hier nicht gelistet.
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if (app()->hasDebugModeEnabled() || $request->is('api/*') || $request->expectsJson()) {
                return $response;
            }

            $status = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : $response->getStatusCode();

            if (! in_array($status, [403, 404, 419, 429, 500, 503], true)) {
                return $response;
            }

            try {
                return Inertia::render('Error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            } catch (Throwable) {
                // Die eigene Fehlerseite braucht selbst ein gebautes Frontend
                // (Vite-Manifest). Fehlt das ausnahmsweise (z. B. ein frischer
                // Checkout ohne `npm run build`), lieber die urspruengliche
                // Antwort zeigen als eine zweite, verwirrendere Exception.
                return $response;
            }
        });
    })->create();
