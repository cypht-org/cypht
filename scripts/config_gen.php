<?php

$options = getopt("", array('ini_file::'));
$settings = '';

if (isset($options['ini_file'])) {
    $settings = parse_ini_file($options['ini_file']);
}
if (!empty($settings)) {
    $js = '';
    $css = '';
    if (isset($settings['modules'])) {
        foreach (explode(',', $settings['modules']) as $mod) {
            if (is_readable(sprintf("modules/%s/site.js", $mod))) {
               $js .= file_get_contents(sprintf("modules/%s/site.js", $mod));
            }
            if (is_readable(sprintf("modules/%s/site.css", $mod))) {
               $css .= file_get_contents(sprintf("modules/%s/site.css", $mod));
            }
        }
    }
    if ($css) {
        file_put_contents('site.css', compress($css));
        printf("site.css file created, move to css/site.css\n");
    }
    if ($js) {
        file_put_contents('site.js', compress($js));
        printf("site.js file created, move to js/site.js\n");
    }
    file_put_contents('hm3.rc', serialize($settings));
    printf("hm3.rc file written\n");
}
else {
    printf("\ncould not find hm3.ini file\n");
}

function compress($string) {
    return preg_replace("/(\r\n|\n|\s{2,})/", ' ', $string);
}
?>
