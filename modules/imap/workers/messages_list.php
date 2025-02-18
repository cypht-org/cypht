<?php
const APP_PATH = '';

require 'lib/framework.php';

require 'modules/core/message_functions.php';
require 'modules/core/message_list_functions.php';

require 'modules/imap/hm-imap.php';
require 'modules/core/hm-mailbox.php';

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
    $connection = $mailbox->get_connection();
    $folder = $dataSource['folder'];
    $mailbox->select_folder(hex2bin($folder));
    $state = $connection->get_mailbox_status(hex2bin($folder));

    if ($mailbox->is_imap()) {
        if ($connection->is_supported('SORT')) {
            $sortedUids = $connection->get_message_sort_order($sort, $reverse, $filter);
        } else {
            $sortedUids = $connection->sort_by_fetch($sort, $reverse, $filter);
        }

        $uids = $mailbox->search(hex2bin($folder), $filter, $sortedUids, $searchTerms);
    } else {
        // EWS
        $uids = $connection->search($folder, $sort, $reverse, $filter, 0, $limit, $searchTerms);
    }

    $total = count($uids);
    $uids = array_slice($uids, $offset, $limit);

    $headers = $mailbox->get_message_list(hex2bin($folder), $uids);
    $messages = [];
    foreach ($uids as $uid) {
        if (isset($headers[$uid])) {
            $messages[] = $headers[$uid];
        }
    }

    echo json_encode([
        'uids' => $uids,
        'status' => $state,
        'messages' => $messages,
        'total' => $total,
        'dataSource' => $dataSource,
        'folder' => $folder
    ]);
} else {
    exit;
}
