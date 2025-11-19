#!/usr/bin/env php

<?php

define('APP_PATH', dirname(dirname(__FILE__)).'/');
define('VENDOR_PATH', APP_PATH.'vendor/');
define('MIGRATIONS_PATH', APP_PATH.'database/migrations');

require VENDOR_PATH.'autoload.php';
require APP_PATH.'lib/framework.php';

// Allow specifying environment file via --env argument
// Usage: php setup_database.php --env=.env.test
$envFile = '.env';
$options = getopt('', ['env:']);
if (isset($options['env'])) {
    $envFile = $options['env'];
}

if (!file_exists(APP_PATH . $envFile)) {
    echo "Environment file {$envFile} not found. Please create it from the example file.\n";
    exit(1);
}

$environment = Hm_Environment::getInstance();
$environment->load($envFile);

/* get config object */
$config = new Hm_Site_Config_File();
$environment->define_default_constants($config);

$session_type = $config->get('session_type');
$auth_type = $config->get('auth_type');
$user_config_type = $config->get('user_config_type');
$db_driver = $config->get('db_driver');
define('SCHEMA_PATH', APP_PATH.'database/'.$db_driver.'_schema.sql');

$connected = false;

// NOTE: these sql commands could be db agnostic if we change the blobs to text
// Check required extensions for the DB driver
checkRequiredExtensions($db_driver);

$connection_tries = 0;
$max_tries = 10;

while (!$connected) {
    $connection_tries++;

    $conn = Hm_DB::connect($config);

    if ($conn !== false) {
        printf("Database connection successful ...\n");
        $connected = true;
    } else {
        printf("Attempting to connect to database ... ({$connection_tries}/{$max_tries})\n");
        sleep(1);
    }

    if ($connection_tries >= $max_tries) {
        error_log('Unable to connect to database');
        exit(1);
    }
}

// Setup database and run migrations
setupDatabase($conn, SCHEMA_PATH, MIGRATIONS_PATH);

print("\nDb setup finished\n");

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
        error_log('Missing required extensions: ' . implode(', ', $missing_extensions));
        exit(1);
    }
}

/**
 * Initializes the database and runs migrations.
 */
function setupDatabase(PDO $pdo, string $schemaFile, string $migrationDir) {
    if (isDatabaseEmpty($pdo)) {
        echo "Database is empty. Initializing...\n";
        initializeDatabase($pdo, $schemaFile, $migrationDir);
    } else {
        echo "Database detected. Running migrations...\n";
        ensureMigrationsTable($pdo);
        runMigrations($pdo, $migrationDir);
    }
}

/**
 * Checks if the database is empty (no tables exist).
 */
function isDatabaseEmpty(PDO $pdo): bool {
    global $db_driver;

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
function ensureMigrationsTable(PDO $pdo) {
    global $db_driver;

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
function initializeDatabase(PDO $pdo, string $schemaFile, string $migrationDir) {
    global $db_driver;
    $schemaSql = file_get_contents($schemaFile);
    $pdo->exec($schemaSql);
    echo "Database schema initialized.\n";

    ensureMigrationsTable($pdo);
    
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
function runMigrations(PDO $pdo, string $migrationDir) {
    echo "Running migrations...\n";
    global $db_driver;

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
            die("Migration failed for $migrationName: " . $e->getMessage());
        }
    }

    echo "Migrations completed.\n";
}
