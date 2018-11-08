<?php

/**
 * Carddav contact modules
 * @package modules
 * @subpackage carddav_contacts
 */

if (!defined('DEBUG_MODE')) { die(); }

require APP_PATH.'modules/carddav_contacts/hm-carddav.php';

/**
 * @subpackage carddav_contacts/handler
 */
class Hm_Handler_load_carddav_contacts extends Hm_Handler_Module {
    public function process() {

        $user = 'testuser';
        $pass = 'testpass';

        $contacts = $this->get('contact_store');
        $details = get_ini($this->config, 'carddav.ini', true);

        foreach ($details as $name => $vals) {
            $carddav = new Hm_Carddav($name, $vals['server'], $user, $pass);
            $contacts->import($carddav->addresses);
            $this->append('contact_sources', 'carddav');
        }
        $this->out('contact_store', $contacts, false);
    }
}
