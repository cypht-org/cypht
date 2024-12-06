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
            case 'pgsql':
            case 'mssql':
                $stmt = self::$pdo->query("SHOW TABLES LIKE '$table'");
                return $stmt->rowCount() > 0;
            case 'sqlite':
                $stmt = self::$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
                return $stmt->rowCount() > 0;
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
            case 'pgsql':
            case 'mssql':
                $stmt = self::$pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
                return $stmt->rowCount() > 0;
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

        $columns = implode(', ', $blueprint->getColumns());
        $sql = "CREATE TABLE IF NOT EXISTS `$table` ($columns)";

        // Executing the SQL statement depending on the database driver
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

        // Add primary key if defined in Blueprint
        if ($primaryKeys = $blueprint->getPrimaryKeys()) {
            $primaryKeySql = 'PRIMARY KEY (' . implode(',', $primaryKeys) . ')';
            self::$pdo->exec("ALTER TABLE `$table` ADD CONSTRAINT pk_{$table} $primaryKeySql");
        }

        // Add unique constraints if defined in Blueprint
        foreach ($blueprint->getUniqueKeys() as $uniqueColumns) {
            $uniqueKeySql = 'UNIQUE (' . implode(',', (array)$uniqueColumns) . ')';
            self::$pdo->exec("ALTER TABLE `$table` ADD CONSTRAINT unique_{$table} (" . implode(',', (array)$uniqueColumns) . ")");
        }

        // Add indexes if defined in Blueprint
        foreach ($blueprint->getIndexes() as $indexColumns) {
            $indexSql = 'INDEX (' . implode(',', (array)$indexColumns) . ')';
            self::$pdo->exec("ALTER TABLE `$table` ADD $indexSql");
        }

        // Add foreign keys if defined in Blueprint
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
        self::$pdo->exec("RENAME TABLE `$from` TO `$to`");
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
}
