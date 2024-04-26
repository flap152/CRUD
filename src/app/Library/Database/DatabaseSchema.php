<?php

namespace Backpack\CRUD\app\Library\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

final class DatabaseSchema
{
    private array $schema;

    /**
     * Return the schema for the table.
     *
     * @param  string  $connection
     * @param  string  $table
     * @return array
     */
    public function getForTable(string $connection, string $table)
    {
        $this->generateDatabaseSchema($connection, $table);

        return $this->schema[$connection][$table] ?? [];
    }

    /**
     * Generates and store the database schema.
     *
     * @param  string  $connection
     * @param  string  $table
     * @return void
     */
    private function generateDatabaseSchema(string $connection, string $table)
    {
        if (! isset($this->schema[$connection])) {
            $this->schema[$connection] = self::mapTables($connection);
        } else {
            // check for a specific table in case it was created after schema had been generated.
            if (! isset($this->schema[$connection][$table])) {
                $this->schema[$connection][$table] = self::mapTable($connection, $table);
            }
        }
    }

    /**
     * Map the tables from raw db values into an usable array.
     *
     *
     * @return array
     */
    private static function mapTables(string $connection)
    {
        return LazyCollection::make(self::getSchemaManager($connection)->getTables())->mapWithKeys(function ($table, $key) use ($connection) {
            return [$table['name'] => self::mapTable($connection, $table['name'])];
        })->toArray();
    }

    private static function mapTable($connection, $table)
    {
        $indexedColumns = self::getIndexColumnNames($connection, $table);

        return LazyCollection::make(self::getSchemaManager($connection)->getColumns($table))->mapWithKeys(function ($column, $key) use ($indexedColumns) {
            $column['index'] = array_key_exists($column['name'], $indexedColumns) ? true : false;

            return [$column['name'] => $column];
        })->toArray();
    }

    private static function getIndexColumnNames($connection, $table)
    {
        $indexedColumns = \Illuminate\Support\Arr::flatten(
            array_column(
                self::getSchemaManager($connection)->getIndexes($table), 'columns')
        );

        return array_unique($indexedColumns);
    }

    public function getColumns()
    {
        return $this->schema;
    }

    public function getColumnType(string $connection, string $table, string $columnName)
    {
        return $this->schema[$connection][$table][$columnName]['type'] ?? 'text';
    }

    public function columnHasDefault(string $connection, string $table, string $columnName)
    {
        return isset($this->schema[$connection][$table][$columnName]['default']);
    }

    public function columnIsNullable(string $connection, string $table, string $columnName)
    {
        return $this->schema[$connection][$table][$columnName]['nullable'] ?? true;
    }

    public function getColumnDefault(string $connection, string $table, string $columnName)
    {
        return $this->schema[$connection][$table][$columnName]['default'] ?? false;
    }

    private static function getSchemaManager(string $connection)
    {
        return DB::connection($connection)->getSchemaBuilder();
    }
}
