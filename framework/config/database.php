<?php
return [
  "dev" => [
    'database_type' => 'mysql',
    'database_name' => 'ddxx_kaowu',
    'server' => '127.0.0.1',
    'username' => 'root',
    'password' => 'root',//'adminiis!!__',
    'port' =>3306,
		'prefix' => '',
		'charset' => 'utf8mb4',
		'collation' => 'utf8mb4_general_ci',
		"logging" => true,
		'option' => [
			PDO::ATTR_CASE => PDO::CASE_NATURAL
		],
  ]
];