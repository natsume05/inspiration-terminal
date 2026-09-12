<?php

declare(strict_types=1);

namespace App\Database;

/**
 * Rewrites MySQL DDL into equivalent SQLite DDL.
 *
 * The production target is MySQL, so the schema files stay single-dialect.
 * This translator exists only so the automated test suite can build the real
 * table definitions — including foreign keys, unique constraints, and check
 * constraints — inside a throwaway SQLite database. The application never uses
 * it at runtime.
 */
final class SqliteDialect
{
    /**
     * Translate a full DDL script, statement by statement.
     *
     * @param string $sql MySQL DDL script.
     * @return string Equivalent SQLite DDL script.
     */
    public static function translate(string $sql): string
    {
        $statements = [];
        $indexes = [];

        foreach (self::split($sql) as $statement) {
            // Everything after the final closing parenthesis is a MySQL table
            // option (ENGINE, CHARSET, COLLATE, ROW_FORMAT, ...). Cutting at
            // that parenthesis is more reliable than pattern-matching each
            // option name, and it cannot touch column definitions.
            $closing = strrpos($statement, ')');
            $normalised = $closing === false
                ? $statement
                : substr($statement, 0, $closing + 1);
            $normalised = trim($normalised);

            if (preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(\w+)\s*\((.*)\)$/is', $normalised, $matches) === 1) {
                [$tableSql, $tableIndexes] = self::translateCreateTable($matches[1], $matches[2]);
                $statements[] = $tableSql;
                $indexes = [...$indexes, ...$tableIndexes];

                continue;
            }

            $statements[] = self::translateScalarTypes($normalised);
        }

        return implode(";\n", [...$statements, ...$indexes]) . ';';
    }

    /**
     * Translate one CREATE TABLE statement and collect its standalone indexes.
     *
     * SQLite only honours PRIMARY KEY, UNIQUE, CHECK, and FOREIGN KEY inline;
     * every `KEY name (cols)` clause has to become a separate CREATE INDEX.
     *
     * @param string $table Table name.
     * @param string $body Column and constraint definitions.
     * @return array{0: string, 1: list<string>} Translated statement and index statements.
     */
    private static function translateCreateTable(string $table, string $body): array
    {
        $indexes = [];

        // Work definition-by-definition. Schema files put one column or
        // constraint per line, so splitting on newlines keeps this readable and
        // avoids fragile regular-expression surgery on comma placement.
        $definitions = [];
        foreach (preg_split('/\R/', trim($body)) ?: [] as $line) {
            $line = rtrim(trim($line), ',');

            if ($line === '') {
                continue;
            }

            // `KEY name (cols)` and `UNIQUE KEY name (cols)` cannot survive as
            // inline SQLite constraints, so they become standalone indexes.
            if (preg_match('/^(UNIQUE\s+)?KEY\s+(\w+)\s*\(([^)]*)\)$/i', $line, $key) === 1) {
                $indexes[] = sprintf(
                    'CREATE %sINDEX IF NOT EXISTS %s ON %s (%s)',
                    trim($key[1]) !== '' ? 'UNIQUE ' : '',
                    $key[2],
                    $table,
                    trim($key[3]),
                );

                continue;
            }

            $definitions[] = $line;
        }

        if ($definitions === []) {
            return [sprintf('CREATE TABLE IF NOT EXISTS %s ()', $table), $indexes];
        }

        // A column declared AUTO_INCREMENT becomes SQLite's rowid alias, which
        // also removes the need for the standalone primary key declaration.
        $hasRowIdAlias = false;
        foreach ($definitions as $position => $definition) {
            if (preg_match('/\bAUTO_INCREMENT\b/i', $definition) === 1) {
                $definitions[$position] = (string) preg_replace(
                    '/\b(?:BIG)?INT\b(?:\s+UNSIGNED)?\s+NOT\s+NULL\s+AUTO_INCREMENT\b/i',
                    'INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT',
                    $definition,
                );
                $hasRowIdAlias = true;
            }
        }

        if ($hasRowIdAlias) {
            $definitions = array_values(array_filter(
                $definitions,
                static fn (string $definition): bool => preg_match('/^PRIMARY\s+KEY\s*\(\s*id\s*\)$/i', $definition) !== 1,
            ));
        }

        // Any AUTO_INCREMENT that survived (unexpected attribute ordering) must
        // still be dropped or SQLite rejects the statement outright.
        $definitions = array_map(
            static fn (string $definition): string => (string) preg_replace('/\s+AUTO_INCREMENT\b/i', '', $definition),
            $definitions,
        );

        // Re-join on commas, so no definition may keep a trailing comma.
        $definitions = array_values(array_filter($definitions, static fn (string $definition): bool => $definition !== ''));
        $lastIndex = count($definitions) - 1;
        if ($lastIndex >= 0) {
            $definitions[$lastIndex] = rtrim($definitions[$lastIndex], ', ');
        }

        $statement = sprintf(
            'CREATE TABLE IF NOT EXISTS %s (%s)',
            $table,
            self::translateScalarTypes(implode(', ', $definitions)),
        );

        return [$statement, $indexes];
    }

