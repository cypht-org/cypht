<?php

namespace Database\Core;

use PDO;
use Closure;

/**
 * The Schema class provides methods for creating and modifying database tables
 */
class Schema
{
    /**
     * The PDO connection
     * @var PDO
     */
    protected static $pdo;

    /**
     * The database driver
     * @var string
     */
    protected static $driver;

    /**
     * The quote character for the database driver
     * @var array
     */
    protected static $driverQuoteMap = [
        'mysql' => '`',
        'pgsql' => '"',
        'mssql' => '[',
        'sqlite' => '`',
    ];

    /**
     * Set the PDO connection and determine the driver
     * @param PDO $pdo
     * @return void
     */
    public static function setConnection(PDO $pdo)
    {
        self::$pdo = $pdo;
        self::$driver = self::$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Check if a table exists in the database
     * @param string $table
     * @return bool
     */
    public static function hasTable($table)
    {
        switch (self::$driver) {
            case 'mysql':
                $stmt = self::$pdo->query("SHOW TABLES LIKE '$table'");
                return $stmt->rowCount() > 0;
            case 'pgsql':
                $stmt = self::$pdo->prepare("
                    SELECT EXISTS (
                        SELECT 1 
                        FROM pg_catalog.pg_tables 
                        WHERE schemaname = 'public' 
                        AND tablename = :table
                    )
                ");
                $stmt->execute(['table' => $table]);
                return (bool) $stmt->fetchColumn();
            case 'mssql':
                $stmt = self::$pdo->prepare("
                    SELECT 1 
                    FROM sys.tables 
                    WHERE name = :table
                ");
                $stmt->execute(['table' => $table]);
                return (bool) $stmt->fetchColumn();
            case 'sqlite':
                $stmt = self::$pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=:table");
                $stmt->execute(['table' => $table]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result !== false;
            default:
                return false;
        }
    }
    

    /**
     * Check if a column exists in a table
     * @param string $table
     * @param string $column
     * @return bool
     */
    public static function hasColumn($table, $column)
    {
        switch (self::$driver) {
            case 'mysql':
                $stmt = self::$pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
                return $stmt->rowCount() > 0;
            case 'pgsql':
                $stmt = self::$pdo->prepare("
                    SELECT EXISTS (
                        SELECT 1 
                        FROM information_schema.columns 
                        WHERE table_schema = 'public' 
                        AND table_name = :table 
                        AND column_name = :column
                    )
                ");
                $stmt->execute(['table' => $table, 'column' => $column]);
                return (bool) $stmt->fetchColumn();
            case 'mssql':
                $stmt = self::$pdo->prepare("
                    SELECT 1 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_NAME = :table 
                    AND COLUMN_NAME = :column
                ");
                $stmt->execute(['table' => $table, 'column' => $column]);
                return (bool) $stmt->fetchColumn();
            case 'sqlite':
                $stmt = self::$pdo->query("PRAGMA table_info('$table')");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($columns as $col) {
                    if ($col['name'] == $column) {
                        return true;
                    }
                }
                return false;
            default:
                return false;
        }
    }

    /**
     * Create a new table
     * @param string $table
     * @param Closure $callback
     * @return void
     */
    public static function create($table, Closure $callback)
    {
        $blueprint = new Blueprint();
        $callback($blueprint);
        $quotedTable = self::quoteIdentifier($table);
        $columns = implode(', ', array_map(function ($column) {
            $quotedColumn = self::quoteIdentifier($column['name']);
            $columnDefinition = "$quotedColumn ";
            // Handling column type definitions
            switch ($column['type']) {
                case 'string':
                    $length = $column['length'] ?? 255;
                    $columnDefinition .= (self::$driver === 'sqlite' ? "TEXT" : "VARCHAR($length)");
                    break;
    
                case 'blob':
                case 'longblob':
                    $columnDefinition .= (self::$driver === 'pgsql') ? "BYTEA" : "LONGBLOB";
                    break;
    
                case 'boolean':
                    $default = isset($column['default']) ? ($column['default'] ? 'TRUE' : 'FALSE') : '';
                    $columnDefinition .= "BOOLEAN" . ($default ? " DEFAULT $default" : '');
                    break;
    
                default:
                    if(self::$driver === 'pgsql' && isset($column['primary'])) {
                        // $columnDefinition .= "INTEGER"; // SERIAL will automatically add incrementing integer
                    } else {
                        $columnDefinition .= strtoupper($column['type']);
                    }
            }
            // Adding default values if applicable
            if (isset($column['default']) && !in_array($column['type'], ['boolean'])) {
                $default = is_numeric($column['default']) ? $column['default'] : "'{$column['default']}'";
                $columnDefinition .= " DEFAULT {$default}";
            }
            // Handling nullable constraint
            if(self::$driver === 'pgsql' && isset($column['primary'])) {
                //No need to add NOT NULL constraint for primary key in postgres
            }else {
                $columnDefinition .= isset($column['nullable']) && $column['nullable'] === true ? " NULL" : " NOT NULL";
            }
            if (isset($column['autoIncrement']) && $column['autoIncrement']) {
                switch (self::$driver) {
                    case 'mysql':
                    case 'mssql':
                        $columnDefinition .= " AUTO_INCREMENT PRIMARY KEY";
                        break;
                    case 'pgsql':
                        $columnDefinition .= " SERIAL PRIMARY KEY";
                        break;
                    case 'sqlite':
                        $columnDefinition .= " PRIMARY KEY";
                        break;
                }
            }
    
            return $columnDefinition;
        }, $blueprint->getColumns()));
        $sql = "CREATE TABLE IF NOT EXISTS $quotedTable ($columns";
        if ($primaryKeys = $blueprint->getPrimaryKeys()) {
            $primaryKeySql = 'PRIMARY KEY (' . implode(',', array_map([self::class, 'quoteIdentifier'], $primaryKeys)) . ')';
            $sql .= ", $primaryKeySql";
        }
        $sql .= ')';
        self::$pdo->exec($sql);
        foreach ($blueprint->getForeignKeys() as $foreignKey) {
            self::addForeignKey(
                $table,
                $foreignKey['columns'],
                $foreignKey['on'],
                $foreignKey['references']
            );
        }
    }    
    
    /**
     * Modify an existing table.
     * @param string $table
     * @param Closure $callback
     * @return void
     */
    public static function table($table, Closure $callback)
    {
        
        if (!self::hasTable($table)) {
            throw new \Exception("Table $table does not exist.");
        }
        $blueprint = new Blueprint();
        $callback($blueprint);

        foreach ($blueprint->getColumns() as $column) {
            if (!self::hasColumn($table, $column['name'])) {
                $type = self::getType($column);
                $tableName = self::quoteIdentifier($table);
                $columnName = self::quoteIdentifier($column['name']);
                $sql = "ALTER TABLE $tableName ADD COLUMN $columnName $type";
                self::$pdo->exec($sql);
            }
        }
        foreach ($blueprint->getModifiedColumns() as $modifiedColumn) {
            $tableName = self::quoteIdentifier($table);
            $columnName = self::quoteIdentifier($modifiedColumn['name']);
            $type = self::getType($modifiedColumn);
            if (self::$driver === 'sqlite') {
                self::rebuildTableWithModifiedColumn($table, $modifiedColumn);
            } else {
                $sql = "ALTER TABLE $tableName ALTER COLUMN $columnName TYPE $type";
                self::$pdo->exec($sql);
            }
        }

        foreach ($blueprint->getDroppedColumns() as $columnName) {
            $tableName = self::quoteIdentifier($table);
            $colName = self::quoteIdentifier($columnName);
            if (self::$driver === 'sqlite') {
                self::rebuildTableWithoutColumn($table, $columnName);
            } else {
                $sql = "ALTER TABLE $tableName DROP COLUMN $colName";
                self::$pdo->exec($sql);
            }
        }
    }    

    /**
     * Add a foreign key to a table
     * @param string $table
     * @param string $column
     * @param string $referenceTable
     * @param string $referenceColumn
     * @return void
     */
    public static function addForeignKey($table, $columns, $referenceTable, $referenceColumn = 'id')
    {
        $columns = (array) $columns;
        $columnList = implode(',', $columns);
        switch (self::$driver) {
            case 'mysql':
            case 'pgsql':
            case 'sqlite':
                self::$pdo->exec("ALTER TABLE `$table` ADD CONSTRAINT fk_{$table}_{$columnList} FOREIGN KEY ($columnList) REFERENCES `$referenceTable`($referenceColumn)");
                break;
            default:
                throw new \Exception("Adding foreign keys is not supported for the database driver");
        }
    }

    /**
     * Drop a table if it exists
     * @param string $table
     * @return void
     */
    public static function dropIfExists($table)
    {
        if (self::hasTable($table)) {
            $quotedTable = self::quoteIdentifier($table);
            self::$pdo->exec("DROP TABLE IF EXISTS $quotedTable");
        }
    }

    /**
     * Drop all tables in the database
     * @return void
     */
    public static function dropAllTables()
    {
        $tables = self::getAllTables();
        foreach ($tables as $table) {
            $quotedTable = self::quoteIdentifier($table);
            self::$pdo->exec("DROP TABLE IF EXISTS $quotedTable");
        }
    }

    /**
     * Get a list of all tables in the database
     * @return array
     */
    public static function getAllTables()
    {
        switch (self::$driver) {
            case 'mysql':
                $stmt = self::$pdo->query("SHOW TABLES");
                return $stmt->fetchAll(PDO::FETCH_COLUMN);
            case 'pgsql':
                $stmt = self::$pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
                return $stmt->fetchAll(PDO::FETCH_COLUMN);
            case 'mssql':
                $stmt = self::$pdo->query("SELECT table_name FROM information_schema.tables WHERE table_type = 'BASE TABLE'");
                return $stmt->fetchAll(PDO::FETCH_COLUMN);
            case 'sqlite':
                $stmt = self::$pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
                return $stmt->fetchAll(PDO::FETCH_COLUMN);
            default:
                return [];
        }
    }

    /**
     * Rename a table
     * @param string $from
     * @param string $to
     * @return void
     */
    public static function rename($from, $to)
    {
        $quotedFrom = self::quoteIdentifier($from);
        $quotedTo = self::quoteIdentifier($to);
        if (self::$driver === 'sqlite' || self::$driver === 'pgsql') {
            self::$pdo->exec("ALTER TABLE $quotedFrom RENAME TO $quotedTo");
        } else {
            self::$pdo->exec("RENAME TABLE $quotedFrom TO $quotedTo");
        }
    }

    /**
     * Enable foreign key constraints
     * @return void
     */
    public static function enableForeignKeyConstraints()
    {
        switch (self::$driver) {
            case 'mysql':
                self::$pdo->exec("SET foreign_key_checks = 1");
                break;
            case 'pgsql':
                self::$pdo->exec("SET CONSTRAINTS ALL IMMEDIATE");
                break;
            case 'sqlite':
                self::$pdo->exec("PRAGMA foreign_keys = ON");
                break;
            default:
                throw new \Exception("Foreign key constraints not supported for the database driver");
        }
    }

    /**
     * Disable foreign key constraints
     * @return void
     */
    public static function disableForeignKeyConstraints()
    {
        switch (self::$driver) {
            case 'mysql':
                self::$pdo->exec("SET foreign_key_checks = 0");
                break;
            case 'pgsql':
                self::$pdo->exec("SET CONSTRAINTS ALL DEFERRED");
                break;
            case 'sqlite':
                self::$pdo->exec("PRAGMA foreign_keys = OFF");
                break;
            default:
                throw new \Exception("Foreign key constraints not supported for the database driver");
        }
    }

    /**
     * Drop a column from a table
     * @param string $table
     * @param string $column
     * @return void
     */
    public static function dropColumn($table, $column)
    {
        $quotedTable = self::quoteIdentifier($table);
        $quotedColumn = self::quoteIdentifier($column);
        self::$pdo->exec("ALTER TABLE $quotedTable DROP COLUMN $quotedColumn");
    }

    /**
     * Get the database driver
     * @return string
     */
    public static function getDriver()
    {
        return self::$driver;
    }

    /**
     * Get the columns for a table in SQLite
     * SQLite does not support the SHOW COLUMNS query
     * @param string $table
     * @return array
     */
    protected static function getTableColumnsForSQLite($table)
    {
        $stmt = self::$pdo->query("PRAGMA table_info(`$table`)");
        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = [
                'name' => $row['name'],
                'type' => $row['type'],
                'nullable' => $row['notnull'] == 0,
                'default' => $row['dflt_value'],
                'primaryKey' => $row['pk'] == 1,
            ];
        }
        return $columns;
    }

    /**
     * Get the type for a column
     * @param array $column
     * @return string
     */
    protected static function getType($column)
    {
        $type = strtoupper($column['type']);
        if (isset($column['length'])) {
            return "$type({$column['length']})";
        }
        return $type;
    }

    /**
     * Rebuild a table with a modified column
     * @param string $table
     * @param array $column
     * @return void
     */
    protected static function rebuildTableWithModifiedColumn($table, $modifiedColumn)
    {
        $columns = self::getTableColumnsForSQLite($table);
        $newColumns = [];
        foreach ($columns as $column) {
            if ($column['name'] === $modifiedColumn['name']) {
                $newColumns[] = array_merge($column, $modifiedColumn);
            } else {
                $newColumns[] = $column;
            }
        }    
        $tempTable = "{$table}_temp_".substr(bin2hex(random_bytes(4)), 0, 8);
        self::$pdo->exec("ALTER TABLE `$table` RENAME TO `$tempTable`");
        self::create($table, function (Blueprint $blueprint) use ($newColumns) {
            foreach ($newColumns as $column) {
                $blueprint->addColumn($column['type'], $column['name'], $column);
            }
        });    
        $columnNames = implode(', ', array_column($newColumns, 'name'));
        self::$pdo->exec("INSERT INTO `$table` ($columnNames) SELECT $columnNames FROM `$tempTable`");
        self::$pdo->exec("DROP TABLE `$tempTable`");
    }       

    /**
     * Rebuild a table without a column
     * @param string $table
     * @param string $column
     * @return void
     */
    protected static function rebuildTableWithoutColumn($table, $droppedColumn)
    {
        $columns = self::getTableColumnsForSQLite($table);
        $remainingColumns = array_filter($columns, function ($column) use ($droppedColumn) {
            return $column['name'] !== $droppedColumn;
        });
        $tempTable = "{$table}_temp_".substr(bin2hex(random_bytes(4)), 0, 8);
        self::$pdo->exec("ALTER TABLE `$table` RENAME TO `$tempTable`");
        self::create($table, function (Blueprint $blueprint) use ($remainingColumns) {
            foreach ($remainingColumns as $column) {
                $blueprint->addColumn($column['type'], $column['name'], $column);
            }
        });
        $columnNames = implode(', ', array_column($remainingColumns, 'name'));
        self::$pdo->exec("INSERT INTO `$table` ($columnNames) SELECT $columnNames FROM `$tempTable`");
        self::$pdo->exec("DROP TABLE `$tempTable`");
    }    

    /**
     * Quote an identifier to prevent SQL injection.
     * The quoting style depends on the database driver.
     * 
     * @param string $identifier
     * @return string
     */
    protected static function quoteIdentifier($identifier)
    {
        $quoteChar = self::$driverQuoteMap[self::$driver] ?? null;
        if (!$quoteChar) {
            throw new \Exception("Unsupported database driver for quoting identifiers");
        }
        return "{$quoteChar}{$identifier}{$quoteChar}";
    }
}
