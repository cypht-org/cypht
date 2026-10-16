<?php

/**
 * Define the vendor path for Cypht
 * 
 * This script must be included only after APP_PATH has been defined, and before any other scripts that require the vendor path.
 * 
 * @package framework
 * @subpackage setup
 */

if (defined('VENDOR_PATH') && file_exists(VENDOR_PATH)) {
    // The vendor path may have already been defined in third-party code that integrates Cypht.
    // If so, we pass.
} elseif (file_exists(APP_PATH.'vendor/')) {
    define('VENDOR_PATH', APP_PATH.'vendor/');
} else {
    // When installed via composer, the vendor path is generally two levels up (jason-munro/cypht) from APP_PATH.
    define('VENDOR_PATH', APP_PATH . '../../');
}