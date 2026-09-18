<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Interne Dienste (Abschnitt 3.3) -- nur im Compose-Netz erreichbar.
    'engine' => [
        'url' => env('ENGINE_URL', 'http://engine:8100'),
        'key' => env('DCMLAB_INTERNAL_KEY'),
    ],

    'sandbox' => [
        'url' => env('SANDBOX_URL', 'http://sandbox:8200'),
        'key' => env('DCMLAB_INTERNAL_KEY'),
    ],

    'scenario_engine' => [
        'url' => env('SCENARIO_ENGINE_URL', 'http://scenario-engine:8300'),
        'key' => env('DCMLAB_INTERNAL_KEY'),
    ],

    // Lab-Content-Deployment (PR #152, Lab-Content-Lifecycle-Audit): das
    // git-versionierte Export-/Import-Artefakt lebt bewusst unter
    // `deploy/labs/` im Repo-ROOT (Geschwister von `content/`), nicht unter
    // `apps/web/storage/**` (Runtime-/Framework-Storage, keine Content-Quelle)
    // und nicht unter `content/**` (das blieb bewusst Node/Lesson vorbehalten,
    // siehe PR-Beschreibung). Der Default geht zwei Ebenen ueber den
    // Laravel-App-Pfad hinaus (apps/web -> apps -> Repo-Root) -- korrekt fuer
    // Host-/CI-Ausfuehrung, wo `apps/web` tatsaechlich im Repo verschachtelt
    // ist. Der lokale Dev-Container sieht diese Verschachtelung nicht (nur
    // einzelne apps/web-Unterordner sind gebindet, siehe
    // infra/docker-compose.dev.yml) und ueberschreibt deshalb per
    // LABS_DEPLOY_PATH auf den dort zusaetzlich gebindeten Pfad.
    'labs_deploy' => [
        'path' => env('LABS_DEPLOY_PATH', base_path('../../deploy/labs')),
    ],

];
