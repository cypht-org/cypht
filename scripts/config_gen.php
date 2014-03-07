<?php

$options = getopt("", array('ini_file::'));
$settings = '';

if (isset($options['ini_file'])) {
    $settings = serialize(parse_ini_file($options['ini_file']));
}
if (strlen($settings)) {
    file_put_contents('hm3.rc', $settings);
    printf("\nhm3.rc file written\n");
}
else {
    printf("\nno ini settings found\n");
}

?>
