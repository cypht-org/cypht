<?php

/**
 * @subpackage tags/handler
 */
class Hm_Handler_mod_env extends Hm_Handler_Module {
    public function process() {
        $this->out('mod_support', array_filter(array(
            $this->module_is_supported('imap') ? 'imap' : false,
            $this->module_is_supported('feeds') ? 'feeds' : false,
            $this->module_is_supported('github') ? 'github' : false,
            $this->module_is_supported('wordpress') ? 'wordpress' : false
        )));
    }
}

/**
 * Add tag/label to message
 * @subpackage imap/handler
 */
class Hm_Handler_add_tag_to_message extends Hm_Handler_Module {
    /**
     * Use IMAP to tag the selected message uid
     */
    public function process() {
        list($success, $form) = $this->process_form(array('tag_id', 'list_path'));
        if (!$success) {
            return;
        }

        $taged_messages = 0;
        $ids = explode(',', $form['list_path']);
        foreach ($ids as $msg_part) {
            list($imap_server_id, $msg_id, $folder) = explode('_', $msg_part);
            $folder = hex2bin($folder);
            $msg_id = hex2bin($msg_id);
            $tagged = Hm_Tags::addMessage($form['tag_id'], $imap_server_id, $folder, $msg_id);
            if ($tagged) {
                $taged_messages++;
            }
        }
        $this->out('taged_messages', $taged_messages);
        $type = 'success';
        if ($taged_messages == count($ids)) {
            $msg = 'Tag added';
        } elseif ($taged_messages > 0) {
            $msg = 'Some messages have been taged';
            $type = 'warning';
        } else {
            $msg = 'ERRFailed to tag selected messages';
            $type = 'danger';
        }
        Hm_Msgs::add($msg, $type);
    }
}

/**
 * @subpackage tags/handler
 */
class Hm_Handler_tag_edit_data extends Hm_Handler_Module {
    public function process() {
        $id = false;
        if (array_key_exists('tag_id', $this->request->get)) {
            $id = $this->request->get['tag_id'];
        }
        $folders = $this->get('tags');
        $folder = null;
        foreach ($folders as $f) {
            if ($f['id'] === $id) {
                $folder = $f;
            }
        }
        if ($id !== false) {
            $this->out('edit_tag', $folder);
            $this->out('edit_tag_id', $id);
        }
        else {
            $this->out('new_tag_id', count($folders));
        }
    }
}
/**
 * @subpackage tags/handler
 */
class Hm_Handler_process_tag_update extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('tag_delete', $this->request->post)) {
            return;
        }
        list($success, $form) = $this->process_form(array('tag_name','parent_tag','tag_id'));// 'tag_id', parent_tag
        if (!$success) {
            return;
        }
        $tag = array(
            'name' => html_entity_decode($form['tag_name'], ENT_QUOTES),
            'parent' => $form['parent_tag'] ?? null
        );
        if (!is_null($form['tag_id']) AND Hm_Tags::get($form['tag_id'])) {
            $tag['id'] = $form['tag_id'];
            Hm_Tags::edit($form['tag_id'], $tag);
            Hm_Msgs::add('Tag Edited');
        } else {
            Hm_Tags::add($tag);
            Hm_Msgs::add('Tag Created');
        }
    }
}

/**
 * @subpackage profile/handler
 */
class Hm_Handler_process_tag_delete extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('tag_delete', 'tag_id'));
        if (!$success) {
            return;
        }
        if (($tag = Hm_Tags::get($form['tag_id']))) {
            Hm_Tags::del($tag['id']);
            Hm_Msgs::add('Tag Deleted');
        } else {
            Hm_Msgs::add('Tag ID not found', 'warning');
            return;
        }
    }
}

/**
 * @subpackage tags/handler
 */
class Hm_Handler_imap_tag_content extends Hm_Handler_Module {
    public function process() {
        $data_sources = imap_data_sources();
        $ids = array_map(function($ds) { return $ds['id']; }, $data_sources);
        $tag_id = $this->request->post['folder'];
        if ($ids && $tag_id) {
            try {
                $msg_list = [];
                foreach ($ids as $serverId) {
                    $folders = Hm_Tags::getFolders($tag_id, $serverId);
                    if (!empty($folders)) {
                        $mailbox = Hm_IMAP_List::get_connected_mailbox($serverId, $this->cache);
                        $server_details = Hm_IMAP_List::dump($serverId);
                        if ($mailbox && $mailbox->authed()) {
                            foreach ($folders as $folder => $messageIds) {
                                $messages = array_map(function($msg) use ($serverId, $folder, $server_details) {
                                    $msg['server_id'] = $serverId;
                                    $msg['folder'] = bin2hex($folder);
                                    $msg['server_name'] = $server_details['name'];
                                    return $msg;
                                }, $mailbox->get_message_list($folder, $messageIds));
                                $msg_list = array_merge($msg_list, $messages);
                            }
                        }
                    }
                }
                $limit = $this->user_config->get('tag_per_source_setting', DEFAULT_TAGS_PER_SOURCE);
                $date = process_since_argument($this->user_config->get('tag_since_setting', DEFAULT_TAGS_SINCE));

                $msg_list = array_filter($msg_list, function($msg) use ($date) {
                    return strtotime($msg['internal_date']) >= strtotime($date);
                });
                $msg_list = array_slice($msg_list, 0, $limit);
                usort($msg_list, function($a, $b) {
                    return strtotime($b['internal_date']) - strtotime($a['internal_date']);
                });

                $this->out('imap_tag_data', $msg_list);
            } catch (\Throwable $th) {
                Hm_Msgs::add('Failed to load tag messages: '.$th->getMessage(), 'danger');
            }
        }
    }
}

/**
 * Process "tag_per_source" setting for the tag page in the settings page
 * @subpackage core/handler
 */
class Hm_Handler_process_tag_source_max_setting extends Hm_Handler_Module {
    /**
     * Allowed values are greater than zero and less than MAX_PER_SOURCE
     */
    public function process() {
        process_site_setting('tag_per_source', $this, 'max_source_setting_callback', DEFAULT_TAGS_PER_SOURCE);
    }
}

class Hm_Handler_tag_data extends Hm_Handler_Module {
    public function process() {
        Hm_Tags::init($this);
        $this->out('tags', Hm_Tags::getAll());
    }
}

class Hm_Handler_move_message extends Hm_Handler_Module {
    public function process()
    {
        $moveResponses = $this->get('move_responses', []);
        foreach ($moveResponses as $response) {
            Hm_Tags::moveMessageToADifferentFolder([
                'oldId' => $response['oldUid'],
                'newId' => $response['newUid'],
                'oldFolder' => $response['oldFolder'],
                'newFolder' => $response['newFolder'],
                'oldServer' => $response['oldServer'],
                'newServer' => $response['newServer'] ?? '',
            ]);
        }
    }
}
