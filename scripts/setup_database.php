#!/usr/bin/env php

<?php

define('APP_PATH', dirname(dirname(__FILE__)).'/');
define('VENDOR_PATH', APP_PATH.'vendor/');
define('MIGRATIONS_PATH', APP_PATH.'database/migrations');

require VENDOR_PATH.'autoload.php';
require APP_PATH.'lib/framework.php';

$environment = Hm_Environment::getInstance();
$environment->load();

/* get config object */
$config = new Hm_Site_Config_File();
/* set the default since and per_source values */
$environment->define_default_constants($config);

$session_type = $config->get('session_type');
$auth_type = $config->get('auth_type');
$user_config_type = $config->get('user_config_type');
$db_driver = $config->get('db_driver');

$connected = false;
// NOTE: these sql commands could be db agnostic if we change the blobs to text

// Check if the required extensions for the configured DB driver are loaded
if ($db_driver == 'mysql') {
    $required_extensions = ['mysqli', 'mysqlnd', 'pdo_mysql'];
    $missing_extensions = [];
    foreach ($required_extensions as $extension) {
        if (!extension_loaded($extension)) {
            $missing_extensions[] = $extension;
        }
    }
    if (!empty($missing_extensions)) {
        error_log('The following required MySQL extensions are missing: ' . implode(', ', $missing_extensions) . ". Please install them.\n");
        exit(1);
    }
} elseif ($db_driver == 'pgsql') {
    $required_extensions = ['pgsql', 'pdo_pgsql'];
    $missing_extensions = [];

    foreach ($required_extensions as $extension) {
        if (!extension_loaded($extension)) {
            $missing_extensions[] = $extension;
        }
    }

    if (!empty($missing_extensions)) {
        error_log('The following required PostgreSQL extensions are missing: ' . implode(', ', $missing_extensions) . ". Please install them.\n");
        exit(1);
    }
} elseif ($db_driver !== 'sqlite'){
    error_log("Unsupported DB driver: {$db_driver}");
    exit(1);
}

$connection_tries=0;
$max_tries=10;

while (!$connected) {
    $connection_tries = $connection_tries + 1;

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

runMigrations($conn, MIGRATIONS_PATH);

function runMigrations(PDO $pdo, string $migrationDir) {
    global $db_driver;
    switch ($db_driver) {
        case 'mysql':
            $createTableSql = "
                CREATE TABLE IF NOT EXISTS migrations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL,
                    batch INT NOT NULL,
                    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
            ";
            break;

        case 'pgsql':
            $createTableSql = "
                CREATE TABLE IF NOT EXISTS migrations (
                    id SERIAL PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL,
                    batch INT NOT NULL,
                    applied_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
                );
            ";
            break;

        case 'sqlite':
            $createTableSql = "
                CREATE TABLE IF NOT EXISTS migrations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    migration TEXT NOT NULL,
                    batch INTEGER NOT NULL,
                    applied_at TEXT DEFAULT CURRENT_TIMESTAMP
                );
            ";
            break;

        default:
            throw new \Exception("Unsupported database driver: " . $db_driver);
    }
    $pdo->exec($createTableSql);
    $executed = $pdo->query("SELECT migration FROM migrations")
                    ->fetchAll(PDO::FETCH_COLUMN);

    $migrationFiles = glob($migrationDir . '/*.sql');
    if ($db_driver !== 'sqlite') {
        $migrationFiles = array_filter($migrationFiles, function ($file) {
            return basename($file) !== '20241209040300_add_lock_to_hm_user_session_table.sql';
        });
    }
    foreach ($migrationFiles as $file) {
        if (in_array(basename($file), $executed)) {
            continue;
        }

        try {
            $sql = file_get_contents($file);
            $pdo->exec($sql);
            
            $stmt = $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (:migration, :batch)");
            $stmt->execute([
                'migration' => basename($file),
                'batch' => 1
            ]);
            echo "Migrated: " . basename($file) . PHP_EOL;
        } catch (PDOException $e) {
            die("Migration failed: " . $e->getMessage());
        }
    }
}
// if (strcasecmp($session_type, 'DB')==0) {

// }

print("\nDb setup finished\n");
