<?php

define('DEBUG_MODE', false);
define('APP_PATH', dirname(dirname(__FILE__)).'/');

require APP_PATH.'lib/framework.php';
$settings = '';
$error = '';

$settings = parse_ini_file(APP_PATH.'hm3.ini');

if (!empty($settings)) {
    $js = '';
    $css = '';
    $mod_map = array();
    $js_compress = false;
    $css_compress = false;
    if (isset($settings['js_compress']) && $settings['js_compress']) {
        $js_compress = $settings['js_compress'];
    }
    if (isset($settings['css_compress']) && $settings['css_compress']) {
        $css_compress = $settings['css_compress'];
    }
    $filters = array('allowed_output' => array(), 'allowed_get' => array(), 'allowed_cookie' => array(), 'allowed_post' => array(), 'allowed_server' => array(), 'allowed_pages' => array());
    if (isset($settings['modules'])) {
        foreach (explode(',', $settings['modules']) as $mod) {
            printf("scanning module %s ...\n", $mod);
            if (is_readable(sprintf("modules/%s/site.js", $mod))) {
               $js .= file_get_contents(sprintf("modules/%s/site.js", $mod));
            }
            if (is_readable(sprintf("modules/%s/site.css", $mod))) {
               $css .= file_get_contents(sprintf("modules/%s/site.css", $mod));
            }
            if (is_readable(sprintf("modules/%s/setup.php", $mod))) {
                $filters = Hm_Router::merge_filters($filters, require sprintf("modules/%s/setup.php", $mod));
            }
        }
    }
    if ($css) {
        file_put_contents('site.css', compress($css, $css_compress));
        printf("site.css file created\n");
    }
    if ($js) {
        $js_lib = file_get_contents("third_party/zepto.min.js");
        file_put_contents('site.js', $js_lib.compress($js, $js_compress));
        printf("site.js file created\n");
    }

    Hm_Handler_Modules::process_all_page_queue();
    Hm_Output_Modules::process_all_page_queue();
    $settings['handler_modules'] = Hm_Handler_Modules::dump();
    $settings['output_modules'] = Hm_Output_Modules::dump();
    $settings['input_filters'] = $filters;
    file_put_contents('hm3.rc', serialize($settings));
    printf("hm3.rc file written\n");

    if (!is_readable('site/')) {
        mkdir('site');
    }
    printf("creating production site\n");
    copy('site.css', 'site/site.css');
    copy('site.js', 'site/site.js');
    $index_file = file_get_contents('index.php');
    $index_file = preg_replace("/APP_PATH', ''/", "APP_PATH', '".APP_PATH."'", $index_file);
    $index_file = preg_replace("/CACHE_ID', ''/", "CACHE_ID', '".urlencode(Hm_Crypt::unique_id(32))."'", $index_file);
    $index_file = preg_replace("/DEBUG_MODE', true/", "DEBUG_MODE', false", $index_file);
    file_put_contents('site/index.php', $index_file);
}
else {
    printf("\nNo settings found in ini file\n");
}

function compress($string, $command) {

    if ($command) {
        exec("echo ".escapeshellarg($string)." | $command", $output);
        return join('', $output);
    }
    else {
        return $string;
    }
}

?>
