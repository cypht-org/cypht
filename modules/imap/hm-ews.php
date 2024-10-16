<?php

/**
 * EWS integration
 * @package modules
 * @subpackage imap
 *
 * This is a drop-in replacment of IMAP, JMAP and SMTP classes that allows usage of Exchange Web Services (EWS)
 * in all functions provided in imap and smtp modules - accessing mailbox, folders, reading messages,
 * attachments, moving, copying, read/unread, flags, sending messages.
 * Connection to EWS is handled by garethp/php-ews package handling NLTM auth and SOAP calls.
 */

use garethp\ews\API\Enumeration;
use garethp\ews\API\Exception;
use garethp\ews\API\ExchangeWebServices;
use garethp\ews\API\Type;
use garethp\ews\MailAPI;

/**
 * public interface to EWS mailboxes
 * @subpackage imap/lib
 */
class Hm_EWS {
    protected $ews;
    protected $api;
    protected $authed = false;

    public function connect(array $config) {
        try {
            $this->ews = ExchangeWebServices::fromUsernameAndPassword($config['server'], $config['username'], $config['password'], ['version' => ExchangeWebServices::VERSION_2016]);
            $this->api = new MailAPI($this->ews);
            $this->api->getFolderByDistinguishedId(Enumeration\DistinguishedFolderIdNameType::INBOX);
            $this->authed = true;
            return true;
        } catch (Exception\UnauthorizedException $e) {
            return false;
        }
    }

    public function authed() {
        return $this->authed;
    }

    public function get_folders($folder = null) {
        $result = [];
        if (empty($folder)) {
            $folder = new Type\DistinguishedFolderIdType(Enumeration\DistinguishedFolderIdNameType::MESSAGE_ROOT);
        } else {
            $folder = new Type\FolderIdType($folder);
        }
        $request = array(
            'Traversal' => 'Deep',
            'FolderShape' => array(
                'BaseShape' => 'AllProperties',
            ),
            'ParentFolderIds' => $folder->toArray(true)
        );
        $resp = $this->ews->FindFolder($request);
        $folders = $resp->get('folders')->get('folder');
        if ($folders) {
            foreach($folders as $folder) {
                $id = $folder->get('folderId')->get('id');
                $name = $folder->get('displayName');
                $result[$id] = array(
                    'id' => $id,
                    'parent' => null, // TODO
                    'delim' => false, // TODO - check, might be IMAP-specific
                    'name' => $name,
                    'name_parts' => [], // TODO - check, might be IMAP-specific
                    'basename' => $name,
                    'realname' => $name,
                    'namespace' => '', // TODO - check, might be IMAP-specific
                    // TODO - flags
                    'marked' => false, 
                    'noselect' => false,
                    'can_have_kids' => true,
                    'has_kids' => $folder->get('childFolderCount') > 0,
                    'special' => true,
                    'clickable' => true
                );
            }
        }
        return $result;
    }

    public function get_folder_status($folder) {
        try {
            $result = $this->api->getFolder((new Type\FolderIdType($folder))->toArray(true));
            return [
                'name' => $result->get('displayName'),
                'messages' => $result->get('totalCount'),
                'uidvalidity' => false,
                'uidnext' => false,
                'recent' => false,
                'unseen' => $result->get('unreadCount'),
            ];
        } catch (Exception $e) {
            return [];
        }
    }

    public function create_folder($folder, $parent = null) {
        if (empty($parent)) {
            $parent = Enumeration\DistinguishedFolderIdNameType::MESSAGE_ROOT;
        }
        try {
            return $this->api->createFolders([$folder], new Type\DistinguishedFolderIdType($parent));
        } catch(Exception $e) {
            Hm_Msgs::add('ERR' . $e->getMessage());
            return false;
        }
    }

    public function rename_folder($folder, $new_name, $parent = null) {
        $result = [];
        $new_folder = new Type\FolderType();
        $new_folder->displayName = $new_name;
        if ($parent) {
            $new_folder->parentFolderId = new Type\FolderIdType($parent);
        }
        $setFolderField = new Type\SetFolderFieldType();
        $setFolderField->folder = $new_folder;
        $fieldURI = new Type\FieldURI();
        $fieldURI->fieldURI = 'folder:displayName';
        $setFolderField->fieldURI = $fieldURI;
        $updates = new Type\NonEmptyArrayOfFolderChangeDescriptionsType();
        $updates->set('setFolderField', [$setFolderField]);
        $change = new Type\FolderChangeType();
        $change->folderId = new Type\FolderIdType($folder);
        $change->updates = $updates;
        $request = [
            'FolderChanges' => [
                $change
            ],
        ];
        try {
            $resp = $this->ews->UpdateFolder($request);
            // TODO: EWS: resolve internal server error issue and return status
            return true;
        } catch (Exception $e) {
            Hm_Msgs::add('ERR' . $e->getMessage());
            return false;
        }
    }

