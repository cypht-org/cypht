<?php
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

function read_config($source) {
    return parse_ini_file('../'.$source);
}

function check_php() {
    $version = phpversion();
    $mb = function_exists('mb_strpos');
    $curl = function_exists('curl_exec');
    $mcrypt = function_exists('mcrypt_decrypt');
    $ssl = function_exists('openssl_random_pseudo_bytes');
    return '<tr><td>PHP version</td><td>'.$version.'</td></tr>'.
        '<tr><td>Multibyte string support</td><td>'.$mb.'</td></tr>'.
        '<tr><td>Curl support</td><td>'.$curl.'</td></tr>'.
        '<tr><td>MCRYPT support</td><td>'.$mcrypt.'</td></tr>'.
        '<tr><td>OpenSSL support</td><td>'.$ssl.'</td></tr>';

}

function config_source($selected='hm3.sample.ini') {
    $opts = array('hm3.sample.ini', 'hm3.ini', 'hm3.rc');
    return '<tr><td>Config Source</td><td>'.select_box('source', $selected, $opts).'</td></tr>';
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
    return '<tr><td>POP3 authentication server TLS</td><td><input type="checkbox" value="1" name="pop3_auth_tls" /></td></tr>';
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
    return '<tr><td>IMAP authentication server TLS</td><td><input type="checkbox" value="1" name="imap_auth_tls" /></td></tr>';
}

function auth_type_setting($selected=false) {
    return '<tr><td>Authentication Type</td><td>'.select_box('auth_type', $selected, array('DB', 'IMAP', 'POP3')).'</td></tr>';
}

function default_smtp_server_setting($current) {
    return '<tr><td>Default SMTP server</td><td><input type="text" value="'.$current.'" name="default_smtp_server" /></td></tr>';
}

function default_smtp_port_setting($current) {
    return '<tr><td>Default SMTP server port</td><td><input type="number" value="'.$current.'" name="default_smtp_port" /></td></tr>';
}

function default_smtp_tls_setting($current) {
    return '<tr><td>Default SMTP server TLS</td><td><input type="checkbox" value="1" name="default_smtp_tls" /></td></tr>';
}

function default_language_setting($current) {
    return '<tr><td>Default language</td><td><input type="text" value="'.$current.'" name="default_language" /></td></tr>';
}

function user_config_type_setting($selected=false) {
    return '<tr><td>User Configuration type</td><td>'.select_box('user_config_type', $selected, array('DB', 'file')).'</td></tr>';
}

function app_data_dir_setting($current) {
    return '<tr><td>Application storage directory</td><td><input type="text" value="'.$current.'" name="app_data_dir" /></td></tr>';
}

function user_settings_dir_setting($current) {
    return '<tr><td>User settings storage directory</td><td><input type="text" value="'.$current.'" name="user_settings_dir" /></td></tr>';
}

function disable_tls_setting($current) {
    return '<tr><td>Allow non-TLS browser connections</td><td><input type="checkbox" value="1" name="disable_tls" /></td></tr>';
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
function modules_setting($current) {
    return '<tr><td>Enabled Modules</td><td><input style="width: 100%;" type="text" value="'.$current.'" name="modules" /></td></tr>';
}

function output_settings($settings) {
    foreach ($settings as $setting => $value) {
        if (function_exists($setting.'_setting')) {
            $func = $setting.'_setting';
            echo $func($value);
        }
        else {
            echo '<tr><td>'.$setting.'</td><td>Not supported yet</td></tr>';
        }
    }
}

function page_style() { ?>
    <style type="text/css">
        .config_settings { padding: 20px; width: 800px; }
        .config_settings td { line-height: 110%; padding: 10px; padding-bottom: 12px; border-bottom: solid 1px #ddd; }
    </style>

<?php }

function output_page($source) { ?>

    <!DOCTYPE html><html dir="ltr" class="ltr_page" lang=en>
        <head>
            <meta charset="utf-8" />
            <link href="../modules/core/site.css" media="all" rel="stylesheet" type="text/css" />
            <title>Cypht Setup</title>
            <?php page_style() ?>
        </head>
        <body>
            <form method="post">
                <table class="settings_table config_settings">
                    <?php echo check_php() ?>
                    <?php echo config_source($source) ?>
                    <?php output_settings(read_config($source)) ?>
                </table>
            </form>
        </body>
    </html>

<?php }

output_page('hm3.sample.ini');

?>
