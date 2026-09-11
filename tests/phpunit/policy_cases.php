<?php

class PolicyCases {
    private static $data;

    public static function forPolicy($policy_id) {
        $policy = self::policy($policy_id);
        $cases = array();
        foreach ($policy['cases'] as $case) {
            $cases[$case['id']] = array($case);
        }
        return $cases;
    }

    public static function policy($policy_id) {
        foreach (self::load()['policies'] as $policy) {
            if ($policy['id'] === $policy_id) {
                return $policy;
            }
        }
        throw new RuntimeException('Unknown policy case set: '.$policy_id);
    }

    private static function load() {
        if (self::$data === null) {
            self::$data = json_decode(
                file_get_contents(APP_PATH.'docs/policy-cases.json'),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        }
        return self::$data;
    }
}