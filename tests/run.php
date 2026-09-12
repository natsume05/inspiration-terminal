<?php

/**
 * Test suite runner.
 *
 * Run: php tests/run.php
 *
 * Exits non-zero when any assertion fails, so CI fails the build on regression.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';

use Tests\TestCase;

$testFiles = glob(__DIR__ . '/Cases/*Test.php') ?: [];
sort($testFiles);

$totalPassed = 0;
$totalFailures = [];
$suiteCount = 0;

foreach ($testFiles as $file) {
    $class = 'Tests\\Cases\\' . basename($file, '.php');
    require_once $file;

    if (!class_exists($class)) {
        $totalFailures[] = sprintf('%s did not declare class %s', basename($file), $class);

        continue;
    }

    /** @var TestCase $instance */
    $instance = new $class();
    $result = $instance->run();
    $suiteCount++;

    $totalPassed += $result['passed'];
    $totalFailures = [...$totalFailures, ...$result['failures']];

    $label = str_replace('Test', '', basename($file, '.php'));

    if ($result['failures'] === []) {
        printf("PASS  %-28s %d assertions\n", $label, $result['passed']);
    } else {
        printf("FAIL  %-28s %d passed, %d failed\n", $label, $result['passed'], count($result['failures']));
    }

    // Per-assertion detail is opt-in so CI logs stay readable.
    if (in_array('--verbose', $argv, true) || in_array('-v', $argv, true)) {
        foreach ($instance->notes() as $note) {
            echo '        ' . $note . PHP_EOL;
        }
    }
}

echo PHP_EOL . str_repeat('-', 60) . PHP_EOL;
printf("Suites: %d   Assertions passed: %d   Failures: %d\n", $suiteCount, $totalPassed, count($totalFailures));

if ($totalFailures !== []) {
    echo PHP_EOL . 'Failures:' . PHP_EOL;
    foreach ($totalFailures as $failure) {
        echo '  - ' . $failure . PHP_EOL;
    }

    exit(1);
}

echo 'All tests passed.' . PHP_EOL;
exit(0);
