<?php

class Hm_Output_filter_tag_data extends Hm_Output_Module {
    /**
     * Build ajax response for the tag message list
     */
    protected function output() {
        if ($this->get('imap_tag_data')) {
            prepare_imap_message_list($this->get('imap_tag_data'), $this, 'tag');
        }
        elseif (!$this->get('formatted_message_list')) {
            $this->out('formatted_message_list', array());
        }
    }
}

/**
 * Option for the maximum number of messages per source for the Junk page
 * @subpackage core/output
 */
class Hm_Output_tag_per_source_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_tag_source_max_setting
     */
    protected function output() {
        $sources = DEFAULT_TAGS_PER_SOURCE;
        $settings = $this->get('user_settings', array());
        $reset = '';
        if (array_key_exists('tag_per_source', $settings)) {
            $sources = $settings['tag_per_source'];
        }
        if ($sources != DEFAULT_TAGS_PER_SOURCE) {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_input"></i></span>';
        }
        return '<tr class="tag_setting"><td><label for="tag_per_source">'.
            $this->trans('Max messages per source').'</label></td>'.
            '<td class="d-flex"><input type="text" size="2" class="form-control form-control-sm w-auto" id="tag_per_source" name="tag_per_source" value="'.$this->html_safe($sources).'" data-default-value="'.DEFAULT_TAGS_PER_SOURCE.'"/>'.$reset.'</td></tr>';
    }
}

/**
 * Starts the Tag section on the settings page
 * @subpackage core/output
 */
class Hm_Output_start_tag_settings extends Hm_Output_Module {
    /**
     * Settings in this section control the tag messages view
     */
    protected function output() {
        $res = '<tr><td data-target=".tag_setting" colspan="2" class="settings_subtitle cursor-pointer border-bottom p-2">'.
            '<i class="bi bi-tags fs-5 me-2"></i>'.
            $this->trans('Tags').'</td></tr>';
        return $res;
    }
}

/**
 * Process "since" setting for the junk page in the settings page
 * @subpackage core/handler
 */
class Hm_Handler_process_tag_since_setting extends Hm_Handler_Module {
    /**
     * valid values are defined in the process_since_argument function
     */
    public function process() {
        process_site_setting('tag_since', $this, 'since_setting_callback');
    }
}

/**
 * Option for the "junk since" date range for the Junk page
 * @subpackage core/output
 */
class Hm_Output_tag_since_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_tag_since_setting
     */
    protected function output() {
        $since = DEFAULT_TAGS_SINCE;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('tag_since', $settings) && $settings['tag_since']) {
            $since = $settings['tag_since'];
        }
        return '<tr class="tag_setting"><td><label for="tag_since">'.
            $this->trans('Show tagged messages since').'</label></td>'.
            '<td>'.message_since_dropdown($since, 'tag_since', $this, DEFAULT_TAGS_SINCE).'</td></tr>';
    }
}

/**
 * Renders the tag/label list in the folder menu, and a hidden
 * JSON blob used by site.js to build the "parent label" picker in the
 * create/edit modal, without a server round trip.
 * @subpackage tags/output
 */
