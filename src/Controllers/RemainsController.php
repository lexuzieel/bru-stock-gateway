<?php

namespace App\Controllers;

use App\Services\BusinessRuApi;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class RemainsOptions
{
    public function __construct(
        public bool $actualAmount = true,
        public int $manyThresholdAmount = 5,
    ) {}
}

class RemainsController
{
    private $api;

    public function __construct(BusinessRuApi $api)
    {
        $this->api = $api;
    }

    protected function getStores(Request $request)
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

    protected function getModification(Request $request)
    {
        $product = $this->getProduct($request);

        $modifications = $product['modifications'] ?? [];

        $variants = $request->getQueryParams()['variant'] ?? [];

        $contains = [];

        foreach ($variants as $key => $value) {
            $contains[] = mb_strtolower($key);
            $contains[] = mb_strtolower($value);
        }

        /**
         * Check if all words in $contains are exact substrings of $name.
         *
         * Returns false if any word is not found, true if all words
         * are found as substrings.
         *
         * @param string $string
         * @param array $contains
         *
         * @return bool
         */
        function matchExactSubstrings($string, $contains)
        {
            foreach ($contains as $word) {
                // Check if the word is an exact substring of the name
                if (strpos($string, $word) === false) {
                    // Return false if any word is not found
                    return false;
                }
            }
            // All words found as substrings
            return true;
        }

        foreach ($modifications as $modification) {
            $name = mb_strtolower($modification['name'] ?? '');

            if (matchExactSubstrings($name, $contains)) {
                return $modification;
            }
        }
    }

    protected function getRemains(
        Request $request,
        RemainsOptions $options = null,
    ) {
        $stores = $this->getStores($request);
        $modification = $this->getModification($request);

        $remains = [];

        foreach ($modification['remains'] ?? [] as $remain) {
            $entry = [
                'store' => [
                    'id' => $remain['store']['id'] ?? '',
                    'name' => $remain['store']['name'] ?? '',
                ],
            ];

            $amount = (int) $remain['amount']['total'] ?? 0;

            if ($options?->actualAmount) {
                $entry['amount'] = $amount;
            } else {
                if ($amount >= $options?->manyThresholdAmount ?? 0) {
                    $entry['quantity'] = 'many';
                } else if ($amount > 0) {
                    $entry['quantity'] = 'few';
                } else {
                    $entry['quantity'] = 'empty';
                }
            }

            $remains[] = $entry;
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

        return $remains;
    }

    public function index(Request $request, Response $response)
    {
        try {
            $remains = $this->getRemains($request, new RemainsOptions(
                actualAmount: false,
                manyThresholdAmount: 5,
            ));

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
