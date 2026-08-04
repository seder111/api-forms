<?php

declare(strict_types=1);

namespace App\Database;

use App\Support\Env;
use PDO;
use PDOException;

/**
 * Single access point to the deployment's database, shared by every
 * database feature.
 *
 * Best-effort by design: an unsupported driver, missing PHP extension or
 * failed connection is logged via error_log() and reported as null — it
 * never throws, so callers can never block the user-facing redirect.
 *
 * DB_DRIVER selects "mysql" (also MariaDB, the default) or "sqlite".
 */
final class Connection
{
    private const OPTIONS = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    private static ?PDO $pdo = null;

    /** Lazy connection, opened once per request and reused. */
    public static function pdo(): ?PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        try {
            self::$pdo = self::connect();
        } catch (PDOException $exception) {
            error_log('Database connection failed: ' . $exception->getMessage());
        }

        return self::$pdo;
    }

    private static function connect(): ?PDO
    {
        $driver = Env::string('DB_DRIVER', 'mysql');

        return match ($driver) {
            'mysql' => new PDO(
                self::mysqlDsn(),
                Env::string('DB_USER'),
                Env::string('DB_PASSWORD'),
                self::OPTIONS
            ),
            'sqlite' => new PDO('sqlite:' . self::sqlitePath(), null, null, self::OPTIONS),
            default => self::unsupportedDriver($driver),
        };
    }

    private static function mysqlDsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            Env::string('DB_HOST', '127.0.0.1'),
            Env::string('DB_PORT', '3306'),
            Env::string('DB_NAME')
        );
    }

    /**
     * A relative DB_SQLITE_PATH is resolved against the app root, so the
     * default lands in storage/ — outside the public webroot, like the .env.
     */
    private static function sqlitePath(): string
    {
        $path = Env::string('DB_SQLITE_PATH', 'storage/database.sqlite');

        return str_starts_with($path, '/') ? $path : dirname(__DIR__, 2) . '/' . $path;
    }

    private static function unsupportedDriver(string $driver): ?PDO
    {
        error_log('Unsupported DB_DRIVER "' . $driver . '": expected "mysql" or "sqlite".');

        return null;
    }
}
