<?php

if (strtolower(php_sapi_name()) !== 'cli') {
    die("Must be run from the command line\n");
}
/* debug mode has to be set to something or include files will die() */
define('DEBUG_MODE', false);

/* determine current absolute path used for require statements */
define('APP_PATH', dirname(dirname(__FILE__)).'/');
define('VENDOR_PATH', APP_PATH.'vendor/');
define('WEB_ROOT', '');

/* get the framework */
require APP_PATH.'lib/framework.php';

function read_config($source) {
    if ($source == 'Defaults') {
        return array();
    }
    if ($source == 'hm3.rc') {
        return @unserialize(file_get_contents('../'.$source));
    }
    return parse_ini_file('../'.$source);
}

function check_php() {
    $version = phpversion();
    if (substr($version, 0, 3) >= 5.4) {
        $version_class = 'yes';
    }
    else {
        $version_class = 'no';
    }
    $mb = function_exists('mb_strpos');
    $curl = function_exists('curl_exec');
    $pdo = class_exists('PDO');
    $ssl = function_exists('openssl_random_pseudo_bytes');

    return '<div class="settings_subtitle"><img alt="" src="'.Hm_Image_Sources::$caret.'" /> PHP Support '.
        (Hm_Dispatch::is_php_setup() ? '<span class="yes">&#10003;</span>' : '<span class="no">X</span>' ).
        '</div><div class="config_settings_container">'.
        '<table class="settings_table config_settings">'.
        '<tr><td>Version</td><td class="'.$version_class.'">'.$version.'</td></tr>'.
        '<tr><td>Multibyte string support</td><td class="'.($mb ? 'yes' : 'no').'">'.($mb ? 'yes' : 'no').'</td></tr>'.
        '<tr><td>Curl support</td><td class="'.($curl ? 'yes' : 'no').'">'.($curl ? 'yes' : 'no').'</td></tr>'.
        '<tr><td>PDO Database support</td><td class="'.($pdo ? 'yes' : 'no').'">'.($pdo ? 'yes' : 'no').'</td></tr>'.
        '<tr><td>OpenSSL support</td><td class="'.($ssl ? 'yes' : 'no').'">'.($ssl ? 'yes' : 'no').'</td></tr>'.
        '</table></div>';

}

function config_source($selected='hm3.sample.ini') {
    $opts = array('Defaults', 'hm3.sample.ini', 'hm3.ini', 'hm3.rc');
    return '<table style="width: 200px; margin: 40px; white-space: nowrap;">'.
        '<tr><td>'.select_box('source', $selected, $opts).' <input type="submit" value="Update" /></td></tr>'.
        '</table>';
}

function session_type_setting($selected=false) {
    return '<tr><td>Session Type</td><td>'.select_box('session_type', $selected, array('DB', 'PHP')).'</td></tr>';
}

function select_box($name, $selected, $opts) {
    $res = '<select name="'.$name.'">';
    foreach ($opts as $opt) {
        $res .= '<option ';
        if ($selected == $opt) {
            $res .= 'selected="selected" ';
        }
        $res .= 'value="'.$opt.'">'.$opt.'</option>';
    }
    $res .= '</select>';
    return $res;
}

function pop3_auth_name_setting($current) {
    return '<tr><td>POP3 authentication server name</td><td><input type="text" value="'.$current.'" name="pop3_auth_name" /></td></tr>';
}

function pop3_auth_port_setting($current) {
    return '<tr><td>POP3 authentication server port</td><td><input type="number" value="'.$current.'" name="pop3_auth_port" /></td></tr>';
}

function pop3_auth_server_setting($current) {
    return '<tr><td>POP3 authentication server hostname</td><td><input type="text" value="'.$current.'" name="pop3_auth_server" /></td></tr>';
}

function pop3_auth_tls_setting($current) {
    return '<tr><td>POP3 authentication server TLS</td><td><input type="checkbox" value="1" '.($current ? 'checked="checked" ' : '').
        ' name="pop3_auth_tls" /></td></tr>';
}

