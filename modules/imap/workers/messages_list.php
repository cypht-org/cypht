<?php

if (mb_strtolower(php_sapi_name()) !== 'cli') {
    die("Must be run from the command line\n");
}

$options = getopt('p:i:c:s:', ['app_path:', 'imports:', 'cache_id:', 'site_id:']);

$appPath = $options['app_path'] ?? $options['p'] ?? '';
$imports = $options['imports'] ?? $options['i'] ?? '';
$cacheId = $options['cache_id'] ?? $options['c'] ?? '';
$siteId = $options['site_id'] ?? $options['s'] ?? '';

define('APP_PATH', $appPath);
define('CACHE_ID', $cacheId);
define('SITE_ID', $siteId);

require $appPath . 'lib/framework.php';

require $appPath . 'modules/core/message_functions.php';
require $appPath . 'modules/core/message_list_functions.php';

require $appPath . 'modules/imap/hm-imap.php';
require $appPath . 'modules/core/hm-mailbox.php';
require $appPath . 'modules/imap/helpers.php';

if ($imports) {
    $imports = explode(',', $imports);
    foreach ($imports as $import) {
        require $import;
    }
}

$input = trim(fgets(STDIN));
$data = json_decode($input, true);

$index = $data['index'];
$search = $data['search'];
$dataSource = $data['dataSource'];
$cache = unserialize($data['cache']);
$session = unserialize($data['session']);
$user_config = unserialize($data['config']);

$filter = $search['filter'];
$sort = $search['sort'];
$reverse = $search['reverse'];
$searchTerms = $search['terms'];
$limit = $search['limit'];
$offsets = $search['offsets'];
$listPage = $search['listPage'];

$offset = $search['defaultOffset'];
if ($offsets && $listPage > 1) {
    if (isset($offsets[$index]) && (int) $offsets[$index] > 0) {
        $offset = (int) $offsets[$index] * ($listPage - 1);
    }
}

Hm_IMAP_List::init($user_config, $session);
$mailbox = Hm_IMAP_List::get_connected_mailbox($dataSource['id'], $cache);
if ($mailbox && $mailbox->authed()) {
    $search['offset'] = $offset;
    $result = getMessagesList($mailbox, $dataSource, $search);

    echo json_encode([
        'uids' => $result['uids'],
        'status' => $result['status'],
        'messages' => $result['messages'],
        'total' => $result['total'],
        'dataSource' => $result['dataSource'],
        'folder' => $result['folder']
    ]);
} else {
    echo json_encode(['error' => 'Failed to establish connection to the mailbox.']);
    exit;
}
