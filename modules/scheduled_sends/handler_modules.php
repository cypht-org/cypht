<?php

class Hm_Handler_load_scheduled_sends_sources extends Hm_Handler_Module {
    public function process()
    {
        if ($this->request->get['list_path'] !=  'scheduled') {
            return;
        }

        $this->out('data_sources', imap_sources($this, 'scheduled'));

    }
}
