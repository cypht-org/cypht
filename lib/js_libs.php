<?php

define('JS_LIBS', [
    'bootstrap' => VENDOR_PATH.'twbs/bootstrap/dist/js/bootstrap.bundle.min.js',
    'cash' => 'third_party/cash.min.js',
    'resumable' => 'third_party/resumable.min.js',
    'ays-beforeunload-shim' =>  'third_party/ays-beforeunload-shim.js',
    'jquery-are-you-sure' => 'third_party/jquery.are-you-sure.js',
    'sortable' => 'third_party/sortable.min.js',
    'kindeditor' => 'third_party/kindeditor/kindeditor-all-min.js',
    'nprogress' => 'third_party/nprogress.js',
]);

define('JS_LIBS_CSS', [
    'bootstrap' => [VENDOR_PATH.'twbs/bootstrap-icons/font/bootstrap-icons.css'],
    'nprogress' => ['third_party/nprogress.css'],
]);

function get_js_libs($exclude_deps = []) {
    $js_lib = '';

    foreach (JS_LIBS as $key => $dep) {
        if (!in_array($key, $exclude_deps)) {
            $js_lib .= '<script type="text/javascript" src="'.WEB_ROOT.$dep.'"></script>';
        }
    }
    return $js_lib;
}

function get_js_libs_content($exclude_deps = []) {
    $js_lib = '';

    foreach (JS_LIBS as $key => $dep) {
        if (!in_array($key, $exclude_deps)) {
            $js_lib .= file_get_contents($dep) . "\n";
        }
    }
    return $js_lib;
}

function get_js_libs_css($exclude_deps = []) {
    $css_lib = '';

    foreach (JS_LIBS_CSS as $key => $css_files) {
        if (!in_array($key, $exclude_deps)) {
            foreach ($css_files as $css_file) {
                $css_lib .= '<link href="'.WEB_ROOT.$css_file.'" media="all" rel="stylesheet" type="text/css" />';
            }
        }
    }
    return $css_lib;
}

function get_js_libs_css_content($exclude_deps = []) {
    $css_lib = '';

    foreach (JS_LIBS_CSS as $key => $css_files) {
        if (!in_array($key, $exclude_deps)) {
            foreach ($css_files as $css_file) {
                $css_lib .= file_get_contents($css_file) . "\n";
            }
        }
    }
    return $css_lib;
}
