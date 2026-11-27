<?php

/**
 * Bounce storage
 * @package modules
 * @subpackage bounces
 */

/**
 * Keeps the state of bouncing addresses (see Hm_Bounce_Policy::newAddress)
 * and the bounce messages already seen. A host application can provide its
 * own implementation with the "bounce_store_class" site config value.
 */
interface Hm_Bounce_Store {

    /**
     * @return array|false state of the address, false when unknown
     */
    public function getAddress($email);

    public function saveAddress(array $address);

    public function deleteAddress($email);

    /**
     * @param string $status only addresses with this status, empty for all
     * @return array
     */
    public function listAddresses($status = '');

    /**
     * @return bool true when this bounce message was already recorded for the address
     */
    public function hasEvent($email, $messageId);

    /**
     * @param array $event ['email', 'message_id', 'type', 'counted', 'received']
     */
    public function addEvent(array $event);
}

/**
 * Store keeping everything in the user config
 */
class Hm_Bounce_Store_User_Config implements Hm_Bounce_Store {

    const ADDRESSES_SETTING = 'bounce_addresses';
    const EVENTS_SETTING = 'bounce_events';

    /* most recent bounce messages remembered per address */
    const MAX_EVENTS_PER_ADDRESS = 100;

    private $userConfig;

    public function __construct($userConfig) {
        $this->userConfig = $userConfig;
    }

    public function getAddress($email) {
        $addresses = $this->setting(self::ADDRESSES_SETTING);
        $key = self::key($email);
        return array_key_exists($key, $addresses) ? $addresses[$key] : false;
    }

    public function saveAddress(array $address) {
        $addresses = $this->setting(self::ADDRESSES_SETTING);
        $addresses[self::key($address['email'])] = $address;
        $this->userConfig->set(self::ADDRESSES_SETTING, $addresses);
    }

    public function deleteAddress($email) {
        $key = self::key($email);
        $addresses = $this->setting(self::ADDRESSES_SETTING);
        unset($addresses[$key]);
        $this->userConfig->set(self::ADDRESSES_SETTING, $addresses);
        $events = $this->setting(self::EVENTS_SETTING);
        unset($events[$key]);
        $this->userConfig->set(self::EVENTS_SETTING, $events);
    }

    public function listAddresses($status = '') {
        $addresses = array_values($this->setting(self::ADDRESSES_SETTING));
        if ($status === '') {
            return $addresses;
        }
        return array_values(array_filter($addresses, function($address) use ($status) {
            return $address['status'] === $status;
        }));
    }

    public function hasEvent($email, $messageId) {
        $events = $this->setting(self::EVENTS_SETTING);
        $key = self::key($email);
        if (!array_key_exists($key, $events)) {
            return false;
        }
        return in_array($messageId, array_column($events[$key], 'message_id'), true);
    }

    public function addEvent(array $event) {
        $events = $this->setting(self::EVENTS_SETTING);
        $key = self::key($event['email']);
        $events[$key][] = $event;
        $events[$key] = array_slice($events[$key], -self::MAX_EVENTS_PER_ADDRESS);
        $this->userConfig->set(self::EVENTS_SETTING, $events);
    }

    private function setting($name) {
        $value = $this->userConfig->get($name, []);
        return is_array($value) ? $value : [];
    }

    private static function key($email) {
        return mb_strtolower(trim((string) $email));
    }
}
