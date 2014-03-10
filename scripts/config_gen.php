<?php

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
                $filters = merge_filters($filters, require sprintf("modules/%s/setup.php", $mod));
            }
        }
    }
    if ($css) {
        file_put_contents('site.css', compress($css));
        printf("site.css file created\n");
    }
    if ($js) {
        file_put_contents('site.js', compress($js));
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

function compress($string) {
    return preg_replace("/(\r\n|\n|\s{2,})/", ' ', $string);
}
function merge_filters($existing, $new) {
    foreach (array('allowed_get', 'allowed_cookie', 'allowed_post', 'allowed_server', 'allowed_pages') as $v) {
        if (isset($new[$v])) {
            if ($v == 'allowed_pages') {
                $existing[$v] = array_merge($existing[$v], $new[$v]);
            }
            else {
                $existing[$v] += $new[$v];
            }
        }
    }
    return $existing;
}
?>
