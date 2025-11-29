<?php

/**
 * highlights module set
 * @package modules
 * @subpackage highlights
 */

if (!defined('DEBUG_MODE')) { die(); }

/*
 * @subpackage highlights/handler
 */
class Hm_Handler_highlight_list_data extends Hm_Handler_Module {
    public function process() {
        $this->out('highlight_rules', $this->user_config->get('highlight_rules', array()));
        $this->out('github_repos', $this->user_config->get('github_repos', array()));
    }
}

/*
 * @subpackage highlights/handler
 */
class Hm_Handler_highlight_page_data extends Hm_Handler_Module {
    public function process() {

        $imap = false;
        $feeds = false;
        $github = false;

        $modules = $this->config->get_modules();

        if ($this->module_is_supported('imap')) {
            $imap = Hm_IMAP_List::dump(false);
        }
        if ($this->module_is_supported('feeds')) {
            $feeds = Hm_Feed_List::dump(false);
        }
        if ($this->module_is_supported('github')) {
            $github = $this->user_config->get('github_repos', array());
        }
        $this->out('highlight_sources', array(
            'imap' => $imap,
            'feeds' => $feeds,
            'github' => $github
        ));
        $this->out('highlight_rules', $this->user_config->get('highlight_rules', array()));
    }
}

/*
 * @subpackage highlights/handler
 */
class Hm_Handler_highlight_process_form extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('rule_del_id', $this->request->post)) {
            $rules = $this->user_config->get('highlight_rules', array());
            unset($rules[$this->request->post['rule_del_id']]);
            $rules = array_merge($rules);
            $this->user_config->set('highlight_rules', $rules);
            $this->session->record_unsaved('Highlight rule deleted');
            Hm_Msgs::add('Hightlight rule deleted');
            return;
        }
        list($success, $form) = $this->process_form(array('hl_source_type', 'hl_target', 'hl_color'));
        if (!$success) {
            return;
        }
        if (!in_array($form['hl_source_type'], array('imap', 'feeds', 'github'))) {
            return;
        }
        if (!preg_match("/^#[0-9abcdef]{6}$/", mb_strtolower($form['hl_color']))) {
            return;
        }
        foreach (array('hl_important', 'hl_imap_flags', 'hl_imap_sources', 'hl_feeds_sources', 'hl_github_sources') as $fld) {
            if (array_key_exists($fld, $this->request->post) && $this->request->post[$fld]) {
                $form[$fld] = $this->request->post[$fld];
            }
        }
        if ($form['hl_source_type'] == 'github' &&
            array_key_exists('hl_github_unseen', $this->request->post) &&
            $this->request->post['hl_github_unseen']) {
            $form['hl_imap_flags'] = array('unseen');
        }
        elseif ($form['hl_source_type'] == 'feeds' &&
            array_key_exists('hl_feeds_unseen', $this->request->post) &&
            $this->request->post['hl_feeds_unseen']) {
            $form['hl_imap_flags'] = array('unseen');
        }
        switch ($form['hl_source_type']) {
            case 'imap':
                $new_rule = hl_imap_rule($form);
                break;
            case 'feeds':
                $new_rule = hl_feeds_rule($form);
                break;
            case 'github':
                $new_rule = hl_github_rule($form, $this);
                break;
        }
        $rules = $this->user_config->get('highlight_rules', array());
        $rules[] = $new_rule;
        $this->user_config->set('highlight_rules', $rules);
        $this->session->record_unsaved('Highlight rule created');
        Hm_Msgs::add('Hightlight rule created');
    }
}

/*
 * @subpackage highlights/output
 */