class Hm_Output_tags extends hm_output_module {
    protected function output() {
        $res = '';
        $folders = $this->get('tags', array());
        if (is_array($folders) && !empty($folders)) {
            $folderTree = [];
            foreach ($folders as $folderId => $folder) {
                if (isset($folder['parent']) && $folder['parent']) {
                    $folders[$folder['parent']]['children'][$folderId] = &$folders[$folderId];
                } else {
                    $folderTree[$folderId] = &$folders[$folderId];
                }
            }
            $res .= $this->render_tag_branch($folderTree);

            $flat = [];
            $this->flatten_tags($folderTree, 0, $flat);
            $res .= '<script type="application/json" class="tags_json_data">'.
                json_encode($flat, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP).'</script>';
            $res .= '<script type="application/json" class="tags_palette_data">'.
                json_encode(Hm_Tags::colorPalette(), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP).'</script>';
        }
        $res .= '<li class="tags_add_new"><a href="#" class="tag_add_new_btn">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<i class="bi bi-plus-square menu-icon"></i>';
        }
        $res .= $this->trans('Add label').'</a></li>';
        $this->append('folder_sources', array('tags_folders', $res));
    }

    /**
     * Recursively render a branch of the tag tree as nested <li> menu items
     */
    private function render_tag_branch($folders) {
        $res = '';
        foreach ($folders as $id => $folder) {
            $hasChild = !empty($folder['children']);
            $parent = isset($folder['parent']) ? $folder['parent'] : '';
            $color = Hm_Tags::sanitizeColor($folder['color'] ?? null);
            $res .= '<li class="tag_row'.($hasChild ? ' has_children' : '').' tags_'.$this->html_safe($id).
                '" data-tag-id="'.$this->html_safe($id).
                '" data-tag-name="'.$this->html_safe($folder['name']).
                '" data-tag-parent="'.$this->html_safe($parent).
                '" data-tag-color="'.$this->html_safe($color).'">';
            $res .= '<div class="tag_row_main d-flex align-items-center">';
            if ($hasChild) {
                $res .= '<a href="#" class="tag_expand_toggle"><i class="bi bi-chevron-down"></i></a>';
            }
            if (!$this->get('hide_folder_icons')) {
                $res .= '<i class="bi bi-circle-fill tag_dot'.($hasChild ? '' : ' tag_dot_indent').'" style="color: '.$color.';"></i>';
            }
            $res .= '<a class="tag_link flex-grow-1" data-id="tag_'.$this->html_safe($id).
                '" href="'.$this->build_page_url('message_list', array('list_path' => 'tag', 'filter' => $this->html_safe($id))).'">';
            $res .= $this->html_safe($folder['name']).'</a>';
            $res .= '<span class="tag_row_actions">';
            $res .= '<a href="#" class="tag_action_add_child" title="'.$this->trans('Add sub-label').'"><i class="bi bi-plus-lg"></i></a>';
            $res .= '<a href="#" class="tag_action_edit" title="'.$this->trans('Edit').'"><i class="bi bi-pencil"></i></a>';
            $res .= '<a href="#" class="tag_action_delete" title="'.$this->trans('Remove').'"><i class="bi bi-trash"></i></a>';
            $res .= '</span>';
            $res .= '</div>';
            if ($hasChild) {
                $res .= '<ul class="tag_children">';
                $res .= $this->render_tag_branch($folder['children']);
                $res .= '</ul>';
            }
            $res .= '</li>';
        }
        return $res;
    }

    /**
     * Flatten the tree into an ordered, indented list for the parent
     * label <select> built client side
     */
    private function flatten_tags($folders, $depth, &$out) {
        foreach ($folders as $id => $folder) {
            $out[] = array(
                'id' => $folder['id'],
                'name' => $folder['name'],
                'depth' => $depth,
                'color' => Hm_Tags::sanitizeColor($folder['color'] ?? null)
            );
            if (!empty($folder['children'])) {
                $this->flatten_tags($folder['children'], $depth + 1, $out);
            }
        }
    }
}

/**
 * @subpackage tags/output
 */
class Hm_Output_tag_bar extends hm_output_module {
    protected function output() {
        $headers = $this->get('msg_headers');
        if (is_string($headers)) {
            $this->out('msg_headers', $headers.'<i class="bi bi-tags-fill fs-4 tag_icon refresh_list"></i>');
        }
    }
}

class Hm_Output_display_configured_tags extends Hm_Output_Module {
    protected function output() {
        $res = '';
        if ($this->format == 'HTML5') {
            foreach ($this->get('feeds', array()) as $index => $vals) {
                $res .= '<div class="configured_server col-12 col-lg-4 mb-2"><div class="card card-body">';
                $res .= sprintf('<div class="server_title"><b>%s</b></div><div title="%s" class="server_subtitle">%s</div>',
                    $this->html_safe($vals['name']), $this->html_safe($vals['server']), $this->html_safe($vals['server']));
                $res .= '<form class="feed_connect d-flex gap-2" method="POST">';
                $res .= '<input type="hidden" name="feed_id" value="'.$this->html_safe($index).'" />';
                $res .= '<input type="submit" value="'.$this->trans('Test').'" class="test_feed_connect btn btn-primary btn-sm" />';
                $res .= '<input type="submit" value="'.$this->trans('Delete').'" class="feed_delete btn btn-outline-danger btn-sm" />';
                $res .= '<input type="hidden" value="ajax_feed_debug" name="hm_ajax_hook" />';
                $res .= '</form></div></div>';
            }
            $res .= '<br class="clear_float" /></div></div>';
        }
        return $res;
    }
}
