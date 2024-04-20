<?php

return [
    /*
    | -----------------------------------------------------------------------------
    | DB Support
    | -----------------------------------------------------------------------------
    |
    | ----------------
    | Database Support
    | ----------------
    | Cypht can use a database for 3 different purposes: authentication, sessions,
    | and user settings. Each requires the following settings to be configured and
    | the correct table to be created. CREATE TABLE SQL statements for MySQL and
    | Postgresql are below.
    |
    | Connection type. Can be "host" to connect to a hostname, or "socket" to
    | connect to a unix socket.
    */
    'db_connection_type' => env('DB_CONNECTION_TYPE', 'host'),

    /*
    |
    | Database host name or ip address. If db_connection_type is set to "socket",
    | this value is ignored
    */
    'db_host' => env('DB_HOST', '127.0.0.1'),

    /*
    |
    | Database port. Only needed if your database is running on a non-standard
    | port
    */
    'db_port' => env('DB_PORT', 3306),

    /*
    |
    | If db_connection_type is set to "socket", this should be the filesystem
    | location of the unix socket file. If db_connection_type is set to "host"
    | this value is ignored.
    */
    'db_socket' => env('DB_SOCKET','/var/lib/mysqld/mysqld.sock'),

    /*
    |
    | Name of the database with the required tables
    */
    'db_name' => env('DB_NAME', 'cypht_db'),

    /*
    |
    | User to connect to the database with
    */
    'db_user' => env('DB_USER', 'root'),

    /*
    |
    | Password to connect to the database with
    */
    'db_pass' => env('DB_PASS'),

    /*
    |
    | Database type. can be any supported PDO driver ; (http://php.net/manual/en/pdo.drivers.php)
    */
    'db_driver' => env('DB_DRIVER','mysql')
];
