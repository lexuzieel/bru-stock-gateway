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

class Store
{
    public function __construct(
        public string $id,
        public string $name,
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

        // Return filtered items
        return array_values(array_map(function ($item) {
            return new Store(id: $item['id'], name: $item['name']);
        }, $filteredItems));
    }

    /**
     * Returns a product with given SKU or null if not found.
     * @param Request $request
     * @return array|null
     * @throws \Exception
     */
    protected function getProduct(Request $request): array|null
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

    /**
     * @param Request $request
     *
     * @return array|null
     */
    protected function getModification(Request $request)
    {
        $product = $this->getProduct($request);

        /**
         * @var array
         */
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

    /**
     * Returns the amount of items in the given store for the current modification
     *
     * @param Store $store
     * @return int
     */
    protected function getAmount(Store $store, ?array $modification)
    {
        foreach ($modification['remains'] ?? [] as $remains) {
            if (($remains['store']['id'] ?? '') == $store->id) {
                return (int) $remains['amount']['total'] ?? 0;
            }
        }

        return 0;
    }

    protected function getQuantity(
        Store $store,
        ?array $modification,
        RemainsOptions $options = null,
    ) {
        $amount = $this->getAmount($store, $modification);

        if ($amount >= $options?->manyThresholdAmount ?? 0) {
            return 'many';
        } else if ($amount > 0) {
            return 'few';
        } else {
            return 'empty';
        }
    }

    protected function getRemains(
        Request $request,
        RemainsOptions $options = null,
    ) {
        $stores = $this->getStores($request);
        $modification = $this->getModification($request);

        $remains = [];

        foreach ($stores as $store) {
            $entry = [
                'store' => [
                    'id' => $store->id,
                    'name' => $store->name,
                ],
            ];

            if ($options?->actualAmount) {
                $entry['amount'] = $this->getAmount($store, $modification);
            } else {
                $entry['quantity'] = $this->getQuantity($store, $modification, $options);
            }

            $remains[] = $entry;
        }

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