function imap_auth_name_setting($current) {
    return '<tr><td>IMAP authentication server name</td><td><input type="text" value="'.$current.'" name="imap_auth_name" /></td></tr>';
}

function imap_auth_port_setting($current) {
    return '<tr><td>IMAP authentication server port</td><td><input type="number" value="'.$current.'" name="imap_auth_port" /></td></tr>';
}

function imap_auth_server_setting($current) {
    return '<tr><td>IMAP authentication server hostname</td><td><input type="text" value="'.$current.'" name="imap_auth_server" /></td></tr>';
}

function imap_auth_tls_setting($current) {
    return '<tr><td>IMAP authentication server TLS</td><td><input type="checkbox" '.($current ? 'checked="checked" ' : '').' value="1" '.
        'name="imap_auth_tls" /></td></tr>';
}

function auth_type_setting($selected=false) {
    return '<tr><td>Authentication Type</td><td>'.select_box('auth_type', $selected, array('DB', 'IMAP', 'POP3')).'</td></tr>';
}

function default_smtp_name_setting($current) {
    return '<tr><td>Default SMTP server name</td><td><input type="text" value="'.$current.'" name="default_smtp_name" /></td></tr>';
}

function default_smtp_server_setting($current) {
    return '<tr><td>Default SMTP server</td><td><input type="text" value="'.$current.'" name="default_smtp_server" /></td></tr>';
}

function default_smtp_port_setting($current) {
    return '<tr><td>Default SMTP server port</td><td><input type="number" value="'.$current.'" name="default_smtp_port" /></td></tr>';
}

function encrypt_ajax_requests_setting($current) {
    return '<tr><td>Encrypt AJAX responses</td><td><input type="checkbox" '.($current ? 'checked="checked" ' : '').' value="1" '.
        'name="encrypt_ajax_requests" /></td></tr>';
}

function encrypt_local_storage_setting($current) {
    return '<tr><td>Encrypt local session storage</td><td><input type="checkbox" '.($current ? 'checked="checked" ' : '').' value="1" '.
        'name="encrypt_local_storage" /></td></tr>';
}

function default_smtp_tls_setting($current) {
    return '<tr><td>Default SMTP server TLS</td><td><input type="checkbox" '.($current ? 'checked="checked" ' : '').' value="1" '.
        'name="default_smtp_tls" /></td></tr>';
}

function default_language_setting($current) {
    return '<tr><td>Default language</td><td><input type="text" value="'.$current.'" name="default_language" /></td></tr>';
}

function user_config_type_setting($selected=false) {
    return '<tr><td>User Configuration type</td><td>'.select_box('user_config_type', $selected, array('DB', 'file')).'</td></tr>';
}

function attachment_dir_setting($current) {
    return '<tr><td>Attachment storage directory</td><td><input type="text" value="'.$current.'" name="attachment_dir" /></td></tr>';
}

function app_data_dir_setting($current) {
    return '<tr><td>Application storage directory</td><td><input type="text" value="'.$current.'" name="app_data_dir" /></td></tr>';
}

function user_settings_dir_setting($current) {
    return '<tr><td>User settings storage directory</td><td><input type="text" value="'.$current.'" name="user_settings_dir" /></td></tr>';
}

function disable_tls_setting($current) {
    return '<tr><td>Allow non-TLS browser connections</td><td><input type="checkbox" '.($current ? 'checked="checked" ' : '').' value="1" '.
        'name="disable_tls" /></td></tr>';
}

function admin_users_setting($current) {
    return '<tr><td>Admin user list</td><td><input type="text" value="'.$current.'" name="admin_users" /></td></tr>';
}

function app_name_setting($current) {
    return '<tr><td>Application name</td><td><input type="text" value="'.$current.'" name="app_name" /></td></tr>';
}

function js_compress_setting($current) {
    return '<tr><td>Javascript compression command</td><td><input type="text" value="'.$current.'" name="js_compress" /></td></tr>';
}

