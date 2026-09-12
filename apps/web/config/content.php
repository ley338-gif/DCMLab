<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content-Verzeichnis
    |--------------------------------------------------------------------------
    |
    | content/ liegt als Geschwisterverzeichnis von apps/ im Monorepo
    | (Abschnitt 3.2 des Auftrags), nicht innerhalb der Laravel-App. Im
    | Docker-Container wird es als eigenes Volume nach /var/www/html/content
    | gemountet -- dort zeigt CONTENT_PATH hin (siehe .env.docker).
    |
    */

    'path' => env('CONTENT_PATH', base_path('../../content')),

];
