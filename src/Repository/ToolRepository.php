<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Database;

/**
 * Data access for toolbox links and the cached GitHub rankings.
 *
 * The rankings are cached in the database rather than fetched per request. That
 * keeps the page usable when the upstream API is rate limited or unavailable,
 * and it keeps an unauthenticated GitHub request limit of sixty per hour from
 * being consumed by ordinary page views.
 */
final class ToolRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * List active toolbox links, grouped by category.
     *
     * @return array<string, list<array<string, mixed>>> Links keyed by category.
     */
    public function linksByCategory(): array
    {
        $rows = $this->database->select(
            'SELECT id, title, url, icon, description, category
             FROM tools WHERE is_active = 1
             ORDER BY category ASC, sort_order ASC, id ASC',
        );

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(string) $row['category']][] = $row;
        }

        return $grouped;
    }

    /**
     * List every active link, flat.
     *
     * @return list<array<string, mixed>> Link rows.
     */
    public function allLinks(): array
    {
        return $this->database->select(
            'SELECT id, title, url, icon, description, category
             FROM tools WHERE is_active = 1
             ORDER BY category ASC, sort_order ASC, id ASC',
        );
    }

    /**
     * Create a toolbox link.
     *
     * @param string $title Display title.
     * @param string $url Target URL.
     * @param string $description Short description.
     * @param string $category Category key.
     * @param string $icon Icon glyph.
     * @return int The new link identifier.
     */
    public function createLink(string $title, string $url, string $description, string $category, string $icon = ''): int
    {
        return $this->database->insert(
            'INSERT INTO tools (title, url, icon, description, category, sort_order)
             VALUES (:title, :url, :icon, :description, :category, 0)',
            [
                'title' => $title,
                'url' => $url,
                'icon' => $icon,
                'description' => $description,
                'category' => $category,
            ],
        );
    }

    /**
     * Remove a toolbox link.
     *
     * @param int $id Link identifier.
     * @return bool True when a row was removed.
     */
    public function deleteLink(int $id): bool
    {
        return $this->database->execute('DELETE FROM tools WHERE id = :id', ['id' => $id]) > 0;
    }

    /**
     * Store a fetched GitHub ranking.
     *
     * `remote_id` carries a unique key, so re-running the fetch refreshes the
     * star count instead of inserting the repository a second time.
     *
     * @param array<string, mixed> $repository Repository payload.
     * @param string $listType Either `trending` or `all_time`.
     * @return void
     */
    public function upsertProject(array $repository, string $listType): void
    {
        // The duplicate-key syntax differs between engines, so it is selected by
        // driver rather than assuming MySQL. Without this the refresh works in
        // production and fails in the test suite, which hides the failure.
        $conflictClause = $this->database->driver() === 'sqlite'
            ? 'ON CONFLICT (remote_id) DO UPDATE SET
                 stars = excluded.stars, forks = excluded.forks, description = excluded.description,
                 list_type = excluded.list_type, fetched_at = CURRENT_TIMESTAMP'
            : 'ON DUPLICATE KEY UPDATE
                 stars = VALUES(stars), forks = VALUES(forks), description = VALUES(description),
                 list_type = VALUES(list_type), fetched_at = CURRENT_TIMESTAMP';

        $this->database->execute(
            'INSERT INTO github_projects (remote_id, name, description, url, stars, forks, language, list_type, fetched_at)
             VALUES (:remote_id, :name, :description, :url, :stars, :forks, :language, :list_type, CURRENT_TIMESTAMP)
             ' . $conflictClause,
            [
                'remote_id' => (int) $repository['id'],
                'name' => mb_substr((string) $repository['full_name'], 0, 190),
                'description' => (string) ($repository['description'] ?? ''),
                'url' => mb_substr((string) $repository['html_url'], 0, 500),
                'stars' => (int) ($repository['stargazers_count'] ?? 0),
                'forks' => (int) ($repository['forks_count'] ?? 0),
                'language' => mb_substr((string) ($repository['language'] ?? ''), 0, 64),
                'list_type' => $listType,
            ],
        );
    }

    /**
     * List cached projects for one ranking.
     *
     * @param string $listType Either `trending` or `all_time`.
     * @param int $limit Maximum rows to return.
     * @return list<array<string, mixed>> Project rows.
     */
    public function projects(string $listType, int $limit = 10): array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT id, name, description, url, stars, forks, language, fetched_at
             FROM github_projects
             WHERE list_type = :list_type
             ORDER BY stars DESC
             LIMIT :limit',
        );
        $statement->bindValue(':list_type', $listType);
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    /**
     * Search cached projects by name or description.
     *
     * Searching the cache rather than the live API keeps the endpoint fast and
     * needs no token, and the results are limited to what the fetcher stored.
     *
     * @param string $term Search term.
     * @param int $limit Maximum rows to return.
     * @return list<array<string, mixed>> Matching project rows.
     */
    public function searchProjects(string $term, int $limit = 20): array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT id, name, description, url, stars, language, list_type
             FROM github_projects
             WHERE name LIKE :term OR description LIKE :term
             ORDER BY stars DESC
             LIMIT :limit',
        );
        $statement->bindValue(':term', '%' . $term . '%');
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    /**
     * @return int Total number of cached projects.
     */
    public function projectCount(): int
    {
        $row = $this->database->selectOne('SELECT COUNT(*) AS total FROM github_projects');

        return (int) ($row['total'] ?? 0);
    }
}