function css_compress_setting($current) {
    return '<tr><td>CSS compression command</td><td><input type="text" value="'.$current.'" name="css_compress" /></td></tr>';
}

function db_host_setting($current) {
    return '<tr><td>Database hostname</td><td><input type="text" value="'.$current.'" name="db_host" /></td></tr>';
}

function db_name_setting($current) {
    return '<tr><td>Database name</td><td><input type="text" value="'.$current.'" name="db_name" /></td></tr>';
}

function db_user_setting($current) {
    return '<tr><td>Database username</td><td><input type="text" value="'.$current.'" name="db_user" /></td></tr>';
}

function db_pass_setting($current) {
    return '<tr><td>Database password</td><td><input type="password" value="'.$current.'" name="db_pass" /></td></tr>';
}

function db_driver_setting($current) {
    $opts = PDO::getAvailableDrivers();
    return '<tr><td>Databse Type</td><td>'.select_box('auth_type', $selected, $opts).'</td></tr>';
}

function output_settings($settings) {
    echo '<div class="settings_subtitle"><img alt="" src="'.Hm_Image_Sources::$caret.'" /> Settings</div>'.
        '<div class="config_settings_container">'.
        '<table class="settings_table settings config_settings">';
    foreach (setting_defaults() as $title => $vals) {
        echo '<tr><td colspan="2" class="config_subtitle">'.$title.'</td></tr>';
        foreach ($vals as $setting => $default) {
            if (function_exists($setting.'_setting')) {
                if (array_key_exists($setting, $settings)) {
                    $value = $settings[$setting];
                }
                else {
                    $value = $default;
                }
                $func = $setting.'_setting';
                echo $func($value);
            }
            else {
                echo '<tr><td class="setting_row">'.$setting.'</td><td>Not supported yet</td></tr>';
            }
        }
    }
    echo '</table><br style="clear: both;"></div>';
}

function setting_defaults() {
    return array(
        'General' => array(
            'session_type' => 'PHP',
            'auth_type' => 'DB',
            'default_language' => 'en',
            'user_config_type' => 'file',
            'app_data_dir' => '/var/lib/hm3',
            'user_settings_dir' => '/var/lib/hm3/users',
            'attachment_dir' => '/var/lib/hm3/attachments',
            'disable_tls' => 'false',
            'admin_users' => '',
            'app_name' => 'HM3',
            'js_compress' => 'false',
            'css_compress' => 'false',
            'encrypt_ajax_requests' => '',
            'encrypt_local_storage' => '',
        ),
        'IMAP' => array(
            'imap_auth_name' => 'localhost',
            'imap_auth_server' => 'localhost',
            'imap_auth_port' => '143',
            'imap_auth_tls' => '',
        ),
        'POP3' => array(
            'pop3_auth_name' => 'localhost',
            'pop3_auth_server' => 'localhost',
            'pop3_auth_port' => '110',
            'pop3_auth_tls' => '',
        ),
        'SMTP' => array(
            'default_smtp_name' => '',
            'default_smtp_server' => '',
            'default_smtp_port' => '',
            'default_smtp_tls' => '',
        ),
        'Database' => array(
            'db_host' => '127.0.0.1',
            'db_name' => 'test',
            'db_user' => 'test',
            'db_pass' => '123456',
            'db_driver' => 'mysql',
        ),
    );
}

