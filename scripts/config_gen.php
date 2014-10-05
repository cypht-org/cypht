<?php

define("DEBUG_MODE", false);

require 'lib/framework.php';

$options = getopt('', array('ini_file::', 'debug'));
$settings = '';

if (isset($options['ini_file'])) {
    $settings = parse_ini_file($options['ini_file']);
}
if (!empty($settings)) {
    $js = file_get_contents("third_party/zepto.min.js");
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
        file_put_contents('site.js', compress($js, $js_compress));
        printf("site.js file created\n");
    }
    $settings['handler_modules'] = Hm_Handler_Modules::dump();
    $settings['output_modules'] = Hm_Output_Modules::dump();
    $settings['input_filters'] = $filters;

    file_put_contents('hm3.rc', serialize($settings));
    printf("hm3.rc file written\n");
    if (isset($options['debug'])) {
        printf("Debug output:\n");
        Hm_Debug::show();
    }
}
else {
    printf("\ncould not find hm3.ini file\n");
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
