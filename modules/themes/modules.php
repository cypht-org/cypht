<?php

/**
 * Theme modules
 * @package modules
 * @subpackage themes
 */

if (!defined('DEBUG_MODE')) { die(); }


/**
 * Setup currently selected theme
 * @subpackage themes/handler
 */
class Hm_Handler_load_theme  extends Hm_Handler_Module {
    public function process() {
        $theme = $this->user_config->get('theme_setting', 'default');
        $themes = custom_themes($this->config, hm_themes());
        if ($theme == 'hn') {
            $this->user_config->set('list_style', 'news_style');
        }
        if ($theme == 'tdark' || $theme == 'dark') {
            hm_theme_icons();
        }
        if ($theme == 'terminal') {
            hm_theme_icons('green');
        }
        $this->out('themes', $themes);
        $this->out('theme', $theme);
    }
}

/**
 * Process theme setting from the general section of the settings page
 * @subpackage themes/handler
 */
class Hm_Handler_process_theme_setting extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('save_settings', 'theme_setting'));
        $new_settings = $this->get('new_user_settings', array());
        $settings = $this->get('user_settings', array());

        if ($success) {
            $new_settings['theme_setting'] = $form['theme_setting'];
        }
        else {
            $settings['theme'] = $this->user_config->get('theme_setting', 'default');
        }
        $this->out('new_user_settings', $new_settings, false);
        $this->out('user_settings', $settings, false);
    }
}

/**
 * Include theme css
 * @subpackage themes/output
 */
class Hm_Output_theme_css extends Hm_Output_Module {
    /**
     * Add HTML head tag for theme css
     */
    protected function output() {
        if ($this->get('theme') && in_array($this->get('theme'), array_keys($this->get('themes', array())), true) && $this->get('theme') != 'default') {
            return '<link href="'.WEB_ROOT.'modules/themes/assets/'.$this->html_safe($this->get('theme')).'.css?v='.CACHE_ID.'" media="all" rel="stylesheet" type="text/css" />';
        }
    }
}

/**
 * Theme setting
 * @subpackage themes/output
 */
class Hm_Output_theme_setting extends Hm_Output_Module {
    /**
     * Theme setting
     */
    protected function output() {

        $current = $this->get('theme', '');
        $res = '<tr class="general_setting"><td><label for="theme_setting">'.
            $this->trans('Theme').'</label></td>'.
            '<td><select id="theme_setting" name="theme_setting">';
        foreach ($this->get('themes', array()) as $name => $label) {
            $res .= '<option ';
            if ($name == $current) {
                $res .= 'selected="selected" ';
            }
            $res .= 'value="'.$this->html_safe($name).'">'.$this->trans($label).'</option>';
        }
        $res .= '</select>';
        return $res;
    }
}

/**
 * icon colors for theme selection
 */
if (!hm_exists('icon_color')) {
function icon_color($theme) {
}}

/**
 * Define available themes
 * @subpackage themes/functions
 */
if (!hm_exists('hm_themes')) {
function hm_themes() {
    return array(
        'default' => 'White Bread (Default)',
        'blue' => 'Boring Blues',
        'dark' => 'Dark But Not Too Dark',
        'tdark' => 'Too Dark',
        'gray' => 'More Gray Than White Bread',
        'green' => 'Poison Mist',
        'tan' => 'A Bunch Of Browns',
        'terminal' => 'VT100',
        'lightblue' => 'Light Blue',
        'hn' => 'Hacker News',
        'so_alone' => 'So Alone',
    );
}}

/**
 * White UI icons
 * @subpackage themes/functions
 */
if (!hm_exists('hm_theme_icons')) {
function hm_theme_icons($color='white') {
    $icons = array(
        'power' => false,
        'home' => false,
        'box' => false,
        'env_closed' => false,
        'env_open' => false,
        'star' => false,
        'globe' => false,
        'doc' => false,
        'monitor' => false,
        'cog' => false,
        'people' => false,
        'caret' => false,
        'folder' => false,
        'chevron' => false,
        'check' => false,
        'refresh' => false,
        'big_caret_left' => false,
        'search' => false,
        'spreadsheet' => false,
        'info' => false,
        'bug' => false,
        'code' => false,
        'person' => false,
        'rss' => false,
        'rss_alt' => false,
        'caret_left' => false,
        'caret_right' => false,
        'calendar' => false,
        'circle_check' => false,
        'circle_x' => false,
        'key' => false,
        'save' => false,
        'plus' => false,
        'minus' => false,
        'book' => false,
        'paperclip' => false,
        'tags' => false,
        'tag' => false,
        'history' => false,
        'sent' => false,
        'unlocked' => false,
        'lock' => false,
        'audio' => false,
        'camera' => false,
        'menu' => false,

        'w' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH4AgKAxYt3lxNfAAAAB1pVFh0Q29tbWVudAAAAAAAQ3JlYXRlZCB3aXRoIEdJTVBkLmUHAAAAzElEQVQ4y+3TMSvFURjH8b9Qt5h0bV6CwaqYlLp1V6lbdsnsLXgBhmsz8AIUd7izlNVgt0kRRikfy6N+3f68AHm28z3ffufpPOc0zX812mup9jZq/YpOsZUUG8ziOdhahJ8E3wo+wCi7OA5xWKyDt+Dn4V9ikAHrIT5VV9u4C/6OBXTxgrkMmMJ9yH1cYAc3wXexh7O2yzwMcVynzGM/+BWu0WsLWJ6YxGnxRXwU+8QjZn4a6W0EbAYfBT/67U0clPSA6YmxfdfqH/sKX5nYdtZS9A38AAAAAElFTkSuQmCC',

    );
    foreach ($icons as $name => $value) {
        if ($value) {
            Hm_Image_Sources::$$name = $value;
        }
        else {
            $raw = rawurldecode(Hm_Image_Sources::$$name);
            $pre = substr($raw, 0, 19);
            $img = substr($raw, 19);
            Hm_Image_Sources::$$name = $pre.rawurlencode(str_replace('/>', 'fill="'.$color.'" />', $img));
        }
    }
}}

/**
 * Custom theme check
 */
if (!hm_exists('custom_themes')) {
function custom_themes($config, $themes) {
    $custom = get_ini($config, 'themes.ini');
    if (!is_array($custom)) {
        return $themes;
    }
    if (!array_key_exists('theme', $custom)) {
        return $themes;
    }
    if (!is_array($custom['theme'])) {
        return $themes;
    }
    foreach ($custom['theme'] as $val) {
        if (strpos($val, '|') === false) {
            continue;
        }
        $parts = explode('|', $val, 2);
        $themes[$parts[0]] = $parts[1];
    }
    return $themes;
}}

