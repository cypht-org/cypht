<?php

/**
 * DB account creation, shared by scripts/create_account.php (CLI) and
 * Hm_Installer (in-process). Throws instead of die()/exit() so a failure
 * here never kills the process it's called from.
 *
 * @return array{created: bool, message: string}
 */
function create_user_account(string $username, string $password, Hm_Site_Config_File $config): array {
    if ($config->get('auth_type') != 'DB') {
        throw new RuntimeException('This script only works if DB auth is enabled in your site configuration');
    }

    $auth = new Hm_Auth_DB($config);

    $dbh = Hm_DB::connect($config);
    if (!$dbh) {
        throw new RuntimeException('Unable to connect to the database.');
    }

    try {
        $dbh->query("SELECT 1 FROM hm_user LIMIT 1");
    }
    catch (Exception $e) {
        throw new RuntimeException("Required table 'hm_user' does not exist in the database. ".
            "You may need to initialize the database structure first. Run: php ./scripts/setup_database.php");
    }

    $res = Hm_DB::execute($dbh, 'select username from hm_user where username = ?', [$username]);
    if (!empty($res)) {
        return ['created' => false, 'message' => "User '{$username}' already exists. Skipping creation..."];
    }

    $result = $auth->create($username, $password);
    return match ($result) {
        2 => ['created' => true, 'message' => 'User account created successfully.'],
        1 => throw new RuntimeException('Unable to create user account.'),
        default => throw new RuntimeException('An unknown error occurred while trying to create user account.'),
    };
}
