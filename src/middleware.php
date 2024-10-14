<?php

namespace App;

use Slim\App;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

function configCors(App $app)
{
    $allowlist = explode(',', $_ENV['CORS_ALLOWLIST'] ?? '');

    $app->options('/{routes:.+}', function ($request, $response, $args) {
        return $response;
    });

    $app->add(function (Request $request, $handler) use ($allowlist) {
        $origin = $request->getServerParams()['HTTP_ORIGIN'] ?? '';

        if (!in_array($origin, $allowlist)) {
            $response = new Response();
            $response->getBody()->write('domain not allowed');

            return $response->withStatus(403);
        }

        $response = $handler->handle($request);

        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
    });
}

return function (App $app) {
    $app->addErrorMiddleware(
        ($_ENV['APP_PRODUCTION'] ?? '') != 'production',
        true,
        true
    );

    configCors($app);
};