class Hm_Output_highlight_css extends Hm_Output_Module {
    protected function output() {
        $css = array();
        $repos = $this->get('github_repos', array());
        $rules = $this->get('highlight_rules', array());
        $defaults = array('imap' => '.email', 'feeds' => '.feeds', 'github' => '.github');
        foreach ($rules as $rule) {
            $ids = get_rule_ids($rule['sources'], $rule['type'], $repos);
            if (!$ids) {
                $ids = array($defaults[$rule['type']]);
            }
            if ($rule['types']) {
                if (!$ids) {
                    foreach ($rule['types'] as $type) {
                        $ids[] = '.'.$type;
                    }
                }
                else {
                    $updated = array();
                    foreach ($ids as $id) {
                        foreach ($rule['types'] as $type) {
                            $updated[] = $id.'.'.$type;
                        }
                    }
                    $ids = $updated;
                }
            }
            foreach ($ids as $id) {
                $css[] = sprintf('.message_table %s td {%s: %s !important;}',
                    $id,
                    ($rule['target'] == 'text' ? 'color': 'background-color'),
                    $rule['color'], ($rule['important'] ? '!important' : '')
                );
                if ($rule['target'] == 'text') {
                    $css[] = sprintf('.message_table %s td a {color: %s !important;}',
                        $id, $rule['color'], ($rule['important'] ? '!important' : ''));
                }
                else {
                    $css[] = sprintf('.message_table %s td div {background-color: %s !important;}',
                        $id, $rule['color'], ($rule['important'] ? '!important' : ''));
                }
            }
        }
        $res = '<style type="text/css">'.implode(' ', $css).'</style>';
        return $res;
    }
}

/*
 * @subpackage highlights/output
 */
