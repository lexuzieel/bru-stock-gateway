<?php

namespace App;

use Slim\App;
use App\Controllers\StockController;

return function (App $app) {
    $app->get('/stock', [StockController::class, 'index']);
};
