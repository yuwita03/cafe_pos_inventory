<?php

namespace Tests;

use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app->make(DatabaseManager::class)->connection();
        if (! $app->environment('testing') || $connection->getDriverName() !== 'pgsql' || $connection->getDatabaseName() !== 'cafe_pos_test') {
            throw new RuntimeException('Refusing to run tests: use PostgreSQL database cafe_pos_test.');
        }
        if ($connection->selectOne('SELECT current_database() AS name')->name !== 'cafe_pos_test') {
            throw new RuntimeException('Refusing to run tests against a non-testing database.');
        }

        return $app;
    }
}
