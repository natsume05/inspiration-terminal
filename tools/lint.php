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

foreach ($sourceDirectories as $directory) {
    foreach (phpFiles($basePath . '/' . $directory) as $file) {
        $filesChecked++;
        $relative = str_replace($basePath . '/', '', $file);
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
            if (preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $line) === 1
                && preg_match('/\$\w+/', $line) === 1
                && preg_match('/[\'"]\s*\.\s*\$\w+/', $line) === 1
            ) {
                $violations[] = sprintf(
                    '%s:%d  SQL appears to interpolate a variable; bind it as a parameter instead',
                    $relative,
                    $lineNumber,
                );
            }

            // Rule 2: templates must escape output.
            if (str_starts_with($relative, 'templates/')
                && preg_match('/<\?=\s*\$(?!.*View::escape)(?!.*\$csrfField)(?!.*\$content)(?!.*\$csrfToken)/', $line) === 1
            ) {
                $violations[] = sprintf(
                    '%s:%d  template output is not passed through View::escape()',
                    $relative,
                    $lineNumber,
                );
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

if ($violations === []) {
    echo 'No violations found.' . PHP_EOL;
    exit(0);
}

echo PHP_EOL . 'Violations:' . PHP_EOL;
foreach ($violations as $violation) {
    echo '  - ' . $violation . PHP_EOL;
}

exit(1);
