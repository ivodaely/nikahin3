<?php
/**
 * Database connection — thin PDO wrapper, singleton.
 */

require_once __DIR__ . '/config.php';

final class DB {
    private static ?PDO $pdo = null;

    public static function pdo(): PDO {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                DB_HOST, DB_PORT, DB_NAME
            );
            try {
                self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ]);
            } catch (PDOException $e) {
                if (APP_DEBUG) {
                    die('DB connection failed: ' . htmlspecialchars($e->getMessage()));
                }
                http_response_code(500);
                die('Service temporarily unavailable.');
            }
        }
        return self::$pdo;
    }

    /** Run a prepared statement and return it. */
    public static function run(string $sql, array $params = []): PDOStatement {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Fetch a single row. */
    public static function one(string $sql, array $params = []): ?array {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Fetch all rows. */
    public static function all(string $sql, array $params = []): array {
        return self::run($sql, $params)->fetchAll();
    }

    /** Insert and return last insert id. */
    public static function insert(string $sql, array $params = []): int {
        self::run($sql, $params);
        return (int)self::pdo()->lastInsertId();
    }
}
