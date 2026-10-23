<?php

/**
 * Database schema setup and migrations, shared by scripts/setup_database.php
 * (CLI) and Hm_Installer (in-process). Throws instead of die()/exit() so a
 * failure here never kills the process it's called from.
 */

/**
 * Checks for required extensions based on the DB driver.
 */
function checkRequiredExtensions(string $db_driver) {
    $extensions = match ($db_driver) {
        'mysql' => ['mysqli', 'mysqlnd', 'pdo_mysql'],
        'pgsql' => ['pgsql', 'pdo_pgsql'],
        'sqlite' => [],
        default => [],
    };

    $missing_extensions = array_filter($extensions, fn($ext) => !extension_loaded($ext));

    if (!empty($missing_extensions)) {
        throw new RuntimeException('Missing required extensions: ' . implode(', ', $missing_extensions));
    }
}

/**
 * Connects to the database, retrying once a second up to $max_tries times.
 */
function connect_database_with_retry(Hm_Site_Config_File $config, int $max_tries = 10): PDO {
    $connection_tries = 0;

    while (true) {
        $connection_tries++;
        $conn = Hm_DB::connect($config);

        if ($conn !== false) {
            printf("Database connection successful ...\n");
            return $conn;
        }

        if ($connection_tries >= $max_tries) {
            throw new RuntimeException('Unable to connect to database');
        }

        printf("Attempting to connect to database ... ({$connection_tries}/{$max_tries})\n");
        sleep(1);
    }
}

/**
 * Checks required extensions, connects, and runs schema setup/migrations
 * for the database described by $config.
 */
function run_database_setup(Hm_Site_Config_File $config, string $migrations_path) {
    $db_driver = $config->get('db_driver');
    $schema_path = APP_PATH.'database/'.$db_driver.'_schema.sql';

    checkRequiredExtensions($db_driver);
    $conn = connect_database_with_retry($config);
    setupDatabase($conn, $schema_path, $migrations_path, $db_driver);
}

/**
 * Initializes the database and runs migrations.
 */
function setupDatabase(PDO $pdo, string $schemaFile, string $migrationDir, string $db_driver) {
    if (isDatabaseEmpty($pdo, $db_driver)) {
        echo "Database is empty. Initializing...\n";
        initializeDatabase($pdo, $schemaFile, $migrationDir, $db_driver);
    } else {
        echo "Database detected. Running migrations...\n";
        ensureMigrationsTable($pdo, $db_driver);
        runMigrations($pdo, $migrationDir, $db_driver);
    }
}

/**
 * Checks if the database is empty (no tables exist).
 */
function isDatabaseEmpty(PDO $pdo, string $db_driver): bool {
    $checkTablesSql = match ($db_driver) {
        'mysql' => "SHOW TABLES;",
        'pgsql' => "SELECT table_name FROM information_schema.tables WHERE table_schema='public';",
        'sqlite' => "SELECT name FROM sqlite_master WHERE type='table';",
        default => throw new Exception("Unsupported database driver: " . $db_driver),
    };

    $tables = $pdo->query($checkTablesSql)->fetchAll(PDO::FETCH_COLUMN);

    return empty($tables);
}

/**
 * Ensures the `migrations` table exists for existing databases.
 */
function ensureMigrationsTable(PDO $pdo, string $db_driver) {
    try {
        $pdo->query("SELECT 1 FROM migrations LIMIT 1");
    } catch (PDOException $e) {
        echo "Migrations table not found. Creating it...\n";

        $createTableSql = match ($db_driver) {
            'mysql' => "
                CREATE TABLE migrations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL,
                    batch INT NOT NULL,
                    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
            ",
            'pgsql' => "
                CREATE TABLE migrations (
                    id SERIAL PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL,
                    batch INT NOT NULL,
                    applied_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
                );
            ",
            'sqlite' => "
                CREATE TABLE migrations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    migration TEXT NOT NULL,
                    batch INTEGER NOT NULL,
                    applied_at TEXT DEFAULT CURRENT_TIMESTAMP
                );
            ",
            default => throw new Exception("Unsupported database driver: " . $db_driver),
        };

        $pdo->exec($createTableSql);
        echo "Migrations table created.\n";
    }
}

/**
 * Initializes the database schema and populates migrations for new installations.
 */
function initializeDatabase(PDO $pdo, string $schemaFile, string $migrationDir, string $db_driver) {
    $schemaSql = file_get_contents($schemaFile);
    $pdo->exec($schemaSql);
    echo "Database schema initialized.\n";

    ensureMigrationsTable($pdo, $db_driver);

    $migrationFiles = glob($migrationDir .'/'.$db_driver.'/*.sql');
    $stmt = $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (:migration, :batch)");
    foreach ($migrationFiles as $file) {
        $stmt->execute([
            'migration' => basename($file),
            'batch' => 0, // Mark as pre-applied
        ]);
    }

    echo "Migrations table populated for new installation.\n";
}

/**
 * Executes pending migrations for existing databases.
 */
function runMigrations(PDO $pdo, string $migrationDir, string $db_driver) {
    echo "Running migrations...\n";

    $executed = $pdo->query("SELECT migration FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
    $migrationFiles = glob($migrationDir .'/'.$db_driver.'/*.sql');
    $stmt = $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (:migration, :batch)");

    foreach ($migrationFiles as $file) {
        $migrationName = basename($file);
        if (in_array($migrationName, $executed)) {
            continue;
        }

        try {
            $sql = file_get_contents($file);
            $pdo->exec($sql);

            $stmt->execute([
                'migration' => $migrationName,
                'batch' => 1
            ]);

            echo "Migrated: $migrationName\n";
        } catch (PDOException $e) {
            throw new RuntimeException("Migration failed for $migrationName: " . $e->getMessage());
        }
    }

    echo "Migrations completed.\n";
}
