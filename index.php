<?php

require __DIR__ . '/vendor/autoload.php';

use \bru\api\Client;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$bru_account = $_ENV['BRU_ACCOUNT'];
$bru_app_id = $_ENV['BRU_APP_ID'];
$bru_app_secret = $_ENV['BRU_APP_SECRET'];

print_r([
    'bru_account' => $bru_account,
    'bru_app_id' => $bru_app_id,
    'bru_app_secret' => $bru_app_secret,
]);

$api = new Client(
    $bru_account,
    $bru_app_id,
    $bru_app_secret,
);

var_dump(
    $api->request('get', 'stores')
);
