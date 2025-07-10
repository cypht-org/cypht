<?php
/* debug mode has to be set to something or include files will die() */
define('DEBUG_MODE', false);

/* determine current absolute path used for require statements */
define('APP_PATH', dirname(__FILE__).'/');
define('VENDOR_PATH', APP_PATH.'vendor/');
define('WEB_ROOT', '');

require 'lib/framework.php';
$config = new Hm_Site_Config_File();

echo "Auth type: " . $config->get('auth_type') . "\n";
echo "IMAP auth server: " . $config->get('imap_auth_server') . "\n";
echo "IMAP auth port: " . $config->get('imap_auth_port') . "\n";
echo "IMAP auth name: " . $config->get('imap_auth_name') . "\n";
echo "Admin users: " . $config->get('admin_users') . "\n";
?> 