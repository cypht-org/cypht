<?php

/**
 * Bounce escalation policy
 * @package modules
 * @subpackage bounces
 */

/**
 * Decides what happens to an address after a bounce: nothing, a sending pause
 * or removal. Rules are steps "count:duration" (h, d or w), optionally ending
 * with "count:remove". The default "10:1d, 5:7d, 5:14d, 5:remove" pauses one
 * day after 10 bounces, a week after 5 more, two weeks after 5 more, then
 * removes after 5 more. The last step repeats while the address keeps bouncing.
 */
class Hm_Bounce_Policy {

    const TYPE_HARD = 'hard';
    const TYPE_SOFT = 'soft';

    const STATUS_ACTIVE = 'active';
    const STATUS_PAUSED = 'paused';
    const STATUS_REMOVED = 'removed';

    const ACTION_PAUSE = 'pause';
    const ACTION_REMOVE = 'remove';

    const DEFAULT_RULES = '10:1d, 5:7d, 5:14d, 5:remove';

    const DURATION_UNITS = ['h' => 3600, 'd' => 86400, 'w' => 604800];

    /**
     * @param string $rules
     * @return array steps ['threshold' => int, 'action' => 'pause'|'remove', 'duration' => seconds], empty when invalid
     */
    public static function parseRules($rules) {
        $steps = [];
        foreach (preg_split('/[\s,;]+/', trim((string) $rules), -1, PREG_SPLIT_NO_EMPTY) as $rule) {
            if (!empty($steps) && end($steps)['action'] === self::ACTION_REMOVE) {
                return [];
            }
            if (!preg_match('/^(\d+):(?:(\d+)([hdw])|(remove))$/i', $rule, $matches) || (int) $matches[1] < 1) {
                return [];
            }
            if (!empty($matches[4])) {
                $steps[] = ['threshold' => (int) $matches[1], 'action' => self::ACTION_REMOVE, 'duration' => 0];
            } else {
                $unit = self::DURATION_UNITS[mb_strtolower($matches[3])];
                $steps[] = ['threshold' => (int) $matches[1], 'action' => self::ACTION_PAUSE, 'duration' => (int) $matches[2] * $unit];
            }
        }
        return $steps;
    }

    public static function rulesOrDefault($rules) {
        $steps = self::parseRules($rules);
        if (empty($steps)) {
            $steps = self::parseRules(self::DEFAULT_RULES);
        }
        return $steps;
    }

    public static function newAddress($email, $now) {
        return [
            'email' => $email,
            'status' => self::STATUS_ACTIVE,
            'level' => 0,
            'level_score' => 0,
            'soft_total' => 0,
            'hard_total' => 0,
            'first_bounce' => $now,
            'last_bounce' => $now,
            'last_counted' => null,
            'paused_until' => null,
            'removed_date' => null,
        ];
    }

    /**
     * New state of an address after a bounce, without side effects
     * @param array $address current state
     * @param int $weight how much this bounce counts for
     * @param int $now current timestamp
     * @param array $rules steps from parseRules
     * @param int $resetAfter seconds without a counted bounce after which escalation restarts from the first step, 0 to never reset
     * @return array [array $address, bool $counted, string|null $action]
     */
    public static function transition(array $address, $weight, $now, array $rules, $resetAfter = 0) {
        if ($address['status'] === self::STATUS_REMOVED) {
            return [$address, false, null];
        }
        if ($address['status'] === self::STATUS_PAUSED) {
            if ((int) $address['paused_until'] > $now) {
                // bounce of a message sent before the pause: sending is already stopped
                return [$address, false, null];
            }
            $address['status'] = self::STATUS_ACTIVE;
            $address['paused_until'] = null;
        }

        if ($resetAfter > 0 && $address['last_counted'] && $address['last_counted'] < $now - $resetAfter) {
            $address['level'] = 0;
            $address['level_score'] = 0;
        }

        $address['level_score'] += max(1, (int) $weight);
        $address['last_counted'] = $now;

        $step = $rules[min((int) $address['level'], count($rules) - 1)];
        if ($address['level_score'] < $step['threshold']) {
            return [$address, true, null];
        }

        $address['level_score'] = 0;
        if ($step['action'] === self::ACTION_REMOVE) {
            $address['status'] = self::STATUS_REMOVED;
            $address['removed_date'] = $now;
            return [$address, true, self::ACTION_REMOVE];
        }

        $address['status'] = self::STATUS_PAUSED;
        $address['paused_until'] = $now + $step['duration'];
        $address['level'] = min((int) $address['level'] + 1, count($rules) - 1);
        return [$address, true, self::ACTION_PAUSE];
    }

    public static function isSuppressed(array $address, $now) {
        if ($address['status'] === self::STATUS_REMOVED) {
            return true;
        }
        return $address['status'] === self::STATUS_PAUSED && (int) $address['paused_until'] > $now;
    }
}
