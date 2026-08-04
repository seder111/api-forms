<?php

declare(strict_types=1);

namespace App\Database;

use App\Support\Env;
use PDO;
use PDOException;

/**
 * Saves every form submission in the deployment's own database, as a log
 * independent from Plunk. Best-effort, never blocks the caller. Opt-in per
 * deployment via DB_SAVE_SUBMISSIONS.
 *
 * The submissions table is created on first use, so a deployment only needs
 * the connection settings in its .env — no manual schema step.
 */
final class Submissions
{
    /**
     * @param array<string, string|array<int, string>> $fields Every submitted
     *        field, already trimmed. Stored verbatim as a JSON payload.
     */
    public static function save(string $email, array $fields): void
    {
        if (!Env::bool('DB_SAVE_SUBMISSIONS')) {
            return;
        }

        $pdo = Connection::pdo();

        if ($pdo === null) {
            error_log('Skipping database save for ' . $email . ': no connection.');
            return;
        }

        try {
            self::createTable($pdo);
            self::insert($pdo, $email, $fields);
        } catch (PDOException $exception) {
            error_log('Database save failed for ' . $email . ': ' . $exception->getMessage());
        }
    }

    private static function createTable(PDO $pdo): void
    {
        $id = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS submissions ('
            . 'id ' . $id . ', '
            . 'email VARCHAR(254) NOT NULL, '
            . 'payload TEXT NOT NULL, '
            . 'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'
            . ')'
        );
    }

    /**
     * @param array<string, string|array<int, string>> $fields
     */
    private static function insert(PDO $pdo, string $email, array $fields): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO submissions (email, payload) VALUES (:email, :payload)'
        );

        $statement->execute([
            'email' => $email,
            'payload' => json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        ]);
    }
}
