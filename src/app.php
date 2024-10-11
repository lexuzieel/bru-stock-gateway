<?php

namespace App;

use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;

AppFactory::setSlimHttpDecoratorsAutomaticDetection(false);
ServerRequestCreatorFactory::setSlimHttpDecoratorsAutomaticDetection(false);

$container = new Container();

// Load service definitions
(require __DIR__ . '/services.php')($container);

$app = AppFactory::createFromContainer($container);

// Load middleware
(require __DIR__ . '/middleware.php')($app);

// Load routes
(require __DIR__ . '/routes.php')($app);

$app->run();
