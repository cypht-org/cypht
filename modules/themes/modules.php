<?php

/**
 * Theme modules
 * @package modules
 * @subpackage themes
 */

if (!defined('DEBUG_MODE')) { die(); }


/**
 * Process language setting from the general section of the settings page
 * @subpackage themes/handler
 */
class Hm_Handler_load_theme  extends Hm_Handler_Module {
    public function process() {
        $this->out('theme', $this->user_config->get('theme_setting'), 'default');
    }
}

/**
 * Process language setting from the general section of the settings page
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
        if ($this->get('theme') && in_array($this->get('theme'), array_keys(hm_themes($this)), true) && $this->get('theme') != 'default') {
            if (DEBUG_MODE) {
                return '<link href="modules/themes/'.$this->html_safe($this->get('theme')).'.css" css" media="all" rel="stylesheet" type="text/css" />';
            }
            else {
                return '<link href="'.$this->html_safe($this->get('theme')).'.css" css" media="all" rel="stylesheet" type="text/css" />';
            }
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
        $res = '<tr class="general_setting"><td><label for="language_setting">'.
            $this->trans('Theme').'</label></td>'.
            '<td><select id="theme_setting" name="theme_setting">';
        foreach (hm_themes($this) as $name => $label) {
            $res .= '<option ';
            if ($name == $current) {
                $res .= 'selected="selected" ';
            }
            $res .= 'value="'.$this->html_safe($name).'">'.$label.'</option>';
        }
        $res .= '</select>';
        return $res;
    }
}

/**
 * Define available themes
 * @subpackage themes/functions
 */
function hm_themes($output_mod) {
    return array(
        'default' => $output_mod->trans('White Bread (Default)'),
        'blue' => $output_mod->trans('Boring Blues'),
        'dark' => $output_mod->trans('Dark But Not Too Dark'),
        'gray' => $output_mod->trans('More Gray Than White Bread'),
        'green' => $output_mod->trans('Poison Mist'),
        'tan' => $output_mod->trans('A Bunch Of Browns'),
        'terminal' => $output_mod->trans('VT100'),
    );
}

?>
