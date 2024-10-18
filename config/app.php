<?php

return [
    /*
    |
    | -----------------------------------------------------------------------------
    | General settings
    | -----------------------------------------------------------------------------
    */

    /*
    | ------------
    | Session Type
    | ------------
    |
    | Sessions are how the server maintains your logged in state. Cypht supports
    | standard PHP sessions, as well as its own completely independent session
    | system that uses memcached or a database for storage. In order to use
    | database sessions, the database must be correctly configured in the "DB
    | Support" section and the hm_user_session table must be created (see
    | config/database.php for more information). In order to use Memcached
    | sessions, the memcached server must be correctly configured in the
    | "Memcached Support" section. In order to use Redis session, the Redis
    | server must be configured in the "Redis Support" section.
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
    | in the "DB Support" section and the hm_user table must be created (see
    | config/database.php for more information). There are 3 PHP cli scripts to
    | help manage database users in the scripts/ directory (create_account.php,
    | delete_account.php, and update_password.php). If you want to authenticate
    | against an IMAP server, you must setup the imap_auth_* settings below. If
    | you want to authenticate against an LDAP server, you must setup the ldap_auth_*
    | settings. Finally, if you want to let users pick from a list of popular mail
    | services or try to auto-discover a mail system, set this to dynamic and make
    | sure the dynamic_login module set is enabled in the "Module Sets" section of
    | this file.
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
    | hm_user_settings table must be created (see config/database.php for more
    | information). To store settings on the filesystem, the user_settings_dir must
    | be created and the webserver software must be able to write to it. For custom
    | implementations, see Hm_User_Config_File.
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
    | This must be set to an existing directory that the webserver software
    | can read and write to. It should not be inside the webserver document root.
    |
    */
    'attachment_dir' => env('ATTACHMENT_DIR', '/var/lib/hm3/attachments'),

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
    | This sets the default for a user who has not done so. Valid values are the
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
    'enable_memcached' => env('ENABLE_MEMCACHED', true),

    'memcached_server' => env('MEMCACHED_SERVER', '127.0.0.1'),

    'memcached_port' => env('MEMCACHED_PORT', 11211),

    /*
    |
    | If you need SASL authentication for memcached, set the following to true
    | and add the username and password to authenticate with
    |
    */
    'memcached_auth' => env('MEMCACHED_AUTH', false),

    'memcached_user' => env('MEMCACHED_USER'),

    'memcached_pass' => env('MEMCACHED_PASS'),

    /*
    | -------------------
    | Allow Long Sessions
    | -------------------
    |
    | Cypht logins only last as long as the browser is open. Closing the browser,
    | or moving to a new network, will cause you to be logged out. This setting
    | provides users with a "stay logged in" option during login that will set the
    | sesison lifetime to a default of 30 days, and disable the IP address check.
    |
    | USE WITH CAUTION SINCE THIS DISABLES SOME SESSION PROTECTIONS.
    |
    | Specifically:
    | - Session cookies stay active after a browser restart making them more
    |   susceptible to snooping
    | - The browser "fingerprint" was use to help protect against session hijacking
    |   includes the client's IP. With this option enabled a client IP can change
    |   mid-session and it won't log them out
    */
    'allow_long_session' => env('ALLOW_LONG_SESSION', false),

    /*
    |
    | Set the session lifetime in days. Only applies if allow_long_session is set to
    | true, and a user checks the box to "stay logged in" during login.
    */
    'long_session_lifetime' => env('LONG_SESSION_LIFETIME', 30),

    /*
    | --------------------------
    | Browser Encryption Options
    | --------------------------
    |
    | Cypht can use the Forge JavaScript encryption library to encrypt AJAX
    | responses and data stored in browser local storage. Enabling either one of
    | these options causes the Forge JavaScript library to be included. This adds
    | about 70KB to the page size (when gzipped).
    |
    | Use AES encryption for AJAX responses. Set to true to enable, leave blank or
    | set to false to disable.
    */
    'encrypt_ajax_requests' => env('ENCRYPT_AJAX_REQUESTS'),

    /*
    | Use AES encryption for data stored in the browser local storage. Set to true
    | to enable, or leave blank or set to false to disable.
    */
    'encrypt_local_storage' => env('ENCRYPT_LOCAL_STORAGE'),

    /*
    | -----------------------
    | Allow client IP changes
    | -----------------------
    |
    | By default Cypht will log you out if your client IP address changes. This is
    | an extra layer of protection against session hijacking, but it's not uncommon
    | for your client IP to change. Change this to true if you want to disable this
    | check
    */
    'disable_ip_check' => env('DISABLE_IP_CHECK', false),

    /*
    | --------------------------
    | Allow remote image sources
    | --------------------------
    |
    | WARNING: Using this feature could leak information to external sources.
    |
    | By default, Cypht will not allow external images to be loaded when viewing an
    | HTML formatted email message. A Content Security Policy header limits images
    | sources to "self" only and the message content is filtered to remove any external
    | resource. If you really want the ability to view external images in an email,
    | you first need to change the following setting to true. When viewing an HTML
    | formatted message, you will now have a link before the message body called "Allow
    | Images" that will reload the message part with external images visible.
    */
    'allow_external_image_sources' => env('ALLOW_EXTERNAL_IMAGE_SOURCES', true),

    /*
    | ------------------
    | Single server mode
    | ------------------
    |
    | This setting restricts Cypht to only using a single email source (
    | IMAP) and the default SMTP server defined in this file. It modifies the UI to
    | remove the ability to add other email sources, and removes the password
    | restriction when saving data between logins. You must use either IMAP
    | authentication for this setting to be enforced, and auth server will be the
    | single email source users have access to. If you enable this after users
    | have saved any settings, they will be lost (or if you disable it after they
    | have saved any settings). WARNING: USER SETTINGS SAVED TO THE SERVER WILL NOT
    | BE ENCRYPTED WITH THIS ENABLED
    */
    'single_server_mode' => env('SINGLE_SERVER_MODE', false),

    /*
    | -------------------
    | Integration options
    | -------------------
    |
    | Cypht does a few things to make it more secure by default, but these features
    | can make it difficult to integrate Cypht into 3rd party software. Specifically,
    | Cypht disables PHP "super globals", and sets a restrictive PHP "open basedir"
    | setting, tweaks PHP ini settings to increase security, and uses a browser
    | "fingerprint" to improve session security. You can disable each with the
    | following options:
    |
    | Don't empty PHP super globals
    */
    'disable_empty_superglobals' => env('DISABLE_EMPTY_SUPERGLOBALS', false),

    /*
    |
    | Don't apply open basedir restrictions
    */
    'disable_open_basedir' => env('DISABLE_OPEN_BASE_DIR', false),

    /*
    |
    | Don't tweak ini settings
    */
    'disable_ini_settings' => env('DISABLE_INI_SETTINGS', false),

    /*
    |
    | Don't use a browser fingerprint
    */
    'disable_fingerprint' => env('DISABLE_FINGERPRINT', false),

    /*
    | You can create your own custom authentication and session classes using the
    | site module set, however you might want those classes located somewhere else
    | outside of the Cypht code base. By setting session_type and auth_type to custom,
    | you can control what class is used with the following settings
    */
    'auth_class' => env('AUTH_CLASS'),
    'session_class' => env('SESSION_CLASS'),

    /*
    | -----------------------------------------------------------------------------
    | Modules
    | -----------------------------------------------------------------------------
    |
    |
    | -----------
    | Module Sets
    | -----------
    |
    | Cypht uses module sets to separate functionality in the program. Module sets
    | can be enabled and disabled independently by commenting out or uncommenting
    | the assignments below. Add a new assignment to enable your own module sets.
    |
    |
    | ----
    | Core
    | ----
    | Handles page layout, login/logout, and the default settings pages. This set
    | is required.
    */
    'modules' => explode(',', env('CYPHT_MODULES','core,contacts,local_contacts,feeds,imap,smtp,account,idle_timer,calendar,themes,nux,developer,history,saved_searches,advanced_search,highlights,profiles,inline_message,imap_folders,keyboard_shortcuts,tags')),
    // 'modules' => [
    //     /*
    //     |  ----
    //     | Core
    //     | ----
    //     | Handles page layout, login/logout, and the default settings pages. This set
    //     | is required.
    //     */
    //     'core',
    //     /*
    //     |  --------
    //     | Contacts
    //     | --------
    //     | Contact support. This module requires that at least one "backend" contacts
    //     | module be enabled (ldap_contacts, gmail_contacts, or local_contacts). You
    //     | can enable all the backends you want to support.
    //     */
    //     'contacts',
    //     /*
    //     | Local contact support. Simple, locally stored contacts backend
    //     */
    //     'local_contacts',

    //     /*
    //     | LDAP contact support. Use an LDAP server to store contacts.
    //     */
    //     'ldap_contacts',

    //     /*
    //     | Gmail contact support. Read-only support for Gmail contacts. Only available
    //     | if you have a Gmail account enabled that uses OAuth2 authentication
    //     */
    //     'gmail_contacts',

    //     /*
    //     | -----
    //     | Feeds
    //     | -----
    //     |
    //     | RSS/ATOM feed support
    //     */
    //     'feeds',

    //     /*
    //     | -----
    //     | JMAP
    //     | -----
    //     |
    //     | JSON Meta Application Protocol for emails
    //     */
    //     // 'jmap',

    //     /*
    //     | -----
    //     | IMAP
    //     | -----
    //     |
    //     | IMAP email account support.
    //     */
    //     'imap',

    //     /*
    //     | -----------------------
    //     | 2 factor authentication
    //     | -----------------------
    //     |
    //     | This module enables 2 factor authentication using TOTP (compatible with
    //     | Google Authenticator).
    //     */
    //     // '2fa',

    //     /*
    //     | -----
    //     | SMTP
    //     | -----
    //     |
    //     | Send outbound email using SMTP servers
    //     */
    //     'smtp',

    //     /*
    //     | -------
    //     | Account
    //     | -------
    //     |
    //     | UI features for admins to create accounts, and for users to update passwords
    //     | (when using the built-in DB authentication)
    //     */
    //     'account',

    //     /*
    //     | ----------
    //     | Idle timer
    //     | ----------
    //     |
    //     | Controls idle time and automatic logout
    //     */
    //     'idle_timer',

    //     /*
    //     | ---------------------
    //     | Desktop notifications
    //     | ---------------------
    //     |
    //     | Enable desktop notifications for new messages
    //     */
    //     // 'desktop_notifications',
    //     /*

    //     /*
    //     | --------
    //     | Calendar
    //     | --------
    //     |
    //     | Basic calendar
    //     */
    //     'calendar',

    //     /*
    //     | ------
    //     | Themes
    //     | ------
    //     |
    //     | Change the UI using CSS
    //     */
    //     'themes',

    //     /*
    //     | ----
    //     | NUX
    //     | ----
    //     |
    //     | Friendly new user experience. Quickly add common email services, and view
    //     | development updates
    //     */
    //     'nux',

    //     /*
    //     | ---------
    //     | Developer
    //     | ---------
    //     |
    //     | For development, provides resources and installation details. Only available
    //     | in "debug mode"
    //     */
    //     'developer',

    //     /*
    //     | -------
    //     | Github
    //     | -------
    //     |
    //     | Github repository tracking.
    //     */
    //     // 'github',

    //     /*
    //     | ---------
    //     | reCAPTCHA
    //     | ---------
    //     |
    //     | Use the reCAPTCHA server on login.
    //     */
    //     // 'recaptcha',

    //     /*
    //     | ---------
    //     | WordPress
    //     | ---------
    //     |
    //     | WordPress.com notifications.
    //     */
    //     // 'wordpress',

    //     /*
    //     | -------
    //     | History
    //     | -------
    //     |
    //     | Simple list of messages read since login
    //     */
    //     'history',

    //     /*
    //     | --------------
    //     | Saved searches
    //     | --------------
    //     |
    //     | Save and re-run searches easily
    //     */
    //     'saved_searches',

    //     /*
    //     | ---------------
    //     | Advanced search
    //     | ---------------
    //     |
    //     | Enable the advanced search form
    //     */
    //     'advanced_search',

    //     /*
    //     | --------------------
    //     | Message highlighting
    //     | --------------------
    //     |
    //     | Create custom rules to highlight messages in lists with different colors
    //     */
    //     'highlights',

    //     /*
    //     | -----
    //     | NASA
    //     | -----
    //     |
    //     | Access the NASA APOD API content
    //     */
    //     // 'nasa',

    //     /*
    //     | --------
    //     | Profiles
    //     | --------
    //     |
    //     | Profiles to set reply-to, name, and signature to associated email accounts
    //     */
    //     'profiles',

    //     /*
    //     | --------------
    //     | Inline message
    //     | --------------
    //     |
    //     | View messages inline in a reading pane instead of on a new page
    //     */
    //     'inline_message',

    //     /*
    //     | ------------
    //     | IMAP folders
    //     | ------------
    //     |
    //     | Support for adding/renaming/deleting folders in IMAP accounts
    //     */
    //     'imap_folders',

    //     /*
    //     | ------------------
    //     | Keyboard Shortcuts
    //     | ------------------
    //     |
    //     | Enables configurable keyboard shortcuts for navigations and actions
    //     */
    //     'keyboard_shortcuts',

    //     /*
    //     | -------------
    //     | Sieve Filters
    //     | -------------
    //     |
    //     | Enables configurable Sieve based IMAP filters
    //     */
    //     // 'sievefilters',

    //     /*
    //     | -----
    //     | Site
    //     | -----
    //     |
    //     | Site specific overrides. Used to control other module sets without hacking
    //     | the code.
    //     */
    //     // 'site',

    //     /*
    //     | -------------
    //     | Dynamic login
    //     | -------------
    //     |
    //     | Allows user to authenticate against a list of popular mail services, or to
    //     | auto-discover the services for the specified email address. The auth_type
    //     | setting must be set to "dynamic", otherwise this module set does not do
    //     | anything
    //     */
    //     // 'dynamic_login',

    //     /*
    //     | ----------
    //     | API login
    //     | ----------
    //     |
    //     | Allows an API based login that returns a JSON response containing the session
    //     | and hm_id values needed to create a login session. You will need to set the
    //     | api_login_key value to something unique and include that in the POST request.
    //     |
    //     */
    //     // 'api_login',

    //     /*
    //     | ----------------
    //     | Recover settings
    //     | ----------------
    //     |
    //     | When using IMAP, if a user's password is changed, we
    //     | can't decrypt the existing user settings. This module detects that situation
    //     | and provides a page where a user can enter their old and new passwords to
    //     | recover their previous settings.
    //     |
    //     */
    //     'recover_settings',

    //     /*
    //     | ------------
    //     | Hello World
    //     | ------------
    //     |
    //     | Example module set with lots of comments
    //     |
    //     */
    //     // 'hello_world',
    // ],

    /*
    | ----------
    | API login
    | ----------
    */
    // 'api_login_key' => env('API_LOGIN_KEY'),

    /*
    | -----------------------------------------------------------------------------
    | User Defaults
    | -----------------------------------------------------------------------------
    | All of these settings can be changed by users, but you can uncomment and set
    | the default behavior using the following options. This will only effect new
    | users or ones that have never saved their settings.
    |
    | Per source time limits have valid values of:
    | -1 day
    | -1 week
    | -2 weeks
    | -4 weeks
    | -6 weeks
    | -6 months
    | -1 year
    | -5 years
    |
    | -----------------------------------------------------------------------------
    | Per source maximums can be from 1 to 1000
    | -----------------------------------------------------------------------------
    |
    | If set to true, passwords for email accounts will never be saved between logins
    | Defaults to false
    */
    'default_setting_no_password_save' => env('DEFAULT_SETTING_NO_PASSWORD_SAVE', false),

    /*
    |
    | Number of messages per page when viewing IMAP folders
    | Defaults to 20
    */
    'default_setting_imap_per_page' => env('DEFAULT_SETTING_IMAP_PER_PAGE', 20),

    /*
    |
    | Amount of IMAP message structure detail on the message view page
    | Defaults to full structure
    */
    'default_setting_simple_msg_parts' => env('DEFAULT_SETTING_SIMPLE_MSG_PARTS', false),

    /*
    |
    | Next and Previous emails on the message view page
    | Defaults to full structure
    */
    'default_setting_pagination_links' => env('DEFAULT_SETTING_PAGINATE_LINKS', true),

    /*
    |
    | Show icons for each IMAP message part type
    | Defaults to true
    */
    'default_setting_msg_part_icons' => env('DEFAULT_SETTING_MSG_PART_ICONS', true),

    /*
    |
    | Prefer text parts when viewing a message
    | Defaults to false
    */
    'default_setting_text_only' => env('DEFAULT_SETTING_TEXT_ONLY', true),

    /*
    |
    | Per source max for the combined sent view
    | Defaults to 20
    */
    'default_setting_sent_per_source' => env('DEFAULT_SETTING_SENT_PER_SOURCE', 20),

    /*
    |
    | Per source time limit for the combined sent view
    | Defaults to 1 week
    */
    'default_setting_sent_since' => env('DEFAULT_SETTING_SENT_SINCE', '-1 week'),
    
    /*
    |
    | Per source time limit for junk page
    | Defaults to 1 week
    */
    'default_setting_junk_since' => env('DEFAULT_SETTING_JUNK_SINCE', '-1 week'),

        /*
    |
    | Per source number limit for junk page
    | Defaults 20
    */
    'default_setting_junk_per_source' => env('DEFAULT_SETTING_JUNK_PER_SOURCE', 20),

    /*
    |
    | Per source time limit for tags page
    | Defaults to 1 week
    */
    'default_setting_tags_since' => env('DEFAULT_SETTING_TAGS_SINCE', '-1 week'),

    /*
    |
    | Per source number limit for tags page
    | Defaults 20
    */
    'default_setting_tags_per_source' => env('DEFAULT_SETTING_TAGS_PER_SOURCE', 20),

    /*
    |
    | Per source time limit for trash page
    | Defaults to 1 week
    */
    'default_setting_trash_since' => env('DEFAULT_SETTING_TRASH_SINCE', '-1 week'),

    /*
    |
    | Per source number limit for trash page
    | Defaults 20
    */
    'default_setting_trash_per_source' => env('DEFAULT_SETTING_TRASH_PER_SOURCE', 20),

    /*
    |
    | Per source time limit for draft page
    | Defaults to 1 week
    */
    'default_setting_draft_since' => env('DEFAULT_SETTING_DRAFT_SINCE', '-1 week'),

    /*
    |
    | Per source number limit for draft page
    | Defaults 20
    */
    'default_setting_draft_per_source' => env('DEFAULT_SETTING_DRAFT_PER_SOURCE', 20),

    /*
    |
    | Display source icons in message lists
    | Defaults to true
    */
    'default_setting_show_list_icons' => env('DEFAULT_SETTING_SHOW_LIST_ICONS', true),

    /*
    |
    | Redirect to this page on login
    | Defaults to none
    */
    'default_setting_start_page' => env('DEFAULT_SETTING_START_PAGE', 'none'),

    /*
    |
    | Don't prompt when deleting something
    | Defaults to false
    */
    'default_setting_disable_delete_prompt' => env('DEFAULT_SETTING_DISABLE_DELETE_PROMPT', false),

    /*
    |
    | Hide icons in the folder list
    | Defaults to false
    */
    'default_setting_no_folder_icons' => env('DEFAULT_SETTING_NO_FOLDER_ICONS', false),

    /*
    |
    | Source max for the all email combined view
    | Defaults to 20
    */
    'default_setting_all_email_per_source' => env('DEFAULT_SETTING_ALL_EMAIL_PER_SOURCE', 20),

    /*
    |
    | Per source time limit for the all email combined view
    | Defaults to 1 week
    */
    'default_setting_all_email_since' => env('DEFAULT_SETTING_ALL_EMAIL_SINCE', '-1 week'),

    /*
    |
    | Per source time limit for the everything combined view
    | Defaults to 1 week
    */
    'default_setting_all_since' => env('DEFAULT_SETTING_ALL_SINCE', '-1 week'),

    /*
    |
    | Per source max for the everything combined view
    | Defaults to 20
    */
    'default_setting_all_per_source' => env('DEFAULT_SETTING_ALL_PER_SOURCE', 20),

    /*
    |
    | Per source max for the unread combined view
    | Defaults to 20
    */
    'default_setting_unread_per_source' => env('DEFAULT_SETTING_UNREAD_PER_SOURCE', 20),

    /*
    |
    | Per source max for the flagged combined view
    | Defaults to 20
    */
    'default_setting_flagged_per_source' => env('DEFAULT_SETTING_FLAGGED_PER_SOURCE', 20),

    /*
    |
    | Per source time limit for the flagged combined view
    | Defaults to 1 week
    */
    'default_setting_flagged_since' => env('DEFAULT_SETTING_FLAGGED_SINCE', '-1 week'),

    /*
    |
    | Per source time limit for the unread combined view
    | Defaults to 1 week
    */
    'default_setting_unread_since' => env('DEFAULT_SETTING_UNREAD_SINCE', '-1 week'),

    /*
    |
    | Per source time limit for IMAP SEARCH to find matching messages
    | Defaults to 1 week
    */
    'default_setting_search_since' => env('DEFAULT_SETTING_SEARCH_SINCE', '-1 week'),

    /*
    |
    | Timezone for date displays
    | Defaults to UTC
    */
    'default_setting_timezone' => env('DEFAULT_SETTING_TIMEZONE', 'UTC'),

    /*
    |
    | Message list format style
    | Defaults to email client style
    */
    'default_setting_list_style' => env('DEFAULT_SETTING_LIST_STYLE', 'email_style'),

    /*
    |
    | Interface language
    | Defaults to en (English)
    */
    'default_setting_language' => env('DEFAULT_SETTING_LANGUAGE', 'en'),

    /*
    |
    | Hide new news feed items from the unread combined view
    | Defaults to false
    */
    'default_setting_unread_exclude_feeds' => env('DEFAULT_SETTING_UNREAD_EXCLUDE_FEEDS', false),

    /*
    |
    | Per source max for news feeds
    | Defaults to 20
    */
    'default_setting_feed_limit' => env('DEFAULT_SETTING_FEED_LIMIT', 20),

    /*
    |
    | Per source time limit for news feeds
    | Defaults to 1 week
    */
    'default_setting_feed_since' => env('DEFAULT_SETTING_FEED_SINCE', '-1 week'),

    /*
    |
    | Toggle between plain text or HTML formatted mail on the compose page
    | Defaults to 0 (plain text)
    */
    'default_setting_smtp_compose_type' => env('DEFAULT_SETTING_SMTP_COMPOSE_TYPE', 0),

    /*
    |
    | BCC a copy of outbound mail to the sender
    | Defaults to false
    */
    'default_setting_smtp_auto_bcc' => env('DEFAULT_SETTING_SMTP_AUTO_BCC', false),

    /*
    |
    | UI theme
    | Defaults to the default white one ("White Bread")
    */
    'default_setting_theme' => env('DEFAULT_SETTING_THEME', 'default'),

    /*
    |
    | Hide WordPress notifications from the unread combined view
    | Defaults to false
    */
    'default_setting_unread_exclude_wordpress' => env('DEFAULT_SETTING_UNREAD_EXCLUDE_WORDPRESS', false),

    /*
    |
    | Time limit for WordPress notifications
    | Defaults to 1 week
    */
    'default_setting_wordpress_since' => env('DEFAULT_SETTING_WORDPRESS_SINCE', '-1 week'),

    /*
    |
    | Hide Github notifications from the unread combined view
    | Defaults to false
    */
    'default_setting_unread_exclude_github' => env('DEFAULT_SETTING_UNREAD_EXCLUDE_GITHUB', false),

    /*
    |
    | Max per source for Github notifications
    | Defaults to 20
    */
    'default_setting_github_limit' => env('DEFAULT_SETTING_GITHUB_LIMIT', 20),

    /*
    |
    | Max per source for Github notifications
    | Defaults to 20
    */
    'default_setting_github_since' => env('DEFAULT_SETTING_GITHUB_SINCE', '-1 week'),

    /*
    |
    | Display message details inline from the message list
    | Defaults to false
    */
    'default_setting_inline_message' => env('DEFAULT_SETTING_INLINE_MESSAGE', false),

    /*
    |
    | Display message style inline from the message list
    | Defaults to right
    */
    'default_setting_inline_message_style' => env('DEFAULT_SETTING_INLINE_MESSAGE', 'right'),

    /*
    |
    | Enable keyboard shortcuts
    | Defaults to false
    */
    'default_setting_enable_keyboard_shortcuts' => env('DEFAULT_SETTING_ENABLE_KEYBOARD_SHORTCUTS', false),

    /*
    |
    | Enable sieve filter
    | Defaults to false
    */
    'default_setting_enable_sieve_filter' => env('DEFAULT_SETTING_ENABLE_SIEVE_FILTER', false),

    /*
    | Fancy Login page
    | Use this setting switch between the legacy login page and the fancy one
    */
    'fancy_login' => env('FANCY_LOGIN', false),
];
