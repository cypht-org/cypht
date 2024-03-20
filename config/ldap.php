<?php

return [
    /* 
    | -------------------------------------
    | Constants used for LDAP communication
    | -------------------------------------
    |
    | Once you edit this file, you must move it to the directory defined by
    | app_data_dir in your config/app.php file. No need to re-run the
    | config_gen.php script.
    | 
    | SECURITY ALERT ! MAKE SURE THAT THIS FILE IS NOT ACCESSIBLE BY THE BROWSER ! 
    | 
    | Create one section for each LDAP backend you want to support. The section name
    | will be used in the UI for the name of this addressbook
    |
    */
    'ldap' => [
        'Personal' => [
            /*
            | LDAP Server hostname or IP address
            */
            'server' => env('LDAP_SERVER', 'localhost'),
            
            /*
            | Flag to enable or disable TLS connections
            */
            'enable_tls' => env('LDAP_ENABLE_TLS', true),
    
            /*
            | Port to connect to
            */
            'port' => env('LDAP_PORT', 389),
    
            /*
            | Base DN
            */
            'base_dn' => env('LDAP_BASE_DN', 'dc=example,dc=com'),
    
            /*
            | Base DN
            */
            'search_term' => env('LDAP_SEARCH_TERM', 'objectclass=inetOrgPerson'),
    
            /*
            | Flag to enable user binding. Anonymous binding is used when set to false
            */
            'auth' => env('LDAP_AUTH', false),
    
            /*
            | Global username and password to bind with if auth is set to true. If left
            | blank, users will have a setting on the Settings -> Site page for this
            | connection to enter their own
            */
            'user' => env('LDAP_USER', ''),
            'pass' => env('LDAP_PASS', ''),
    
            /*
            | Object classes for the addressbook entries
            */
            'objectclass' => explode(',', env('LDAP_OBJECT_CLASS','top,person,organizationalperson,inetorgperson')),
    
            /*
            | Flag to allow editing of the addressbook contents
            */
            'read_write' => env('LDAP_READ_WRITE', true),
        ],
    ],
];