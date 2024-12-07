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
                $stmt = self::$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
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
        $columns = implode(', ', array_map(function($column) {
            $columnDefinition = "`{$column['name']}` ";
            if (isset($column['length'])) {
                if ($column['type'] == 'string') {
                    if (self::$driver == 'sqlite') {
                        $columnDefinition .= " TEXT";
                    } else {
                        $columnDefinition .= " VARCHAR"; 
                    }
                    $columnDefinition .= "({$column['length']})";
                }
            } else {
                $columnDefinition .= strtoupper($column['type']);
            }
            if (isset($column['default'])) {
                if (is_numeric($column['default'])) {
                    $columnDefinition .= " DEFAULT {$column['default']}";
                } else {
                    $columnDefinition .= " DEFAULT '{$column['default']}'";
                }
            }
            if (isset($column['nullable'])){
                if($column['nullable'] === false) {
                    $columnDefinition .= " NOT NULL";
                } elseif ($column['nullable'] === true) {
                    $columnDefinition .= " NULL";
                }
            }else {
                $columnDefinition .= " NOT NULL";
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
                        //SQLite automatically treats INTEGER PRIMARY KEY columns as autoincrementing by default. 
                        //So, you don’t need to add AUTOINCREMENT explicitly unless you want it to enforce uniqueness and ensure it doesn't reuse row IDs.
                        // $columnDefinition .= " AUTOINCREMENT";
                        break;
                    default:
                        throw new \Exception("Unsupported database driver for auto increment");
                }
            }
            if ($column['type'] === 'enum' && isset($column['allowed'])) {
                $allowedValues = implode("', '", $column['allowed']);
                $columnDefinition .= " CHECK (`{$column['name']}` IN ('$allowedValues'))";
            }    
            if (!empty($column['check'])) {
                $columnDefinition .= " CHECK ({$column['check']})";
            }
            if (!empty($column['unsigned'])) {
                switch (self::$driver) {
                    case 'mysql':
                        $columnDefinition .= " UNSIGNED";
                        break;
                    case 'pgsql':
                    case 'sqlite':
                    case 'mssql':
                        $columnDefinition .= " CHECK ({$column['name']} >= 0)";
                        break;
                    default:
                        throw new \Exception("Unsupported database driver for unsigned columns");
                }
            }
    
            return $columnDefinition;
        }, $blueprint->getColumns()));
        $sql = "CREATE TABLE IF NOT EXISTS `$table` ($columns";
        // Adding primary key constraint to the table creation statement
        if ($primaryKeys = $blueprint->getPrimaryKeys()) {
            $primaryKeySql = 'PRIMARY KEY (' . implode(',', $primaryKeys) . ')';
            $sql .= ', ' . $primaryKeySql;
        }
        // Adding unique constraints to the table creation statement
        foreach ($blueprint->getUniqueKeys() as $uniqueColumns) {
            $uniqueKeySql = 'UNIQUE (' . implode(',', (array)$uniqueColumns) . ')';
            $sql .= ', ' . $uniqueKeySql;
        }
        // Adding indexes to the table creation statement
        foreach ($blueprint->getIndexes() as $indexColumns) {
            $indexSql = 'INDEX (' . implode(',', (array)$indexColumns) . ')';
            $sql .= ', ' . $indexSql;
        }
        $sql .= ')';
        switch (self::$driver) {
            case 'mysql':
            case 'pgsql':
            case 'mssql':
                self::$pdo->exec($sql);
                break;
            case 'sqlite':
                self::$pdo->exec($sql);
                break;
            default:
                throw new \Exception("Unsupported database driver");
        }
    
        // Adding foreign keys if defined
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
            throw new \Exception("Table `$table` does not exist.");
        }
    
        $blueprint = new Blueprint();
        $callback($blueprint);
    
        foreach ($blueprint->getColumns() as $column) {
            if (!self::hasColumn($table, $column['name'])) {
                $type = self::getType($column);
                self::addColumnToTable($table, $column['name'], $type, $column);
            }
        }
    
        foreach ($blueprint->getModifiedColumns() as $column) {
            if (self::$driver === 'sqlite') {
                self::rebuildTableWithModifiedColumn($table, $column);
            } else {
                $type = self::getType($column);
                self::modifyColumn($table, $column['name'], $type, $column);
            }
        }

        foreach ($blueprint->getDroppedColumns() as $columnName) {
            if (self::$driver === 'sqlite') {
                self::rebuildTableWithoutColumn($table, $columnName);
            } else {
                self::dropColumn($table, $columnName);
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
            self::$pdo->exec("DROP TABLE IF EXISTS `$table`");
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
            self::$pdo->exec("DROP TABLE IF EXISTS `$table`");
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
            case 'pgsql':
            case 'mssql':
                $stmt = self::$pdo->query("SHOW TABLES");
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
        if (self::$driver === 'sqlite') {
            self::$pdo->exec("ALTER TABLE `$from` RENAME TO `$to`");
        } else {
            self::$pdo->exec("RENAME TABLE `$from` TO `$to`");
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
        self::$pdo->exec("ALTER TABLE `$table` DROP COLUMN `$column`");
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
     * Add a column to an existing table
     * @param string $table
     * @param string $name
     * @param string $type
     * @param array $options
     * @return void
     */
    protected static function addColumnToTable($table, $name, $type, $options)
    {
        $sql = "ALTER TABLE `$table` ADD COLUMN `$name` $type";

        // TODO: Add additional options (nullable, default, etc.) here
        self::$pdo->exec($sql);
    }

    /**
     * Modify an existing column in a table
     * @param string $table
     * @param string $column
     * @param string $type
     * @param array $options
     * @return void
     */
    protected static function modifyColumn($table, $column, $type, $options)
    {
        if (self::$driver === 'sqlite') {
            self::modifyColumnForSQLite($table, $column, $type, $options);
        }else {
            $sql = "ALTER TABLE `$table` MODIFY `$column` $type";
            //TODO: Add additional options (nullable, default, etc.) here
            self::$pdo->exec($sql);
        }
    }

    /**
     * Modify an existing column in a table for SQLite
     * we will need 4 steps to modify a column in SQLite
     * @param string $table
     * @param string $column
     * @param string $type
     * @param array $options
     * @return void
     */
    protected static function modifyColumnForSQLite($table, $column, $type, $options)
    {
        $newTable = $table . '_new';
        $columns = self::getTableColumnsForSQLite($table);
        $columnsSql = [];
        foreach ($columns as $col) {
            if ($col['name'] !== $column) {
                $columnsSql[] = "`{$col['name']}` {$col['type']}";
            } else {
                $columnsSql[] = "`{$col['name']}` $type";
            }
        }

        // Create the new table
        $columnsSql = implode(', ', $columnsSql);
        $createTableSql = "CREATE TABLE `$newTable` ($columnsSql)";
        self::$pdo->exec($createTableSql);

        // Step 2: Copy data from the old table to the new table
        $copyDataSql = "INSERT INTO `$newTable` SELECT * FROM `$table`";
        self::$pdo->exec($copyDataSql);

        // Step 3: Drop the old table
        $dropOldTableSql = "DROP TABLE `$table`";
        self::$pdo->exec($dropOldTableSql);

        // Step 4: Rename the new table to the original table name
        $renameTableSql = "ALTER TABLE `$newTable` RENAME TO `$table`";
        self::$pdo->exec($renameTableSql);
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
     * Get the existing columns for a table
     * @param string $table
     * @return array
     */
    protected static function getExistingColumns($table)
    {
        $sql = "PRAGMA table_info(`$table`)";
        $stmt = self::$pdo->query($sql);
    
        $columns = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $columns[] = [
                'name' => $row['name'],
                'type' => $row['type'],
                'nullable' => $row['notnull'] == 0,
                'default' => $row['dflt_value'],
            ];
        }
    
        return $columns;
    }

    /**
     * Rebuild the table with the modified column
     * @param string $table
     * @param array $modifiedColumn
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

        $columnNames = implode(', ', array_column($columns, 'name'));
        self::$pdo->exec("INSERT INTO `$table` ($columnNames) SELECT $columnNames FROM `$tempTable`");

        self::$pdo->exec("DROP TABLE `$tempTable`");
    } 

    /**
     * Rebuild the table without the dropped column
     * @param string $table
     * @param string $droppedColumn
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
     * Get the column type for the database driver
     * @param array $column
     * @return string
     */
    private static function getType($column)
    {
        if ($column['type'] == 'string') {
            if (self::$driver == 'sqlite') {
                $type = " TEXT";
            } else {
                $type = " VARCHAR"; 
            }
        }else {
            $type = strtoupper($column['type']);
        }
        return $type;
    }
}
