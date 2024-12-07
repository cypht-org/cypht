<?php

namespace Database\Core;

class Blueprint
{
    private $columns = [];
    protected $modifiedColumns = [];
    protected $droppedColumns = [];
    private $primaryKeys = [];
    private $uniqueKeys = [];
    private $indexes = [];
    private $foreignKeys = [];

    /**
     * Add a column to the table with specified type, name, and options.
     *
     * @param  string  $type    The type of the column (e.g., 'integer', 'string').
     * @param  string  $name    The name of the column.
     * @param  array   $options Additional options for the column (nullable, default, etc.).
     * @return $this
     */
    public function addColumn($type, $name, $options = [])
    {
        $this->columns[] = array_merge(['type' => $type, 'name' => $name], $options);
        return $this;
    }

    /**
     * Add a column to the table if a condition is met.
     *
     * @param  string  $type  The type of the column.
     * @param  string  $name  The name of the column.
     * @param  array   $options  Additional options for the column.
     * @param  bool|callable  $condition  A boolean or a callback that returns a boolean.
     * @return $this
     */
    public function addColumnIf($type, $name, $options = [], $condition = true)
    {
        if (is_callable($condition)) {
            $condition = $condition();
        }
        if ($condition) {
            return $this->addColumn($type, $name, $options);
        }
        return $this;
    }

    public function modifyColumn($name, $type, $options = [])
    {
        $this->modifiedColumns[] = ['name' => $name, 'type' => $type, 'options' => $options];
    }

    public function dropColumn($name)
    {
        $this->droppedColumns[] = $name;
    }

    /**
     * Create an auto-incrementing "id" column.
     *
     * @param  string  $name The name of the column (default is 'id').
     * @return $this
     */
    public function id($name = 'id') { return $this->increments($name); }

    /**
     * Create a "blob" column.
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function blob($name) { return $this->addColumn('blob', $name); }

    /**
     * Create a "bigIncrements" column.
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function bigIncrements($name) { return $this->addColumn('bigInteger', $name, ['autoIncrement' => true, 'primary' => true])->primary(); }

    /**
     * Create a "bigInteger" column.
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function bigInteger($name) { return $this->addColumn('bigInteger', $name); }

    /**
     * Create a "string" column with an optional length.
     * MySQL: VARCHAR($length), PostgreSQL: VARCHAR($length), SQLite: TEXT
     *
     * @param  string  $name   The name of the column.
     * @param  int     $length The length of the column (default is 255).
     * @return $this
     */
    public function string($name, $length = 255)
    {
        return $this->addColumn('string', $name, compact('length'));
    }
    /**
     * Create an "unsignedBigInteger" column.
     * MySQL: UNSIGNED BIGINT, PostgreSQL: BIGINT (no unsigned in PG), SQLite: INTEGER
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function unsignedBigInteger($name)
    {
        return $this->addColumn('bigInteger', $name, ['unsigned' => true]);
    }

    /**
     * Create a "binary" column.
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function binary($name) { return $this->addColumn('binary', $name); }

    /**
     * Create a "boolean" column.
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function boolean($name) { return $this->addColumn('boolean', $name); }

    /**
     * Create a "char" column with a specific length.
     *
     * @param  string  $name   The name of the column.
     * @param  int     $length The length of the column (default is 255).
     * @return $this
     */
    public function char($name, $length = 255) { return $this->addColumn('char', $name, compact('length')); }

    /**
     * Create a "dateTimeTz" column (timestamp with timezone).
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function dateTimeTz($name) { return $this->addColumn('dateTimeTz', $name); }

    /**
     * Create a "dateTime" column.
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function dateTime($name) { return $this->addColumn('dateTime', $name); }

    /**
     * Create a "date" column.
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function date($name) { return $this->addColumn('date', $name); }

    /**
     * Create a "decimal" column with precision and scale.
     *
     * @param  string  $name       The name of the column.
     * @param  int     $precision  The total number of digits (default is 8).
     * @param  int     $scale      The number of digits after the decimal point (default is 2).
     * @return $this
     */
    public function decimal($name, $precision = 8, $scale = 2) { return $this->addColumn('decimal', $name, compact('precision', 'scale')); }

