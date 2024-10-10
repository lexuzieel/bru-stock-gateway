<?php

require __DIR__ . '/../vendor/autoload.php';

use \bru\api\Client;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$bru_account = $_ENV['BRU_ACCOUNT'];
$bru_app_id = $_ENV['BRU_APP_ID'];
$bru_app_secret = $_ENV['BRU_APP_SECRET'];

$api = new Client(
    $bru_account,
    $bru_app_id,
    $bru_app_secret,
);

$stores = $api->request('get', 'stores');

var_dump(json_encode($stores));
