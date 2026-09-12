<?php

declare(strict_types=1);

namespace App\Database;

use RuntimeException;

/**
 * Applies and tracks the SQL migration files in `database/migrations`.
 *
 * Applied migrations are recorded so re-running is idempotent. Migration files
 * are plain SQL written for MySQL, which is the production target.
 */
final class Migrator
{
    public const TABLE = 'schema_migrations';

    /**
     * @param Database $database Connection to migrate.
     * @param string $directory Directory holding `NNN_name.sql` files.
     * @param bool $translateToSqlite Adapt statements for the SQLite test driver.
     */
    public function __construct(
        private readonly Database $database,
        private readonly string $directory,
        private readonly bool $translateToSqlite = false,
    ) {
    }

    /**
     * @return list<string> Paths of the migration files in apply order.
     */
    public function pending(): array
    {
        $this->ensureTrackingTable();
        $applied = $this->appliedVersions();

        $pending = [];
        foreach ($this->migrationFiles() as $version => $path) {
            if (!in_array($version, $applied, true)) {
                $pending[] = $path;
            }
        }

        return $pending;
    }

    /**
     * Apply every migration that has not run yet.
     *
     * @return list<string> Versions applied during this call.
     */
    public function migrate(): array
    {
        $this->ensureTrackingTable();
        $applied = $this->appliedVersions();
        $executed = [];

        foreach ($this->migrationFiles() as $version => $path) {
            if (in_array($version, $applied, true)) {
                continue;
            }

            $this->runFile($path);
            $this->database->execute(
                sprintf('INSERT INTO %s (version, applied_at) VALUES (:version, CURRENT_TIMESTAMP)', self::TABLE),
                ['version' => $version],
            );

            $executed[] = $version;
        }

        return $executed;
    }

    /**
     * Execute every statement in one migration file.
     *
     * @param string $path Path to the migration file.
     * @return void
     */
    private function runFile(string $path): void
    {
        $sql = (string) file_get_contents($path);

        if ($this->translateToSqlite) {
            $sql = SqliteDialect::translate($sql);
        }

        foreach ($this->splitStatements($sql) as $statement) {
            $this->database->exec($statement);
        }
    }

    /**
     * Split a SQL script into individual statements.
     *
     * Handles line comments, quoted strings, and the backtick identifiers that
     * appear in generated DDL.
     *
     * @param string $sql The full script text.
     * @return list<string> Non-empty statements without trailing semicolons.
     */
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;
        $inLineComment = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($inLineComment) {
                if ($char === "\n") {
                    $inLineComment = false;
                    $buffer .= $char;
                }

                continue;
            }

            if (!$inSingle && !$inDouble && !$inBacktick && $char === '-' && $next === '-') {
                $inLineComment = true;
                $i++;

                continue;
            }

            if ($char === "'" && !$inDouble && !$inBacktick) {
                // A doubled quote inside a string literal is an escaped quote.
                if ($inSingle && $next === "'") {
                    $buffer .= "''";
                    $i++;

                    continue;
                }

                $inSingle = !$inSingle;
                $buffer .= $char;

                continue;
            }

            if ($char === '"' && !$inSingle && !$inBacktick) {
                $inDouble = !$inDouble;
                $buffer .= $char;

                continue;
            }

            if ($char === '`' && !$inSingle && !$inDouble) {
                $inBacktick = !$inBacktick;
                $buffer .= $char;

                continue;
            }

            if ($char === ';' && !$inSingle && !$inDouble && !$inBacktick) {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }

                $buffer = '';

                continue;
            }

            $buffer .= $char;
        }

        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return $statements;
    }

    /**
     * @return array<string, string> Map of version to file path, ordered.
     */
    private function migrationFiles(): array
    {
        if (!is_dir($this->directory)) {
            throw new RuntimeException(sprintf('Migration directory not found: %s', $this->directory));
        }

        $files = glob($this->directory . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        $map = [];
        foreach ($files as $file) {
            $map[basename($file, '.sql')] = $file;
        }

        return $map;
    }

    /**
     * @return list<string> Versions already recorded as applied.
     */
    private function appliedVersions(): array
    {
        $rows = $this->database->select(sprintf('SELECT version FROM %s', self::TABLE));

        return array_map(static fn (array $row): string => (string) $row['version'], $rows);
    }

    /**
     * Create the bookkeeping table when it does not exist yet.
     *
     * @return void
     */
    private function ensureTrackingTable(): void
    {
        $this->database->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS %s (
                    version VARCHAR(190) NOT NULL,
                    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (version)
                )',
                self::TABLE,
            ),
        );
    }
}
