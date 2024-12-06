<?php

namespace Database\Core;

use PDO;
use Exception;

class MigrationRunner
{
    /**
     * The PDO connection
     * @var PDO
     */
    protected $pdo;

    /**
     * The name of the migrations table
     * @var string
     */
    protected $migrationsTable = 'migrations';

    /**
     * MigrationRunner constructor.
     * Ensure the migrations table exists in the database.
     *
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;

        $this->ensureMigrationsTableExists();
    }

    /**
     * Ensure the migrations table exists in the database.
     *
     * @return void
     */
    private function ensureMigrationsTableExists()
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                batch INT NOT NULL,
                applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }

    /**
     * Run all the pending migrations.
     * we use sort() to sort migrations by file name (ensure run order is correct)
     *
     * @return void
     */
    public function run()
    {
        $migrationFiles = glob(__DIR__ . '/database/migrations/*.php');

        sort($migrationFiles);

        foreach ($migrationFiles as $migrationFile) {
            $migrationClass = '\\' . basename($migrationFile, '.php');

            if ($this->hasMigrationBeenRun($migrationClass)) {
                echo "Skipping migration (already run): " . $migrationClass . "\n";
                continue;
            }

            try {
                require_once $migrationFile;
                $migration = new $migrationClass();
                $migration->runUp();

                $this->markMigrationAsRun($migrationClass);

                echo "Running migration: " . $migrationClass . "\n";
            } catch (Exception $e) {
                echo "Error running migration: " . $migrationClass . " - " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Rollback the last batch of migrations.
     * we use sort() to sort migrations by file name (ensure rollback order is correct)
     *
     * @return void
     */
    public function rollback()
    {
        $batch = $this->getLastBatchNumber();

        $migrationFiles = glob(__DIR__ . '/database/migrations/*.php');

        sort($migrationFiles);

        $migrationFiles = array_reverse($migrationFiles);

        foreach ($migrationFiles as $migrationFile) {
            $migrationClass = '\\' . basename($migrationFile, '.php');

            if ($this->hasMigrationBeenRolledBack($migrationClass, $batch)) {
                try {
                    require_once $migrationFile;
                    $migration = new $migrationClass();
                    $migration->runDown();

                    $this->markMigrationAsRolledBack($migrationClass);

                    echo "Rolling back migration: " . $migrationClass . "\n";
                } catch (Exception $e) {
                    echo "Error rolling back migration: " . $migrationClass . " - " . $e->getMessage() . "\n";
                }
            }
        }
    }

    /**
     * Check if a migration has already been run.
     *
     * @param string $migrationClass
     * @return bool
     */
    private function hasMigrationBeenRun($migrationClass)
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->migrationsTable} WHERE migration = :migration");
        $stmt->execute(['migration' => $migrationClass]);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * Check if a migration has already been rolled back in the last batch.
     *
     * @param string $migrationClass
     * @param int $batch
     * @return bool
     */
    private function hasMigrationBeenRolledBack($migrationClass, $batch)
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->migrationsTable} WHERE migration = :migration AND batch = :batch");
        $stmt->execute(['migration' => $migrationClass, 'batch' => $batch]);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * Mark a migration as completed (run).
     *
     * @param string $migrationClass
     * @return void
     */
    private function markMigrationAsRun($migrationClass)
    {
        // Get the last batch number
        $batch = $this->getLastBatchNumber() + 1;

        $stmt = $this->pdo->prepare("INSERT INTO {$this->migrationsTable} (migration, batch) VALUES (:migration, :batch)");
        $stmt->execute(['migration' => $migrationClass, 'batch' => $batch]);
    }

    /**
     * Mark a migration as rolled back.
     *
     * @param string $migrationClass
     * @return void
     */
    private function markMigrationAsRolledBack($migrationClass)
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->migrationsTable} WHERE migration = :migration");
        $stmt->execute(['migration' => $migrationClass]);
    }

    /**
     * Get the most recent batch number.
     *
     * @return int
     */
    private function getLastBatchNumber()
    {
        $stmt = $this->pdo->query("SELECT MAX(batch) FROM {$this->migrationsTable}");
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get all the migrations that have been run (optionally for logging or debugging).
     *
     * @return array
     */
    public function getRanMigrations()
    {
        $stmt = $this->pdo->query("SELECT migration FROM {$this->migrationsTable}");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
