<?php

namespace Backpack\CRUD\app\Library\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

final class DatabaseSchema
{
    private static $schema;

    /**
     * Return the schema for the table.
     */
    public static function getForTable(string $connection, string $table)
    {
        $connection = $connection ?: config('database.default');

        self::generateDatabaseSchema($connection);

        return self::$schema[$connection][$table] ?? null;
    }

    public static function getTables(string $connection = null): array
    {
        $connection = $connection ?: config('database.default');
        self::generateDatabaseSchema($connection);

        return self::$schema[$connection] ?? [];
    }

    public function listTableColumnsNames(string $connection, string $table)
    {
        $table = self::getForTable($connection, $table);

        return array_keys($table->getColumns());
    }

    public function listTableIndexes(string $connection, string $table)
    {
        return self::getIndexColumnNames($connection, $table);
    }

    public function getManager(string $connection = null)
    {
        $connection = $connection ?: config('database.default');

        return self::getSchemaManager($connection);
    }

    /**
     * Generates and store the database schema.
     */
    private static function generateDatabaseSchema(string $connection)
    {
        if (! isset(self::$schema[$connection])) {
            self::$schema[$connection] = self::mapTables($connection);
        }
    }

    /**
     * Map the tables from raw db values into an usable array.
     *
     * @param  string  $connection
     * @return array
     */
    private static function mapTables(string $connection)
    {
        $currentSchema = self::getCurrentSchemaName($connection);

        return LazyCollection::make(self::getCreateSchema($connection)->getTables())
            // Laravel 11's schema builder getTables() returns tables across ALL
            // schemas/databases on the server. Scope to the current connection's
            // schema so we only introspect this app's tables — otherwise every
            // other database on the server (e.g. leftover isolated test DBs in CI)
            // gets column+index introspected, exploding to tens of thousands of
            // queries and multi-second cold renders.
            //
            // Compare against the connection's CURRENT SCHEMA, not its database
            // name. On MySQL those are the same thing, which is why comparing to
            // the database name worked. On PostgreSQL the schema is `public`
            // while the database is e.g. `tenantnrj`, so that comparison matched
            // nothing, every table was discarded, and setFromDb() produced a CRUD
            // panel with zero columns and zero fields. SQLite (`main`) and SQL
            // Server (`dbo`) fail the same way.
            ->filter(function ($table) use ($currentSchema) {
                if (! is_array($table)) {
                    return true;
                }

                $schema = $table['schema'] ?? null;

                return $schema === null || $currentSchema === null || $schema === $currentSchema;
            })
            ->mapWithKeys(function ($table, $key) use ($connection) {
            $tableName = is_array($table) ? $table['name'] : $table->getName();

            if (self::$schema[$connection][$tableName] ?? false) {
                return [$tableName => self::$schema[$connection][$tableName]];
            }

            if (is_array($table)) {
                $table = new Table($tableName, self::mapTableColumns($connection, $tableName));
            }

            return [$tableName => $table];
        })->toArray();
    }

    /**
     * The schema that unqualified table names on this connection resolve to.
     *
     * Falls back to the database name so behaviour is unchanged on any
     * connection whose builder predates getCurrentSchemaName() — on MySQL the
     * two are identical anyway. A null result means "do not filter", which keeps
     * a panel rendering rather than silently emptying it.
     */
    private static function getCurrentSchemaName(string $connection): ?string
    {
        $connection = DB::connection($connection);
        $builder = $connection->getSchemaBuilder();

        if (method_exists($builder, 'getCurrentSchemaName')) {
            $schema = $builder->getCurrentSchemaName();

            if (is_string($schema) && $schema !== '') {
                return $schema;
            }
        }

        $database = $connection->getDatabaseName();

        return is_string($database) && $database !== '' ? $database : null;
    }

    private static function getIndexColumnNames(string $connection, string $table)
    {
        $schemaManager = self::getSchemaManager($connection);
        $indexes = method_exists($schemaManager, 'listTableIndexes') ? $schemaManager->listTableIndexes($table) : $schemaManager->getIndexes($table);

        $indexes = array_map(function ($index) {
            return is_array($index) ? $index['columns'] : $index->getColumns();
        }, $indexes);

        $indexes = \Illuminate\Support\Arr::flatten($indexes);

        return array_unique($indexes);
    }

    private static function mapTableColumns(string $connection, string $table)
    {
        $indexedColumns = self::getIndexColumnNames($connection, $table);

        return LazyCollection::make(self::getSchemaManager($connection)->getColumns($table))->mapWithKeys(function ($column, $key) use ($indexedColumns) {
            $column['index'] = array_key_exists($column['name'], $indexedColumns) ? true : false;

            return [$column['name'] => $column];
        })->toArray();
    }

    private static function getCreateSchema(string $connection)
    {
        $schemaManager = self::getSchemaManager($connection);

        return method_exists($schemaManager, 'createSchema') ? $schemaManager->createSchema() : $schemaManager;
    }

    private static function dbalTypes()
    {
        return [
            'enum' => \Doctrine\DBAL\Types\Types::STRING,
            'jsonb' => \Doctrine\DBAL\Types\Types::JSON,
            'geometry' => \Doctrine\DBAL\Types\Types::STRING,
            'point' => \Doctrine\DBAL\Types\Types::STRING,
            'lineString' => \Doctrine\DBAL\Types\Types::STRING,
            'polygon' => \Doctrine\DBAL\Types\Types::STRING,
            'multiPoint' => \Doctrine\DBAL\Types\Types::STRING,
            'multiLineString' => \Doctrine\DBAL\Types\Types::STRING,
            'multiPolygon' => \Doctrine\DBAL\Types\Types::STRING,
            'geometryCollection' => \Doctrine\DBAL\Types\Types::STRING,
        ];
    }

    private static function getSchemaManager(string $connection)
    {
        $connection = DB::connection($connection);

        if (method_exists($connection, 'getDoctrineSchemaManager')) {
            foreach (self::dbalTypes() as $key => $value) {
                $connection->getDoctrineSchemaManager()->getDatabasePlatform()->registerDoctrineTypeMapping($key, $value);
            }

            return $connection->getDoctrineSchemaManager();
        }

        return $connection->getSchemaBuilder();
    }
}
