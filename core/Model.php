<?php

declare(strict_types=1);

namespace Core;

abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected static bool $softDelete = false;
    /** @var list<string> */
    protected static array $fillable = [];

    protected static function db(): Database
    {
        return Database::instance();
    }

    public static function find(int $id): ?array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE %s = ?%s LIMIT 1',
            Database::quote(static::$table),
            Database::quote(static::$primaryKey),
            static::$softDelete ? ' AND `deleted_at` IS NULL' : ''
        );

        return static::db()->fetch($sql, [$id]);
    }

    public static function findBy(string $column, mixed $value): ?array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE %s = ?%s LIMIT 1',
            Database::quote(static::$table),
            Database::quote($column),
            static::$softDelete ? ' AND `deleted_at` IS NULL' : ''
        );

        return static::db()->fetch($sql, [$value]);
    }

    public static function create(array $data): int
    {
        return static::db()->insert(static::$table, static::filter($data));
    }

    public static function update(int $id, array $data): int
    {
        return static::db()->update(static::$table, static::filter($data), [static::$primaryKey => $id]);
    }

    public static function delete(int $id): int
    {
        if (static::$softDelete) {
            return static::db()->update(static::$table, ['deleted_at' => Database::now()], [static::$primaryKey => $id]);
        }

        return static::db()->delete(static::$table, [static::$primaryKey => $id]);
    }

    protected static function filter(array $data): array
    {
        return static::$fillable === [] ? $data : array_intersect_key($data, array_flip(static::$fillable));
    }
}