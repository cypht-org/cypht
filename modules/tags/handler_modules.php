<?php

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
            $cache = Hm_IMAP_List::get_cache($this->cache, $imap_server_id);
            $imap = Hm_IMAP_List::connect($imap_server_id, $cache);
            if (imap_authed($imap)) {
                $folder = hex2bin($folder);
                if (add_tag_to_message($imap, $msg_id, $folder, $form['tag_id'])) {
                    $taged_messages++;
                    Hm_Tags::registerFolder($form['tag_id'], $imap_server_id, $folder);
                }
            }
        }
        $this->out('taged_messages', $taged_messages);
        if ($taged_messages == count($ids)) {
            $msg = 'Tag added';
        } elseif ($taged_messages > 0) {
            $msg = 'Some messages have been taged';
        } else {
            $msg = 'ERRFailed to tag selected messages';
        }
        Hm_Msgs::add($msg);
    }
}