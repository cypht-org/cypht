#!/usr/bin/env php

<?php

define('APP_PATH', dirname(dirname(__FILE__)).'/');
define('VENDOR_PATH', APP_PATH.'vendor/');

require VENDOR_PATH.'autoload.php';
require APP_PATH.'lib/framework.php';

/**
 * Function to check required extensions for a database driver
 */
function checkRequiredExtensions($db_driver, $extensions) {
    $missing_extensions = [];

    foreach ($extensions as $extension) {
        if (!extension_loaded($extension)) {
            $missing_extensions[] = $extension;
        }
    }

    if (!empty($missing_extensions)) {
        error_log(
            "The following required {$db_driver} extensions are missing: " .
            implode(', ', $missing_extensions) .
            ". Please install them.\n"
        );
        exit(1);
    }
}

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
$create_table = "CREATE TABLE IF NOT EXISTS";
$alter_table = "ALTER TABLE";
$bad_driver = "Unsupported db driver: {$db_driver}";

// NOTE: these sql commands could be db agnostic if we change the blobs to text

// Check if the required extensions for the configured DB driver are loaded
if ($db_driver == 'mysql') {
    checkRequiredExtensions('MySQL', ['mysqli', 'mysqlnd', 'pdo_mysql']);
} elseif ($db_driver == 'pgsql') {
    checkRequiredExtensions('PostgreSQL', ['pgsql', 'pdo_pgsql']);
} elseif ($db_driver !== 'sqlite') {
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

function get_existing_columns($conn, $table_name, $db_driver) {
    $columns = [];
    try {
        if ($db_driver == 'mysql') {
            $query = "SHOW COLUMNS FROM {$table_name};";
        } elseif ($db_driver == 'pgsql') {
            $query = "SELECT column_name FROM information_schema.columns WHERE table_name = '{$table_name}';";
        } elseif ($db_driver == 'sqlite') {
            $query = "PRAGMA table_info({$table_name});";
        } else {
            throw new Exception("Unsupported DB driver for column retrieval.");
        }

        $stmt = $conn->query($query);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($db_driver == 'sqlite') {
                $columns[] = $row['name'];
            } else {
                $columns[] = $row['Field'] ?? $row['column_name'];
            }
        }
    } catch (Exception $e) {
        printf("Error retrieving columns: %s\n", $e->getMessage());
    }
    return $columns;
}

function add_missing_columns($conn, $table_name, $required_columns, $db_driver) {
    global $alter_table;
    $existing_columns = get_existing_columns($conn, $table_name, $db_driver);

    foreach ($required_columns as $column_name => $column_def) {
        if (!in_array($column_name, $existing_columns)) {
            printf("Adding column %s to table %s ...\n", $column_name, $table_name);
            $query = "{$alter_table} {$table_name} ADD COLUMN {$column_name} {$column_def};";
            try {
                $conn->exec($query);
            } catch (PDOException $e) {
                printf("Error adding column %s: %s\n", $column_name, $e->getMessage());
                exit(1);
            }
        }
    }
}

$tables = [
    'hm_user_session' => [
        'mysql' => [
            'hm_id'   => 'varchar(255) PRIMARY KEY',
            'data'    => 'longblob',
            'hm_version' => 'INT DEFAULT 1',
            'date'    => 'timestamp',
        ],
        'sqlite' => [
            'hm_id' => 'varchar(255) PRIMARY KEY',
            'data' => 'longblob',
            'lock' => 'INTEGER DEFAULT 0',
            'hm_version' => 'INT DEFAULT 1',
            'date' => 'timestamp',
        ],
        'pgsql' => [
            'hm_id' => 'varchar(255) PRIMARY KEY',
            'data' => 'text',
            'hm_version' => 'INT DEFAULT 1',
            'date' => 'timestamp',
        ],
    ],
    'hm_user' => [
        'mysql' => [
            'username' => 'varchar(255) PRIMARY KEY',
            'hash' => 'varchar(255)',
        ],
        'sqlite' => [
            'username' => 'varchar(255) PRIMARY KEY',
            'hash' => 'varchar(255)',
        ],
        'pgsql' => [
            'username' => 'varchar(255) PRIMARY KEY',
            'hash' => 'varchar(255)',
        ],
    ],
    'hm_user_settings' => [
        'mysql' => [
            'username' => 'varchar(255) PRIMARY KEY',
            'settings' => 'longblob',
        ],
        'sqlite' => [
            'username' => 'varchar(255) PRIMARY KEY',
            'settings' => 'longblob',
        ],
        'pgsql' => [
            'username' => 'varchar(255) PRIMARY KEY',
            'settings' => 'text',
        ],
    ],
];

if (strcasecmp($session_type, 'DB')==0) {
    foreach ($tables as $table_name => $definitions) {
        $required_columns = $definitions[$db_driver];
    
        // Create table if it doesn't exist
        $columns = implode(', ', array_map(fn($col, $def) => "$col $def", array_keys($required_columns), $required_columns));
        $query = "{$create_table} {$table_name} ({$columns});";
        $conn->exec($query);
    
        // Add any missing columns using ALTER TABLE
        add_missing_columns($conn, $table_name, $required_columns, $db_driver);
    }
    
    print("\nDatabase setup and migration finished\n");
}

print("\nDb setup finished\n");
