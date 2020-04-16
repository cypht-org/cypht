<?php

/**
 * CLI script to build the site configuration
 */

if (strtolower(php_sapi_name()) !== 'cli') {
    die("Must be run from the command line\n");
}

/* debug mode has to be set to something or include files will die() */
define('DEBUG_MODE', false);

/* determine current absolute path used for require statements */
define('APP_PATH', dirname(dirname(__FILE__)).'/');
define('VENDOR_PATH', APP_PATH.'vendor/');
define('WEB_ROOT', '');
chdir(APP_PATH);

/* get the framework */
require APP_PATH.'lib/framework.php';

/* check for proper php support */
check_php();

/* create site */
build_config();


/**
 * Check PHP for correct support
 *
 * @return void
 * */
function check_php() {
    $version = phpversion();
    if (substr($version, 0, 3) < 5.4) {
        die('Cypht requires PHP version 5.4 or greater');
    }
    if (!function_exists('mb_strpos')) {
        die('Cypht requires PHP MB support');
    }
    if (!function_exists('curl_exec')) {
        die('Cypht requires PHP cURL support');
    }
    if (!function_exists('openssl_random_pseudo_bytes')) {
        die('Cypht requires PHP OpenSSL support');
    }
    if (!class_exists('PDO')) {
        echo "\nWARNING: No PHP PDO support found, database featueres will not work\n\n";
    }
    if (!class_exists('Redis')) {
        echo "\nWARNING: No PHP Redis support found, Redis caching or sessions will not work\n\n";
    }
    if (!class_exists('Memcached')) {
        echo "\nWARNING: No PHP Memcached support found, Memcached caching or sessions will not work\n\n";
    }
    if (!class_exists('gnupg')) {
        echo "\nWARNING: No PHP gnupg support found, The PGP module set will not work if enabled\n\n";
    }
}

/**
 * build sub-resource integrity hash
 */
function build_integrity_hash($data) {
    return sprintf('sha512-%s', base64_encode(hash('sha512', $data, true)));
}

/**
 * include module ini files in the main config
 */
function parse_module_ini_files($settings) {
    $files = array(
        array('2fa.ini', false),
        array('github.ini', false),
        array('ldap.ini', true),
        array('oauth2.ini', true),
        array('carddav.ini', true),
        array('wordpress.ini', false),
        array('recaptcha.ini', false),
        array('dynamic_login.ini', false)
    );
    if (!array_key_exists('app_data_dir', $settings)) {
        return $settings;
    }
    foreach ($files as $vals) {
        $file = $vals[0];
        $sections = $vals[1];
        $ini_file = rtrim($settings['app_data_dir'], '/').'/'.$file;
        if (is_readable($ini_file)) {
            $data = parse_ini_file($ini_file, $sections);
            if (is_array($data) && count($data) > 0) {
                echo 'Found module set ini file: '.$ini_file."\n";
                $settings[$file] = $data;
            }
        }
    }
    return $settings;
}

/**
 * Entry point into the configuration process
 *
 * @return void
 */
function build_config() {
    if (!Hm_Dispatch::is_php_setup()) {
        printf("\nPHP is not correctly configured\n");
        printf("\nMbstring:   %s\n", function_exists('mb_strpos') ? 'yes' : 'no');
        printf("Curl:       %s\n", function_exists('curl_exec') ? 'yes' : 'no');
        printf("Openssl:    %s\n", function_exists('openssl_random_pseudo_bytes') ? 'yes' : 'no');
        printf("PDO:        %s\n\n", class_exists('PDO') ? 'yes' : 'no');
        exit;
    }

    /* get the site settings */
    $settings = parse_ini_file(APP_PATH.'hm3.ini');

    if (is_array($settings) && !empty($settings)) {
        $settings['version'] = VERSION;
        $settings = parse_module_ini_files($settings);

        /* determine compression commands */
        list($js_compress, $css_compress) = compress_methods($settings);

        /* get module detail */
        list($js, $css, $filters, $assets) = get_module_assignments($settings);

        /* combine and compress page content */
        $hashes = combine_includes($js, $js_compress, $css, $css_compress, $settings);

        /* write out the hm3.rc file */
        write_config_file($settings, $filters);

        /* create the production version */
        create_production_site($assets, $settings, $hashes);
    }
    else {
        printf("\nNo settings found in ini file\n");
    }
}

