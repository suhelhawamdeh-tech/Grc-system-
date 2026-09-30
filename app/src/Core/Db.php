<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class Db
{
    private static ?PDO $pdo = null;

    public static function connect(array $c): PDO
    {
        $dsn = 'mysql:host=' . ($c['host'] ?? 'localhost')
            . (!empty($c['port']) ? ';port=' . (int)$c['port'] : '')
            . (!empty($c['socket']) ? ';unix_socket=' . $c['socket'] : '')
            . ';dbname=' . $c['name'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, (string)$c['user'], (string)$c['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        return $pdo;
    }

    public static function init(array $c): void
    {
        self::$pdo = self::connect($c);
    }

    public static function pdo(): PDO
    {
        if (!self::$pdo) {
            throw new \RuntimeException('Database not initialised');
        }
        return self::$pdo;
    }

    public static function ready(): bool
    {
        return self::$pdo !== null;
    }

    private static function run(string $sql, array $params): \PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $i = 1;
        foreach ($params as $p) {
            $type = match (true) {
                is_int($p) => PDO::PARAM_INT,
                is_bool($p) => PDO::PARAM_BOOL,
                $p === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $st->bindValue($i++, $p, $type);
        }
        $st->execute();
        return $st;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $r = self::run($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public static function val(string $sql, array $params = []): mixed
    {
        $r = self::run($sql, $params)->fetchColumn();
        return $r === false ? null : $r;
    }

    public static function exec(string $sql, array $params = []): int
    {
        return self::run($sql, $params)->rowCount();
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        self::run($sql, array_values($data));
        return (int)self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(',', array_map(static fn($c) => '`' . $c . '`=?', array_keys($data)));
        return self::exec('UPDATE `' . $table . '` SET ' . $set . ' WHERE ' . $where, array_merge(array_values($data), $whereParams));
    }

    public static function tx(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $r = $fn();
            $pdo->commit();
            return $r;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Escape LIKE wildcards in user input. */
    public static function like(string $s): string
    {
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s) . '%';
    }
}
