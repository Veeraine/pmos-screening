<?php

/*
|--------------------------------------------------------------------------
| Database backups
|--------------------------------------------------------------------------
|
| Settings for the daily backup (php artisan pmos:backup). Change them in .env.
|
*/

return [

    /*
    | Path to mysqldump. On Laragon it's in C:\laragon\bin\mysql\<version>\bin\.
    */

    'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),

    /*
    | Where backups go. Use another drive (USB or network) so they survive if this disk fails.
    */

    'folder' => env('BACKUP_FOLDER', storage_path('app/private/backups')),

    /*
    | Days of backups to keep. Older ones are deleted, but the newest is always kept.
    */

    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 30),

];
