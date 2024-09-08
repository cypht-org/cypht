<?php

return [
    /*
    | ----------------------------------------------
    | Constants used for dynamic login configuration
    | ----------------------------------------------
    |
    | SECURITY ALERT ! MAKE SURE THAT THIS FILE IS NOT ACCESSIBLE BY THE BROWSER !
    |
    | The dynamic login module set tries to autodetect mail server configurations,
    | but you can configure it to act specifically with the following settings.
    |
    | Set this to true to use the hostname in the URL used to access Cypht to
    | determine the domain for mail services (IMAP/SMTP). If this is set to
    | true, the mail service selection dropdown is not displayed on the login page.
    |
    */
    'dynamic_host' => env('DYNAMIC_HOST', true),

    /*
    | If dynamic_host is true, you can strip the subdomain on the url with this
    | setting. Leave empty for no subdomain. For example, if a user accesses Cypht
    | at webmail.example.com, set this to "webmail" to use just example.com for the
    | IMAP/SMTP services.
    |
    */
    'dynamic_host_subdomain' => env('DYNAMIC_HOST_SUBDOMAIN', ''),

    /*
    | Set this to true to use the domain portion of an E-mail address used as a
    | username during login for mail services. Even if this is set to false, it
    | will still by attempted if dynamic_host is disabled and "other" is selected
    | from the mail service dropdown. If set to true, the mail service selection
    | dropdown is not displayed on the login page.
    */
    'dynamic_user' => env('DYNAMIC_USER', false),

    /*
    | Subdomain to prepend to the mail service domain for SMTP. If the mail service
    | domain is example.com, but the SMTP server is at smtp.example.com, you would
    | set this to "smtp". Leave blank for no subdomain.
    */
    'dynamic_smtp_subdomain' => env('DYNAMIC_SMTP_SUBDOMAIN', ''),

    /*
    | Subdomain to prepend to the mail service domain for IMAP. If the mail
    | service is example.com, but the IMAP service is at "mail", you would
    | set this to "mail". Leave blank for no subdomain.
    */
    'dynamic_mail_subdomain' => env('DYNAMIC_MAIL_SUBDOMAIN', ''),
];
