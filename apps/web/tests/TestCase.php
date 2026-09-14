<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Tests\Support\DatabaseSafety;

abstract class TestCase extends BaseTestCase
{
    /**
     * Sicherheitssperre gegen den Vorfall aus P10.67 (Testsuite lief gegen
     * die echte Dev-Datenbank, migrate:fresh hat sie geleert): hier statt in
     * setUp() ueberschrieben, weil createApplication() der fruehestmoegliche
     * Zeitpunkt ist -- noch vor setUpTraits(), also bevor RefreshDatabase
     * ueberhaupt die Chance hat, etwas an der Datenbank zu tun. Siehe
     * tests/Support/DatabaseSafety.php und docs/adr/0069-test-datenbank-isolation.md.
     */
    public function createApplication()
    {
        $this->syncForcedEnvIntoServerSuperglobal();

        $app = parent::createApplication();

        DatabaseSafety::assertTestingEnvironment($app);
        DatabaseSafety::assertDatabaseIsTestDatabase($app);

        return $app;
    }

    /**
     * phpunit.xml erzwingt Testwerte ueber <env ... force="true">, aber
     * PHPUnits eigener Handler schreibt dabei nur putenv()/$_ENV, nie
     * $_SERVER (siehe PHPUnit\TextUI\Configuration\PhpHandler::handleEnvVariables).
     * Laravels env()-Aufloesung liest aber auch $_SERVER, und die CLI-SAPI
     * fuellt $_SERVER bereits beim PHP-Start aus der echten Prozessumgebung
     * -- also z. B. mit dem DB_CONNECTION=pgsql, das infra/docker-compose.yml
     * in den app-Container exportiert. Ohne diesen Abgleich wuerde genau das
     * wieder durchschlagen, das den Vorfall aus P10.67 verursacht hat.
     */
    private function syncForcedEnvIntoServerSuperglobal(): void
    {
        foreach ($_ENV as $key => $value) {
            $_SERVER[$key] = $value;
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
