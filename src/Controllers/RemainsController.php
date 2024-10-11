<?php

namespace App\Controllers;

use App\Services\BusinessRuApi;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class RemainsController
{
    private $api;

    public function __construct(BusinessRuApi $api)
    {
        $this->api = $api;
    }

    protected function getStoreIds(Request $request)
    {
        $result = $this->api->request('get', 'stores');

        if ($result['status'] != 'ok') {
            throw new \Exception('API request failed');
        }

        $items = $result['result'] ?? [];

        $substrings = $request->getQueryParams()['store'] ?? [];

        // Filter the items based on the substrings
        $filteredItems = array_filter($items, function ($item) use ($substrings) {
            foreach ($substrings as $substring) {
                if (
                    strpos(
                        mb_strtolower($item['name'] ?? ''),
                        mb_strtolower($substring)
                    ) !== false
                ) {
                    return ($item['deleted'] ?? true) == false;
                }
            }

            return false;
        });

        // Map the filtered and sorted items to get their IDs
        $ids = array_map(function ($item) {
            return $item['id'] ?? '';
        }, $filteredItems);

        return $ids;
    }

    protected function getProduct(Request $request)
    {
        $sku = $request->getQueryParams()['sku'] ?? null;

        $result = $this->api->request('get', 'goods', [
            'part' => $sku,
            'with_remains' => 1,
            'filter_positive_free_remains' => 1,
            'with_modifications' => 1,
            'archive' => 0,
            'deleted' => 0,
        ]);

        if ($result['status'] != 'ok') {
            throw new \Exception('API request failed');
        }

        $items = $result['result'] ?? [];

        return $items[0] ?? null;
    }

    protected function getModification(Request $request, ?array $product)
    {
        $modifications = $product['modifications'] ?? [];

        $variant = $request->getQueryParams()['variant'] ?? [];

        $parts = [];

        foreach ($variant as $key => $value) {
            $parts[] = "$key: $value";
        }

        $name = implode(', ', $parts);

        foreach ($modifications as $modification) {
            if (
                mb_strtolower($modification['name'] ?? '') ==
                mb_strtolower($name)
            ) {
                return $modification;
            }
        }
    }

    public function index(Request $request, Response $response)
    {
        try {
            $stores = $this->getStoreIds($request);
            $product = $this->getProduct($request);
            $modification = $this->getModification($request, $product);

            $remains = [];

            foreach ($modification['remains'] ?? [] as $remain) {
                $remains[] = [
                    'store' => [
                        'id' => $remain['store']['id'] ?? '',
                        'name' => $remain['store']['name'] ?? '',
                    ],
                    'amount' => (int)($remain['amount']['total'] ?? 0),
                ];
            }

            $remains = array_filter($remains, function ($remain) use ($stores) {
                return in_array($remain['store']['id'] ?? '', $stores);
            });

            // Sort remains by store name alphabetically
            usort($remains, function ($a, $b) {
                return strcmp(
                    mb_strtolower($a['store']['name'] ?? ''),
                    mb_strtolower($b['store']['name'] ?? '')
                );
            });

            $response->getBody()->write(json_encode([
                'result' => [
                    'remains' => $remains,
                ],
            ]));
        } catch (\Exception $e) {
            return $response->withStatus(500);
        }

        return $response->withHeader('Content-Type', 'application/json');
    }
}
