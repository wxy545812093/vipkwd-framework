<?php
return [
    'driver' => '\\Medoo\\Medoo',
    'env' => "dev",
    "dev" => [
        'database_type' => 'mysql',
        'database_name' => 'vps_platform',
        'server' => '127.0.0.1',
        'username' => 'root',
        'password' => 'root',
        'port' => 3306,
        'prefix' => 'vipkwd_',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_general_ci',
        "logging" => false,
        'option' => [
            PDO::ATTR_CASE => PDO::CASE_NATURAL
        ],
    ]
];