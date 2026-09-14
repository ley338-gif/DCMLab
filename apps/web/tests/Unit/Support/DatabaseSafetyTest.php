<?php

namespace Tests\Unit\Support;

use RuntimeException;
use Tests\Support\DatabaseSafety;
use Tests\TestCase;

/**
 * Regressionstest fuer die Sperre aus P10.67 (Testsuite lief einmal gegen
 * die echte Dev-Postgres-Datenbank, migrate:fresh hat sie geleert). Prueft
 * die Klasse direkt, ohne selbst eine Datenbankaktion auszuloesen -- das
 * eigentliche "faehrt niemals gegen dcmlab" wird schon dadurch bewiesen,
 * dass diese Suite ueberhaupt laeuft (siehe Tests\TestCase::createApplication()).
 */
class DatabaseSafetyTest extends TestCase
{
    public function test_it_passes_for_the_real_test_configuration(): void
    {
        // Kein Wurf = bestanden. $this->app ist zu diesem Zeitpunkt schon
        // durch createApplication() gelaufen, also die reale Testkonfiguration.
        $this->expectNotToPerformAssertions();

        DatabaseSafety::assertTestingEnvironment($this->app);
        DatabaseSafety::assertDatabaseIsTestDatabase($this->app);
    }

    public function test_it_aborts_when_app_env_is_not_testing(): void
    {
        $this->app['env'] = 'production';

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/APP_ENV ist/');

            DatabaseSafety::assertTestingEnvironment($this->app);
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_it_aborts_when_the_default_connection_is_the_known_development_database(): void
    {
        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql.driver' => 'pgsql',
            'database.connections.pgsql.database' => 'dcmlab',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/echte Entwicklungsdatenbank/');

        DatabaseSafety::assertDatabaseIsTestDatabase($this->app);
    }

    public function test_it_aborts_when_the_database_name_does_not_look_like_a_test_database(): void
    {
        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql.driver' => 'pgsql',
            'database.connections.pgsql.database' => 'dcmlab_staging',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/enthaelt kein "_test"/');

        DatabaseSafety::assertDatabaseIsTestDatabase($this->app);
    }

    public function test_it_aborts_when_the_database_name_is_empty(): void
    {
        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql.driver' => 'pgsql',
            'database.connections.pgsql.database' => '',
        ]);

        $this->expectException(RuntimeException::class);

        DatabaseSafety::assertDatabaseIsTestDatabase($this->app);
    }

    public function test_it_accepts_a_named_test_database(): void
    {
        $this->expectNotToPerformAssertions();

        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql.driver' => 'pgsql',
            'database.connections.pgsql.database' => 'dcmlab_test',
        ]);

        DatabaseSafety::assertDatabaseIsTestDatabase($this->app);
    }

    public function test_it_never_flags_sqlite_regardless_of_the_database_name(): void
    {
        $this->expectNotToPerformAssertions();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.driver' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DatabaseSafety::assertDatabaseIsTestDatabase($this->app);
    }
}
