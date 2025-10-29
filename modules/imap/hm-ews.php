<?php

/**
 * EWS integration
 * @package modules
 * @subpackage core
 *
 * This is a drop-in replacment of IMAP, JMAP and SMTP classes that allows usage of Exchange Web Services (EWS)
 * in all functions provided in imap and smtp modules - accessing mailbox, folders, reading messages,
 * attachments, moving, copying, read/unread, flags, sending messages.
 * Connection to EWS is handled by garethp/php-ews package handling NLTM auth and SOAP calls.
 */

use garethp\ews\API\Enumeration;
use garethp\ews\API\Enumeration\DistinguishedFolderIdNameType;
use garethp\ews\API\Exception;
use garethp\ews\API\ExchangeWebServices;
use garethp\ews\API\ItemUpdateBuilder;
use garethp\ews\API\Type;
use garethp\ews\MailAPI;
use garethp\ews\Utilities;

use ZBateson\MailMimeParser\MailMimeParser;

/**
 * public interface to EWS mailboxes
 * @subpackage imap/lib
 */
class Hm_EWS {
    protected $ews;
    protected $api;
    protected $authed = false;

    // Extended property tags and their values defined in MS-OXOFLAG, MS-OXPROPS, MS-OXOMSG, MS-OXCMSG specs
    const PID_TAG_FLAG_STATUS = 0x1090;
    const PID_TAG_FLAG_FLAGGED = 0x00000002;
    const PID_TAG_ICON_INDEX = 0x1080;
    const PID_TAG_ICON_REPLIED = 0x00000105;
    const PID_TAG_MESSAGE_FLAGS = 0x0E07;
    const PID_TAG_MESSAGE_READ = 0x00000001;
    const PID_TAG_MESSAGE_DRAFT = 0x00000008;

    public function connect(array $config) {
        try {
            $this->ews = ExchangeWebServices::fromUsernameAndPassword($config['server'], $config['username'], $config['password'], ['version' => ExchangeWebServices::VERSION_2016, 'trace' => 1]);
            $this->api = new MailAPI($this->ews);
            $this->api->getFolderByDistinguishedId(Enumeration\DistinguishedFolderIdNameType::INBOX);
            $this->authed = true;
            return true;
        } catch (Exception\UnauthorizedException | \SoapFault $e) {
            return false;
        }
    }

    public function authed() {
        return $this->authed;
    }

    public function get_capability() {
        // IMAP extra capabilities not supported here
        return '';
    }

