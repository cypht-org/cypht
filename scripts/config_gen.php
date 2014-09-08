<?php

define("DEBUG_MODE", false);

/* command that takes js from stdin and outputs compressed results
 * example: 'java -jar /usr/local/lib/yuicompressor-2.4.8.jar --type js';
 */
$js_compress = false;

/* command that takes css from stdin and outputs compressed results
 * example: 'java -jar /home/jason/Downloads/yuicompressor-2.4.8.jar --type css';
 */
$css_compress = false;

require 'lib/framework.php';

$options = getopt('', array('ini_file::', 'debug'));
$settings = '';

if (isset($options['ini_file'])) {
    $settings = parse_ini_file($options['ini_file']);
}
if (!empty($settings)) {
    $js = '';
    $css = '';
    $mod_map = array();
    $filters = array('allowed_get' => array(), 'allowed_cookie' => array(), 'allowed_post' => array(), 'allowed_server' => array(), 'allowed_pages' => array());
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
        file_put_contents('site.css', compress($css, 'css'));
        printf("site.css file created\n");
    }
    if ($js) {
        file_put_contents('site.js', compress($js, 'js'));
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

function compress($string, $type) {

    global $js_compress;
    global $css_compress;

    if ($type == 'js' && $js_compress) {
        exec("echo ".escapeshellarg($string)." | $js_compress", $output);
        return join('', $output);
    }
    elseif ($type == 'css' && $css_compress) {
        exec("echo ".escapeshellarg($string)." | $css_compress", $output);
        return join('', $output);
    }
    else {
        return preg_replace("/(\r\n|\n|\s{2,})/", '', $string);
    }
}

?>
