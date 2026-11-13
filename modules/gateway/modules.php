<?php

if (!defined('DEBUG_MODE')) { die(); }

require_once APP_PATH.'modules/gateway/functions.php';
require_once APP_PATH.'modules/gateway/handler_modules.php';
require_once APP_PATH.'modules/gateway/contacts.php';
require_once APP_PATH.'modules/gateway/contact_handlers.php';
require_once APP_PATH.'modules/gateway/tags.php';
require_once APP_PATH.'modules/gateway/tag_handlers.php';
require_once APP_PATH.'modules/gateway/saved_search_handlers.php';
require_once APP_PATH.'modules/gateway/calendar_handlers.php';
require_once APP_PATH.'modules/gateway/sieve_handlers.php';
require_once APP_PATH.'modules/gateway/feed_handlers.php';
require_once APP_PATH.'modules/gateway/output_modules.php';
