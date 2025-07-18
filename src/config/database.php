<?php

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->safeLoad();

return [
    'type'     => $_ENV['DB_TYPE']     ?? 'mysql',
    'host'     => $_ENV['DB_HOST']     ?? 'localhost',
    'database' => $_ENV['DB_NAME']     ?? 'db_medoo',
    'username' => $_ENV['DB_USER']     ?? 'root',
    'password' => $_ENV['DB_PASS']     ?? '',
    'charset'  => $_ENV['DB_CHARSET']  ?? 'utf8mb4',
];
