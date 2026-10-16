<?php

/**
 * CLI script to validate policy cases against the schema and test references
 */

use Opis\JsonSchema\Validator;

if (mb_strtolower(php_sapi_name()) !== 'cli') {
    die("Must be run from the command line\n");
}

define('APP_PATH', dirname(dirname(__FILE__)).'/');
require APP_PATH.'lib/define_vendor_path.php';

$policy_path = APP_PATH.'/docs/policy-cases.json';
$schema_path = APP_PATH.'/docs/policy-cases.schema.json';

require_once VENDOR_PATH.'/autoload.php';

function load_json_file($path) {
    if (!is_readable($path)) {
        throw new RuntimeException('File is not readable: '.$path);
    }
    return json_decode(file_get_contents($path), false, 512, JSON_THROW_ON_ERROR);
}

function policy_test_exists($test_reference) {
    if (!preg_match('/^([A-Za-z0-9_]+)::([A-Za-z0-9_]+)$/', $test_reference, $matches)) {
        return false;
    }
    $needle = 'class '.$matches[1];
    $method = 'function '.$matches[2];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_PATH.'/tests'));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $contents = file_get_contents($file->getPathname());
        if (strpos($contents, $needle) !== false && strpos($contents, $method) !== false) {
            return true;
        }
    }
    return false;
}

try {
    $policy_data = load_json_file($policy_path);
    $schema = load_json_file($schema_path);
    $validator = new Validator(null, 20, false);
    $result = $validator->validate($policy_data, $schema);

    if (!$result->isValid()) {
        throw new RuntimeException('Schema validation failed: '.$result);
    }

    $policy_ids = array();
    foreach ($policy_data->policies as $policy) {
        if (isset($policy_ids[$policy->id])) {
            throw new RuntimeException('Duplicate policy ID: '.$policy->id);
        }
        $policy_ids[$policy->id] = true;

        $case_ids = array();
        foreach ($policy->cases as $case) {
            if (isset($case_ids[$case->id])) {
                throw new RuntimeException('Duplicate case ID in '.$policy->id.': '.$case->id);
            }
            $case_ids[$case->id] = true;
        }

        foreach ($policy->tests as $test_reference) {
            if (!policy_test_exists($test_reference)) {
                throw new RuntimeException('Policy test reference not found: '.$test_reference);
            }
        }
    }

    echo 'Policy cases are valid: '.count($policy_data->policies).' policies.'.PHP_EOL;
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'Policy cases are invalid: '.$error->getMessage().PHP_EOL);
    exit(1);
}
