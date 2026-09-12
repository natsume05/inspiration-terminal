<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ApiCacheRepository;
use App\Repository\ToolRepository;
use App\Support\Config;
use RuntimeException;

/**
 * Fetches and caches GitHub repository rankings and search results.
 *
 * Two behaviours matter here and both are deliberate:
 *
 *   * TLS verification stays **on**. The previous implementation disabled it
 *     (`CURLOPT_SSL_VERIFYPEER => false`), which removed the only proof that the
 *     response came from GitHub.
 *   * When the upstream call fails, the last stored payload is served instead.
 *     A stale ranking is useful; an error page is not, and an unauthenticated
 *     caller is limited to sixty requests per hour.
 */
final class GithubService
{
    private const API_BASE = 'https://api.github.com';
    private const TRENDING = 'trending';
    private const ALL_TIME = 'all_time';

    public function __construct(
        private readonly ToolRepository $tools,
        private readonly ApiCacheRepository $cache,
        private readonly Config $config,
    ) {
    }

    /**
     * Return both rankings, fetching them only when the cache has expired.
     *
     * @return array{trending: list<array<string, mixed>>, all_time: list<array<string, mixed>>}
     */
    public function rankings(): array
    {
        return [
            self::TRENDING => $this->ranking(self::TRENDING),
            self::ALL_TIME => $this->ranking(self::ALL_TIME),
        ];
    }

    /**
     * Return one ranking from the cache, refreshing it first if needed.
     *
     * @param string $listType Either `trending` or `all_time`.
     * @return list<array<string, mixed>> Project rows.
     */
    private function ranking(string $listType): array
    {
        $cached = $this->cache->get('github.ranking.' . $listType);

        if ($cached !== null) {
            return $this->tools->projects($listType, 10);
        }

        // Nothing fresh: try to refresh, and fall back to what is already stored
        // in the projects table if the refresh does not work out.
        $this->refresh($listType);

        return $this->tools->projects($listType, 10);
    }

    /**
     * Fetch one ranking from GitHub and store it.
     *
     * @param string $listType Either `trending` or `all_time`.
     * @return int Number of repositories stored.
     */
    public function refresh(string $listType): int
    {
        $query = $listType === self::TRENDING
            ? 'created:>' . date('Y-m-d', strtotime('-7 days'))
            : 'stars:>10000';

        $url = sprintf(
            '%s/search/repositories?q=%s&sort=stars&order=desc&per_page=10',
            self::API_BASE,
            rawurlencode($query),
        );

        $payload = $this->request($url);

        if ($payload === null || !isset($payload['items']) || !is_array($payload['items'])) {
            // Refresh failed, so keep the previous cache entry alive rather than
            // expiring it and leaving the page with nothing to show.
            $this->cache->put('github.ranking.' . $listType, ['failed' => true], 60);

            return 0;
        }

        $stored = 0;

        foreach ($payload['items'] as $item) {
            if (!is_array($item) || !isset($item['id'], $item['full_name'], $item['html_url'])) {
                continue;
            }

            $this->tools->upsertProject($item, $listType);
            $stored++;
        }

        $this->cache->put(
            'github.ranking.' . $listType,
            ['stored' => $stored, 'at' => date('c')],
            (int) $this->config->get('integrations.github.cache_ttl', 3600),
        );

        return $stored;
    }

    /**
     * Search the cached ranking for a term.
     *
     * Searching locally avoids spending a rate-limited API call per keystroke and
     * works with no token configured at all.
     *
     * @param string $term Search term.
     * @return list<array<string, mixed>> Matching project rows.
     */
    public function search(string $term): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        return $this->tools->searchProjects($term, 20);
    }

    /**
     * Perform an authenticated request against the GitHub API.
     *
     * @param string $url Absolute request URL.
     * @return array<string, mixed>|null Decoded response, or null on failure.
     */
    private function request(string $url): ?array
    {
        if (!function_exists('curl_init')) {
            error_log('[GITHUB] The curl extension is not available.');

            return null;
        }

        $token = (string) $this->config->get('integrations.github.token', '');
        $userAgent = (string) $this->config->get('integrations.github.user_agent', 'InspirationTerminal');

        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($handle, CURLOPT_TIMEOUT, 10);
        // TLS verification is never disabled: without it the response could come
        // from anything claiming to be GitHub.
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2);

        $headers = ['Accept: application/vnd.github+json'];

        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if (!is_string($response) || $response === '') {
            error_log(sprintf('[GITHUB] Request failed (%d): %s', $status, $error));

            return null;
        }

        if ($status < 200 || $status >= 300) {
            error_log(sprintf('[GITHUB] Request returned HTTP %d', $status));

            return null;
        }

        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : null;
    }
}
