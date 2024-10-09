<?php

/**
 * EWS integration
 * @package modules
 * @subpackage imap
 *
 * This is a drop-in replacment of IMAP and JMAP classes that allows usage of Exchange Web Services (EWS)
 * in all functions provided in imap and smtp modules - accessing mailbox, folders, reading messages,
 * attachments, moving, copying, read/unread, flags, sending messages.
 * Connection to EWS is handled by garethp/php-ews package handling NLTM auth and SOAP calls.
 */

/**
 * public interface to EWS mailboxes
 * @subpackage imap/lib
 */
class Hm_EWS {
}