class Hm_Output_highlight_config_page extends Hm_Output_Module {
    protected function output() {
        $rules = $this->get('highlight_rules', array());
        $sources = $this->get('highlight_sources', array());
        $source_types = array('E-mail' => 'imap');
        if ($sources['feeds'] !== false) {
            $source_types['RSS'] = 'feeds';
        }
        if ($sources['github'] !== false) {
            $source_types['Github'] = 'github';
        }
        $email_types = array(
            'Unseen' => 'unseen',
            'Seen' => 'seen',
            'Flagged' => 'flagged',
            'Deleted' => 'deleted',
            'Anwered' => 'answered'
        );
        $targets = array(
            'Text' => 'text',
            'Background' => 'background'
        );
        $res = '<div class="content_title">'.$this->trans('Message highlighting').'</div>';
        $res .= '<div class="settings_subtitle mt-3 mb-2"><b>'.$this->trans('Existing rules').'</b></div>';
        if (!$rules) {
            $res .= '<div class="empty_list">'.$this->trans('No rules').'</div>';
        }
        else {
            $res .= '<div class="px-3"><table class="hl_rules table table-striped">'.
                '<th>'.$this->trans('Type').'</th>'.
                '<th>'.$this->trans('Target').'</th>'.
                '<th>'.$this->trans('Color').'</th>'.
                '<th>'.$this->trans('Sources').'</th>'.
                '<th>'.$this->trans('Flags').'</th><th>'.$this->trans('Force').'</th><th></th></tr>';
            foreach ($rules as $index => $rule) {
                if ($rule['types']) {
                    $types = implode(' ', $rule['types']);
                }
                else {
                    $types = '*';
                }
                if ($rule['sources']) {
                    $src = array();
                    foreach ($rule['sources'] as $vals) {
                        $src[] = $vals['name'];
                    }
                    $src = implode(' ', $src);
                }
                else {
                    $src = '*';
                }
                $res .= '<tr>'.
                    '<td>'.$this->html_safe($rule['type']).'</td>'.
                    '<td>'.$this->html_safe($rule['target']).'</td>'.
                    '<td style="'.($rule['target'] == 'text' ? 'color' : 'background-color').': '.
                    $this->html_safe($rule['color']).' !important;">'.$this->html_safe($rule['color']).'</td>'.
                    '<td>'.$this->html_safe($src).'</td>'.
                    '<td>'.$this->html_safe($types).'</td>'.
                    '<td>'.$this->html_safe($rule['important'] ? 'true' : 'false').'</td>'.
                    '<td><form method="POST">'.
                    '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
                    '<input type="hidden" name="rule_del_id" value="'.$this->html_safe($index).'" />'.
                    '<button type="submit" class="rule_del btn btn-light"><i class="bi bi-x-circle-fill"></i></button></form>'.
                    '</tr>';
            }
        }
        $res .= '</table></div>';

        $res .= '<div class="settings_subtitle mt-3 px-3"><b>'.$this->trans('Add a new rule').'</b></div>';
        $res .= '<div class="col-12 col-lg-5 mt-2 px-3 pb-3">';
        $res .= '<form method="POST">';

        // Source Type
        $res .= '<div class="form-floating mb-3">';
        $res .= '<select name="hl_source_type" required class="form-select hl_source_type">';
        $res .= '<option selected="selected">'.$this->trans('Select source type').'</option>';
        foreach ($source_types as $name => $val) {
            $res .= '<option value="'.$this->html_safe($val).'">'.$this->trans($name).'</option>';
        }
        $res .= '</select>';
        $res .= '<label>'.$this->trans('Source type').'</label></div>';

        // IMAP Flags
        if ($sources['imap']) {
            $res .= '<div class="form-floating mb-3 imap_row d-none">';
            $res .= '<select style="min-height: 8rem" name="hl_imap_flags[]" size="4" multiple="multiple" class="form-select imap_flags">';
            foreach ($email_types as $index => $value) {
                $res .= '<option value="'.$this->html_safe($value).'">'.$this->trans($index).'</option>';
            }
            $res .= '</select>';
            $res .= '<label>'.$this->trans('Flags').'</label></div>';

            // IMAP Accounts
            $res .= '<div class="form-floating mb-3 imap_row d-none">';
            $res .= '<select style="min-height: 8rem" name="hl_imap_sources[]" size="4" multiple="multiple" class="form-select imap_source">';
            foreach ($sources['imap'] as $index => $vals) {
                $res .= '<option value="'.$this->html_safe($index).'">'.$this->trans($vals['name']).'</option>';
            }
            $res .= '</select>';
            $res .= '<label>'.$this->trans('Accounts').'</label></div>';
        }

        // Feeds
        if ($sources['feeds']) {
            // Feeds Sources
            $res .= '<div class="form-floating mb-3 feeds_row d-none">';
            $res .= '<select style="min-height: 8rem" name="hl_feeds_sources[]" size="4" multiple="multiple" class="form-select feeds_source">';
            foreach ($sources['feeds'] as $index => $vals) {
                $res .= '<option value="'.$this->html_safe($index).'">'.$this->trans($vals['name']).'</option>';
            }
            $res .= '</select>';
            $res .= '<label>'.$this->trans('Feeds').'</label></div>';

            // Unseen Feeds
            $res .= '<div class="form-check mb-3 feeds_row d-none">';
            $res .= '<input name="hl_feeds_unseen" type="checkbox" value="true" class="form-check-input" />';
            $res .= '<label class="form-check-label">'.$this->trans('Unseen').'</label></div>';
        }

        // GitHub
        if ($sources['github']) {
            // GitHub Repos
            $res .= '<div class="form-floating mb-3 github_row d-none">';
            $res .= '<select style="min-height: 8rem" name="hl_github_sources[]" size="4" multiple="multiple" class="form-select github_source">';
            foreach ($sources['github'] as $repo) {
                $res .= '<option value="'.$this->html_safe($repo).'">'.$this->trans($repo).'</option>';
            }
            $res .= '</select>';
            $res .= '<label>'.$this->trans('Repos').'</label></div>';

            // Unseen GitHub
            $res .= '<div class="form-check mb-3 github_row d-none">';
            $res .= '<input name="hl_github_unseen" type="checkbox" value="true" class="form-check-input" />';
            $res .= '<label class="form-check-label">'.$this->trans('Unseen').'</label></div>';
        }

        // Highlight Target
        $res .= '<div class="form-floating mb-3">';
        $res .= '<select name="hl_target" class="form-select">';
        foreach ($targets as $name => $val) {
            $res .= '<option value="'.$this->html_safe($val).'">'.$this->trans($name).'</option>';
        }
        $res .= '</select>';
        $res .= '<label>'.$this->trans('Highlight target').'</label></div>';

        // Highlight Color
        $res .= '<div class="mb-3">';
        $res .= '<label>'.$this->trans('Highlight color').'</label>';
        $res .= '<input style="min-height: 3rem" name="hl_color" type="color" class="form-control p-1" /></div>';

        // CSS Override
        $res .= '<div class="form-check mb-3">';
        $res .= '<input value="true" type="checkbox" name="hl_important" class="form-check-input" />';
        $res .= '<label class="form-check-label">'.$this->trans('CSS override').'</label></div>';

        // Submit Button
        $res .= '<div class="submit_row">';
        $res .= '<input type="submit" value="'.$this->trans('Add').'" class="btn btn-primary px-5" />';
        $res .= '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />';
        $res .= '</div></form></div>';

        return $res;

    }
}

