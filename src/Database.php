<?php
declare(strict_types=1);

namespace AM;

use PDO;
use PDOException;
use PDOStatement;

/**
 * PDO 数据库封装（单例 + 预处理语句）
 */
class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct(array $cfg)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'] ?? '127.0.0.1',
            $cfg['port'] ?? 3306,
            $cfg['name'],
            $cfg['charset'] ?? 'utf8mb4'
        );

        $this->pdo = new PDO($dsn, $cfg['user'], $cfg['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, sql_mode='STRICT_TRANS_TABLES'",
        ]);
    }

    public static function getInstance(?array $cfg = null): Database
    {
        if (self::$instance === null) {
            $cfg = $cfg ?? ($GLOBALS['AM_CONFIG']['database'] ?? null);
            if (!$cfg) {
                throw new \RuntimeException('Database config missing');
            }
            self::$instance = new self($cfg);
        }
        return self::$instance;
    }

    public static function pdo(): PDO
    {
        return self::getInstance()->pdo;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`,`', $cols),
            implode(',', $placeholders)
        );
        self::query($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        foreach (array_keys($data) as $col) {
            $sets[] = sprintf('`%s` = :%s', $col, $col);
        }
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(',', $sets), $where);
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute(array_merge($data, $whereParams));
        return $stmt->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        $sql = sprintf('DELETE FROM `%s` WHERE %s', $table, $where);
        return self::query($sql, $params)->rowCount();
    }

    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
