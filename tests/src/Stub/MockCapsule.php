<?php
declare(strict_types=1);

namespace NextcloudSaaS\Tests\Stub;

/**
 * Configurable WHMCS Capsule stub for PHPUnit.
 */
final class MockCapsule
{
    /** @var array<string, list<array<string, mixed>>> */
    private static array $tables = [];

    public static function reset(): void
    {
        self::$tables = [];
    }

    /**
     * @param array<string, list<array<string, mixed>>> $tables
     */
    public static function seed(array $tables): void
    {
        self::$tables = $tables;
    }

    public static function table(string $table): MockQuery
    {
        return new MockQuery($table, self::$tables[$table] ?? []);
    }
}