function output_modules($settings) {
    echo '<div class="settings_subtitle"><img alt="" src="'.Hm_Image_Sources::$caret.
        '" /> Module Sets</div><div class="config_settings_container">'.
        '<table class="settings_table config_settings">';
    $mod_str = 'core,contacts,feeds,pop3,imap,smtp,site,account,idle_timer,calendar,'.
        'themes,nux,developer,github,wordpress,history,saved_searches,nasa';
    if (array_key_exists('modules', $settings)) {
        $mod_str = $settings['modules'];
    }
    $mods = array('core', 'contacts', 'feeds', 'pop3', 'imap', 'smtp', 'site', 'account',
        'idle_timer', 'calendar', 'themes', 'nux,developer', 'github', 'wordpress',
        'history', 'saved_searches', 'nasa');
    foreach ($mods as $mod) {
        echo '<tr><td>'.ucfirst(str_replace('_', ' ', $mod)).
            '</td><td><input type="checkbox" value="1" name="'.$mod.'" ';
        if (strstr($mod_str, $mod)) {
            echo 'checked="checked" ';
        }
        echo '/></td></tr>';
    }
    echo '</table></div>';
}

function page_style() { ?>
    <style type="text/css">
        h5 { background-color: #fff; margin: 0px; padding: 20px; font-size: 130%; font-weight: normal; color: #666; }
        table { max-width: 800px !important; }
        body, .settings_subtitle { background: none; background-color: #fff; }
        .config_settings_container { display: none; max-height: 300px; overflow-y: scroll; width: 800px; }
        p { padding-left: 40px; max-width: 800px; }
        .settings tr td { padding-left: 55px }
        .config_settings { border-right: solid 1px #eee; }
        .config_subtitle { padding-left: 35px !important; background-color: #f9f9f9; border: solid 1px #eee; }
        .yes { color: green !important; }
        .no { color: red !important; }
        .btn { margin-left: 35px; margin-top: 20px; }
    </style>

<?php }
function output_page($source) { ?><!DOCTYPE html><html dir="ltr" class="ltr_page" lang=en>
        <head>
            <meta charset="utf-8" />
            <link href="../modules/core/site.css" media="all" rel="stylesheet" type="text/css" />
            <script type="text/javascript" src="../third_party/cash.min.js"></script>
            <script type="text/javascript">$(function() {
                $('.settings_subtitle').click(function() { $(this).next().toggle(); });
                $('select[name=session_type]').change(function() { console.log('here'); });
                $('select[name=auth_type]').change(function() { console.log('here'); });
            });</script>
            <title>Cypht Setup</title>
            <?php page_style() ?>
        </head>
        <body>
            <div><h5>Cypht configuration builder</h5></div>
            <p>
                Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor
                incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis
                nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
                Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu
                fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in
                culpa qui officia deserunt mollit anim id est laborum.
            </p>
            <form method="get">
                <?php echo config_source($source) ?>
            </form>
            <form class="user_settings" method="post">
                <?php echo check_php() ?>
                <?php output_settings(read_config($source)) ?>
                <?php output_modules(read_config($source)) ?>
                <input type="button" class="btn" name="test_imap" value="Test IMAP" />
                <input type="button" class="btn" name="test_pop3" value="Test POP3" />
                <input type="button" class="btn" name="test_smtp" value="Test SMTP" /><br />
                <input type="button" class="btn" name="test_smtp" value="Test Database" />
                <input type="button" class="btn" name="test_files" value="Test Files" />
                <input type="submit" class="btn" name="build" value="Build Config" /><br />
                <input type="reset" class="btn" name="reset" value="Reset" />
            </form>
        </body>
    </html>

<?php }

if (array_key_exists('source', $_GET) &&
    in_array($_GET['source'], array('Defaults', 'hm3.sample.ini', 'hm3.ini', 'hm3.rc'), true)) {
    $source = $_GET['source'];
}
else {
    $source = 'hm3.ini';
}
output_page($source);

/*
TODO
----
- check php for correct support, provide links to php mod installation
    - curl
    - mcrypt

- read config and setup form from
        - sample ini file
        - existing ini file
        - rc file?

- callbacks to generate form elements per key
    - fallback for unknown/new ini values

- check for writable folders

- db setup
    - access test
    - schema for diffrent db types
        - postgres
        - mysql
        - sqlite
        - others?

- write new config
    - write to ini file
    - compile rc file

- create required directories? output create commands to be run as root?
- symlink production site? output commands to be run as root?
*/

?>
