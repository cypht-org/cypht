<?php

/**
 * CLI script to update a user password
 *
 * Usage:
 * - With old password (preserves user data): php ./scripts/update_password.php <username> <old_password> <new_password>
 * - Without old password (data loss warning): php ./scripts/update_password.php <username> <new_password>
 */
if (mb_strtolower(php_sapi_name()) !== 'cli') {
    die("Must be run from the command line\n");
}

$user = '';
$old_pass = '';
$new_pass = '';
$incorrect_usage_msg = "Incorrect usage\n\nUsage:\n" .
            "  With old password (preserves user config and needs [sudo]):\n    php ./scripts/update_password.php <username> <old_password> <new_password>\n\n" .
            "  Without old password (user config will be lost):\n    php ./scripts/update_password.php <username> <new_password>\n\n";

if (is_array($argv)) {
    if (count($argv) == 4) {
        // Mode 1: With old password
        $user = $argv[1];
        $old_pass = $argv[2];
        $new_pass = $argv[3];
        $preserve_data = true;
    } elseif (count($argv) == 3) {
        $user = $argv[1];
        $new_pass = $argv[2];
        $preserve_data = false;
    } else {
        die($incorrect_usage_msg);
    }
} else {
    die($incorrect_usage_msg);
}

/* debug mode has to be set to something or include files will die() */
define('DEBUG_MODE', false);

/* determine current absolute path used for require statements */
define('APP_PATH', dirname(dirname(__FILE__)).'/');
define('VENDOR_PATH', APP_PATH.'vendor/');
define('WEB_ROOT', '');

/* get the framework */
require VENDOR_PATH.'autoload.php';
require APP_PATH.'lib/framework.php';

$environment = Hm_Environment::getInstance();
$environment->load();
/* get config object */
$config = new Hm_Site_Config_File(merge_config_files(APP_PATH.'config'));
/* set the default since and per_source values */
$environment->define_default_constants($config);

/* check config for db auth */
if ($config->get('auth_type') != 'DB') {
    die("Incorrect usage\n\nThis script only works if DB auth is enabled in your site configuration\n\n");
}

$auth = new Hm_Auth_DB($config);

/**
 * Get user confirmation from stdin
 * 
 * @param string $message Message to display to the user
 * @return bool True if user provides a valid confirmation, false otherwise
 */
function get_user_confirmation($message) {
    echo $message;
    $handle = fopen("php://stdin", "r");
    $input = strtolower(trim(fgets($handle)));
    fclose($handle);

    return in_array($input, ['y', 'yes']);
}

/**
 * Change user password with error handling
 * 
 * @param Hm_Auth_DB $auth Authentication object
 * @param string $user Username
 * @param string $new_pass New password
 * @return void Dies on failure
 */
function change_password_or_die($auth, $user, $new_pass) {
    if (!$auth->change_pass($user, $new_pass)) {
        die("Error: Failed to update password\n\n");
    }
}

if ($preserve_data) {
    if (! $auth->check_credentials($user, $old_pass)) {
        die("Invalid username or password provided\n\n");
    }
    $user_config = load_user_config_object($config);
    $user_config->load($user, $old_pass);
    $load_success = ! $user_config->decrypt_failed;
    if ($load_success) {
        $config_data = $user_config->dump();

        change_password_or_die($auth, $user, $new_pass);
        try {
            $user_config->save($user, $new_pass);
            echo "Password updated successfully.\n\n";
        } catch (Exception $e) {
            echo "Error: Could not save user configuration: " . $e->getMessage() . "\n";
            echo "Reverting password change...\n";
            if ($auth->change_pass($user, $old_pass)) {
                die("Password change reverted successfully.\n\n");
            } else {
                die("CRITICAL ERROR: Password was changed but could not be reverted! You need to revert it manually.\n\n");
            }
        }

    } else {
        $confirm = get_user_confirmation(
            "Warning: Could not decrypt existing user configuration with provided old password.\n" .
            "User data may not be preserved. Continue? (y/N): "
        );

        if (!$confirm) {
            die("Password update cancelled.\n\n");
        } else {
            change_password_or_die($auth, $user, $new_pass);
            echo "Password updated successfully !\n\n";
        }
    }
} else {
    $confirm = get_user_confirmation(
        "Not providing old password will result in LOSS OF ALL USER CONFIGURATION DATA!\n" .
        "Are you sure you want to continue? Type 'y' or 'yes' to confirm: "
    );

    if (!$confirm) {
        die("Password update cancelled.\n\n");
    }

    change_password_or_die($auth, $user, $new_pass);
    echo "Password updated successfully.\n";
}
