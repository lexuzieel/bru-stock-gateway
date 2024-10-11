<?php

namespace App\Services;

use bru\api\Client;

class BusinessRuApi
{
    private $api;

    public function __construct(Env $env)
    {
        $this->api = new Client(
            $env->get('BRU_ACCOUNT'),
            $env->get('BRU_APP_ID'),
            $env->get('BRU_APP_SECRET'),
        );
    }

    public function api()
    {
        return $this->api;
    }

    public function request(string $method, string $path, array $params = [])
    {
        return $this->api->request($method, $path, $params);
    }

    public function requestAll(string $method, array $params = [])
    {
        return $this->api->requestAll($method, $params);
    }
}
