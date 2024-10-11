<?php

namespace App;

use Slim\App;
use App\Controllers\RemainsController;

return function (App $app) {
    $app->get('/remains', [RemainsController::class, 'index']);
};
