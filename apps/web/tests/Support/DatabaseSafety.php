<?php

namespace Tests\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

/**
 * Harte Sicherheitssperre gegen den Vorfall aus P10.67: die Testsuite lief
 * einmal versehentlich gegen die echte Dev-Postgres-Datenbank (statt gegen
 * die per phpunit.xml erzwungene SQLite-:memory:-Verbindung) und
 * RefreshDatabase hat sie mit migrate:fresh geleert.
 *
 * phpunit.xml erzwingt die Testkonfiguration inzwischen mit force="true"
 * (siehe dort) -- das behebt die eigentliche Ursache. Diese Klasse ist die
 * zweite, unabhaengige Verteidigungslinie: sie prueft zur Laufzeit, bevor
 * irgendein Trait (insbesondere RefreshDatabase) die Datenbank anfasst, ob
 * Umgebung und Datenbankname wirklich nach "Test" aussehen -- und bricht
 * andernfalls hart ab (fail closed), statt im Zweifel weiterzumachen.
 *
 * Aufruf ausschliesslich aus Tests\TestCase::createApplication(), also vor
 * jedem einzelnen Test in der Suite (siehe docs/adr/0069-test-datenbank-isolation.md).
 */
final class DatabaseSafety
{
    /**
     * Bekannte Namen der echten Entwicklungsdatenbanken (siehe .env,
     * .env.docker, .env.example: DB_DATABASE=dcmlab). Fest verdrahtet statt
     * aus der Konfiguration gelesen -- zur Testzeit spiegelt die
     * Konfiguration ja bereits die (moeglicherweise falsche) Testumgebung,
     * ein Vergleich gegen sich selbst waere wirkungslos.
     */
    private const KNOWN_DEVELOPMENT_DATABASES = ['dcmlab'];

    public static function assertTestingEnvironment(Application $app): void
    {
        if (! $app->environment('testing')) {
            self::abort(sprintf(
                'APP_ENV ist "%s", nicht "testing". Tests duerfen nur mit '
                .'APP_ENV=testing laufen -- siehe README.md "Tests sicher ausfuehren".',
                $app->environment(),
            ));
        }
    }

    /**
     * Verbindungsnamen, die tatsaechlich benutzt werden und deshalb geprueft
     * werden muessen. config/database.php definiert zusaetzlich sqlite,
     * mysql, mariadb, pgsql und sqlsrv als Platzhalter-Bloecke, die alle
     * denselben DB_DATABASE/DB_HOST/... aus der Umgebung lesen (Laravel-
     * Standardgeruest) -- ohne diese Liste wuerde ein Platzhalterblock, der
     * zufaellig denselben Namen wie die aktive Testverbindung erbt, faelschlich
     * mitgeprueft und die Sperre wuerde grundlos ausloesen. Kommt eine
     * zusaetzliche, wirklich eigenstaendige Verbindung dazu (Abschnitt
     * "Apply the same protection to all additional database connections"),
     * gehoert ihr Name hier rein.
     */
    private const CHECKED_CONNECTION_KEYS = ['default'];

    public static function assertDatabaseIsTestDatabase(Application $app): void
    {
        /** @var Repository $config */
        $config = $app->make('config');

        foreach (self::CHECKED_CONNECTION_KEYS as $key) {
            $connectionName = $config->get("database.{$key}");

            if (! is_string($connectionName) || $connectionName === '') {
                self::abort(sprintf('database.%s ist nicht gesetzt.', $key));
            }

            $connection = (array) $config->get("database.connections.{$connectionName}", []);
            self::assertConnectionIsSafe($connectionName, $connection);
        }
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private static function assertConnectionIsSafe(string $name, array $connection): void
    {
        $driver = $connection['driver'] ?? null;

        // SQLite kann per Bauart nie die echte Postgres-Dev-Datenbank sein --
        // das ist der von phpunit.xml erzwungene Normalfall und braucht
        // keine Namenspruefung.
        if ($driver === 'sqlite') {
            return;
        }

        $database = trim((string) ($connection['database'] ?? ''));

        if ($database === '') {
            self::abort(sprintf(
                'Verbindung "%s" (Treiber "%s") hat keinen Datenbanknamen -- '
                .'kann nicht bestaetigen, dass es sich um eine Testdatenbank handelt.',
                $name,
                $driver,
            ));
        }

        if (in_array($database, self::KNOWN_DEVELOPMENT_DATABASES, true)) {
            self::abort(sprintf(
                'Verbindung "%s" zeigt auf "%s" -- das ist die echte '
                .'Entwicklungsdatenbank, keine Testdatenbank. Abbruch, bevor '
                .'irgendein Test (z. B. via RefreshDatabase) sie veraendert.',
                $name,
                $database,
            ));
        }

        if (! str_contains(strtolower($database), '_test')) {
            self::abort(sprintf(
                'Verbindung "%s" zeigt auf "%s" -- der Name enthaelt kein '
                .'"_test" und identifiziert sich damit nicht eindeutig als '
                .'Testdatenbank (Konvention: <name>_test, z. B. dcmlab_test).',
                $name,
                $database,
            ));
        }
    }

    private static function abort(string $reason): never
    {
        throw new RuntimeException('[Test-Datenbank-Sperre] '.$reason);
    }
}
