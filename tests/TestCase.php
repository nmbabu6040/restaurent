<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Runs before any test touches the database. If the configured database is anything other than
     * SQLite in memory or a database whose name ends in "_test", the whole test run stops.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $default = $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$default}.database");

        if ($database !== ':memory:' && ! str_ends_with($database, '_test')) {
            throw new \RuntimeException(
                "Refusing to run tests against database \"{$database}\". Use SQLite in memory or a database ending in _test."
            );
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // The tests render pages without a Vite build.
        $this->withoutVite();
    }
}