    public function delete_folder($folder) {
        try {
            return $this->api->deleteFolder(new Type\FolderIdType($folder));
        } catch(Exception $e) {
            Hm_Msgs::add('ERR' . $e->getMessage());
            return false;
        }
    }

    public function get_messages($folder, $sort, $reverse, $flag_filter, $offset, $limit, $keyword, $trusted_senders) {
        $folder = new Type\FolderIdType($folder);
        $request = array(
            'Traversal' => 'Shallow',
            'ItemShape' => array(
                'BaseShape' => 'IdOnly'
            ),
            'ParentFolderIds' => $folder->toArray(true)
        );
        // TODO: sort, pagination, search
        $request = Type::buildFromArray($request);
        $result = $this->ews->FindItem($request);
        $itemIds = array_map(function($msg) {
            return $msg->get('itemId')->get('id');
        }, $result->get('items')->get('message'));
        return [$result->get('totalItemsInView'), $this->get_message_list($itemIds)];
    }

    public function get_message_list($itemIds) {
        $request = array(
            'ItemShape' => array(
                'BaseShape' => 'AllProperties'
            ),
            'ItemIds' => [
                'ItemId' => array_map(function($id) {
                    return ['Id' => $id];
                }, $itemIds),
            ],
        );
        $request = Type::buildFromArray($request);
        $result = $this->ews->GetItem($request);
        $messages = [];
        foreach ($result as $message) {
            // TODO: EWS - check \Answered, \Flagged, \Deleted flags
            $flags = [];
            if ($message->get('isRead')) {
                $flags[] = '\\Seen';
            }
            if ($message->get('isDraft')) {
                $flags[] = '\\Draft';
            }
            $uid = bin2hex($message->get('itemId')->get('id'));
            $msg = [
                'uid' => $uid,
                'flags' => implode(' ', $flags),
                'internal_date' => $message->get('dateTimeCreated'),
                'size' => $message->get('size'),
                'date' => $message->get('dateTimeReceived'),
                'from' => $message->get('sender')->get('mailbox')->get('name') . ' <' . $message->get('from')->get('mailbox')->get('emailAddress') . '>',
                'to' => $message->get('toRecipients')->Mailbox->get('name') . ' <' . $message->get('toRecipients')->Mailbox->get('emailAddress') . '>',
                'subject' => $message->get('subject'),
                'content-type' => null,
                'timestamp' => time(),
                'charset' => null,
                'x-priority' => null,
                'google_msg_id' => null,
                'google_thread_id' => null,
                'google_labels' => null,
                'list_archive' => null,
                'references' => $message->get('references'),
                'message_id' => $message->get('internetMessageId'),
                'x_auto_bcc' => null,
                'x_snoozed'  => null,
            ];
            foreach ($message->get('internetMessageHeaders')->InternetMessageHeader as $header) {
                foreach (['x-gm-msgid' => 'google_msg_id', 'x-gm-thrid' => 'google_thread_id', 'x-gm-labels' => 'google_labels', 'x-auto-bcc' => 'x_auto_bcc', 'message-id' => 'message_id', 'references' => 'references', 'x-snoozed' => 'x_snoozed', 'list-archive' => 'list_archive', 'content-type' => 'content-type', 'x-priority' => 'x-priority'] as $hname => $key) {
                    if (strtolower($header->get('headerName')) == $hname) {
                        $msg[$key] = (string) $header;
                    }
                }
            }
            $cset = '';
            if (mb_stristr($msg['content-type'], 'charset=')) {
                if (preg_match("/charset\=([^\s;]+)/", $msg['content-type'], $matches)) {
                    $cset = trim(mb_strtolower(str_replace(array('"', "'"), '', $matches[1])));
                }
            }
            $msg['charset'] = $cset;
            $messages[$uid] = $msg;
        }
        return $messages;
    }
}
