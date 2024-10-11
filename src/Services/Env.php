<?php

namespace App\Services;

use Dotenv\Dotenv;

class Env
{
    private $dotenv;

    public function __construct()
    {
        $this->dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
        $this->dotenv->load();
    }

    public function get(string $key)
    {
        return $_ENV[$key] ?? null;
    }
}
