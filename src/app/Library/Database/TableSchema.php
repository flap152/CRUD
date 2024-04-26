<?php

namespace Backpack\CRUD\app\Library\Database;

class TableSchema
{
    public array $schema;

    public function __construct(string $connection, string $table)
    {
        $this->schema = app('DatabaseSchema')->getForTable($connection, $table);
    }

    /**
     * Return an array of column names in database.
     *
     * @return array
     */
    public function getColumnsNames()
    {
        return array_keys($this->schema);
    }

    public function getColumns()
    {
        return $this->schema;
    }

    /**
     * Return the column type in database.
     *
     * @param  string  $columnName
     * @return string
     */
    public function getColumnType(string $columnName)
    {
        if (! $this->schemaExists() || ! $this->schema->hasColumn($columnName)) {
            return 'varchar';
        }

        return $this->schema[$columnName]['type'];
    }

    /**
     * Check if the column exists in the database.
     *
     * @param  string  $columnName
     * @return bool
     */
    public function hasColumn($columnName)
    {
        if (! $this->schemaExists()) {
            return false;
        }

        return array_key_exists($columnName, $this->schema);
    }

    /**
     * Check if the column is nullable in database.
     *
     * @param  string  $columnName
     * @return bool
     */
    public function columnIsNullable($columnName)
    {
        if (! $this->columnExists($columnName)) {
            return true;
        }

        return $this->schema[$columnName]['nullable'] ?? true;
    }

    /**
     * Check if the column has default value set on database.
     *
     * @param  string  $columnName
     * @return bool
     */
    public function columnHasDefault($columnName)
    {
        if (! $this->columnExists($columnName)) {
            return false;
        }

        return $this->schema[$columnName]['default'] !== null;
    }

    /**
     * Get the default value for a column on database.
     *
     * @param  string  $columnName
     * @return bool
     */
    public function getColumnDefault($columnName)
    {
        if (! $this->columnExists($columnName)) {
            return false;
        }

        return $this->schema[$columnName]['default'];
    }

    /**
     * Make sure column exists or throw an exception.
     *
     * @param  string  $columnName
     * @return bool
     */
    private function columnExists($columnName)
    {
        if (! $this->schemaExists()) {
            return false;
        }

        return array_key_exists($columnName, $this->schema);
    }

    /**
     * Make sure the schema for the connection is initialized.
     *
     * @return bool
     */
    private function schemaExists()
    {
        if (! empty($this->schema)) {
            return true;
        }

        return false;
    }
}