    /**
     * Create a "double" column with precision and scale.
     *
     * @param  string  $name       The name of the column.
     * @param  int     $precision  The total number of digits (default is 8).
     * @param  int     $scale      The number of digits after the decimal point (default is 2).
     * @return $this
     */
    public function double($name, $precision = 8, $scale = 2) { return $this->addColumn('double', $name, compact('precision', 'scale')); }

    /**
     * Set a default value for the current column.
     *
     * @param  mixed  $value The default value.
     * @return $this
     */
    public function default($value)
    {
        $this->columns[count($this->columns) - 1]['default'] = $value;
        return $this;
    }

    /**
     * Create an "enum" column with allowed values.
     *
     * @param  string  $name    The name of the column.
     * @param  array   $allowed The allowed values for the column.
     * @return $this
     */
    public function enum($name, $allowed) { return $this->addColumn('enum', $name, ['allowed' => $allowed]); }

    /**
     * Create a "float" column with precision and scale.
     *
     * @param  string  $name       The name of the column.
     * @param  int     $precision  The total number of digits (default is 8).
     * @param  int     $scale      The number of digits after the decimal point (default is 2).
     * @return $this
     */
    public function float($name, $precision = 8, $scale = 2) { return $this->addColumn('float', $name, compact('precision', 'scale')); }

    /**
     * Create a "foreignId" column.
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function foreignId($name) { return $this->unsignedBigInteger($name); }

    /**
     * Create a "foreignId" for a related model.
     *
     * @param  string  $model The related model name.
     * @return $this
     */
    public function foreignIdFor($model) { return $this->foreignId($model . '_id'); }

    /**
     * Create a "foreignUlid" column.
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function foreignUlid($name)
    { 
        return $this->addColumn('char', $name, ['length' => 26, 'nullable' => true]);
    }

    /**
     * Create a "foreignUuid" column.
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function foreignUuid($name) {
        return $this->addColumn('char', $name, ['length' => 36, 'nullable' => true]);
    }

    /**
     * Create a "geography" column.
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function geography($name) { return $this->addColumn('geography', $name); }

    /**
     * Create a "geometry" column.
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function geometry($name) { return $this->addColumn('geometry', $name); }

    /**
     * Create an "increments" column (auto-incrementing integer).
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function increments($name) { return $this->addColumn('integer', $name, ['autoIncrement' => true, 'primary' => true]); }

    /**
     * Create an "integer" column.
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function integer($name) { return $this->addColumn('integer', $name); }

    /**
     * Create an "ipAddress" column.
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function ipAddress($name) { return $this->addColumn('ipAddress', $name); }

    /**
     * Create a "json" column.
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function json($name) { return $this->addColumn('json', $name); }

    /**
     * Create a "jsonb" column.
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function jsonb($name) { return $this->addColumn('jsonb', $name); }

    /**
     * Create a "longText" column.
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function longText($name) { return $this->addColumn('longText', $name); }

    /**
     * Create a "longBlob" column.
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function longBlob($name) { return $this->addColumn('longblob', $name); }

    /**
     * Create a "macAddress" column.
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function macAddress($name) {
        return $this->addColumn('char', $name, ['length' => 17, 'nullable' => true]);
    }

    /**
     * Create a "mediumIncrements" column (auto-incrementing integer).
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function mediumIncrements($name) { return $this->addColumn('mediumIncrements', $name); }

    /**
     * Create a "mediumInteger" column.
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function mediumInteger($name) { return $this->addColumn('mediumInteger', $name); }

    /**
     * Create a "mediumText" column.
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function mediumText($name) { return $this->addColumn('mediumText', $name); }

    /**
     * Create "morphs" columns for polymorphic relations.
     *
     * @param  string  $name The base name of the column.
     * @return $this
     */
    public function morphs($name)
    { 
        $this->addColumn('integer', $name . '_id');
        $this->addColumn('string', $name . '_type');
        return $this;
    }

    /**
     * Create "nullableMorphs" columns for polymorphic relations.
     *
     * @param  string  $name The base name of the column.
     * @return $this
     */
    public function nullableMorphs($name)
    {
        $this->addColumn('integer', $name . '_id', ['nullable' => true]);
        $this->addColumn('string', $name . '_type', ['nullable' => true]);
        return $this;
    }

