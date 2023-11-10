<?php

return [
    /* 

    | -----------------------------------------------------------------------------
    | General settings
    | -----------------------------------------------------------------------------
    |
    | ------------
    | Session Type
    | ------------
    |
    | Sessions are how the server maintains your logged in state. Cypht supports
    | standard PHP sessions, as well as its own completely independent session
    | system that uses memcached or a database for storage. In order to use
    | database sessions, the database must be correctly configured in the "DB
    | Support" section and the hm_user_session table must be created. In order to
    | use Memcached sessions, the memcached server must be correctly configured
    | in the "Memcached Support" section. In order to use Redis session, the
    | Redis server must be configured in the "Redis Support" section.
    | Valid values for this setting:
    | PHP     Standard PHP session handlers
    | DB      Custom database based sessions
    | MEM     Custom Memcached based sessions
    | REDIS   Custom Redis based sessions
    | custom  Create your own session class. See the modules/site/lib.php file for
            more info
    */
    'session_type' => env('SESSION_TYPE', 'PHP'),

    /*
    | -------------------
    | Authentication Type
    | -------------------
    |
    | This setting defines how Cypht will authenticate your username and password
    | when you login. If you want to use a database it must be correctly configured
    | in the "DB Support" section and the hm_user table must be created. There are
    | 3 PHP cli scripts to help manage database users in the scripts/ directory (
    | create_account.php, delete_account.php, and update_password.php). If you want
    | to authenticate against an IMAP server, you must setup the imap_auth_* settings
    | below. If you want to authenticate against an LDAP server,
    | you must setup the ldap_auth_* settings. Finally, if you want to let users
    | pick from a list of popular mail services or try to auto-discover a mail
    | system, set this to dynamic and make sure the dynamic_login module set is
    | enabled in the "Module Sets" section of this file.
    |
    | Valid values for this setting:
    |
    | DB       Authenticate against the database
    | LDAP     Authenticate against an LDAP server
    | IMAP     Authenticate using an IMAP server
    | dynamic  Let the user choose from a list, or try to auto discover the mail
    |         services based on the email domain
    | custom   Create your own auth class. See the modules/site/lib.php file for
    |          more info
    */
    'auth_type' => env('AUTH_TYPE', 'DB'),

    /*
    | -------------------
    | LDAP Authentication
    | -------------------
    |
    | If auth_type is set to LDAP, configure the LDAP server to authenticate against
    | with the following settings, otherwise these are ignored.
    |
    |
    | The hostname or IP address of the LDAP server to authenticate to
    */
    'ldap_auth_server' => env('LDAP_AUTH_SERVER', 'localhost'),

    /*
    |
    | The port the LDAP server is listening on.
    |
    */
    'ldap_auth_port' => env('LDAP_AUTH_PORT', 389),

    /*
    |
    | Enable TLS/SSL connections. Leave blank or set to false to disable. Set to
    | true to enable TLS connections.
    |
    */
    'ldap_auth_tls' => env('LDAP_AUTH_TLS'),

    /*
    |
    | The "base dn" of the LDAP server
    |
    */
    'ldap_auth_base_dn' => env('LDAP_AUTH_BASE_DN', 'example,dc=com'),

    /*
    | -------------------
    | IMAP Authentication
    | -------------------
    |
    | If auth_type is set to IMAP, configure the IMAP server to authenticate against
    | with the following settings, otherwise these are ignored.
    |
    | This is just a label used in the UI. It can be set to anything
    */
    'imap_auth_name' => env('IMAP_AUTH_NAME', 'Gandi'),

    /*
    |
    | The hostname or IP address of the IMAP server to authenticate to
    |
    */
    'imap_auth_server' => env('IMAP_AUTH_SERVER', 'mail.gandi.net'),

    /*
    |
    | The hostname or IP address of the IMAP server to authenticate to
    |
    */
    'imap_auth_port' => env('IMAP_AUTH_PORT', 993),

    /*
    |
    | Enable TLS/SSL connections. Leave blank or set to false to disable. Set to
    | true to enable TLS connections. If you want to use IMAP STARTTLS, do NOT
    | enable this. This is only for TLS enabled sockets (typically on port 993).
    |
    */
    'imap_auth_tls' => env('IMAP_AUTH_TLS', true),

    /*
    |
    | The hostname/IP address and port sieve is listening on. Example: example.org:4190
    | Note: Add tls:// prefix to enable explicit STARTTLS
    |
    */
    'imap_auth_sieve_conf_host' => env('IMAP_AUTH_SIEVE_CONF_HOST', 'tls://mail.gandi.net:4190'),

    /*
    | -------------------
    | Default SMTP Server
    | -------------------
    |
    | You can set a default SMTP server for all Cypht users. Authentication will be
    | done with the users login credentials, so this only makes sense if you are
    | using IMAP for authentication. Leave these values blank to disable a
    | default SMTP server, otherwise fill in the required values below
    |
    | This is just a label used in the UI. It can be set to anything
    */
    'default_smtp_name' => env('DEFAULT_SMTP_NAME'),

    /*
    |
    | The hostname or IP address of the SMTP server
    |
    */
    'default_smtp_server' => env('DEFAULT_SMTP_SERVER'),

    /*
    |
    | The port the SMTP server is listening on.
    |
    */
    'default_smtp_port' => env('DEFAULT_SMTP_PORT'),

    /*
    |
    | Enable TLS/SSL connections. Leave blank or set to false to disable. Set to
    | true to enable TLS connections.
    |
    */
    'default_smtp_tls' => env('DEFAULT_SMTP_TLS'),

    /*
    |
    | If your SMTP service does not require authentication, you can disable it
    | by setting the following to true.
    |
    */
    'default_smtp_no_auth' => env('DEFAULT_SMTP_NO_AUTH'),

    /*
    | ----------------
    | Settings Storage
    | ----------------
    |
    | Cypht supports 3 methods for saving user settings between logins. File based
    | settings, database table or custom implementation. To store settings in a
    | database, it must be configured in the "DB Support" section and the
    | hm_user_settings table must be created. To store settings on the filesystem,
    | the user_settings_dir must be created and the webserver software must be able
    | to write to it. For custom implementations, see Hm_User_Config_File.
    |
    | Valid values for this setting:
    | file    Store user settings in the filesystem
    | DB      Store user settings in a database
    | custom  Store user settings via custom implementation. Specify class name
    |      after colon, e.g. custom:Custom_User_Config
    */

    'user_config_type' => env('USER_CONFIG_TYPE', 'file'),

    /*
    | -----------------
    | Settings Location
    | -----------------
    |
    | If user_config_type is set to file, this must be set to an existing directory
    | that the webserver software can read and write to. If settings storage is set
    | to DB, this is ignored. It should not be inside the webserver document root.
    | 
    */
    'user_settings_dir' => env('USER_SETTINGS_DIR', '/var/lib/hm3/users'),

    /*
    | -------------------
    | Attachment Location
    | -------------------
    |
    | If user_config_type is set to file, this must be set to an existing directory
    | that the webserver software can read and write to. If settings storage is set
    | to DB, this is ignored. It should not be inside the webserver document root.
    | 
    */
    'attachment_dir' => env('ATTACHMENT_DIR', '/var/lib/hm3/attachments'),

    /*
    | -------------------------
    | Application Data Location
    | -------------------------
    |
    | Some Cypht module sets have their own ini files that need to be readable by
    | the webserver software, but not writable, and definitely not inside the
    | webserver document root.
    | 
    */
    'app_data_dir' => env('APP_DATA_DIR', '/var/lib/hm3/app_data'),

    /*
    | --------------------
    | Disable origin check
    | --------------------
    |
    | To help protect against CSRF attacks, Cypht checks origin headers to confirm
    | that the source and target origin domains match. If you are using proxies this
    | could create a problem making it impossible to login. Change this to true to
    | disable the origin check.
    | 
    */
    'disable_origin_check' => env('DISABLE_ORIGIN_CHECK', false),

    /*
    | -----------
    | Admin Users
    | -----------
    |
    | You can define a comma delimited list of admin users that Cypht will grant
    | special rights to. Currently this only enables the "create account" link in
    | the account module set that provides a form to create a new account. This is
    | only used if the auth_type is set to DB. Leave this blank if you don't want
    | to define any admin users, or are using IMAP authentication.
    | 
    */
    'admin_users' => env('ADMIN_USERS'),

    /*
    | -------------
    | Cookie Domain
    | -------------
    |
    | By default Cypht uses the server name used in the request to determine
    | the domain name to set the cookie for. Configurations that use a reverse
    | proxy might need to define the domain name used for cookies. Leave this
    | blank to let Cypht automatically determine the domain. You can also use
    | the special value of "none" to force Cypht to NOT set the cookie domain
    | property at all. This is not recommended unless you know what you are
    | doing!
    | 
    */
    'cookie_domain' => env('COOKIE_DOMAIN'),

    /*
    | -----------
    | Cookie Path
    | -----------
    |
    | By default Cypht uses the request URI to determine the cookie path to set
    | the cookie for. Configurations that use mod_rewrite might need to define
    | the path used for cookies. E.g. /cypht/embedded?page=compose will set path
    | to /cypht/embedded/ which won't send the cookies back to the server. In that
    | case set cookie_path=/cypht/. Leave this blank to let Cypht automatically
    | determine the path. You can also use the special value of "none" to force
    | Cypht to NOT set the cookie path property at all. This is not recommended
    | unless you know what you are doing!
    | 
    */
    'cookie_path' => env('COOKIE_PATH'),

    /*
    | ---------------------
    | Outbound Email Domain
    | ---------------------
    |
    | Default domain used for outbound email addresses when using IMAP auth and
    | users don't login with a full email address. Users can customize this with
    | the profiles module which will override this default
    | 
    */
    'default_email_domain' => env('DEFAULT_EMAIL_DOMAIN'),

    /*
    | -------------------
    | Auto-Create Profile
    | -------------------
    |
    | When a user logs in and they have only 1 IMAP server and 1 SMTP server, and
    | no configured profiles - enabling this option will auto-create a profile for
    | them. Email and reply-to addresses will use the default_email_domain if
    | set, otherwise it will fallback to the domain Cypht is hosted on.
    | 
    */
    'autocreate_profile' => env('AUTO_CREATE_PROFILE'),

    /*
    | --------------------
    | Redirect After Login
    | --------------------
    |
    | You can login directly to any page in Cypht by going to the correct url before
    | logging in, but that is not very user-friendly. To redirect users to a url
    | after login, add the url arguments below (everything in the url after, but
    | including, the question mark). You must use double quotes around the value
    | otherwise it will cause an ini parsing error. To redirect users after login
    | to the combined unread view you would use:
    |
    | redirect_after_login="?page=message_list&list_path=unread"
    | 
    */
    'redirect_after_login' => env('REDIRECT_AFTER_LOGIN'),

    /*
    | ----------------
    | Application Name
    | ----------------
    |
    | This label is used in the UI to reference the program - you can change it to
    | "Your awesome webmail" to replace the Cypht name used in various places.
    | 
    */
    'app_name' => env('APP_NAME', 'Cypht'),

    /*
    | ---------------
    | Force Mobile UI
    | ---------------
    |
    | Cypht will detect mobile devices and display a mobile optimized UI. If you want
    | to aways use this UI regardless of device, set this to true
    | 
    */
    'always_mobile_ui' => env('ALWAYS_MOBILE_UI'),

    /*
    | ----------------
    | Default Language
    | ----------------
    |
    | Users can select from available interface languages on the site settings page.
    | This sets the default for a user who has not done so. Valid values are 2 character
    | langauge codes that have matching language definitions in the language/ folder.
    | 
    */
    'default_language' => env('DEFAULT_LANGUAGE', 'en'),

    /*
    | ----------------------
    | JavaScript Compression
    | ----------------------
    |
    | When the configuration script is run, all JavaScript files are concatenated
    | and optionally compressed. To compress the content, define a command and its
    | options below. Cypht does not come with compresson software, so you must
    | install and configure that separately. Leave blank or set to false to disable
    | external compression. Compression software must be able to handle ES6.
    | Example:
    |     js_compress='uglifyjs.terser -c -m --verbose --warn'
    | 
    */
    'js_compress' => env('JS_COMPRESS', false),

    /*
    | ---------------
    | CSS Compression
    | ---------------
    |
    | When the configuration script is run, all CSS files are concatenated and
    | optionally compressed. To compress the content, define a command and its
    | options below. Cypht does not come with compresson software, so you must
    | install and configure that separately. Leave blank or set to false to disable
    | external compression.
    |
    | Example:
    |    css_compress='java -jar /usr/local/lib/yuicompressor-2.4.8.jar --type css'
    |
    */
    'css_compress' => env('CSS_COMPRESS', false),

    /*
    | ----------------------
    | Caching Server Support
    | ----------------------
    |
    | Cypht can use Redis or Memcache to improve performance, as well as to store
    | user sessions. Configure Redis or Memcached below and Cypht will
    | automatically use them for caching. All data cached for a user in either
    | system is encrypted. Currently, the feeds, and IMAP modules will use
    | the configured cache. If both Redis and Memcached are configured, Redis will
    | be used for the cache.
    |
    | If you want to use the user session as a cache, uncomment the line below and
    | set to true. THIS IS NOT RECOMMENDED. Cypht uses parallel requests to the
    | server, and using the session as a cache is likely to cause race conditions
    | and integrity issues. If you are running Cypht in an "embedded" mode with
    | only one email source, this option is less likely to be a problem.
    | 
    | 
    | 'allow_session_cache' => env('ALLOW_SESSION_CACHE', false),
    | 'cache_class' => env('CACHE_CLASS')
    */


    /*
    | -------------
    | Redis Support
    | -------------
    |
    | Configure Redis details below to use it for caching
    */
    'enable_redis' => env('ENABLE_REDIS', true),

    'redis_server' => env('REDIS_SERVER', '127.0.0.1'),

    'redis_port' => env('REDIS_PORT', 6379),

    'redis_index' => env('REDIS_INDEX', 1),

    'redis_pass' => env('REDIS_PASS'),

    'redis_socket' => env('REDIS_SOCKET', '/var/run/redis/redis-server.sock'),

    /*
    | -----------------
    | Memcached Support
    | -----------------
    |
    | Configure Memcached details below to use it for caching
    */
    'enable_memcached' => env('ENABLE_MEMCACHED',true),

    'memcached_server' => env('MEMCACHED_SERVER','127.0.0.1'),

    'memcached_port' => env('MEMCACHED_PORT',11211),

    /*
    |
    | If you need SASL authentication for memcached, set the following to true
    | and add the username and password to authenticate with
    |
    */
    'memcached_auth' => env('MEMCACHED_AUTH',false),

    'memcached_user' => env('MEMCACHED_USER'),
    
    'memcached_pass' => env('MEMCACHED_PASS')
];