    /**
     * Rewrite MySQL column types and attributes that SQLite rejects outright.
     *
     * @param string $sql A statement or column body.
     * @return string The rewritten text.
     */
    private static function translateScalarTypes(string $sql): string
    {
        // ENUM becomes TEXT guarded by a CHECK constraint. SQLite has no
        // row-value CHECK, so the constraint is rebuilt against the column name
        // (`CHECK (col IN (...))`) and placed immediately after the type.
        $sql = (string) preg_replace_callback(
            "/\b(\w+)\s+ENUM\s*\(((?:\s*'[^']*'\s*,?)+)\)/i",
            static function (array $matches): string {
                preg_match_all("/'([^']*)'/", $matches[2], $values);

                $allowed = array_map(
                    static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'",
                    $values[1],
                );

                return sprintf(
                    '%s TEXT CHECK (%s IN (%s))',
                    $matches[1],
                    $matches[1],
                    implode(', ', $allowed),
                );
            },
            $sql,
        );

        // ON UPDATE CURRENT_TIMESTAMP has no SQLite equivalent; the application
        // sets updated_at explicitly.
        $sql = (string) preg_replace('/\s+ON\s+UPDATE\s+CURRENT_TIMESTAMP/i', '', $sql);

        $replacements = [
            '/\bTINYINT\s*\(\s*1\s*\)/i' => 'TINYINT',
            '/\bMEDIUMTEXT\b/i' => 'TEXT',
            '/\bLONGTEXT\b/i' => 'TEXT',
            '/\bMEDIUMBLOB\b/i' => 'BLOB',
            '/\bBINARY\s*\(\s*\d+\s*\)/i' => 'BLOB',
            '/\bVARBINARY\s*\(\s*\d+\s*\)/i' => 'BLOB',
            '/\bBIGINT\b/i' => 'INTEGER',
            '/\bSMALLINT\b/i' => 'INTEGER',
            '/\bDOUBLE\b/i' => 'REAL',
            '/\bUNSIGNED\b/i' => '',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $sql = (string) preg_replace($pattern, $replacement, $sql);
        }

        return $sql;
    }

    /**
     * Split a script into statements, respecting comments and string literals.
     *
     * @param string $sql The full script text.
     * @return list<string> Individual statements without trailing semicolons.
     */
    private static function split(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $inSingle = false;
        $inBacktick = false;
        $inComment = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($inComment) {
                if ($char === "\n") {
                    $inComment = false;
                }

                continue;
            }

            if (!$inSingle && !$inBacktick && $char === '-' && $next === '-') {
                $inComment = true;
                $i++;

                continue;
            }

            if ($char === "'" && !$inBacktick) {
                if ($inSingle && $next === "'") {
                    $buffer .= "''";
                    $i++;

                    continue;
                }

                $inSingle = !$inSingle;
                $buffer .= $char;

                continue;
            }

            if ($char === '`' && !$inSingle) {
                $inBacktick = !$inBacktick;

                continue;
            }

            if ($char === ';' && !$inSingle) {
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
}
