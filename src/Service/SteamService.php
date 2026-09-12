<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ApiCacheRepository;
use App\Support\Config;

/**
 * Proxies Steam discount data from the CheapShark API.
 *
 * The browser never calls the third-party API directly. Proxying keeps the
 * upstream contract in one place, avoids a cross-origin request from the client,
 * and lets the response be cached so a popular page does not translate into one
 * upstream call per visitor.
 *
 * TLS verification is enabled, unlike the previous implementation.
 */
final class SteamService
{
    public function __construct(
        private readonly ApiCacheRepository $cache,
        private readonly Config $config,
    ) {
    }

    /**
     * Return current discounts.
     *
     * @param string $mode One of `deals`, `trending`, or `search`.
     * @param string $term Search term, used when mode is `search`.
     * @return array<string, mixed> Normalised result.
     */
    public function deals(string $mode = 'deals', string $term = ''): array
    {
        $query = match ($mode) {
            'trending' => ['storeID' => '1', 'steamRating' => '85', 'metacritic' => '80', 'sortBy' => 'Metacritic', 'pageSize' => '8'],
            'search' => ['storeID' => '1', 'title' => $term, 'pageSize' => '12'],
            default => ['storeID' => '1', 'onSale' => '1', 'metacritic' => '75', 'pageSize' => '12', 'sortBy' => 'Savings'],
        };

        $cacheKey = 'steam.' . $mode . '.' . md5(http_build_query($query));

        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return ['ok' => true, 'cached' => true, 'deals' => $cached['deals'] ?? []];
        }

        $payload = $this->request($query);

        if ($payload === null) {
            // Serve the previous response rather than an error, so the panel
            // degrades to slightly stale prices instead of disappearing.
            $stale = $this->cache->getStale($cacheKey);

            if ($stale !== null) {
                return ['ok' => true, 'cached' => true, 'stale' => true, 'deals' => $stale['deals'] ?? []];
            }

            return ['ok' => false, 'message' => '暂时无法获取折扣数据，请稍后再试。', 'deals' => []];
        }

        $deals = $this->normalise($payload);

        $this->cache->put($cacheKey, ['deals' => $deals], (int) $this->config->get('integrations.steam.cache_ttl', 900));

        return ['ok' => true, 'cached' => false, 'deals' => $deals];
    }

    /**
     * Return the seasonal sale calendar.
     *
     * Held locally because it is editorial content, not upstream data: it does
     * not change between requests and needs no network call at all.
     *
     * @return list<array<string, string>> Calendar entries.
     */
    public function calendar(): array
    {
        return [
            ['name' => '春季特卖', 'date' => '2026-03-14', 'icon' => '🌱', 'description' => '万物复苏，独立游戏的主场'],
            ['name' => '建造节', 'date' => '2026-04-20', 'icon' => '🔨', 'description' => '模拟经营类游戏爱好者的狂欢'],
            ['name' => '夏日大促', 'date' => '2026-06-25', 'icon' => '🔥', 'description' => '全年力度最大的一场'],
            ['name' => '万圣节特卖', 'date' => '2026-10-26', 'icon' => '🎃', 'description' => '恐怖游戏与灵异题材'],
            ['name' => '秋季特卖', 'date' => '2026-11-22', 'icon' => '🍁', 'description' => 'Steam 大奖提名开启'],
            ['name' => '冬季特卖', 'date' => '2026-12-21', 'icon' => '🎄', 'description' => '年终清算，清空愿望单'],
        ];
    }

    /**
     * Reduce a CheapShark response to the fields the interface uses.
     *
     * Keeping the shape explicit means an upstream field rename shows up as a
     * missing value here rather than leaking into the templates.
     *
     * @param array<string, mixed> $payload Decoded upstream response.
     * @return list<array<string, mixed>> Normalised deals.
     */
    private function normalise(array $payload): array
    {
        $deals = [];

        foreach ($payload as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $deals[] = [
                'title' => (string) ($entry['title'] ?? '未知游戏'),
                'sale_price' => (string) ($entry['salePrice'] ?? '0'),
                'normal_price' => (string) ($entry['normalPrice'] ?? '0'),
                'savings' => (string) ($entry['savings'] ?? '0'),
                'metacritic' => (string) ($entry['metacriticScore'] ?? ''),
                'thumb' => (string) ($entry['thumb'] ?? ''),
                'deal_id' => (string) ($entry['dealID'] ?? ''),
            ];
        }

        return $deals;
    }

    /**
     * Call the upstream API.
     *
     * @param array<string, string> $query Query parameters.
     * @return array<string, mixed>|null Decoded list, or null on failure.
     */
    private function request(array $query): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $endpoint = (string) $this->config->get('integrations.steam.endpoint', 'https://www.cheapshark.com/api/1.0');
        $url = rtrim($endpoint, '/') . '/deals?' . http_build_query($query);

        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($handle, CURLOPT_TIMEOUT, 10);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($handle, CURLOPT_USERAGENT, 'InspirationTerminal/2.0');

        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if (!is_string($response) || $response === '' || $status < 200 || $status >= 300) {
            error_log(sprintf('[STEAM] Request failed (HTTP %d): %s', $status, $error));

            return null;
        }

        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : null;
    }
}