/*
 * @subpackage highlights/output
 */
class Hm_Output_highlight_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_highlights"><a class="unread_link" href="?page=highlights">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<i class="bi bi-highlighter me-2"></i>';
        }
        $res .= $this->trans('Highlights').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/*
 * @subpackage highlights/functions
 */
function hl_base_rule($form, $type) {
    if (array_key_exists('hl_important', $form) && $form['hl_important']) {
        $important = true;
    }
    else {
        $important = false;
    }
    return array(
        'important' => $important,
        'type' => $type,
        'color' => $form['hl_color'],
        'target' => $form['hl_target'],
        'sources' => array(),
        'types' => array()
    );
}

/*
 * @subpackage highlights/functions
 */
function hl_imap_rule($form) {
    $rule = hl_base_rule($form, 'imap');
    if (array_key_exists('hl_imap_sources', $form)) {
        foreach ($form['hl_imap_sources'] as $id) {
            $server = Hm_IMAP_List::dump($id);
            if (!$server) {
                continue;
            }
            $rule['sources'][] = array('name' => $server['name'],
                'user' => $server['user'], 'server' => $server['server']);
        }
    }
    if (array_key_exists('hl_imap_flags', $form)) {
        $rule['types'] = $form['hl_imap_flags'];
    }
    return $rule;
}

/*
 * @subpackage highlights/functions
 */
function hl_feeds_rule($form) {
    $rule = hl_base_rule($form, 'feeds');
    if (array_key_exists('hl_feeds_sources', $form)) {
        foreach ($form['hl_feeds_sources'] as $id) {
            $server = Hm_Feed_List::dump($id);
            if (!$server) {
                continue;
            }
            $rule['sources'][] = array('server' => $server['server'],
                'name' => $server['name']);
        }
    }
    if (array_key_exists('hl_imap_flags', $form)) {
        $rule['types'] = $form['hl_imap_flags'];
    }
    return $rule;
}

/*
 * @subpackage highlights/functions
 */
function hl_github_rule($form, $mod) {
    $rule = hl_base_rule($form, 'github');
    if (array_key_exists('hl_github_sources', $form)) {
        $repos = $mod->user_config->get('github_repos', array());
        foreach ($form['hl_github_sources'] as $id) {
            foreach ($repos as $repo) {
                if ($id == $repo) {
                    $rule['sources'][] = array('server' => $repo, 'name' => $repo);
                }
            }
        }
    }
    if (array_key_exists('hl_imap_flags', $form)) {
        $rule['types'] = $form['hl_imap_flags'];
    }
    return $rule;
}

/*
 * @subpackage highlights/functions
 */
function get_rule_ids($sources, $type, $repos) {
    if ($type == 'imap') {
        return get_imap_ids($sources);
    }
    elseif ($type == 'feeds') {
        return get_feed_ids($sources);
    }
    elseif ($type == 'github') {
        return get_github_ids($sources, $repos);
    }
    else {
        return array();
    }
}

/*
 * @subpackage highlights/functions
 */
function get_imap_ids($sources) {
    $ids = array();
    foreach (Hm_IMAP_List::dump() as $index => $vals) {
        foreach ($sources as $src) {
            if ($src['user'] == $vals['user'] && $src['server'] == $vals['server']) {
                $ids[] = sprintf('[class^="imap_%s_"]', $index);
            }
        }
    }
    return $ids;
}

/*
 * @subpackage highlights/functions
 */
function get_feed_ids($sources) {
    $ids = array();
    foreach (Hm_Feed_List::dump() as $index => $vals) {
        foreach ($sources as $src) {
            if ($src['name'] == $vals['name'] && $src['server'] == $vals['server']) {
                $ids[] = sprintf('[class^="feeds_%s_"]', $index);
            }
        }
    }
    return $ids;
}

/*
 * @subpackage highlights/functions
 */
function get_github_ids($sources, $repos) {
    $ids = array();
    foreach ($repos as $index => $val) {
        foreach ($sources as $src) {
            if ($src['server'] == $val) {
                $ids[] = sprintf('[class^="github_%s_"]', $index);
            }
        }
    }
    return $ids;
}
