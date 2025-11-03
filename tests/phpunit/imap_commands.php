<?php
return array(

    'A1 CAPABILITY' =>
        "* CAPABILITY IMAP4rev1 LITERAL+ SASL-IR LOGIN-REFERRALS ID ENABLE IDLE AUTH=PLAIN AUTH=CRAM-MD5 AUTH=SCRAM-SHA-256-PLUS\r\n",

    'A3 CAPABILITY' =>
        "* CAPABILITY IMAP4rev1 LITERAL+ SASL-IR LOGIN-REFERRALS ID ENABLE IDLE SORT SORT=DISPLAY THREAD=".
        "REFERENCES THREAD=REFS THREAD=ORDEREDSUBJECT MULTIAPPEND URL-PARTIAL CATENATE UNSELECT CHILDREN NAMESPACE UIDPLUS ".
        "LIST-EXTENDED I18NLEVEL=1 CONDSTORE QRESYNC ESEARCH ESORT SEARCHRES WITHIN CONTEXT=SEARCH LIST-STATUS BINARY MOVE\r\n".
        "A3 OK Capability completed (0.001 + 0.000 secs).\r\n",

    'A2 LOGIN "testuser" "testpass"' =>
        "* BANNER\r\n".
        "* BANNER2\r\n".
        "A2 OK [CAPABILITY IMAP4rev1 LITERAL+ SASL-IR LOGIN-REFERRALS ID ENABLE IDLE SORT ".
        "SORT=DISPLAY THREAD=REFERENCES THREAD=REFS THREAD=ORDEREDSUBJECT MULTIAPPEND URL-PARTIAL CATENATE UNSELECT CHILDREN ".
        "NAMESPACE UIDPLUS LIST-EXTENDED I18NLEVEL=1 CONDSTORE QRESYNC ESEARCH ESORT SEARCHRES WITHIN CONTEXT=SEARCH LIST-STATUS ".
        "BINARY MOVE] Logged in\r\n",

    'A4 ENABLE QRESYNC' =>
        "* ENABLED QRESYNC\r\nA4 OK Enabled (0.001 + 0.000 secs).\r\n",

    'A5 ENABLE QRESYNC' =>
        "* ENABLED QRESYNC\r\nA4 OK Enabled (0.001 + 0.000 secs).\r\n",

    'A2 AUTHENTICATE CRAM-MD5' =>
        "+ PDBFOTRCMUMwMkY5NDFFEFU2QkM5MjVFMUITFCMjZAbG9naW5wcm94eTZiLLmFsaWNlLml0Pg==\r\n",

    'dGVzdHVzZXIgNTRmMDgwM2FhZTA2MzVmOWM3Y2M0YWVmZTUzODYzZTU=' =>
        "A2 OK authentication successful\r\n",

    'dGVzdHVzZXIgMGYxMzE5YmIxMzMxOWViOWU4ZDdkM2JiZDJiZDJlOTQ=' =>
        "A2 OK authentication successful\r\n",
    
    'A2 AUTHENTICATE SCRAM-SHA-256' =>
        "+ r=fyko+d2lbbFgONRv9qkxdawL,z=SCRAM-SHA-256,c=biws,n,,\r\n",

    'c=biws,r=fyko+d2lbbFgONRv9qkxdawL1qkxdawL,p=YzQxNmVmY2ViMDAxYzdmNTkxOWFlZDIyNTgzM2NlZDBlZjBiNTNkZjUxNTQ1ZmZmNmY5NTlkOGZjNjYxYWEyNQ==' =>
        "A2 OK authentication successful\r\n",

    'A2 AUTHENTICATE SCRAM-SHA-256-PLUS' =>
        "+ r=fyko+d2lbbFgONRv9qkxdawL,z=SCRAM-SHA-256-PLUS,c=tls-unique,n,,\r\n",
    
    'c=tls-unique,r=fyko+d2lbbFgONRv9qkxdawL1qkxdawL,p=YzQxNmVmY2ViMDAxYzdmNTkxOWFlZDIyNTgzM2NlZDBlZjBiNTNkZjUxNTQ1ZmZmNmY5NTlkOGZjNjYxYWEyNQ==' =>
        "A2 OK authentication successful\r\n",
    'A2 AUTHENTICATE XOAUTH2 dXNlcj10ZXN0dXNlcgFhdXRoPUJlYXJlciB0ZXN0cGFzcwEB' =>
        "+ V1WTI5dENnPT0BAQ==\r\n",

    'A5 LIST (SPECIAL-USE) "" "*"' =>
        "* LIST (\NoInferiors \UnMarked \Sent) \"/\" Sent\r\n".
        "A5 OK List completed (0.003 + 0.000 + 0.002 secs).\r\n",

    'A6 LIST (SPECIAL-USE) "" "*"' =>
        "* LIST (\NoInferiors \UnMarked \Sent) \"/\" Sent\r\n".
        "A6 OK List completed (0.003 + 0.000 + 0.002 secs).\r\n",

    'A6 LIST "" "%" RETURN (CHILDREN STATUS (MESSAGES UNSEEN UIDVALIDITY UIDNEXT RECENT))' =>
        "* LIST (\NoInferiors \UnMarked \Noselect) \"/\" Sent\r\n".
        "* STATUS Sent (MESSAGES 0 RECENT 0 UIDNEXT 1 UIDVALIDITY 1474301542 UNSEEN 0)\r\n".
        "* LIST (\NoInferiors \UnMarked) \"/\" Sent\r\n".
        "* STATUS Sent (MESSAGES 0 RECENT 0 UIDNEXT 1 UIDVALIDITY 1474301542 UNSEEN 0)\r\n".
        "* LIST (\HasChildren) \"/\" INBOX\r\n".
        "* STATUS INBOX (MESSAGES 93 RECENT 0 UIDNEXT 1736 UIDVALIDITY 1422554786 UNSEEN 0)\r\n".
        "* LIST (\HasNoChildren) \"/\" INBOX/test\r\n".
        "* STATUS INBOX/test (MESSAGES 93 RECENT 0 UIDNEXT 1736 UIDVALIDITY 1422554786 UNSEEN 0)\r\n".
        "A6 OK List completed (0.005 + 0.000 + 0.004 secs).\r\n",

    'A6 LIST "" "*" RETURN (CHILDREN STATUS (MESSAGES UNSEEN UIDVALIDITY UIDNEXT RECENT))' =>
        "* LIST (\NoInferiors \UnMarked \Noselect) \"/\" Sent\r\n".
        "* STATUS Sent (MESSAGES 0 RECENT 0 UIDNEXT 1 UIDVALIDITY 1474301542 UNSEEN 0)\r\n".
        "* LIST (\NoInferiors \UnMarked) \"/\" Sent\r\n".
        "* STATUS Sent (MESSAGES 0 RECENT 0 UIDNEXT 1 UIDVALIDITY 1474301542 UNSEEN 0)\r\n".
        "* LIST (\HasChildren) \"/\" INBOX\r\n".
        "* STATUS INBOX (MESSAGES 93 RECENT 0 UIDNEXT 1736 UIDVALIDITY 1422554786 UNSEEN 0)\r\n".
        "* LIST (\HasNoChildren) \"/\" INBOX/test\r\n".
        "* STATUS INBOX/test (MESSAGES 93 RECENT 0 UIDNEXT 1736 UIDVALIDITY 1422554786 UNSEEN 0)\r\n".
        "A6 OK List completed (0.005 + 0.000 + 0.004 secs).\r\n",

    'A7 LIST "" "*" RETURN (CHILDREN SPECIAL-USE STATUS (MESSAGES UNSEEN UIDVALIDITY UIDNEXT RECENT))' =>
        "* LIST (\NoInferiors \UnMarked \Noselect) \"/\" Sent\r\n".
        "* STATUS Sent (MESSAGES 0 RECENT 0 UIDNEXT 1 UIDVALIDITY 1474301542 UNSEEN 0)\r\n".
        "* LIST (\NoInferiors \UnMarked) \"/\" Sent\r\n".
        "* STATUS Sent (MESSAGES 0 RECENT 0 UIDNEXT 1 UIDVALIDITY 1474301542 UNSEEN 0)\r\n".
        "* LIST (\HasChildren) \"/\" INBOX\r\n".
        "* STATUS INBOX (MESSAGES 93 RECENT 0 UIDNEXT 1736 UIDVALIDITY 1422554786 UNSEEN 0)\r\n".
        "* LIST (\HasNoChildren) \"/\" INBOX/test\r\n".
        "* STATUS INBOX/test (MESSAGES 93 RECENT 0 UIDNEXT 1736 UIDVALIDITY 1422554786 UNSEEN 0)\r\n".
        "A7 OK List completed (0.005 + 0.000 + 0.004 secs).\r\n",

    'A6 LSUB "" "*" RETURN (CHILDREN STATUS (MESSAGES UNSEEN UIDVALIDITY UIDNEXT RECENT))' =>
        "* LSUB (\NoInferiors \UnMarked \Sent) \"/\" Sent\r\n".
        "* STATUS Sent (MESSAGES 0 RECENT 0 UIDNEXT 1 UIDVALIDITY 1474301542 UNSEEN 0)\r\n".
        "* A7 OK Lsub completed (0.005 + 0.000 + 0.004 secs).\r\n",

    'A5 NAMESPACE' =>
        "* NAMESPACE ((\"\" \"/\")) NIL NIL\r\nA5 OK Namespace completed (0.001 + 0.000 secs).\r\n",

    'A5 STATUS "INBOX" (UNSEEN UIDVALIDITY UIDNEXT MESSAGES RECENT)' =>
        "* STATUS INBOX (MESSAGES 93 RECENT 0 UIDNEXT 1736 UIDVALIDITY 1422554786 UNSEEN 0)\r\n".
        "A5 OK Status completed (0.001 + 0.000 secs).\r\n",

    'A6 SELECT "INBOX"' =>
        "* FLAGS (\Answered \Flagged \Deleted \Seen \Draft)\r\n".
        "* OK [PERMANENTFLAGS (\Answered \Flagged \Deleted \Seen \Draft \*)] Flags permitted.\r\n".
        "* 93 EXISTS\r\n".
        "* 0 RECENT\r\n".
        "* OK [UIDVALIDITY 1422554786] UIDs valid\r\n".
        "* OK [UIDNEXT 1736] Predicted next UID\r\n".
        "* OK [HIGHESTMODSEQ 91323] Highest\r\n".
        "A6 OK [READ-WRITE] Select completed (0.001 + 0.000 secs).\r\n",

    'A2 STARTTLS' =>
        "A2 OK Begin TLS negotiation now\r\n",

    'A5 NOOP' =>
        "* 23 EXISTS\r\n".
        "A5 OK NOOP Completed\r\n",
    'A8 UID FETCH 1731,1732 (FLAGS INTERNALDATE RFC822.SIZE BODY.PEEK[HEADER.FIELDS (SUBJECT X-AUTO-BCC FROM DATE CONTENT-TYPE X-PRIORITY TO LIST-ARCHIVE REFERENCES MESSAGE-ID X-SNOOZED)])' =>
        "* 92 FETCH (UID 1731 FLAGS (\Seen) INTERNALDATE \"02-May-2017 16:32:24 -0500\" RFC822.SIZE 1940 BODY[HEADER.FIELDS (SUBJECT X-AUTO-BCC FROM DATE CONTENT-TYPE X-PRIORITY TO LIST-ARCHIVE REFERENCES MESSAGE-ID)] {240}\r\n".
        "Subject: =?utf-8?q?apt-listchanges=3A_news_for_shop?=\r\n".
        "To: root@shop.jackass.com\r\n".
        "Content-Type: text/plain; charset=\"utf-8\"\r\n".
        "Message-Id: <E1d5fPE-0005Vm-8L@shop>\r\n".
        "From: root <root@shop.jackass.com>\r\n".
        "Date: Tue, 02 May 2017 16:32:24 -0500\r\n".
        "\r\n".
        ")\r\n".
        "* 93 FETCH (UID 1732 FLAGS (\Seen) INTERNALDATE \"11-May-2017 14:28:40 -0500\" RFC822.SIZE 1089 BODY[HEADER.FIELDS (SUBJECT X-AUTO-BCC FROM DATE CONTENT-TYPE X-PRIORITY TO LIST-ARCHIVE REFERENCES MESSAGE-ID)] {240}\r\n".
        "Subject: =?utf-8?q?apt-listchanges=3A_news_for_shop?=\r\n".
        "To: root@shop.jackass.com\r\n".
        "Content-Type: text/plain; charset=\"utf-8\"\r\n".
        "Message-Id: <E1d8tlQ-00065l-4t@shop>\r\n".
        "From: root <root@shop.jackass.com>\r\n".
        "Date: Thu, 11 May 2017 14:28:40 -0500\r\n".
        "\r\n".
        ")\r\n".
        "A5 OK Fetch completed (0.001 + 0.000 secs).\r\n",

    'A5 UID FETCH 1731,1732 (FLAGS INTERNALDATE RFC822.SIZE BODY.PEEK[HEADER.FIELDS (SUBJECT X-AUTO-BCC FROM DATE CONTENT-TYPE X-PRIORITY TO LIST-ARCHIVE REFERENCES MESSAGE-ID X-SNOOZED)])' =>
        "* 92 FETCH (UID 1731 FLAGS (\Seen) INTERNALDATE \"02-May-2017 16:32:24 -0500\" RFC822.SIZE 1940 BODY[HEADER.FIELDS (SUBJECT X-AUTO-BCC FROM DATE CONTENT-TYPE X-PRIORITY TO LIST-ARCHIVE REFERENCES MESSAGE-ID)] {240}\r\n".
        "Subject: =?utf-8?q?apt-listchanges=3A_news_for_shop?=\r\n".
        "To: root@shop.jackass.com\r\n".
        "Content-Type: text/plain; charset=\"utf-8\"\r\n".
        "Message-Id: <E1d5fPE-0005Vm-8L@shop>\r\n".
        "From: root <root@shop.jackass.com>\r\n".
        "Date: Tue, 02 May 2017 16:32:24 -0500\r\n".
        "\r\n".
        ")\r\n".
        "* 93 FETCH (UID 1732 FLAGS (\Seen) INTERNALDATE \"11-May-2017 14:28:40 -0500\" RFC822.SIZE 1089 BODY[HEADER.FIELDS (SUBJECT X-AUTO-BCC FROM DATE CONTENT-TYPE X-PRIORITY TO LIST-ARCHIVE REFERENCES MESSAGE-ID)] {240}\r\n".
        "Subject: =?utf-8?q?apt-listchanges=3A_news_for_shop?=\r\n".
        "To: root@shop.jackass.com\r\n".
        "Content-Type: text/plain; charset=\"utf-8\"\r\n".
        "Message-Id: <E1d8tlQ-00065l-4t@shop>\r\n".
        "From: root <root@shop.jackass.com>\r\n".
        "Date: Thu, 11 May 2017 14:28:40 -0500\r\n".
        "\r\n".
        ")\r\n".
        "A5 OK Fetch completed (0.001 + 0.000 secs).\r\n",

    'A5 UID FETCH 1731 BODYSTRUCTURE' =>
        "* 92 FETCH (UID 1731 BODYSTRUCTURE (\"text\" \"plain\" (\"charset\" \"utf-8\") NIL NIL \"7bit\" 1317 32 NIL NIL NIL NIL))\r\n".
        "A5 OK Fetch completed (0.001 + 0.000 secs).\r\n",

    'A5 UID FETCH 1731 BODY[]' =>
        "* 92 FETCH (UID 1731 BODY[] {706}\r\n".
        "Return-path: <root@shop.jackass.com>\r\n".
        "Envelope-to: root@shop.jackass.com\r\n".
        "Delivery-date: Tue, 02 May 2017 16:32:24 -0500\r\n".
        "Received: from root by shop with local (Exim 4.89)\r\n".
        "        (envelope-from <root@shop.jackass.com>)\r\n".
        "        id 1d5fPE-0005Vm-8L\r\n".
        "        for root@shop.jackass.com; Tue, 02 May 2017 16:32:24 -0500\r\n".
        "Auto-Submitted: auto-generated\r\n".
        "Subject: =?utf-8?q?apt-listchanges=3A_news_for_shop?=\r\n".
        "To: root@shop.jackass.com\r\n".
        "MIME-Version: 1.0\r\n".
        "Content-Type: text/plain; charset=\"utf-8\"\r\n".
        "Content-Transfer-Encoding: 7bit\r\n".
        "Message-Id: <E1d5fPE-0005Vm-8L@shop>\r\n".
        "From: root <root@shop.jackass.com>\r\n".
        "Date: Tue, 02 May 2017 16:32:24 -0500\r\n".
        "\r\n".
        "Test message\r\n".
        "\r\n".
        ")\r\n".
        "A5 OK Fetch completed (0.001 + 0.000 secs).\r\n",

    'A5 UID SEARCH (ALL) ALL BODY "debian" NOT DELETED NOT HEADER X-Auto-Bcc cypht' =>
        "* SEARCH 23 34 43 47 1680 1682 1683 1684 1685 1689 1690 1700 1701 1702 1705 1709 1715 1716 1717 1719 1720 1721 1724 1725 1726 1727 1730 1731 1732\r\n".
        "A5 OK Search completed (0.007 + 0.000 + 0.006 secs).\r\n",

    'A6 UID SEARCH (ALL) UID 1680,1682 BODY "debian" NOT DELETED NOT HEADER X-Auto-Bcc cypht' =>
        "* SEARCH 1680 1682\r\n".
        "A5 OK Search completed (0.007 + 0.000 + 0.006 secs).\r\n",

    'A5 UID FETCH 1731 (FLAGS INTERNALDATE BODY[HEADER])' =>
        "* 92 FETCH (UID 1731 FLAGS (\Seen) BODY[HEADER] {623}\r\n".
        "Return-path: <root@shop.jackass.com>\r\n".
        "Envelope-to: root@shop.jackass.com\r\n".
        "Delivery-date: Tue, 02 May 2017 16:32:24 -0500\r\n".
        "Received: from root by shop with local (Exim 4.89)\r\n".
        "        (envelope-from <root@shop.jackass.com>)\r\n".
        "        id 1d5fPE-0005Vm-8L\r\n".
        "        for root@shop.jackass.com; Tue, 02 May 2017 16:32:24 -0500\r\n".
        "Auto-Submitted: auto-generated\r\n".
        "Subject: =?utf-8?q?apt-listchanges=3A_news_for_shop?=\r\n".
        "To: root@shop.jackass.com\r\n".
        "MIME-Version: 1.0\r\n".
        "Content-Type: text/plain; charset=\"utf-8\"\r\n".
        "Content-Transfer-Encoding: 7bit\r\n".
        "Message-Id: <E1d5fPE-0005Vm-8L@shop>\r\n".
        "From: root <root@shop.jackass.com>\r\n".
        "Date: Tue, 02 May 2017 16:32:24 -0500\r\n".
        ")\r\n".
        "A5 OK Fetch completed (0.001 + 0.000 secs).\r\n",

    'A6 UID FETCH 1731 BODY[1]' =>
        "* 92 FETCH (UID 1731 BODY[1] {10}\r\n".
        "0123456789\r\n",

    'A5 UID FETCH 1731 BODY[1]' =>
            "* 92 FETCH (UID 1731 BODY[1] {1317}\r\n",

    'A5 UID FETCH 1:* (FLAGS BODY.PEEK[HEADER.FIELDS (DATE)])' =>

        "* 1 FETCH (UID 4 FLAGS (\Seen) BODY[HEADER.FIELDS (DATE)] {41}\r\n".
        "Date: Thu, 29 Jan 2015 11:56:27 -0600\r\n".
        "\r\n".
        ")\r\n".
        "* 93 FETCH (UID 1732 FLAGS (\Seen) BODY[HEADER.FIELDS (DATE)] {41}\r\n".
        "Date: Thu, 11 May 2017 14:28:40 -0500\r\n".
        "\r\n".
        ")\r\n".
        "A5 OK Fetch completed (0.004 + 0.000 + 0.003 secs).\r\n",

    'A5 CREATE "foo"' =>
        "A5 OK Create completed (0.004 + 0.000 + 0.003 secs).\r\n",

    'A5 RENAME "foo" "bar"' =>
        "A5 OK Rename completed (0.003 + 0.000 + 0.002 secs).\r\n",

    'A5 DELETE "bar"' =>
        "A5 OK Delete completed (0.003 + 0.000 + 0.002 secs).\r\n",

    'A5 UID STORE 1 +FLAGS (\Deleted)' =>
        "* 1 FETCH (FLAGS (\Deleted \Seen))\r\n".
        "A5 OK Store completed (0.001 + 0.000 secs).\r\n",

    'A6 UID STORE 1 +FLAGS (\Seen)' =>
        "* 1 FETCH (FLAGS (\Seen))\r\n".
        "A6 OK Store completed (0.001 + 0.000 secs).\r\n",

    'A7 UID STORE 1 +FLAGS (\Flagged)' =>
        "* 1 FETCH (FLAGS (\Flagged \Seen))\r\n".
        "A7 OK Store completed (0.001 + 0.000 secs).\r\n",

    'A8 UID STORE 1 -FLAGS (\Flagged)' =>
        "* 1 FETCH (FLAGS (\Unflag \Seen))\r\n".
        "A8 OK Store completed (0.001 + 0.000 secs).\r\n",

    'A9 UID STORE 1 +FLAGS (\Answered)' =>
        "* 1 FETCH (FLAGS (\Answered \Seen))\r\n".
        "A9 OK Store completed (0.001 + 0.000 secs).\r\n",

    'A10 UID STORE 1 -FLAGS (\Seen)' =>
        "* 1 FETCH (FLAGS (\Seen))\r\n".
        "A10 OK Store completed (0.001 + 0.000 secs).\r\n",

    'A11 UID STORE 1 -FLAGS (\Deleted)' =>
        "* 1 FETCH (FLAGS (\Deleted \Seen))\r\n".
        "A11 OK Store completed (0.001 + 0.000 secs).\r\n",

    'A12 UID STORE 1 +FLAGS (bar)' =>
        "* FLAGS (\Answered \Flagged \Deleted \Seen \Draft bar)\r\n".
        "* OK [PERMANENTFLAGS (\Answered \Flagged \Deleted \Seen \Draft bar \*)] Flags permitted.\r\n".
        "A12 OK Store completed (0.001 + 0.000 secs).\r\n",

    'A13 UID MOVE 1 "Sent"' =>
        "A13 OK No messages found (0.003 + 0.000 + 0.002 secs).\r\n",

    'A14 EXPUNGE' =>
        "* 1 EXPUNGE\r\n".
        "A14 OK Expunge completed (0.006 + 0.000 + 0.005 secs).\r\n",

    'A15 UID COPY 1 "Sent"' =>
        "A15 OK No messages found (0.001 + 0.000 secs).\r\n",

    'A5 GETQUOTAROOT "INBOX"' =>
        "* QUOTAROOT INBOX \"\"\r\n".
        "* QUOTA \"\" (STORAGE 10 512)\r\n".
        "A5 OK Getquota completed\r\n",

    'A5 GETQUOTA ""' =>
        "* QUOTA \"\" (STORAGE 10 512)\r\n".
        "A5 OK Getquota completed\r\n",

    'A5 UNSELECT' =>
        "A5 OK Unselect completed\r\n",

    'A5 ID ("name" "Hm_IMAP" "version" "3.0" "vendor" "Hastymail Development Group" "support-url" "http://hastymail.org/contact_us/")' =>
        "* ID NIL\r\n".
        "a023 OK ID completed\r\n",

    'A7 UID SORT (ARRIVAL) US-ASCII ALL' =>
        "* SORT 1731 1732\r\n".
        "A5 OK SORT completed\r\n",

    'A5 UID SORT (ARRIVAL) US-ASCII ALL' =>
        "* SORT 2 84 882\r\n".
        "A5 OK SORT completed\r\n",

    'A5 UID SEARCH X-GM-RAW "foo"' =>
        "* SEARCH 123 12344 5992\r\n".
        "A5 OK SEARCH (Success)\r\n",

    'A5 COMPRESS DEFLATE' =>
        "A5 OK DEFLATE active\r\n",

    'A5 TEST MULTIBYTE' =>
        "A1 OK {12}\r\n".
        "Literäääl\r\n".
        "A2 OK {7}\r\n".
        "Literal\r\n",

    'A5 UID FETCH 1731,1732 (FLAGS INTERNALDATE RFC822.SIZE BODYSTRUCTURE BODY.PEEK[HEADER.FIELDS (SUBJECT FROM DATE CONTENT-TYPE TO)] BODY.PEEK[1.MIME])' =>
        "* 92 FETCH (UID 1731 FLAGS (\Seen) INTERNALDATE \"02-May-2017 16:32:24 -0500\" RFC822.SIZE 1940 BODYSTRUCTURE ((\"text\" \"plain\" (\"charset\" \"utf-8\") NIL NIL \"7bit\" 1317 32 NIL NIL NIL NIL) \"text\" \"calendar\" (\"charset\" \"utf-8\" \"method\" \"REQUEST\") NIL NIL \"base64\" 1234 NIL (\"attachment\" (\"filename\" \"meeting.ics\")) NIL NIL) BODY[HEADER.FIELDS (SUBJECT FROM DATE CONTENT-TYPE TO)] {240}\r\n" .
        "Subject: Meeting Invitation\r\n" .
        "To: root@shop.jackass.com\r\n" .
        "Content-Type: multipart/mixed; boundary=\"boundary123\"\r\n" .
        "From: root@shop.jackass.com\r\n" .
        "Date: Tue, 02 May 2017 16:32:24 -0500\r\n" .
        "\r\n" .
        " BODY[1.MIME] {45}\r\n" .
        "Content-Type: text/calendar; method=REQUEST\r\n" .
        "\r\n" .
        ")\r\n" .
        "* 93 FETCH (UID 1732 FLAGS (\Seen) INTERNALDATE \"11-May-2017 14:28:40 -0500\" RFC822.SIZE 1089 BODYSTRUCTURE ((\"text\" \"plain\" (\"charset\" \"utf-8\") NIL NIL \"7bit\" 1317 32 NIL NIL NIL NIL) \"application\" \"ics\" (\"name\" \"event.ics\") NIL NIL \"base64\" 567 NIL (\"attachment\" (\"filename\" \"event.ics\")) NIL NIL) BODY[HEADER.FIELDS (SUBJECT FROM DATE CONTENT-TYPE TO)] {240}\r\n" .
        "Subject: Another Event\r\n" .
        "To: root@shop.jackass.com\r\n" .
        "Content-Type: multipart/mixed; boundary=\"boundary456\"\r\n" .
        "From: root@shop.jackass.com\r\n" .
        "Date: Thu, 11 May 2017 14:28:40 -0500\r\n" .
        "\r\n" .
        " BODY[1.MIME] {35}\r\n" .
        "Content-Type: application/ics\r\n" .
        "\r\n" .
        ")\r\n" .
        "A5 OK Fetch completed (0.001 + 0.000 secs).\r\n",

    'A5 UID FETCH 1733 (FLAGS INTERNALDATE RFC822.SIZE BODYSTRUCTURE BODY.PEEK[HEADER.FIELDS (SUBJECT FROM DATE CONTENT-TYPE TO)])' =>
        "* 94 FETCH (UID 1733 FLAGS (\Seen) INTERNALDATE \"12-May-2017 10:15:30 -0500\" RFC822.SIZE 2500 BODYSTRUCTURE (\"text\" \"plain\" (\"charset\" \"utf-8\") NIL NIL \"7bit\" 1500 45 NIL NIL NIL NIL) BODY[HEADER.FIELDS (SUBJECT FROM DATE CONTENT-TYPE TO)] {180}\r\n" .
        "Subject: Regular Email\r\n" .
        "To: root@shop.jackass.com\r\n" .
        "Content-Type: text/plain; charset=\"utf-8\"\r\n" .
        "From: root@shop.jackass.com\r\n" .
        "Date: Fri, 12 May 2017 10:15:30 -0500\r\n" .
        "\r\n" .
        ")\r\n" .
        "A5 OK Fetch completed (0.001 + 0.000 secs).\r\n",
);