/**
 * Compress a string
 *
 * @param $string string content to compress
 * @param $command string command to do the compression
 *
 * @return string compressed string
 */
function compress($string, $command, $file=false) {
    if ($command) {
        if ($file) {
            exec("cat ./".$file." | $command", $output);
            $result = join('', $output);
        }
        else {
            exec("echo ".escapeshellarg($string)." | $command", $output);
            $result = join('', $output);
        }
    }
    else {
        $result = $string;
    }
    if (!trim($result)) {
        printf("WARNING: Compression command failed: %s\n", $command);
        return $string;
    }
    return $result;
}

/**
 * Check for site specific compression commands
 *
 * @param $settings array site settings list
 *
 * @return array compression methods or false for none
 */
function compress_methods($settings) {
    $js_compress = false;
    $css_compress = false;
    if (isset($settings['js_compress']) && $settings['js_compress']) {
        $js_compress = $settings['js_compress'];
    }
    if (isset($settings['css_compress']) && $settings['css_compress']) {
        $css_compress = $settings['css_compress'];
    }
    return array($js_compress, $css_compress);
}

/**
 * Get module content and filters. This function has a side effect of setting
 * up all the module assignments in Hm_Output_Modules and Hm_Handler_Modules.
 * (this happens when the module set's setup.php file is included).
 * These will be recorded later in the write_config_file function
 *
 * @param $settings array site settings list
 *
 * @return array js and css blobs, combined filers array, and module assets
 */
function get_module_assignments($settings) {
    $js = '';
    $css = '';
    $assets = array();
    $filters = array('allowed_output' => array(), 'allowed_get' => array(), 'allowed_cookie' => array(),
        'allowed_post' => array(), 'allowed_server' => array(), 'allowed_pages' => array());

    if (isset($settings['modules'])) {
        $mods = get_modules($settings);
        foreach ($mods as $mod) {
            printf("scanning module %s ...\n", $mod);
            if (is_readable(sprintf("modules/%s/site.js", $mod))) {
               $js .= str_replace("'use strict';", '', file_get_contents(sprintf("modules/%s/site.js", $mod)));
            }
            if (is_readable(sprintf("modules/%s/site.css", $mod))) {
               $css .= file_get_contents(sprintf("modules/%s/site.css", $mod));
            }
            if (is_readable(sprintf("modules/%s/setup.php", $mod))) {
                $filters = Hm_Module_Exec::merge_filters($filters, require sprintf("modules/%s/setup.php", $mod));
            }
            if (is_readable(sprintf("modules/%s/assets/", $mod))) {
                $assets[] = sprintf("modules/%s/assets", $mod);
            }
        }
    }
    return array($js, $css, $filters, $assets);
}

/**
 * get module list from settings
 * @param array $settings site settings list
 * @return array
 */
function get_modules($settings) {
    $mods = array();
    if (isset($settings['modules'])) {
        $mods = $settings['modules'];
        if (is_string($mods)) {
            $mods = explode(',', $mods);
        }
    }
    return $mods;
}

/**
 * Write out combined javascript and css files
 *
 * @param $js string combined javascript from all modules
 * @param $js_compress string command to compress the js
 * @param $css string combined css from all modules
 * @param $css_compress string command to compress the css
 * @param $settings array site settings list
 *
 * @return void
 */
