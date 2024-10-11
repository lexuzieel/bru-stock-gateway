<?php

namespace App;

use Slim\App;

return function (App $app) {
    $app->addErrorMiddleware(
        ($_ENV['APP_PRODUCTION'] ?? '') != 'production',
        true,
        true
    );
};
