<?php

function getMessagesList($mailbox, $dataSource, $search) {
    $sort = $search['sort'];
    $reverse = $search['reverse'];
    $filter = $search['filter'];
    $searchTerms = $search['terms'];
    $limit = $search['limit'];
    $offset = $search['offset'];
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

    return [
        'uids' => $uids,
        'status' => $state,
        'messages' => $messages,
        'total' => $total,
        'dataSource' => $dataSource,
        'folder' => $folder
    ];
}
