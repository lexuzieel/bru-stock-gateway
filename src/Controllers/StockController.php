<?php

namespace App\Controllers;

use App\Services\BusinessRuApi;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class StockController
{
    private $api;

    public function __construct(BusinessRuApi $api)
    {
        $this->api = $api;
    }

    public function index(Request $request, Response $response)
    {
        $result = $this->api->request('get', 'stores');

        if ($result['status'] != 'ok') {
            return $response->withStatus(500);
        }

        $items = $result['result'];

        $substrings = $request->getQueryParams()['name'] ?? [];

        // Filter the items based on the substrings
        $filteredItems = array_filter($items, function ($item) use ($substrings) {
            foreach ($substrings as $substring) {
                if (
                    strpos(
                        mb_strtolower($item['name']),
                        mb_strtolower($substring)
                    ) !== false
                ) {
                    return $item['deleted'] == false;
                }
            }

            return false;
        });

        // Sort the filtered items by name alphabetically
        usort($filteredItems, function ($a, $b) {
            return strcmp(mb_strtolower($a['name']), mb_strtolower($b['name']));
        });

        // Map the filtered and sorted items to get their IDs
        $ids = array_map(function ($item) {
            return $item['id'];
        }, $filteredItems);

        // Return the filtered items as JSON response
        $response->getBody()->write(json_encode($filteredItems));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