    /**
     * Add a nullable constraint to the column.
     *
     * @return $this
     */
    public function nullable()
    {
        $column['options']['nullable'] = true;
        return $this;
    }

    /**
     * Create a nullable "timestamp" column.
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function nullableTimestamp($name)
    {
        $this->addColumn('timestamp', $name, ['nullable' => true]);
        return $this;
    }
    
    /**
     * Create a "timestamp" column.
     *
     * @param  string  $name The name of the column.
     * @return $this
     */
    public function timestamp($name) { return $this->addColumn('timestamp', $name, ['type' => 'timestamp']); }

    /**
     * Add a comment to the column.
     *
     * @param  string  $comment The comment to be added.
     * @return $this
     */
    public function comment($comment) {
        $this->columns[count($this->columns) - 1]['comment'] = $comment;
        return $this;
    }

    /**
     * Set the position of the current column after a specified column.
     *
     * @param  string  $column The column to place the current column after.
     * @return $this
     */
    public function after($column) {
        $this->columns[count($this->columns) - 1]['after'] = $column;
        return $this;
    }

    /**
     * Add a primary key constraint to the specified columns.
     *
     * @param  mixed  $columns The columns to be used as primary key.
     * @return $this
     */
    public function primary($columns = null) {
        if ($columns === null) {
            $column = &$this->columns[count($this->columns) - 1];
            $column['primary'] = true;
            $this->primaryKeys[] = $column['name'];
        }
        else {
            $columns = (array)$columns;
            foreach ($columns as $column) {
                $this->primaryKeys[] = $column;
                foreach ($this->columns as &$col) {
                    if ($col['name'] == $column) {
                        $col['primary'] = true;
                    }
                }
            }
        }
        return $this;
    }

    /**
     * Add a unique constraint to the specified columns.
     *
     * @param  array  $columns The columns to be used for the unique constraint.
     * @return $this
     */
    public function unique($columns = null) {
        if ($columns === null) {
            $column = &$this->columns[count($this->columns) - 1];
            $column['unique'] = true;
            $this->uniqueKeys[] = $column['name'];
        }
        else {
            $columns = (array)$columns;
            foreach ($columns as $column) {
                $this->uniqueKeys[] = $column;
                foreach ($this->columns as &$col) {
                    if ($col['name'] == $column) {
                        $col['unique'] = true;
                    }
                }
            }
        }
        return $this;
    }

    /**
     * Add an index to the specified columns.
     *
     * @param  array  $columns The columns to be indexed.
     * @return $this
     */
    public function index($columns) {
        $this->indexes = array_merge($this->indexes, (array)$columns);
        return $this;
    }

    /**
     * Add a foreign key constraint to the specified columns.
     *
     * @param  array  $columns    The columns to be used for the foreign key.
     * @param  string  $references The referenced column.
     * @param  string  $on        The table that contains the referenced column.
     * @return $this
     */
    public function foreign($columns, $references, $on) {
        $this->foreignKeys[] = compact('columns', 'references', 'on');
        return $this;
    }

    /**
     * Get the table columns.
     *
     * @return array
     */
    public function getColumns() {
        return $this->columns;
    }

    /**
     * Get the modified columns.
     *
     * @return array
     */
    public function getModifiedColumns()
    {
        return $this->modifiedColumns;
    }

    /**
     * Get the dropped columns.
     *
     * @return array
     */
    public function getDroppedColumns()
    {
        return $this->droppedColumns;
    }

    /**
     * Get the primary keys.
     *
     * @return array
     */
    public function getPrimaryKeys() {
        return $this->primaryKeys;
    }

    /**
     * Get the unique keys.
     *
     * @return array
     */
    public function getUniqueKeys() {
        return $this->uniqueKeys;
    }

    /**
     * Get the indexes.
     *
     * @return array
     */
    public function getIndexes() {
        return $this->indexes;
    }

    /**
     * Get the foreign keys.
     *
     * @return array
     */
    public function getForeignKeys() {
        return $this->foreignKeys;
    }
}
