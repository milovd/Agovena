<?php

declare(strict_types=1);

namespace Tests;

/**
 * RefreshDatabase without wrapping each test in a transaction so child PHP
 * processes can see committed rows on the shared MariaDB database.
 */
abstract class MultiProcessTestCase extends TestCase
{
    /** @var list<string> */
    protected $connectionsToTransact = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'database',
            'queue.default' => 'database',
        ]);
    }
}
