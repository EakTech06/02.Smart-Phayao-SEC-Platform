<?php

declare(strict_types=1);

namespace Core;

use PDO;
use PDOStatement;

final class Database
{
    private static ?self $instance = null;
    private PDO $pdo;

    private function __construct(array $config)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'] ?? '127.0.0.1',
            (int) ($config['port'] ?? 3306),
            $config['name'] ?? '',
            $config['charset'] ?? 'utf8mb4'
        );
        $this->pdo = new PDO($dsn, (string) ($config['user'] ?? ''), (string) ($config['pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        $this->pdo->exec("SET time_zone = '+07:00'");
    }

    public static function instance(): self
    {
        return self::$instance ??= new self((array) Config::get('database', []));
    }

    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    public static function quote(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException('Invalid SQL identifier: ' . $identifier);
        }

        return '`' . $identifier . '`';
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            if (is_bool($value)) {
                $value = (int) $value;
            }
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $param = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
            $statement->bindValue($param, $value, $type);
        }
        $statement->execute();

        return $statement;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchColumn(string $sql, array $params = []): mixed
    {
        $value = $this->query($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    public function insert(string $table, array $data): int
    {
        $columns = array_map([self::class, 'quote'], array_keys($data));
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            self::quote($table),
            implode(', ', $columns),
            implode(', ', array_fill(0, count($data), '?'))
        );
        $this->query($sql, array_values($data));

        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, array $where): int
    {
        if ($data === [] || $where === []) {
            throw new \InvalidArgumentException('update() requires data and where conditions');
        }
        $set = implode(', ', array_map(static fn (string $c): string => self::quote($c) . ' = ?', array_keys($data)));
        $cond = implode(' AND ', array_map(static fn (string $c): string => self::quote($c) . ' = ?', array_keys($where)));
        $sql = 'UPDATE ' . self::quote($table) . " SET {$set} WHERE {$cond}";

        return $this->query($sql, [...array_values($data), ...array_values($where)])->rowCount();
    }

    public function delete(string $table, array $where): int
    {
        if ($where === []) {
            throw new \InvalidArgumentException('delete() requires where conditions');
        }
        $cond = implode(' AND ', array_map(static fn (string $c): string => self::quote($c) . ' = ?', array_keys($where)));

        return $this->query('DELETE FROM ' . self::quote($table) . " WHERE {$cond}", array_values($where))->rowCount();
    }

    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $callback($this);
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}