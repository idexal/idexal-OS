<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database backups
    |--------------------------------------------------------------------------
    |
    | The dump binary. On a Plesk host it is /usr/bin/mysqldump or
    | /usr/bin/mariadb-dump; in a container "mysqldump" on PATH is fine.
    |
    */

    'mysqldump' => env('DB_DUMP_BINARY', 'mysqldump'),
];
