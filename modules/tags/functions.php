<?php

if (!defined('DEBUG_MODE')) { die(); }

if (!hm_exists('add_label')) {
    function add_profile($name, $parent = null) {
        $tag = array(
            'name' => $name,
            'parent' => $parent,
        );

        Hm_Tags::add($tag);
    }
}
