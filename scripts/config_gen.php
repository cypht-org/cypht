<?php

/**
 * CLI script to build the site configuration
 */

if (strtolower(php_sapi_name()) !== 'cli') {
    die("Must be run from the command line\n");
}

/* determine current absolute path used for require statements */
define('APP_PATH', dirname(dirname(__FILE__)).'/');
define('VENDOR_PATH', APP_PATH.'vendor/');
define('WEB_ROOT', '');
chdir(APP_PATH);

/* get the framework */
require VENDOR_PATH.'autoload.php';
require APP_PATH.'lib/framework.php';

$environment = Hm_Environment::getInstance();
$environment->load();

/* Define DEBUG_MODE from environment variable */
define('DEBUG_MODE', filter_var(env('ENABLE_DEBUG', false), FILTER_VALIDATE_BOOLEAN));

/* create site */
build_config();


/**
 * build sub-resource integrity hash
 */
function build_integrity_hash($data) {
    return sprintf('sha512-%s', base64_encode(hash('sha512', $data, true)));
}

/**
 * Entry point into the configuration process
 *
 * @return void
 */
function build_config() {
    /* check PHP version before loading settings (mb_* functions used throughout) */
    $minVersion = 8.1;
    if ((float) substr(phpversion(), 0, 3) < $minVersion) {
        die("Cypht requires PHP version $minVersion or greater\n");
    }

    /* get the site settings */
    $settings = merge_config_files(APP_PATH.'config');

    if (is_array($settings) && !empty($settings)) {
        $settings['version'] = VERSION;

        /* check all PHP dependencies (fatal framework deps + module/settings-specific) */
        check_dependencies($settings);

        read_mstnef_viewer_config($settings);

        /* determine compression commands */
        list($js_compress, $css_compress) = compress_methods($settings);

        /* get module detail */
        list($js, $css, $filters, $assets) = get_module_assignments($settings);

        /* combine and compress page content */
        $hashes = combine_includes($js, $js_compress, $css, $css_compress, $settings);

        /* write out the dynamic.php file */
        write_config_file($settings, $filters);

        /* create the production version */
        create_production_site($assets, $settings, $hashes);

        process_bootswatch_files();
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
 * Check all PHP dependencies required to build and run the site.
 *
 * Covers three tiers in one pass:
 *   1. Core framework requirements (fatal — build aborts if missing)
 *   2. Module-specific requirements (warning — only checked for enabled modules)
 *   3. Settings-driven requirements (warning — only checked when relevant settings are active)
 *
 * Each dependency descriptor: ['type', 'name', 'label', 'fatal', 'required_by']
 *   type       — 'extension', 'function', or 'class'
 *   fatal      — true: die after reporting; false: warn and continue
 *   required_by — list of module names / setting descriptions for context
 *
 * @param array $settings merged site settings array
 * @return void
 */
function check_dependencies($settings) {
    // 1. Core framework deps — always required, fatal if missing
    $deps = [
        ['type' => 'extension', 'name' => 'mbstring',
         'label' => 'Multibyte String (mbstring) extension',
         'fatal' => true, 'required_by' => ['core']],
        // curl is ext-curl (wraps libcurl); every call site guards against
        // c_init() returning false, so absence degrades features rather than
        // crashing. OAuth2, API integrations, and contacts sync all go silent.
        ['type' => 'extension', 'name' => 'curl',
         'label' => 'cURL extension (OAuth2 and all external HTTP API calls will fail silently)',
         'fatal' => false, 'required_by' => ['core']],
        ['type' => 'function', 'name' => 'openssl_random_pseudo_bytes',
         'label' => 'OpenSSL extension',
         'fatal' => true, 'required_by' => ['core']],
        // DOM can be disabled at PHP compile time; used unconditionally by the
        // HTMLToText class in core/message_functions.php (HTML email rendering).
        ['type' => 'extension', 'name' => 'dom',
         'label' => 'DOM extension (required for HTML email rendering in the core module)',
         'fatal' => true, 'required_by' => ['core']],
    ];

    // 2. Module-specific deps — warnings, only for enabled modules
    $module_map = [
        'imap' => [
            // EWS (Exchange Web Services) support is bundled in the imap module
            // via hm-ews.php. The garethp/php-ews library uses SoapClient;
            // without the soap extension any Exchange server connection will fail.
            ['type' => 'extension', 'name' => 'soap',
             'label' => 'SOAP extension (required for Exchange Web Services / EWS connectivity in the imap module)'],
        ],
        'pgp' => [
            ['type' => 'class',    'name' => 'gnupg',
             'label' => 'GnuPG PECL extension (required for PGP encryption and signing)'],
        ],
        'ldap_contacts' => [
            ['type' => 'extension', 'name' => 'ldap',
             'label' => 'LDAP extension (required for LDAP contact lookups)'],
        ],
        'carddav_contacts' => [
            ['type' => 'extension', 'name' => 'simplexml',
             'label' => 'SimpleXML extension (required for CardDAV vCard parsing)'],
        ],
        'feeds' => [
            ['type' => 'function', 'name' => 'xml_parser_create',
             'label' => 'XML extension (required for parsing RSS/Atom feeds)'],
            ['type' => 'function', 'name' => 'simplexml_load_string',
             'label' => 'SimpleXML extension (required for OPML import in feeds)'],
        ],
        '2fa' => [
            // hash_hmac is part of the hash extension which cannot be disabled
            // in PHP 8.1+ (locked to core since PHP 7.4). No runtime check needed.
        ],
    ];

    foreach (get_modules($settings) as $mod) {
        $mod = trim($mod);
        if (!array_key_exists($mod, $module_map)) {
            continue;
        }
        foreach ($module_map[$mod] as $dep) {
            $deps[] = ['type' => $dep['type'], 'name' => $dep['name'],
                       'label' => $dep['label'], 'fatal' => false, 'required_by' => [$mod]];
        }
    }

    // 3. Settings-driven deps — warnings, only when relevant settings are active
    $session_type = strtoupper($settings['session_type'] ?? '');
    $auth_type    = strtoupper($settings['auth_type']    ?? '');

    if ($session_type === 'DB' || $auth_type === 'DB') {
        $deps[] = ['type' => 'class', 'name' => 'PDO',
                   'label' => 'PDO extension (required for database authentication/sessions)',
                   'fatal' => false, 'required_by' => ['setting: AUTH_TYPE/SESSION_TYPE=DB']];
    }
    if ($session_type === 'REDIS' || !empty($settings['enable_redis'])) {
        $deps[] = ['type' => 'class', 'name' => 'Redis',
                   'label' => 'Redis PHP extension (required for Redis caching/sessions)',
                   'fatal' => false, 'required_by' => ['setting: ENABLE_REDIS / SESSION_TYPE=REDIS']];
    }
    if ($session_type === 'MEM' || !empty($settings['enable_memcached'])) {
        $deps[] = ['type' => 'class', 'name' => 'Memcached',
                   'label' => 'Memcached PHP extension (required for Memcached caching/sessions)',
                   'fatal' => false, 'required_by' => ['setting: ENABLE_MEMCACHED / SESSION_TYPE=MEM']];
    }
    if ($auth_type === 'LDAP') {
        $deps[] = ['type' => 'extension', 'name' => 'ldap',
                   'label' => 'LDAP extension (required for LDAP authentication)',
                   'fatal' => false, 'required_by' => ['setting: AUTH_TYPE=LDAP']];
    }

    // Probe each dep; deduplicate by type:name, merging required_by lists
    $seen = [];
    foreach ($deps as $dep) {
        $absent = match($dep['type']) {
            'extension' => !extension_loaded($dep['name']),
            'function'  => !function_exists($dep['name']),
            'class'     => !class_exists($dep['name'], false),
            default     => false,
        };
        if (!$absent) {
            continue;
        }
        $key = $dep['type'] . ':' . $dep['name'];
        if (!isset($seen[$key])) {
            $seen[$key] = ['label' => $dep['label'], 'fatal' => $dep['fatal'],
                           'required_by' => $dep['required_by']];
        } else {
            if ($dep['fatal']) {
                $seen[$key]['fatal'] = true; // escalate severity if needed
            }
            foreach ($dep['required_by'] as $rb) {
                if (!in_array($rb, $seen[$key]['required_by'], true)) {
                    $seen[$key]['required_by'][] = $rb;
                }
            }
        }
    }

    $fatal_missing   = array_filter($seen, fn($d) => $d['fatal']);
    $warning_missing = array_filter($seen, fn($d) => !$d['fatal']);

    printf("\nchecking dependencies ...\n");

    if (empty($fatal_missing) && empty($warning_missing)) {
        printf("all dependency checks passed.\n\n");
        return;
    }

    printf("\n%s\n", str_repeat('-', 72));

    if (!empty($warning_missing)) {
        printf("WARNING: %d missing PHP dependency(s) — affected features will not work\n",
               count($warning_missing));
        printf("%s\n", str_repeat('-', 72));
        foreach ($warning_missing as $dep) {
            printf("  MISSING  : %s\n", $dep['label']);
            printf("  Needed by: %s\n\n", implode(', ', $dep['required_by']));
        }
    }

    if (!empty($fatal_missing)) {
        printf("FATAL: %d missing required PHP dependency(s) — build cannot continue\n",
               count($fatal_missing));
        printf("%s\n", str_repeat('-', 72));
        foreach ($fatal_missing as $dep) {
            printf("  MISSING  : %s\n", $dep['label']);
            printf("  Needed by: %s\n\n", implode(', ', $dep['required_by']));
        }
        printf("%s\n", str_repeat('-', 72));
        die("Aborting: install the missing required extension(s) and re-run.\n");
    }

    printf("%s\n\n", str_repeat('-', 72));
}

/**
 * Check if a required executable dependency is available for use in shell.
 * 
 * @param string $dependency Name of the executable to check
 * @return bool True if the executable is found, false otherwise
 */
function check_executable_dependency($dependency) {
    if (PHP_OS_FAMILY == 'Windows') {
        exec("where " . escapeshellarg($dependency) . " >null 2>&1", $output, $resultCode);
    } else {
        exec("which " . escapeshellarg($dependency) . " 2>/dev/null", $output, $resultCode);
    }

    return $resultCode === 0;
}

function read_mstnef_viewer_config($settings) {
    if ($settings['enable_mstnef_viewer']) {
        if (! check_executable_dependency('tnef')) {
            printf("\n%s\n", str_repeat('-', 72));
            printf("ERROR: 'tnef' executable not found. Please install `tnef` cli tool or disable 'enable_mstnef_viewer' to continue.\n");
            printf("\n%s\n", str_repeat('-', 72));
            exit(1);
        }
        if (! check_executable_dependency('unrtf')) {
            printf("\n%s\n", str_repeat('-', 72));
            printf("ERROR: 'unrtf' executable not found. Please install `unrtf` cli tool or disable 'enable_mstnef_viewer' to continue.\n");
            printf("\n%s\n", str_repeat('-', 72));
            exit(1);
        }
    }
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
    $core = false;
    $js_exclude_dependencies = explode(',', ($settings['js_exclude_deps'] ?? ''));
    $filters = array('allowed_output' => array(), 'allowed_get' => array(), 'allowed_cookie' => array(),
        'allowed_post' => array(), 'allowed_server' => array(), 'allowed_pages' => array());

    if (isset($settings['modules'])) {
        $mods = get_modules($settings);
        foreach ($mods as $mod) {
            printf("scanning module %s ...\n", $mod);
            if ($mod === 'core') {
                // We'll load the navigation modules last, after all other modules have been loaded, as they depend on the others.
                $core = true;
            }
            $directoriesPattern = str_replace('/', DIRECTORY_SEPARATOR, "{*,*/*}");
            foreach (glob('modules' . DIRECTORY_SEPARATOR . $mod . DIRECTORY_SEPARATOR . 'js_modules' . DIRECTORY_SEPARATOR . $directoriesPattern . '*.js', GLOB_BRACE) as $js_module) {
                if (preg_match('/\[(.+)\]/', $js_module, $matches)) {
                    $dep = $matches[1];
                    if (in_array($dep, $js_exclude_dependencies)) {
                        continue;
                    }
                }
                $js .= file_get_contents($js_module);
            }
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

        if ($core) {
            foreach (glob('modules/core/navigation/*.js') as $js_module) {
                $js .= file_get_contents($js_module);
            }
        }

        $css .= file_get_contents(sprintf("third_party/nprogress.css", 'third_party'));
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
        $css_out = file_get_contents(VENDOR_PATH . "twbs/bootstrap-icons/font/bootstrap-icons.css");
        $css_out .= compress($css, $css_compress);
        $css_hash = build_integrity_hash($css_out);
        file_put_contents('site.css', $css_out);
        printf("site.css file created\n");
    }
    if ($js) {
        $mods = get_modules($settings);
        $js_lib = get_js_libs_content(explode(',', $settings['js_exclude_deps']));
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
 * @param $settings array site settings list (unsued with .env support)
 * @param $filters array combined list of filters from all modules (unsued with .env support)
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

    $data = [
        'handler_modules' => Hm_Handler_Modules::dump(),
        'output_modules' => Hm_Output_Modules::dump(),
        'input_filters' => $filters,
    ];
    $dynamicConfigPath = APP_PATH.'config/dynamic.php';
    // Create or overwrite the PHP file
    file_put_contents($dynamicConfigPath, '<?php return ' . var_export($data, true) . ';');
    printf("dynamic.php file written\n");
}

/**
 * Copies bootstrap icons fonts folder as it is
 * referenced and needed by bootstrap icons css file
 *
 * @return void
 */
function append_bootstrap_icons_files() {
    // Ensure target font directories exist for both deployment modes:
    // - Running from site/: fonts are expected under site/fonts relative to site/site.css
    // - Running from the main directory: fonts are expected under fonts relative to site.css
    if (!is_dir("site/fonts")) {
        mkdir('site/fonts', 0755);
    }
    if (!is_dir("fonts")) {
        mkdir('fonts', 0755);
    }
    $source_folder = VENDOR_PATH.'twbs/bootstrap-icons/font/fonts/';
    $files = glob("$source_folder*.*");
    foreach($files as $file){
        // Copy for site/ deployments (CSS loaded from site/site.css → ./fonts resolves to site/fonts)
        $dest_folder_site = str_replace($source_folder, "site/fonts/", $file);
        copy($file, $dest_folder_site);
        // Copy for main-directory deployments (CSS loaded from site.css → ./fonts resolves to fonts)
        $dest_folder_root = str_replace($source_folder, "fonts/", $file);
        copy($file, $dest_folder_root);
    }
}

/**
 * Copies KindEditor runtime assets (themes, plugins, lang) to site/ directory.
 * KindEditor needs these resources at runtime for the HTML compose functionality.
 *
 * @return void
 */
function copy_kindeditor_assets() {
    $source = 'third_party/kindeditor';
    $dest = 'site/third_party/kindeditor';

    if (!is_readable($source)) {
        printf("WARNING: KindEditor source directory not found at %s\n", $source);
        return;
    }

    // Create the destination directory structure
    if (!is_dir('site/third_party')) {
        mkdir('site/third_party', 0755, true);
    }

    if (!is_dir($dest)) {
        mkdir($dest, 0755, true);
    }

    // Copy necessary directories and files (excluding the main JS file which is already bundled)
    $items_to_copy = array('themes', 'plugins', 'lang');

    foreach ($items_to_copy as $item) {
        $source_path = $source . '/' . $item;
        if (is_dir($source_path)) {
            copy_recursive($source_path);
        }
    }

    printf("KindEditor assets copied successfully\n");
}

function process_bootswatch_files() {
    $src = 'site/modules/themes/assets';
    if (! is_dir($src)) {
        return;
    }
    $dir = opendir($src);
    while(false !== ($folder = readdir($dir))) {
        if (($folder != '.' ) && ($folder != '..' )) {
            if (is_dir($src . '/' . $folder) && $folder != 'fonts') {
                $target = $src . '/' . $folder . '/css/' . $folder . '.css';
                if ($folder == 'default') {
                    $content = file_get_contents(VENDOR_PATH . 'twbs/bootstrap/dist/css/bootstrap.min.css');
                } else {
                    $content = file_get_contents(VENDOR_PATH . 'thomaspark/bootswatch/dist/' . $folder . '/bootstrap.min.css');
                }
                // Append customization done to the default theme
                $custom = file_get_contents($target);
                $custom = preg_replace('/^@import.+/m', '', $custom);
                $custom = preg_replace('/^@charset.+/m', '', $custom);
                $content .= "\n" . $custom;

                file_put_contents($target, $content);
            }
        }
    }
    closedir($dir);
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
    append_bootstrap_icons_files();

    // Copy KindEditor resources (themes, plugins, lang)
    copy_kindeditor_assets();

    // Copy main assets directory
    if (is_readable('assets/')) {
        printf("copying assets directory...\n");
        copy_recursive('assets');
    }

    $index_file = file_get_contents('index.php');
    $index_file = preg_replace("/APP_PATH', ''/", "APP_PATH', '".APP_PATH."'", $index_file);
    $index_file = preg_replace("/ASSETS_PATH', APP_PATH\.'assets\/'/", "ASSETS_PATH', WEB_ROOT.'assets/'", $index_file);
    $index_file = preg_replace("/CACHE_ID', ''/", "CACHE_ID', '".urlencode(Hm_Crypt::unique_id(32))."'", $index_file);
    $index_file = preg_replace("/SITE_ID', ''/", "SITE_ID', '".urlencode(Hm_Crypt::unique_id(64))."'", $index_file);
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