function combine_includes($js, $js_compress, $css, $css_compress, $settings) {
    $js_hash = '';
    $css_hash = '';
    if ($css) {
        $css_out = compress($css, $css_compress);
        $css_hash = build_integrity_hash($css_out);
        file_put_contents('site.css', $css_out);
        printf("site.css file created\n");
    }
    if ($js) {
        $mods = get_modules($settings);
        $js_lib = file_get_contents("third_party/cash.min.js");
        if (in_array('desktop_notifications', $mods, true)) {
            $js_lib .= file_get_contents("third_party/push.min.js");
        }
        if ((array_key_exists('encrypt_ajax_requests', $settings) &&
            $settings['encrypt_ajax_requests']) ||
            (array_key_exists('encrypt_local_storage', $settings) &&
            $settings['encrypt_local_storage'])) {
            $js_lib .= file_get_contents("third_party/forge.min.js");
        }
        file_put_contents('tmp.js', $js);
        $js_out = $js_lib.compress($js, $js_compress, 'tmp.js');
        $js_hash = build_integrity_hash($js_out);
        file_put_contents('site.js', $js_out);
        unlink('./tmp.js');
        printf("site.js file created\n");
    }
    return array('js' => $js_hash, 'css' => $css_hash);
}

/**
 * Write the hm3.rc file to disk
 *
 * @param $settings array site settings list
 * @param $filters array combined list of filters from all modules
 * 
 * @return void
 */
function write_config_file($settings, $filters) {
    Hm_Handler_Modules::try_queued_modules();
    Hm_Handler_Modules::process_all_page_queue();
    Hm_Handler_Modules::try_queued_modules();
    Hm_Output_Modules::try_queued_modules();
    Hm_Output_Modules::process_all_page_queue();
    Hm_Output_Modules::try_queued_modules();
    $settings['handler_modules'] = Hm_Handler_Modules::dump();
    $settings['output_modules'] = Hm_Output_Modules::dump();
    $settings['input_filters'] = $filters;
    file_put_contents('hm3.rc', json_encode($settings));
    printf("hm3.rc file written\n");
}

/**
 * Copies the site.js and site.css files to the site/ directory, and creates
 * a production version of the index.php file.
 *
 * @return void
 */
function create_production_site($assets, $settings, $hashes) {
    if (!is_readable('site/')) {
        mkdir('site', 0755);
    }
    printf("creating production site\n");
    copy('site.css', 'site/site.css');
    copy('site.js', 'site/site.js');
    $index_file = file_get_contents('index.php');
    $index_file = preg_replace("/APP_PATH', ''/", "APP_PATH', '".APP_PATH."'", $index_file);
    $index_file = preg_replace("/CACHE_ID', ''/", "CACHE_ID', '".urlencode(Hm_Crypt::unique_id(32))."'", $index_file);
    $index_file = preg_replace("/SITE_ID', ''/", "SITE_ID', '".urlencode(Hm_Crypt::unique_id(64))."'", $index_file);
    $index_file = preg_replace("/DEBUG_MODE', true/", "DEBUG_MODE', false", $index_file);
    $index_file = preg_replace("/JS_HASH', ''/", "JS_HASH', '".$hashes['js']."'", $index_file);
    $index_file = preg_replace("/CSS_HASH', ''/", "CSS_HASH', '".$hashes['css']."'", $index_file);
    file_put_contents('site/index.php', $index_file);
    foreach ($assets as $path) {
        copy_recursive($path);
    }
}

/**
 * Recursively copy files
 * @param string $path file path with no trailing slash
 * @return void
 */
function copy_recursive($path) {
    $path .= '/';
    if (!is_readable('site/'.$path)) {
        mkdir('site/'.$path, 0755, true);
    }
    foreach (scandir($path) as $file) {
        if (in_array($file, array('.', '..'), true)) {
            continue;
        }
        elseif (is_dir($path.$file)) {
            copy_recursive($path.$file);
        }
        else {
            copy($path.$file, 'site/'.$path.$file);
        }
    }
}

?>
