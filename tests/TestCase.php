<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        $databasePath = dirname(__DIR__).'/database/database.sqlite';

        if (! file_exists($databasePath)) {
            touch($databasePath);
        }

        putenv('DB_DATABASE='.$databasePath);
        $_ENV['DB_DATABASE'] = $databasePath;
        $_SERVER['DB_DATABASE'] = $databasePath;

        parent::setUp();

        config()->set('database.connections.sqlite.database', $databasePath);
    }
}
