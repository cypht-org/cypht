<?php

define('JS_LIBS', [
    'bootstrap' => 'vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js',
    'cash' => 'third_party/cash.min.js',
    'resumable' => 'third_party/resumable.min.js',
    'ays-beforeunload-shim' =>  'third_party/ays-beforeunload-shim.js',
    'jquery-are-you-sure' => 'third_party/jquery.are-you-sure.js',
    'sortable' => 'third_party/sortable.min.js',
    'kindeditor' => 'third_party/kindeditor/kindeditor-all-min.js',
    'nprogress' => 'third_party/nprogress.js',
]);

function get_js_libs($exclude_deps = []) {
    $js_lib = '';

    foreach (JS_LIBS as $dep) {
        if (!in_array($dep, $exclude_deps)) {
            $js_lib .= '<script type="text/javascript" src="'.WEB_ROOT.$dep.'"></script>';
        }
    }
    return $js_lib;
}

function get_js_libs_content($exclude_deps = []) {
    $js_lib = '';

    foreach (JS_LIBS as $key => $dep) {
        if (!in_array($key, $exclude_deps)) {
            $js_lib .= file_get_contents(APP_PATH.$dep) . "\n";
        }
    }
    return $js_lib;
}
