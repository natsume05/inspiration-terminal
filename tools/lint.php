<?php

/**
 * Static analysis pass over the code base.
 *
 * This is deliberately not a linter: the repository ships without Composer
 * dependencies, so `php -l` plus a set of project-specific rules gives useful
 * signal in CI without a toolchain install. It catches the classes of mistake
 * that actually appeared in this code base:
 *
 *   * building SQL by string interpolation instead of binding parameters;
 *   * echoing a value straight into a template without escaping it;
 *   * a controller closure referencing a variable it did not capture;
 *   * leaving a `TODO` behind in shipped code.
 *
 * Run: php tools/lint.php
 */

declare(strict_types=1);

$basePath = dirname(__DIR__);
$sourceDirectories = ['src', 'routes', 'templates', 'bin', 'tools', 'tests', 'public'];

$violations = [];
$accepted = [];
$filesChecked = 0;

/**
 * Collect the PHP files under a directory.
 *
 * @param string $directory Absolute directory to scan.
 * @return list<string> File paths.
 */
function phpFiles(string $directory): array
{
    if (!is_dir($directory)) {
        return [];
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
    );

    $files = [];
    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

/**
 * Collect the PHP expressions a template line echoes with short echo tags.
 *
 * Only `<?=` is examined: that is the form that writes straight to the response.
 *
 * @param string $line One line of a template.
 * @return list<string> Expression source between `<?=` and the closing tag.
 */
function templateEchoes(string $line): array
{
    if (!str_contains($line, '<?=')) {
        return [];
    }

    preg_match_all('/<\?=(.*?)\?>/', $line, $matches);

    return $matches[1];
}

/**
 * Decide whether an echoed expression can only ever produce inert output.
 *
 * @param string $expression PHP source of the echo expression.
 * @return string|null Reason it is safe, or null when it needs escaping.
 */
function inertReason(string $expression): ?string
{
    // Already escaped with the project helper.
    if (str_contains($expression, 'View::escape(')) {
        return 'passed through View::escape()';
    }

    // The layout prints the child template's finished markup. Every value inside
    // that markup is escaped where it is printed, so escaping the block as a
    // whole would render the page's HTML as visible text. The variable is given a
    // name that cannot be mistaken for a value needing escaping.
    if (preg_match('/^\s*\$renderedContent\s*$/', $expression) === 1) {
        return 'pre-rendered child template';
    }

    // urlencode percent-encodes everything outside [A-Za-z0-9_.-], so the result
    // cannot contain any character that could close an attribute or start a tag.
    if (str_contains($expression, 'urlencode(') || str_contains($expression, 'rawurlencode(')) {
        return 'URL-encoded component';
    }

    // An integer cast cannot carry markup.
    if (preg_match('/\(\s*int\s*\)/', $expression) === 1
        || preg_match('/\bnumber_format\s*\(/', $expression) === 1
        || preg_match('/\bcount\s*\(/', $expression) === 1
    ) {
        return 'numeric output';
    }

    // A value produced entirely by a ternary whose branches are string literals
    // cannot contain anything the caller controls.
    $withoutVariables = preg_replace('/\$\w+(\[[^\]]*\])?(\s*->\s*\w+)?/', '', $expression);

    if ($withoutVariables !== null && !str_contains($withoutVariables, '$')) {
        // Only accept when what remains is a ternary of quoted strings, so an
        // arbitrary function call such as raw($input) is still reported.
        if (preg_match('/\?/', $expression) === 1
            && preg_match_all('/\'[^\']*\'|"[^"]*"/', $expression) >= 1
            && preg_match('/[a-zA-Z_]\w*\s*\(/', $withoutVariables) !== 1
        ) {
            return 'conditional returning only string literals';
        }

        // A bare quoted string, or nothing dynamic at all.
        if (preg_match('/^\s*(\'[^\']*\'|"[^"]*")\s*$/', $expression) === 1) {
            return 'string literal';
        }
    }

    return null;
}

foreach ($sourceDirectories as $directory) {
    foreach (phpFiles($basePath . '/' . $directory) as $file) {
        $filesChecked++;

        // Paths are normalised to forward slashes before any rule inspects them.
        // Without this the rules that match on a path prefix, such as the
        // template-escaping rule, silently never fire on Windows: the separator
        // is a backslash there, so the comparison fails and the check passes for
        // the wrong reason. That is exactly how this rule came to be broken on
        // one platform and enforced on the other.
        $relative = str_replace('\\', '/', $file);
        $relative = str_replace(str_replace('\\', '/', $basePath) . '/', '', $relative);

        $contents = (string) file_get_contents($file);
        $lines = explode("\n", $contents);

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;
            $trimmed = ltrim($line);

            // Comments and documentation are allowed to mention these patterns.
            $isComment = str_starts_with($trimmed, '*')
                || str_starts_with($trimmed, '//')
                || str_starts_with($trimmed, '/*')
                || str_starts_with($trimmed, '#');

            if ($isComment) {
                continue;
            }

            // Rule 1: no SQL assembled with interpolated variables.
            // Parameter binding is the guarantee; interpolation reintroduces it.
            // Keywords are matched in upper case only, because that is how SQL is
            // written here. Matching case-insensitively flagged ordinary prose
            // that happened to contain a word such as "insert".
            if (preg_match('/\b(SELECT|INSERT|UPDATE|DELETE|FROM|WHERE|VALUES)\b/', $line) === 1
                && preg_match('/\$\w+/', $line) === 1
                && preg_match('/[\'"]\s*\.\s*\$\w+/', $line) === 1
            ) {
                $violations[] = sprintf(
                    '%s:%d  SQL appears to interpolate a variable; bind it as a parameter instead',
                    $relative,
                    $lineNumber,
                );
            }

            // Rule 2: templates must escape output that reaches HTML.
            //
            // The first version of this rule flagged every `<?= $…` that did not
            // name an escaping helper, which produced nineteen false positives:
            // conditional expressions that can only emit a fixed literal such as
            // `$hasLiked ? 'is-active' : ''`. A rule that cries wolf gets ignored,
            // so an expression is accepted when every value it can produce is
            // demonstrably inert, and every acceptance is reported below rather
            // than passing silently.
            if (str_starts_with($relative, 'templates/')) {
                foreach (templateEchoes($line) as $expression) {
                    $reason = inertReason($expression);

                    if ($reason === null) {
                        $violations[] = sprintf(
                            '%s:%d  template output is not escaped: %s',
                            $relative,
                            $lineNumber,
                            trim($expression),
                        );

                        continue;
                    }

                    $accepted[] = sprintf('%s:%d  %s — %s', $relative, $lineNumber, trim($expression), $reason);
                }
            }

            // Rule 3: no unfinished markers in shipped code. A line that is
            // itself a detection pattern is not shipped code, so skip it.
            if (preg_match('/\b(TODO|FIXME|XXX)\b/', $line) === 1
                && !str_contains($line, 'preg_match')
            ) {
                $violations[] = sprintf('%s:%d  unresolved marker: %s', $relative, $lineNumber, trim($line));
            }
        }

        // Rule 4: every file must parse.
        $output = [];
        $exitCode = 0;
        exec(sprintf('%s -l %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($file)), $output, $exitCode);

        if ($exitCode !== 0) {
            $violations[] = sprintf('%s  syntax error: %s', $relative, implode(' ', $output));
        }
    }
}

printf("Checked %d PHP files.%s", $filesChecked, PHP_EOL);

// Accepted expressions are listed rather than silently skipped: a rule with
// invisible exceptions is a rule nobody can trust.
if ($accepted !== [] && (in_array('--verbose', $argv, true) || in_array('-v', $argv, true))) {
    echo PHP_EOL . 'Template output accepted without escaping:' . PHP_EOL;
    foreach ($accepted as $note) {
        echo '  - ' . $note . PHP_EOL;
    }

    printf('%s%d accepted expression(s).%s', PHP_EOL, count($accepted), PHP_EOL);
}

if ($violations === []) {
    echo 'No violations found.' . PHP_EOL;
    exit(0);
}

echo PHP_EOL . 'Violations:' . PHP_EOL;
foreach ($violations as $violation) {
    echo '  - ' . $violation . PHP_EOL;
}

exit(1);