    public function get_folders($folder = null, $only_subscribed = false, $unsubscribed_folders = [], $with_input = false) {
        $result = [];
        if (empty($folder)) {
            $folder = new Type\DistinguishedFolderIdType(Enumeration\DistinguishedFolderIdNameType::MESSAGE_ROOT);
        } else {
            $folder = new Type\FolderIdType($folder);
        }
        $request = array(
            'Traversal' => 'Shallow',
            'FolderShape' => array(
                'BaseShape' => 'AllProperties',
            ),
            'ParentFolderIds' => $folder->toArray(true)
        );
        $resp = $this->ews->FindFolder($request);
        $folders = $resp->getFolders()->getFolder();
        if ($folders) {
            $special = $this->get_special_use_folders();
            if ($folders instanceof Type\FolderType) {
                $folders = [$folders];
            }
            foreach($folders as $folder) {
                $id = $folder->getFolderId()->getId();
                $parentId = $folder->getParentFolderId()->getId();
                $name = $folder->getDisplayName();
                if ($only_subscribed && in_array($id, $unsubscribed_folders)) {
                    continue;
                }
                $result[$id] = array(
                    'id' => $id,
                    'parent' => $parentId,
                    'delim' => false,
                    'name' => $name,
                    'name_parts' => [],
                    'basename' => $name,
                    'realname' => $name,
                    'namespace' => '',
                    'marked' => false, // doesn't seem to be used anywhere but imap returns it
                    'noselect' => false, // all EWS folders are selectable
                    'can_have_kids' => true,
                    'has_kids' => $folder->getChildFolderCount() > 0,
                    'children' => $folder->getChildFolderCount(),
                    'special' => in_array($id, $special),
                    'clickable' => ! $with_input && ! in_array($id, $unsubscribed_folders),
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
            'junk' => Enumeration\DistinguishedFolderIdNameType::JUNKEMAIL,
            'archive' => false,
            'drafts' => Enumeration\DistinguishedFolderIdNameType::DRAFTS,
        ];
        foreach ($special as $type => $folderId) {
            if ($folderId) {
                try {
                    $distinguishedFolder = $this->api->getFolderByDistinguishedId($folderId);
                    if ($distinguishedFolder) {
                        $special[$type] = $distinguishedFolder->getFolderId()->getId();
                    }
                } catch (\Exception $e) {
                    Hm_Msgs::add($e->getMessage(), 'danger');
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

    public function get_folder_name_quick($folder) {
        if ($this->is_distinguished_folder($folder)) {
            return $folder;
        } else {
            return false;
        }
    }

    public function get_folder_status($folder, $report_error = true) {
        try {
            if ($this->is_distinguished_folder($folder)) {
                $folderObj = new Type\DistinguishedFolderIdType($folder);
                $result = $this->api->getFolder($folderObj->toArray(true));
            } else {
                $folderObj = new Type\FolderIdType($folder);
                $result = $this->api->getFolder($folderObj->toArray(true));
            }
            return [
                'id' => $result->getFolderId()->getId(),
                'name' => $result->getDisplayName(),
                'messages' => $result->getTotalCount(),
                'uidvalidity' => false,
                'uidnext' => false,
                'recent' => false,
                'unseen' => $result->getUnreadCount(),
            ];
        } catch (Exception\ExchangeException $e) {
            // since this is used for missing folders check, we skip error reporting
            return [];
        } catch (\Exception $e) {
            if ($report_error && $e->getMessage()) {
                Hm_Msgs::add($e->getMessage(), 'danger');
            }
            return [];
        }
    }

    public function create_folder($folder, $parent = null) {
        if (empty($parent)) {
            $parent = new Type\DistinguishedFolderIdType(Enumeration\DistinguishedFolderIdNameType::MESSAGE_ROOT);
        } else {
            $parent = new Type\FolderIdType($parent);
        }
        try {
            $request = [
                'Folders' => ['Folder' => [
                    'DisplayName' => $folder
                ]],
                'ParentFolderId' => $parent->toArray(true),
            ];
            $result = $this->ews->CreateFolder($request);
            return $result->getId();
        } catch(\Exception $e) {
            Hm_Msgs::add($e->getMessage(), 'danger');
            return false;
        }
    }

    public function rename_folder($folder, $new_name, $parent = null) {
        $result = [];
        if ($this->is_distinguished_folder($folder)) {
            $folderObj = new Type\DistinguishedFolderIdType($folder);
        } else {
            $folderObj = new Type\FolderIdType(hex2bin($folder));
        }
        $new_folder = new Type\FolderType();
        $new_folder->displayName = $new_name;
        $request = [
            'FolderChanges' => [
                'FolderChange' => [
                    'FolderId' => $folderObj->toArray(false),
                    'Updates' => [
                        'SetFolderField' => [
                            'FieldURI' => [
                                'FieldURI' => 'folder:DisplayName',
                            ],
                            'Folder' => $new_folder,
                        ],
                    ],
                ],
            ],
        ];
        try {
            $request = Type::buildFromArray($request);
            $this->ews->UpdateFolder($request);
        } catch (\Exception $e) {
            Hm_Msgs::add($e->getMessage(), 'danger');
            return false;
        }
        if ($parent) {
            if ($this->is_distinguished_folder($parent)) {
                $parentObj = new Type\DistinguishedFolderIdType($parent);
            } else {
                // Convert hex ID to binary for EWS
                $parentObj = new Type\FolderIdType(hex2bin($parent));
            }
            $request = [
                'FolderIds' => Utilities\getFolderIds([$folderObj]),
                'ToFolderId' => $parentObj->toArray(true),
            ];
            try {
                $request = Type::buildFromArray($request);
                $this->ews->MoveFolder($request);
            } catch (\Exception $e) {
                Hm_Msgs::add($e->getMessage(), 'danger');
                return false;
            }
        }
        return true;
    }

    public function delete_folder($folder) {
        try {
            return $this->api->deleteFolder(new Type\FolderIdType(hex2bin($folder)));
        } catch(\Exception $e) {
            Hm_Msgs::add($e->getMessage(), 'danger');
            return false;
        }
    }

    public function send_message($from, $recipients, $message, $delivery_receipt = false) {
        try {
            $msg = new Type\MessageType();
            $msg->setFrom($from);
            $msg->setToRecipients($recipients);
            $mimeContent = Type\MimeContentType::buildFromArray([
                'CharacterSet' => 'UTF-8',
                '_' => base64_encode($message)
            ]);
            $msg->setMimeContent($mimeContent);

            if ($delivery_receipt) {
                $msg->setIsDeliveryReceiptRequested($delivery_receipt);
            }
            $this->api->sendMail($msg, [
                'MessageDisposition' => 'SendOnly', // saving to sent items is handled by the imap module depending on the chosen sent folder
            ]);
            return;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function store_message($folder, $message, $seen = true, $draft = false) {
        try {

            if ($this->is_distinguished_folder($folder)) {
                $folderObj = new Type\DistinguishedFolderIdType($folder);
            } else {
                $folderObj = new Type\FolderIdType($folder);
            }
            $msg = new Type\MessageType();
            $mimeContent = Type\MimeContentType::buildFromArray([
                'CharacterSet' => 'UTF-8',
                '_' => base64_encode($message)
            ]);
            $msg->setMimeContent($mimeContent);

            $flags = 0;
            if ($seen) {
                $flags |= self::PID_TAG_MESSAGE_READ;
            }
            if ($draft) {
                $flags |= self::PID_TAG_MESSAGE_DRAFT;
            }
            $extendedFieldURI = Type\PathToExtendedFieldType::buildFromArray([
                'PropertyTag' => self::PID_TAG_MESSAGE_FLAGS,
                'PropertyType' => Enumeration\MapiPropertyTypeType::INTEGER,
            ]);

            $extendedProperty = Type\ExtendedPropertyType::buildFromArray([
                'ExtendedFieldURI' => $extendedFieldURI,
                'Value' => $flags,
            ]);
            $msg->addExtendedProperty($extendedProperty);

            $result = $this->api->sendMail($msg, [
                'MessageDisposition' => 'SaveOnly',
                'SavedItemFolderId' => $folderObj->toArray(true),
            ]);
            return bin2hex($result->getId());
        } catch (\Exception $e) {
            Hm_Msgs::add($e->getMessage(), 'danger');
            return false;
        }
    }

    /**
     * Performs an EWS search using FindItem operation and supplies sorting + pagination arguments.
     * Search can be perfomed using Advanced Query Syntax when keyword is an array containing terms
     * searching in specific fields (e.g. advanced search) or Restrictions list when requesting
     * filtering by extended properties as answered or unanswered emails.
     */
    public function search($folder, $sort, $reverse, $flag_filter, $offset, $limit, $keyword, $trusted_senders) {
        $lower_folder = strtolower($folder);
        if ($this->is_distinguished_folder($lower_folder)) {
            $folderObj = new Type\DistinguishedFolderIdType($lower_folder);
        } elseif (ctype_xdigit($folder)) {
            $folderObj = new Type\FolderIdType(hex2bin($folder));
        } else {
            $folderObj = new Type\FolderIdType($folder);
        }

        $request = array(
            'Traversal' => 'Shallow',
            'ItemShape' => array(
                'BaseShape' => 'IdOnly'
            ),
            'IndexedPageItemView' => [
                'MaxEntriesReturned' => $limit,
                'Offset' => $offset,
                'BasePoint' => 'Beginning',
            ],
            'ParentFolderIds' => $folderObj->toArray(true)
        );
        if (! empty($sort)) {
            switch ($sort) {
                case 'ARRIVAL':
                    $fieldURI = 'item:DateTimeCreated';
                    break;
                case 'DATE':
                    $fieldURI = 'item:DateTimeReceived';
                    break;
                case 'CC':
                    // TODO: figure out a way to sort by something not availalbe in FindItem operation
                    $fieldURI = null;
                    break;
                case 'TO':
                    // TODO: figure out a way to sort by something not availalbe in FindItem operation
                    $fieldURI = null;
                    break;
                case 'SUBJECT':
                    $fieldURI = 'item:Subject';
                    break;
                case 'FROM':
                    $fieldURI = 'message:From';
                    break;
                case 'SIZE':
                    $fieldURI = 'item:Size';
                    break;
                default:
                    $fieldURI = null;
            }
            if ($fieldURI) {
                $request['SortOrder'] = [
                    'FieldOrder' => [
                        'Order' => $reverse ? 'Descending' : 'Ascending',
                        'FieldURI' => [
                            'FieldURI' => $fieldURI,
                        ],
                    ]
                ];
            }
        }
        $qs = [];
        if (is_array($keyword)) {
            foreach ($keyword as $term) {
                switch ($term[0]) {
                    case 'SINCE':
                        $qs[] = "Received:>$term[1]";
                        break;
                    case 'SENTSINCE':
                        $qs[] = "Sent:>$term[1]";
                        break;
                    case 'FROM':
                        $qs[] = "From:($term[1])";
                        break;
                    case 'TO':
                        $qs[] = "To:($term[1])";
                        break;
                    case 'CC':
                        $qs[] = "Cc:($term[1])";
                        break;
                    case 'TEXT':
                        $qs[] = "(Subject:($term[1]) OR Body:($term[1]))";
                        break;
                    case 'BODY':
                        $qs[] = "Body:($term[1])";
                        break;
                    case 'SUBJECT':
                        $qs[] = "Subject:($term[1])";
                        break;
                    default:
                        // noop
                }
            }
        } elseif (! empty($keyword)) {
            $qs[] = $keyword;
        }
        switch ($flag_filter) {
            case 'UNSEEN':
                $qs[] = 'isRead:false';
                break;
            case 'SEEN':
                $qs[] = 'isRead:true';
                break;
            case 'FLAGGED':
                $qs[] = 'isFlagged:true';
                break;
            case 'UNFLAGGED':
                $qs[] = 'isFlagged:false';
                break;
            case 'ANSWERED':
                $request['Restriction'] = [
                    'IsEqualTo' => [
                        'ExtendedFieldURI' => [
                            'PropertyTag' => self::PID_TAG_ICON_INDEX,
                            'PropertyType' => Enumeration\MapiPropertyTypeType::INTEGER,
                        ],
                        'FieldURIOrConstant' => [
                            'Constant' => ['Value' => self::PID_TAG_ICON_REPLIED],
                        ],
                    ],
                ];
                break;
            case 'UNANSWERED':
                $request['Restriction'] = [
                    'IsNotEqualTo' => [
                        'ExtendedFieldURI' => [
                            'PropertyTag' => self::PID_TAG_ICON_INDEX,
                            'PropertyType' => Enumeration\MapiPropertyTypeType::INTEGER,
                        ],
                        'FieldURIOrConstant' => [
                            'Constant' => ['Value' => self::PID_TAG_ICON_REPLIED],
                        ],
                    ],
                ];
                break;
                break;
            case 'ALL':
            default:
                // noop
        }
        if ($qs && empty($request['Restriction'])) {
            $request['QueryString'] = implode(' AND ', $qs);
        } elseif ($keyword && ! empty($request['Restriction'])) {
            $restriction = ['And' => $request['Restriction']];
            $restriction['And']['Or'] = [
                [
                    'Contains' => [
                        'FieldURI' => ['FieldURI' => 'item:Subject'],
                        'Constant' => ['Value' => $keyword],
                    ],
                ],
                [
                    'Contains' => [
                        'FieldURI' => ['FieldURI' => 'item:Body'],
                        'Constant' => ['Value' => $keyword],
                    ],
                ],
            ];
            $request['Restriction'] = $restriction;
        }
        $request = Type::buildFromArray($request);
        $result = $this->ews->FindItem($request);
        $items = [];
        $responseItems = $result->getItems();

        if (method_exists($responseItems, 'getMessage') && $messages = $responseItems->getMessage()) {
            if (!is_array($messages)) {
                $messages = [$messages];
            }
            $items = array_merge($items, $messages);
        }

        if (method_exists($responseItems, 'getCalendarItem') && $calendarItems = $responseItems->getCalendarItem()) {
            if (!is_array($calendarItems)) {
                $calendarItems = [$calendarItems];
            }
            $items = array_merge($items, $calendarItems);
        }

        if (method_exists($responseItems, 'getMeetingRequest') && $meetingRequests = $responseItems->getMeetingRequest()) {
            if (!is_array($meetingRequests)) {
                $meetingRequests = [$meetingRequests];
            }
            $items = array_merge($items, $meetingRequests);
        }

        $itemIds = [];
        foreach ($items as $item) {
            $itemIds[] = bin2hex($item->getItemId()->getId());
        }
        return [$result->getTotalItemsInView(), $itemIds];
    }

    public function get_messages($folder, $sort, $reverse, $flag_filter, $offset, $limit, $keyword, $trusted_senders, $include_preview = false) {
        list ($total, $itemIds) = $this->search($folder, $sort, $reverse, $flag_filter, $offset, $limit, $keyword, $trusted_senders);
        return [$total, $this->get_message_list($itemIds, $include_preview)];
    }

    public function get_message_list($itemIds, $include_preview = false) {

        if (empty($itemIds)) {
            return [];
        }
        $request = array(
            'ItemShape' => array(
                'BaseShape' => 'AllProperties',
                'AdditionalProperties' => [
                    'ExtendedFieldURI' => [
                        [
                            'PropertyTag' => self::PID_TAG_FLAG_STATUS, //check flagged msg
                            'PropertyType' => Enumeration\MapiPropertyTypeType::INTEGER,
                        ],
                        [
                            'PropertyTag' => self::PID_TAG_ICON_INDEX, // check if replied/answered
                            'PropertyType' => Enumeration\MapiPropertyTypeType::INTEGER,
                        ],
                    ],
                ],
            ),
            'ItemIds' => [
                'ItemId' => array_map(function($id) {
                    return ['Id' => hex2bin($id)];
                }, $itemIds),
            ],
        );

        $request = Type::buildFromArray($request);
        $result = $this->ews->GetItem($request);
        $messages = [];

        // For a mailbox with a single item, EWS returns the item directly instead of an array
        if (! is_array($result)) {
            $uid = bin2hex($result->getItemId()->getId());
            $flags = $this->extract_flags($result);
            $messages[$uid] = $this->getMessageProperties($result, $uid, $flags, $include_preview);
            return $messages;
        } else {
            foreach ($result as $message) {
                $flags = $this->extract_flags($message);
                $uid = bin2hex($message->getItemId()->getId());
                $msg = $this->getMessageProperties($message, $uid, $flags, $include_preview);
                $messages[$uid] = $msg;
            }
        }

        return $messages;
    }

    /**
     * Extract message properties based on EWS item type
     */
    protected function getMessageProperties($message, $uid, $flags, $include_preview = false) {
        $messageType = $this->detectMessageType($message);

        $msg = [
            'uid' => $uid,
            'flags' => implode(' ', $flags),
            'type_msg' => $messageType,
            'timestamp' => time(),
            'charset' => null,
            'x-priority' => null,
            'google_msg_id' => null,
            'google_thread_id' => null,
            'google_labels' => null,
            'list_archive' => null,
            'x_auto_bcc' => null,
            'x_snoozed'  => null,
            'x_schedule' => null,
            'x_profile_id' => null,
            'x_delivery' => null,
        ];

        switch ($messageType) {
            case 'calendar':
                $msg = array_merge($msg, $this->getCalendarProperties($message));
                break;
            case 'meeting_request':
                $msg = array_merge($msg, $this->getMeetingRequestProperties($message));
                break;
            case 'message':
            default:
                $msg = array_merge($msg, $this->getRegularMessageProperties($message));
                break;
        }

        $this->extractInternetHeaders($message, $msg);
        $cset = '';
        if (mb_stristr($msg['content-type'], 'charset=')) {
            if (preg_match("/charset\=([^\s;]+)/", $msg['content-type'], $matches)) {
                $cset = trim(mb_strtolower(str_replace(array('"', "'"), '', $matches[1])));
            }
        }
        $msg['charset'] = $cset;
        $msg['preview_msg'] = $include_preview ? strip_tags($message->getBody()) : "";

        return $msg;
    }

    /**
     * Detect the type of EWS message/item
     */
    protected function detectMessageType($message) {
        if ($message instanceof Type\CalendarItemType) {
            return 'calendar';
        } elseif ($message instanceof Type\MeetingRequestMessageType ||
                  $message instanceof Type\MeetingResponseMessageType ||
                  $message instanceof Type\MeetingCancellationMessageType) {
            return 'meeting_request';
        } elseif ($message instanceof Type\MessageType) {
            return 'message';
        } else {
            return 'unknown';
        }
    }

    /**
     * Extract properties specific to regular messages
     */
    protected function getRegularMessageProperties($message) {
        return [
            'internal_date' => $message->getDateTimeCreated() ?
                $message->getDateTimeCreated()->format('Y-m-d H:i:s.u') : date('Y-m-d H:i:s.u'),
            'size' => $message->getSize() ?: 0,
            'date' => $message->getDateTimeReceived() ?
                $message->getDateTimeReceived()->format('Y-m-d H:i:s.u') : date('Y-m-d H:i:s.u'),
            'from' => $message->getFrom() ? $this->extract_mailbox($message->getFrom()) : '',
            'to' => $message->getToRecipients() ? $this->extract_mailbox($message->getToRecipients()) : '',
            'subject' => $message->getSubject() ?: '',
            'content-type' => null,
            'references' => $message->getReferences() ?: null,
            'message_id' => $message->getInternetMessageId() ?: null,
        ];
    }

    /**
     * Extract properties specific to calendar items
     */
    protected function getCalendarProperties($message) {
        $props = [
            'internal_date' => $message->getDateTimeCreated() ?
                $message->getDateTimeCreated()->format('Y-m-d H:i:s.u') : date('Y-m-d H:i:s.u'),
            'size' => $message->getSize() ?: 0,
            'date' => $message->getDateTimeReceived() ?
                $message->getDateTimeReceived()->format('Y-m-d H:i:s.u') :
                ($message->getStart() ? $message->getStart()->format('Y-m-d H:i:s.u') : date('Y-m-d H:i:s.u')),
            'from' => $message->getOrganizer() ? $this->extract_mailbox($message->getOrganizer()) : '',
            'to' => $message->getRequiredAttendees() ? $this->extract_mailbox($message->getRequiredAttendees()) : '',
            'subject' => $message->getSubject() ?: '',
            'content-type' => null,
            'references' => null,
            'message_id' => null,
        ];

        return $props;
    }

    /**
     * Extract properties specific to meeting requests
     */
    protected function getMeetingRequestProperties($message) {
        $props = [
            'internal_date' => $message->getDateTimeCreated() ?
                $message->getDateTimeCreated()->format('Y-m-d H:i:s.u') : date('Y-m-d H:i:s.u'),
            'size' => $message->getSize() ?: 0,
            'date' => $message->getDateTimeReceived() ?
                $message->getDateTimeReceived()->format('Y-m-d H:i:s.u') : date('Y-m-d H:i:s.u'),
            'from' => $message->getFrom() ? $this->extract_mailbox($message->getFrom()) : '',
            'to' => $message->getToRecipients() ? $this->extract_mailbox($message->getToRecipients()) : '',
            'subject' => $message->getSubject() ?: '',
            'content-type' => null,
            'references' => $message->getReferences() ?: null,
            'message_id' => $message->getInternetMessageId() ?: null,
        ];

        return $props;
    }

    /**
     * Extract internet message headers that may be present
     */
    protected function extractInternetHeaders($message, &$msg) {
        if (method_exists($message, 'getInternetMessageHeaders') && $message->getInternetMessageHeaders()) {

            foreach ($message->getInternetMessageHeaders() as $header) {
                $headerMappings = [
                    'x-gm-msgid' => 'google_msg_id',
                    'x-gm-thrid' => 'google_thread_id',
                    'x-gm-labels' => 'google_labels',
                    'x-auto-bcc' => 'x_auto_bcc',
                    'message-id' => 'message_id',
                    'references' => 'references',
                    'x-snoozed' => 'x_snoozed',
                    'x-schedule' => 'x_schedule',
                    'x-profile-id' => 'x_profile_id',
                    'x-delivery' => 'x_delivery',
                    'list-archive' => 'list_archive',
                    'content-type' => 'content-type',
                    'x-priority' => 'x-priority'
                ];

                foreach ($headerMappings as $hname => $key) {
                    if (method_exists($header, 'getHeaderName') && strtolower($header->getHeaderName()) == $hname) {
                        $msg[$key] = (string) $header;
                    }
                }
            }
        }
    }

    public function message_action($action, $itemIds, $folder=false, $keyword=false) {
        if (empty($itemIds)) {
            return ['status' => true, 'responses' => []];
        }
        if (! is_array($itemIds)) {
            $itemIds = [$itemIds];
        }
        $change = null;
        $status = false;
        $responses = [];
        switch ($action) {
            case 'ARCHIVE':
                $status = $this->archive_items($itemIds);
                break;
            case 'JUNK' :
                $status = $this->move_items_to_junk($itemIds);
                break;
            case 'DELETE':
                $status = $this->delete_items($itemIds);
                break;
            case 'HARDDELETE':
                $status = $this->delete_items($itemIds, true);
                break;
            case 'COPY':
                $newIds = $this->copy_items($itemIds, $folder);
                if ($newIds) {
                    foreach ($newIds as $key => $newId) {
                        $responses[] = ['oldUid' => $itemIds[$key], 'newUid' => $newId];
                    }
                    $status = true;
                }
                break;
            case 'MOVE':
                $newIds = $this->move_items($itemIds, $folder);
                if ($newIds) {
                    foreach ($newIds as $key => $newId) {
                        $responses[] = ['oldUid' => $itemIds[$key], 'newUid' => $newId];
                    }
                    $status = true;
                }
                break;
            case 'READ':
                $change = ItemUpdateBuilder::buildUpdateItemChanges('Message', 'message', ['IsRead' => true]);
                break;
            case 'UNREAD':
                $change = ItemUpdateBuilder::buildUpdateItemChanges('Message', 'message', ['IsRead' => false]);
                break;
            case 'FLAG':
                $change = [
                    'SetItemField' => [
                        'ExtendedFieldURI' => [
                            'PropertyTag' => self::PID_TAG_FLAG_STATUS,
                            'PropertyType' => Enumeration\MapiPropertyTypeType::INTEGER,
                        ],
                        'Message' => [
                            'ExtendedProperty' => [
                                'ExtendedFieldURI' => [
                                    'PropertyTag' => self::PID_TAG_FLAG_STATUS,
                                    'PropertyType' => Enumeration\MapiPropertyTypeType::INTEGER,
                                ],
                                'Value' => self::PID_TAG_FLAG_FLAGGED,
                            ],
                        ],
                    ],
                ];
                break;
            case 'UNFLAG':
                $change = [
                    'DeleteItemField' => [
                        'ExtendedFieldURI' => [
                            'PropertyTag' => self::PID_TAG_FLAG_STATUS,
                            'PropertyType' => Enumeration\MapiPropertyTypeType::INTEGER,
                        ],
                    ],
                ];
                break;
            case 'ANSWERED':
            case 'UNDELETE':
            case 'CUSTOM':
                // TODO: unsupported out of the box, we can emulate via custom extended properties
                $change = null;
                $status = true;
                break;
            case 'EXPUNGE':
                // not needed for EWS
                return ['status' => true, 'responses' => $responses];
            default:
                $change = null;
        }

        if ($change) {
            $changes = ['ItemChange' => []];
            foreach ($itemIds as $itemId) {
                $changes['ItemChange'][] = [
                    'ItemId' => (new Type\ItemIdType(hex2bin($itemId)))->toArray(),
                    'Updates' => $change,
                ];
            }
            $status = $this->api->updateItems($changes);
        }

        return ['status' => $status, 'responses' => $responses];
    }

    public function get_message_headers($itemId) {
        $binaryId = hex2bin($itemId);
        $request = array(
            'ItemShape' => array(
                'BaseShape' => 'AllProperties',
                'AdditionalProperties' => [
                    'ExtendedFieldURI' => [
                        [
                            'PropertyTag' => self::PID_TAG_FLAG_STATUS, //check flagged msg
                            'PropertyType' => Enumeration\MapiPropertyTypeType::INTEGER,
                        ],
                        [
                            'PropertyTag' => self::PID_TAG_ICON_INDEX, // check if replied/answered
                            'PropertyType' => Enumeration\MapiPropertyTypeType::INTEGER,
                        ],
                    ],
                ],
            ),
            'ItemIds' => [
                'ItemId' => ['Id' => $binaryId],
            ],
        );
        $request = Type::buildFromArray($request);
        $message = $this->ews->GetItem($request);
        $sender = $message->getSender();
        $from = $message->getFrom();
        $headers = [];
        $headers['Arrival Date'] = $message->getDateTimeCreated()->format('Y-m-d H:i:s.u');
        if ($sender && $from) {
            $headers['From'] = $message->getSender()->getMailbox()->getName() . ' <' . $message->getSender()->getMailbox()->getEmailAddress() . '>';
        } elseif ($sender) {
            $headers['From'] = $this->extract_mailbox($sender);
        } elseif ($from) {
            $headers['From'] = $this->extract_mailbox($from);
        } else {
            $headers['From'] = null;
        }
        $headers['To'] = $this->extract_mailbox($message->getToRecipients());
        $headers['To'] = flatten_headers_to_string($headers, 'To');
        if($message->getCcRecipients()) {
            $headers['Cc'] = $this->extract_mailbox($message->getCcRecipients());
            $headers['Cc'] = flatten_headers_to_string($headers, 'Cc');
        }
        if ($message->getBccRecipients()) {
            $headers['Bcc'] = $this->extract_mailbox($message->getBccRecipients());
            $headers['Bcc'] = flatten_headers_to_string($headers, 'Bcc');
        }
        $headers['Flags'] = implode(' ', $this->extract_flags($message));
        foreach ($message->getInternetMessageHeaders() as $header) {
            $name = $header->getHeaderName();
            if (isset($headers[$name])) {
                if (! is_array($headers[$name])) {
                    $headers[$name] = [$headers[$name]];
                }
                $headers[$name][] = (string) $header;
            } else {
                $headers[$name] = (string) $header;
            }
        }
        if (! $message->isRead()) {
            $this->api->updateMailItem($message->getItemId(), [
                'IsRead' => true,
            ]);
        }
        return $headers;
    }

    public function get_message_content($itemId, $part) {
        if ($part) {
            list($msg_struct, $msg_struct_current, $msg_text, $part) = $this->get_structured_message($itemId, $part, false);
            return $msg_text;
        } else {
            $message = $this->get_mime_message_by_id($itemId);
            $content = (string) $message;
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

    public function get_mime_message_by_id($itemId) {
        $binaryId = hex2bin($itemId);
        $request = array(
            'ItemShape' => array(
                'BaseShape' => 'IdOnly',
                'IncludeMimeContent' => true,
            ),
            'ItemIds' => [
                'ItemId' => ['Id' => $binaryId],
            ],
        );
        $request = Type::buildFromArray($request);
        $message = $this->ews->GetItem($request);
        $mime = $message->getMimeContent();
        $content = base64_decode($mime);
        if (strtoupper($mime->getCharacterSet()) != 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $mime->getCharacterSet());
        }
        $parser = new MailMimeParser();
        return $parser->parse($content, false);
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
            } else {
                $struct[$part_num]['attributes'] = [];
            }
            $struct[$part_num]['id'] = $part->getContentId();
            $struct[$part_num]['description'] = $part->getHeaderValue('Content-Description');
            $struct[$part_num]['encoding'] = $part->getContentTransferEncoding();
            $struct[$part_num]['size'] = strlen($content);
            $struct[$part_num]['lines'] = substr_count($content, "\n");
            $struct[$part_num]['md5'] = '';
            $struct[$part_num]['disposition'] = $part->getContentDisposition();

            if ($filename = $part->getFilename()) {
                $struct[$part_num]['file_attributes'] = ['filename' => $filename];

                if ($part->getContentDisposition() == 'attachment') {
                    $struct[$part_num]['file_attributes']['attachment'] = true;
                }
            } else {
                $struct[$part_num]['file_attributes'] = '';
            }
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

    protected function extract_mailbox($data) {
        if (is_array($data)) {
            $result = [];
            foreach ($data as $mailbox) {
                $result[] = $this->extract_mailbox($mailbox);
            }
            return $result;
        } elseif (is_object($data) && $data->Mailbox) {
            if(is_array($data->Mailbox)) {
                $result = [];
                foreach ($data->Mailbox as $mailbox) {
                    $result[] = $this->extract_mailbox($mailbox);
                }
                return $result;
            }else {
                return $data->Mailbox->getName() . ' <' . $data->Mailbox->getEmailAddress() . '>';
            }
        } elseif (is_object($data) && method_exists($data, 'getMailbox')) {
            $mailbox = $data->getMailbox()->getMailbox();
            $name = $mailbox->getName();
            $email = $mailbox->getEmailAddress();
            return $name ? $name . ' <' . $email . '>' : $email;
        } elseif (is_object($data) && method_exists($data, 'getName') && method_exists($data, 'getEmailAddress')) {
            $name = $data->getName();
            $email = $data->getEmailAddress();
            return $name ? $name . ' <' . $email . '>' : $email;
        }else {
            return (string) $data;
        }
    }

    protected function extract_flags($message) {
        // note about flags: EWS - doesn't support the \Deleted flag
        $flags = [];
        if ($message->getIsRead()) {
            $flags[] = '\\Seen';
        }
        if ($message->getIsDraft()) {
            $flags[] = '\\Draft';
        }
        if ($extended_properties = $message->getExtendedProperty()) {
            if ($extended_properties instanceof Type\ExtendedPropertyType) {
                $extended_properties = [$extended_properties];
            }
            foreach ($extended_properties as $prop) {
                if (hexdec($prop->getExtendedFieldURI()->getPropertyTag()) == self::PID_TAG_FLAG_STATUS && $prop->getValue() > 0) {
                    $flags[] = '\\Flagged';
                }
                if (hexdec($prop->getExtendedFieldURI()->getPropertyTag()) == self::PID_TAG_ICON_INDEX && $prop->getValue() == self::PID_TAG_ICON_REPLIED) {
                    $flags[] = '\\Answered';
                }
            }
        }
        return $flags;
    }

    protected function is_distinguished_folder(&$folder) {
        $oClass = new ReflectionClass(new Enumeration\DistinguishedFolderIdNameType());
        $constants = $oClass->getConstants();
        if (in_array($folder, $constants)) {
            return true;
        }
        if (isset($constants[$folder])) {
            $folder = $constants[$folder];
            return true;
        }
        return false;
    }

    protected function archive_items($itemIds) {
        $result = true;
        $folders = $this->get_parent_folders_of_items($itemIds);
        if (!$folders) {
            return false;
        }
        foreach ($folders as $folder => $folderItemIds) {
            if ($this->is_distinguished_folder($folder)) {
                $folderObj = new Type\DistinguishedFolderIdType($folder);
            } else {
                $folderObj = new Type\FolderIdType(hex2bin($folder));
            }

            $payload = [
                'ArchiveSourceFolderId' => $folderObj->toArray(true),
                'ItemIds' => [
                    'ItemId' => array_map(function($itemId) {
                        return (new Type\ItemIdType(hex2bin($itemId)))->toArray();
                    }, $folderItemIds),
                ]
            ];

            $request = Type::buildFromArray($payload);

            try {
                $result = $result && $this->ews->ArchiveItem($request);
            } catch (\Exception $e) {
                Hm_Msgs::add($e->getMessage(), 'danger');
                $result = false;
            }
        }
        return $result;
    }

    protected function move_items_to_junk($itemIds) {
        $result = true;
        $folders = $this->get_parent_folders_of_items($itemIds);
        if (!$folders) {
            return false;
        }
        foreach ($folders as $folder => $itemIds) {
            if ($this->is_distinguished_folder($folder)) {
                $folder = new Type\DistinguishedFolderIdType($folder);
            } else {
                // Convert hex folder ID to binary for EWS API
                $folder = new Type\FolderIdType(hex2bin($folder));
            }

            $junkFolder = new Type\DistinguishedFolderIdType(Enumeration\DistinguishedFolderIdNameType::JUNK);
            $request = [
                'SourceFolderId' => $folder->toArray(true),
                'DestinationFolderId' => $junkFolder->toArray(true),
                'ItemIds' => [
                    'ItemId' => $itemIds = array_map(function($itemId) {
                        return (new Type\ItemIdType(hex2bin($itemId)))->toArray();
                    }, $itemIds),
                ]
            ];

            $request = Type::buildFromArray($request);

            try {
                $result = $result && $this->ews->MoveItem($request);
            } catch (\Exception $e) {
                Hm_Msgs::add('ERR' . $e->getMessage());
                $result = false;
            }
        }
        return $result;
    }


    protected function delete_items($itemIds, $hard = false) {
        $result = true;
        try {
            if ($hard) {
                $result = $this->api->deleteItems(array_map(function($itemId) {
                    return (new Type\ItemIdType(hex2bin($itemId)))->toArray(true);
                }, $itemIds), [
                    'DeleteType' => 'HardDelete',
                ]);
            } else {
                $trash = $this->api->getFolderByDistinguishedId(Type\DistinguishedFolderIdNameType::DELETED);
                $folders = $this->get_parent_folders_of_items($itemIds);
                if (!$folders) {
                    return false;
                }
                foreach ($folders as $folder => $itemIds) {
                    if ($trash && $trash->getFolderId()->getId() == $folder) {
                        $options = ['DeleteType' => 'HardDelete'];
                    } else {
                        $options = [];
                    }
                    $binaryItemIds = array_map(function($itemId) {
                        return (new Type\ItemIdType($itemId))->toArray();
                    }, $itemIds);
                    $result = $result && $this->api->deleteItems($binaryItemIds, $options);
                }
            }
        } catch (\Exception $e) {
            Hm_Msgs::add($e->getMessage(), 'danger');
            $result = false;
        }
        return $result;
    }

    protected function copy_items($itemIds, $folder) {
        if ($this->is_distinguished_folder($folder)) {
            $folder = new Type\DistinguishedFolderIdType($folder);
        } else {
            $folder = new Type\FolderIdType($folder);
        }
        $request = [
            'ToFolderId' => $folder->toArray(true),
            'ItemIds' => [
                'ItemId' => array_map(function($itemId) {
                    return (new Type\ItemIdType(hex2bin($itemId)))->toArray();
                }, $itemIds),
            ]
        ];
        $request = Type::buildFromArray($request);
        try {
            $result = $this->ews->CopyItem($request);
            if (! is_array($result)) {
                $result = [$result];
            }
            $result = array_map(function($itemId) {
                return $itemId->getId();
            }, $result);
        } catch (\Exception $e) {
            Hm_Msgs::add($e->getMessage(), 'danger');
            $result = [];
        }
        return $result;
    }

    protected function move_items($itemIds, $folder) {
        if ($this->is_distinguished_folder($folder)) {
            $folder = new Type\DistinguishedFolderIdType($folder);
        } else {
            $folder = new Type\FolderIdType($folder);
        }
        $request = [
            'ToFolderId' => $folder->toArray(true),
            'ItemIds' => [
                'ItemId' => array_map(function($itemId) {
                    return (new Type\ItemIdType(hex2bin($itemId)))->toArray();
                }, $itemIds),
            ],
            'ReturnNewItemIds' => false,
        ];
        $request = Type::buildFromArray($request);
        try {
            $result = $this->ews->MoveItem($request);
            if (! is_array($result)) {
                $result = [$result];
            }
            $result = array_map(function($itemId) {
                return $itemId->getId();
            }, $result);
        } catch (\Exception $e) {
            Hm_Msgs::add($e->getMessage(), 'danger');
            $result = [];
        }
        return $result;
    }

    protected function get_parent_folders_of_items($itemIds) {
        $itemIds = array_map(function($itemId) {
            return (new Type\ItemIdType(hex2bin($itemId)))->toArray();
        }, $itemIds);
        $folders = [];
        $request = [
            'ItemShape' => [
                'BaseShape' => 'IdOnly',
                'AdditionalProperties' => [
                    'FieldURI' => ['FieldURI' => 'item:ParentFolderId'],
                ],
            ],
            'ItemIds' => [
                'ItemId' => $itemIds,
            ],
        ];
        $request = Type::buildFromArray($request);
        $result = $this->ews->GetItem($request);
        if ($result instanceof Type\MessageType) {
            $result = [$result];
        }
        foreach ($result as $message) {
            $folder = $message->getParentFolderId()->getId();
            if (! isset($folders[$folder])) {
                $folders[$folder] = [];
            }
            $folders[$folder][] = $message->getItemId()->getId();
        }
        return $folders;
    }
}

if(!hm_exists('flatten_headers_to_string')) {
    function flatten_headers_to_string($headers, $key) {
        if (!isset($headers[$key]) || !is_array($headers[$key])) {
            return isset($headers[$key]) ? $headers[$key] : '';
        }

        $flattened_header = [];
        foreach ($headers[$key] as $to_item) {
            if (is_array($to_item)) {
                $flattened_header = array_merge($flattened_header, $to_item);
            } else {
                $flattened_header[] = $to_item;
            }
        }
        return implode(', ', $flattened_header);
    }
}
