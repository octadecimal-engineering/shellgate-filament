<?php

/**
 * IDE stub for Orchestra\Testbench\TestCase.
 *
 * This file is for static analysis / IDE only. At runtime the real class
 * is provided by the orchestra/testbench package (require-dev).
 *
 * @see https://github.com/orchestral/testbench
 */

namespace Orchestra\Testbench;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    /**
     * Load Laravel migrations (Laravel + package).
     *
     * @param array<string, mixed> $options
     */
    protected function loadLaravelMigrations(array $options = []): void
    {
    }
}
