<?php

return [
    'retention_days' => 7,
    'mysql_dump_binary' => env(
        'MYSQLDUMP_BINARY',
        PHP_OS_FAMILY === 'Windows' ? 'C:\\xampp\\mysql\\bin\\mysqldump.exe' : 'mysqldump',
    ),
    'mysql_binary' => env(
        'MYSQL_BINARY',
        PHP_OS_FAMILY === 'Windows' ? 'C:\\xampp\\mysql\\bin\\mysql.exe' : 'mysql',
    ),
];
