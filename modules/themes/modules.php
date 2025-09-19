<?php

/**
 * Theme modules using thomaspark/bootswatch
 * Code : https://github.com/thomaspark/bootswatch
 * @package modules
 * @subpackage themes
 */

if (!defined('DEBUG_MODE')) {
    die();
}


/**
 * Setup currently selected theme
 * @subpackage themes/handler
 */
class Hm_Handler_load_theme  extends Hm_Handler_Module
{
    public function process()
    {
        $theme = $this->user_config->get('theme_setting', DEFAULT_THEME);
        $themes = custom_themes($this->config, hm_themes());
        if ($theme == 'hn') {
            $this->user_config->set('list_style', 'news_style');
        }
        $this->out('themes', $themes);
        $this->out('theme', $theme);
    }
}

/**
 * Process theme setting from the general section of the settings page
 * @subpackage themes/handler
 */
class Hm_Handler_process_theme_setting extends Hm_Handler_Module
{
    public function process()
    {
        list($success, $form) = $this->process_form(array('save_settings', 'theme_setting'));
        $new_settings = $this->get('new_user_settings', array());
        $settings = $this->get('user_settings', array());

        if ($success) {
            $new_settings['theme_setting'] = $form['theme_setting'];
        } else {
            $settings['theme'] = $this->user_config->get('theme_setting', DEFAULT_THEME);
        }
        $this->out('new_user_settings', $new_settings, false);
        $this->out('user_settings', $settings, false);
    }
}

/**
 * Include theme css
 * @subpackage themes/output
 */
class Hm_Output_theme_css extends Hm_Output_Module
{
    /**
     * Add HTML head tag for theme css
     */
    protected function output()
    {
        if ($this->get('theme') && in_array($this->get('theme'), array_keys($this->get('themes', array())), true)) {
            $theme_name = $this->html_safe($this->get('theme'));
            return '<link href="' . ASSETS_THEMES_ROOT . 'modules/themes/assets/' . $theme_name . '/css/' . $theme_name . '.css?v=' . CACHE_ID . '" media="all" rel="stylesheet" type="text/css" />';
        }
    }
}

/**
 * Theme setting
 * @subpackage themes/output
 */
class Hm_Output_theme_setting extends Hm_Output_Module
{
    /**
     * Theme setting
     */
    protected function output()
    {

        $current = $this->get('theme', DEFAULT_THEME);
        $res = '<tr class="general_setting"><td class="d-block d-md-table-cell"><label for="theme_setting">' .
            $this->trans('Theme') . '</label></td><td class="d-block d-md-table-cell"><div class="d-flex align-items-center"><select class="form-select form-select-sm" id="theme_setting" name="theme_setting" data-default-value="'.DEFAULT_THEME.'">';
        $reset = '';
        foreach ($this->get('themes', array()) as $name => $label) {
            $res .= '<option ';
            if ($name == $current) {
                $res .= 'selected="selected" ';
                if ($name != 'default') {
                    $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_select"></i></span>';
                }
            }
            $res .= 'value="' . $this->html_safe($name) . '">' . $this->trans($label) . '</option>';
        }
        $res .= '</select>' . $reset . '</div></td></tr>';
        return $res;
    }
}

/**
 * icon colors for theme selection
 */
if (!hm_exists('icon_color')) {
    function icon_color($theme)
    {
    }
}

/**
 * Define available themes
 * @subpackage themes/functions
 */
if (!hm_exists('hm_themes')) {
    function hm_themes()
    {
        return array(
            'default' => 'White Bread (Default)',
            'cosmo' => 'Cosmo',
            'cerulean' => 'Cerulean',
            'cyborg' => 'Cyborg',
            'darkly' => 'Darkly',
            'flatly' => 'Flatly',
            'journal' => 'Journal',
            'litera' => 'Litera',
            'lumen' => 'Lumen',
            'lux' => 'Lux',
            'materia' => 'Materia',
            'minty' => 'Minty',
            'morph' => 'Morph',
            'pulse' => 'Pulse',
            'quartz' => 'Quartz',
            'sandstone' => 'Sandstone',
            'simplex' => 'Simplex',
            'sketchy' => 'Sketchy',
            'slate' => 'Slate',
            'solar' => 'Solar',
            'spacelab' => 'Spacelab',
            'superhero' => 'Superhero',
            'united' => 'United',
            'vapor' => 'Vapor',
            'yeti' => 'Yeti',
            'zephyr' => 'Zephyr'
        );
    }
}

/**
 * Custom theme check
 */
if (!hm_exists('custom_themes')) {
    function custom_themes($config, $themes)
    {
        $custom = $config->get('theme', []);
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
            if (mb_strpos($val, '|') === false) {
                continue;
            }
            $parts = explode('|', $val, 2);
            $themes[$parts[0]] = $parts[1];
        }
        return $themes;
    }
}
