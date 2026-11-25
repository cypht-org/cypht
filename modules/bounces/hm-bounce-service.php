<?php

/**
 * Bounce handling service
 * @package modules
 * @subpackage bounces
 */

/**
 * Records bounces once per bounce message, applies the escalation policy and
 * answers whether sending to an address should be suppressed.
 */
class Hm_Bounce_Service {

    private $store;
    private $rules;
    private $hardWeight;
    private $resetAfter;
    private $clock;

    /**
     * @param Hm_Bounce_Store $store
     * @param array $options 'rules' (string, see Hm_Bounce_Policy), 'hard_weight' (how much a hard bounce
     *                       counts for, default 1), 'reset_after' (seconds without a counted bounce after
     *                       which escalation restarts from the first step, default 0: never), 'clock'
     *                       (callable returning the current timestamp, default time())
     */
    public function __construct(Hm_Bounce_Store $store, array $options = []) {
        $this->store = $store;
        $this->rules = Hm_Bounce_Policy::rulesOrDefault($options['rules'] ?? '');
        $this->hardWeight = max(1, (int) ($options['hard_weight'] ?? 1));
        $this->resetAfter = max(0, (int) ($options['reset_after'] ?? 0));
        $this->clock = $options['clock'] ?? 'time';
    }

    public function getRules() {
        return $this->rules;
    }

    /**
     * @param string $email address that bounced
     * @param string $messageId identifier of the bounce message, see Hm_Bounce_Parser::messageId
     * @param string $type 'hard' or 'soft'
     * @return array 'recorded' (false when invalid or already recorded), 'action' (null, 'pause' or 'remove'),
     *               'address' (state afterwards)
     */
    public function record($email, $messageId, $type) {
        $email = self::normalize($email);
        $messageId = mb_substr(trim((string) $messageId), 0, 250);
        if ($email === '' || $messageId === '') {
            return ['recorded' => false, 'action' => null, 'address' => null];
        }
        if ($this->store->hasEvent($email, $messageId)) {
            return ['recorded' => false, 'action' => null, 'address' => $this->store->getAddress($email) ?: null];
        }

        $now = $this->now();
        $type = $type === Hm_Bounce_Policy::TYPE_HARD ? Hm_Bounce_Policy::TYPE_HARD : Hm_Bounce_Policy::TYPE_SOFT;
        $address = $this->store->getAddress($email) ?: Hm_Bounce_Policy::newAddress($email, $now);
        $address[$type === Hm_Bounce_Policy::TYPE_HARD ? 'hard_total' : 'soft_total']++;
        $address['last_bounce'] = $now;

        $weight = $type === Hm_Bounce_Policy::TYPE_HARD ? $this->hardWeight : 1;
        list($address, $counted, $action) = Hm_Bounce_Policy::transition($address, $weight, $now, $this->rules, $this->resetAfter);

        $this->store->addEvent([
            'email' => $email,
            'message_id' => $messageId,
            'type' => $type,
            'counted' => $counted,
            'received' => $now,
        ]);
        $this->store->saveAddress($address);

        return ['recorded' => true, 'action' => $action, 'address' => $address];
    }

    /**
     * Record events from Hm_Bounce_Parser
     * @return array one result of record() per event, with the event under 'event'
     */
    public function recordEvents(array $events) {
        $results = [];
        foreach ($events as $event) {
            $result = $this->record($event['email'], $event['message_id'], $event['type']);
            $result['event'] = $event;
            $results[] = $result;
        }
        return $results;
    }

    public function isSuppressed($email) {
        $address = $this->store->getAddress(self::normalize($email));
        if (!$address) {
            return false;
        }
        return Hm_Bounce_Policy::isSuppressed($address, $this->now());
    }

    /**
     * @return array [array $allowed, array $suppressed]
     */
    public function filterRecipients(array $emails) {
        $allowed = [];
        $suppressed = [];
        foreach ($emails as $email) {
            if ($this->isSuppressed($email)) {
                $suppressed[] = $email;
            } else {
                $allowed[] = $email;
            }
        }
        return [$allowed, $suppressed];
    }

    public function getAddress($email) {
        return $this->store->getAddress(self::normalize($email));
    }

    public function listAddresses($status = '') {
        return $this->store->listAddresses($status);
    }

    /**
     * Forget the escalation state: sending resumes and counting restarts from the first step
     * @return bool false when the address is unknown
     */
    public function reset($email) {
        $address = $this->getAddress($email);
        if (!$address) {
            return false;
        }
        $address['status'] = Hm_Bounce_Policy::STATUS_ACTIVE;
        $address['level'] = 0;
        $address['level_score'] = 0;
        $address['last_counted'] = null;
        $address['paused_until'] = null;
        $address['removed_date'] = null;
        $this->store->saveAddress($address);
        return true;
    }

    /**
     * @param int $until timestamp at which sending resumes
     */
    public function pause($email, $until) {
        $email = self::normalize($email);
        $address = $this->store->getAddress($email) ?: Hm_Bounce_Policy::newAddress($email, $this->now());
        $address['status'] = Hm_Bounce_Policy::STATUS_PAUSED;
        $address['paused_until'] = (int) $until;
        $address['removed_date'] = null;
        $this->store->saveAddress($address);
    }

    public function remove($email) {
        $email = self::normalize($email);
        $now = $this->now();
        $address = $this->store->getAddress($email) ?: Hm_Bounce_Policy::newAddress($email, $now);
        $address['status'] = Hm_Bounce_Policy::STATUS_REMOVED;
        $address['level_score'] = 0;
        $address['paused_until'] = null;
        $address['removed_date'] = $now;
        $this->store->saveAddress($address);
    }

    public function delete($email) {
        $this->store->deleteAddress(self::normalize($email));
    }

    private function now() {
        return (int) call_user_func($this->clock);
    }

    private static function normalize($email) {
        return mb_strtolower(trim((string) $email));
    }
}
