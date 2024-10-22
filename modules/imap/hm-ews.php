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

use ZBateson\MailMimeParser\MailMimeParser;

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

    public function get_folders($folder = null, $only_subscribed = false, $unsubscribed_folders = []) {
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
            $special = $this->get_special_use_folders();
            foreach($folders as $folder) {
                $id = $folder->get('folderId')->get('id');
                $name = $folder->get('displayName');
                if ($only_subscribed && in_array($id, $unsubscribed_folders)) {
                    continue;
                }
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
                    'special' => in_array($id, $special),
                    'clickable' => true,
                    'subscribed' => ! in_array($id, $unsubscribed_folders),
                );
            }
        }
        return $result;
    }

    public function get_special_use_folders($folder = false) {
        $special = [
            'trash' => Enumeration\DistinguishedFolderIdNameType::DELETED,
            'sent' => Enumeration\DistinguishedFolderIdNameType::SENT,
            'flagged' => false,
            'all' => false,
            'junk' => Enumeration\DistinguishedFolderIdNameType::JUNK,
            'archive' => false, // TODO: check if Enumeration\DistinguishedFolderIdNameType::ARCHIVEMSGFOLDERROOT should be used - it is outside of MESSAGE_ROOT, however.
            'drafts' => Enumeration\DistinguishedFolderIdNameType::DRAFTS,
        ];
        foreach ($special as $type => $folderId) {
            if ($folderId) {
                $distinguishedFolder = $this->api->getFolderByDistinguishedId($folderId);
                if ($distinguishedFolder) {
                    $special[$type] = $distinguishedFolder->get('folderId')->get('id');
                }
            }
        }
        $special = array_filter($special);
        if (isset($special[$folder])) {
            return [$folder => $special[$folder]];
        } else {
            return $special;
        }
    }

    public function get_folder_status($folder) {
        try {
            if ($this->is_distinguished_folder($folder)) {
                $folder = new Type\DistinguishedFolderIdType($folder);
            } else {
                $folder = new Type\FolderIdType($folder);
            }
            $result = $this->api->getFolder($folder->toArray(true));
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
                'to' => $this->extract_mailbox($message->get('toRecipients')),
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

    public function get_message_headers($itemId) {
        $request = array(
            'ItemShape' => array(
                'BaseShape' => 'AllProperties',
            ),
            'ItemIds' => [
                'ItemId' => ['Id' => hex2bin($itemId)],
            ],
        );
        $request = Type::buildFromArray($request);
        $message = $this->ews->GetItem($request);
        $headers = [];
        $headers['Arrival Date'] = $message->get('dateTimeCreated');
        $headers['From'] = $message->get('sender')->get('mailbox')->get('name') . ' <' . $message->get('from')->get('mailbox')->get('emailAddress') . '>';
        $headers['To'] = $this->extract_mailbox($message->get('toRecipients'));
        if ($message->get('ccRecipients')) {
            $headers['Cc'] = $this->extract_mailbox($message->get('ccRecipients'));
        }
        if ($message->get('bccRecipients')) {
            $headers['Bcc'] = $this->extract_mailbox($message->get('bccRecipients'));
        }
        foreach ($message->get('internetMessageHeaders')->InternetMessageHeader as $header) {
            $name = $header->get('headerName');
            if (isset($headers[$name])) {
                if (! is_array($headers[$name])) {
                    $headers[$name] = [$headers[$name]];
                }
                $headers[$name][] = (string) $header;
            } else {
                $headers[$name] = (string) $header;
            }
        }
        return $headers;
    }

    public function get_message_content($itemId, $part) {
        if ($part) {
            list($msg_struct, $msg_struct_current, $msg_text, $part) = $this->get_structured_message($itemId, $part, false);
            return $msg_text;
        } else {
            $message = $this->get_mime_message_by_id($itemId);
            $content = $message->getHtmlContent();
            if (empty($content)) {
                $content = $message->getTextContent();
            }
            return $content;
        }
    }

    public function stream_message_part($itemId, $part, $start_cb) {
        if ($part !== '0' && $part) {
            // imap handler modules strip this prefix
            $part = '0.' . $part;
        }
        list($msg_struct, $part_struct, $msg_text, $part) = $this->get_structured_message($itemId, $part, false);
        $charset = '';
        if (! empty($part_struct['attributes']['charset'])) {
            $charset = '; charset=' . $part_struct['attributes']['charset'];
        }
        $part_name = get_imap_part_name($part_struct, $itemId, $part);
        $start_cb($part_struct['type'] . '/' . $part_struct['subtype'] . $charset, $part_name);
        if (! $charset) {
            $charset = 'UTF-8';
        } else {
            $charset = $part_struct['attributes']['charset'];
        }
        $stream = $part_struct['mime_object']->getContentStream($charset);
        if ($stream) {
            while (! $stream->eof()) {
                echo $stream->read(1024);
            }
        }
    }

    public function get_structured_message($itemId, $part, $text_only) {
        $message = $this->get_mime_message_by_id($itemId);
        $msg_struct = [];
        $this->parse_mime_part($message, $msg_struct, 0);
        if ($part !== false) {
            $struct = $this->search_mime_part_in_struct($msg_struct, ['part_id' => $part], true);
        } else {
            $struct = null;
            if (! $text_only) {
                $struct = $this->search_mime_part_in_struct($msg_struct, ['type' => 'text', 'subtype' => 'html']);
            }
            if (! $struct) {
                $struct = $this->search_mime_part_in_struct($msg_struct, ['type' => 'text']);
            }
        }
        if ($struct) {
            $part = array_key_first($struct);
            $msg_struct_current = $struct[$part];
            $msg_text = $msg_struct_current['mime_object']->getContent();
        } else {
            $part = false;
            $msg_struct_current = null;
            $msg_text = '';
        }
        if (isset($msg_struct_current['subtype']) && mb_strtolower($msg_struct_current['subtype'] == 'html')) {
            // add inline images
            if (preg_match_all("/src=('|\"|)cid:([^\s'\"]+)/", $msg_text, $matches)) {
                $cids = array_pop($matches);
                foreach ($cids as $id) {
                    $struct = $this->search_mime_part_in_struct($msg_struct, ['id' => $id, 'type' => 'image']);
                    if ($struct) {
                        $struct = array_shift($struct);
                        $msg_text = str_replace('cid:'.$id, 'data:image/'.$struct['subtype'].';base64,'.base64_encode($struct['mime_object']->getContent()), $msg_text);
                    }
                }
            }
        }
        return [$msg_struct, $msg_struct_current, $msg_text, $part];
    }

    protected function parse_mime_part($part, &$struct, $part_num) {
        $struct[$part_num] = [];
        list($struct[$part_num]['type'], $struct[$part_num]['subtype']) = explode('/', $part->getContentType());
        if ($part->isMultiPart()) {
            $boundary = $part->getHeaderParameter('Content-Type', 'boundary');
            if ($boundary) {
                $struct[$part_num]['attributes'] = ['boundary' => $boundary];
            }
            $struct[$part_num]['disposition'] = $part->getContentDisposition();
            $struct[$part_num]['language'] = '';
            $struct[$part_num]['location'] = '';
        } else {
            $content = $part->getContent();
            $charset = $part->getCharset();
            if ($charset) {
                $struct[$part_num]['attributes'] = ['charset' => $charset];
            }
            $struct[$part_num]['id'] = $part->getContentId();
            $struct[$part_num]['description'] = $part->getHeaderValue('Content-Description');
            $struct[$part_num]['encoding'] = $part->getContentTransferEncoding();
            $struct[$part_num]['size'] = strlen($content);
            $struct[$part_num]['lines'] = substr_count($content, "\n");
            $struct[$part_num]['md5'] = '';
            $struct[$part_num]['disposition'] = $part->getContentDisposition();
            $struct[$part_num]['file_attributes'] = '';
            $struct[$part_num]['language'] = '';
            $struct[$part_num]['location'] = '';
        }
        $struct[$part_num]['mime_object'] = $part;
        if ($part->getChildCount() > 0) {
            $struct[$part_num]['subs'] = [];
            foreach ($part->getChildParts() as $i => $child) {
                $this->parse_mime_part($child, $struct[$part_num]['subs'], $part_num . '.' . ($i+1));
            }
        }
    }

    protected function search_mime_part_in_struct($struct, $conditions, $all = false) {
        $found = [];
        foreach ($struct as $part_id => $sub) {
            $matches = 0;
            if (isset($conditions['part_id']) && $part_id == $conditions['part_id']) {
                $matches++;
            }
            foreach ($conditions as $name => $value) {
                if (isset($sub[$name]) && mb_stristr($sub[$name], $value)) {
                    $matches++;
                }
            }
            if ($matches === count($conditions)) {
                $part = $sub;
                if (isset($part['subs'])) {
                    $part['subs'] = count($part['subs']);
                }
                $found[$part_id] = $part;
                if (! $all) {
                    break;
                }
            }
            if (isset($sub['subs'])) {
                $found = array_merge($found, $this->search_mime_part_in_struct($sub['subs'], $conditions, $all));
            }
            if (! $all && $found) {
                break;
            }
        }
        return $found;
    }

    protected function get_mime_message_by_id($itemId) {
        $request = array(
            'ItemShape' => array(
                'BaseShape' => 'IdOnly',
                'IncludeMimeContent' => true,
            ),
            'ItemIds' => [
                'ItemId' => ['Id' => hex2bin($itemId)],
            ],
        );
        $request = Type::buildFromArray($request);
        $message = $this->ews->GetItem($request);
        $mime = $message->get('mimeContent');
        $content = base64_decode($mime);
        if (strtoupper($mime->get('characterSet')) != 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $mime->get('characterSet'));
        }
        $parser = new MailMimeParser();
        return $parser->parse($content, false);
    }

    protected function extract_mailbox($data) {
        if (is_array($data)) {
            $result = [];
            foreach ($data as $mailbox) {
                $result[] = $this->extract_mailbox($mailbox);
            }
            return $result;
        } elseif (is_object($data)) {
            return $data->Mailbox->get('name') . ' <' . $data->Mailbox->get('emailAddress') . '>';
        } else {
            return (string) $data;
        }
    }

    protected function is_distinguished_folder($folder) {
        $oClass = new ReflectionClass(new Enumeration\DistinguishedFolderIdNameType());
        $constants = $oClass->getConstants();
        return in_array($folder, $constants);
    }
}
