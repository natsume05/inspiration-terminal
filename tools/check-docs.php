<?php

/**
 * Check the documentation for the two failure modes that are easy to introduce
 * and hard to notice.
 *
 * A README is the first thing anyone reads, and it is also the file nobody
 * compiles. Two mistakes therefore survive review: a relative link or image path
 * that no longer resolves after a file is renamed, and encoding damage from an
 * editor or shell that wrote UTF-8 as something else — which turns Chinese text
 * into mojibake that looks like a rendering bug in the browser.
 *
 * Both are checked here so a rename cannot silently break the front page.
 *
 * Usage:
 *   php tools/check-docs.php
 */

declare(strict_types=1);

$basePath = dirname(__DIR__);

/** Documents whose links and encoding are checked. */
$documents = [
    'README.md',
    'README.en.md',
    'CHANGELOG.md',
    'SECURITY.md',
    'NOTICE',
    'docs/project-overview.md',
    'docs/security.md',
    'docs/deployment.md',
    'docs/interview-questions.md',
    'docs/release-notes-v2.0.0.md',
    'docs/release-notes-v2.0.1.md',
];

/**
 * Links that are deliberately not resolved locally: absolute URLs, anchors, and
 * anything inside a code span.
 */
$externalPattern = '~^(?:https?:|mailto:|#)~i';

/**
 * Turn a heading into the identifier a Markdown host generates for it.
 *
 * The rule GitHub uses, and the one every other renderer copies: lower-case,
 * drop punctuation, and replace spaces with hyphens. Letters outside ASCII are
 * kept, which is what makes the Chinese headings addressable at all.
 *
 * @param string $heading Heading text.
 * @return string Anchor identifier.
 */
function slugify(string $heading): string
{
    $slug = mb_strtolower(trim($heading), 'UTF-8');
    // Punctuation and symbols go; letters, digits, hyphens and underscores stay.
    $slug = (string) preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $slug);
    $slug = (string) preg_replace('/\s+/u', '-', $slug);

    return trim($slug, '-');
}

$checked = 0;
$failures = [];

foreach ($documents as $relative) {
    $path = $basePath . '/' . $relative;

    if (!is_file($path)) {
        $failures[] = sprintf('%s  listed as documentation but missing', $relative);

        continue;
    }

    $contents = (string) file_get_contents($path);

    if (!mb_check_encoding($contents, 'UTF-8')) {
        $failures[] = sprintf('%s  is not valid UTF-8', $relative);

        continue;
    }

    // Two or more consecutive Latin-1 letters is the signature of UTF-8 bytes
    // having been read as single-byte characters, which is how a Chinese file
    // ends up full of sequences such as "æµ‹è¯•".
    if (preg_match('/[\x{00c0}-\x{00ff}]{2,}/u', $contents) === 1) {
        $failures[] = sprintf('%s  looks like UTF-8 that was saved as another encoding', $relative);
    }

    // Every heading in the file, as an anchor target.
    preg_match_all('/^#{1,6}\s+(.+?)\s*$/mu', $contents, $headingMatches);
    $anchors = array_map(slugify(...), $headingMatches[1]);

    preg_match_all('#!?\[[^\]]*\]\(([^)\s]+)\)#', $contents, $matches);

    foreach (array_unique($matches[1]) as $target) {
        // A link into the same document has to name a heading that exists. This
        // is the check that earns its keep: renaming a section silently breaks
        // every reference to it, and nothing else notices.
        if (str_starts_with($target, '#')) {
            $checked++;

            if (!in_array(slugify(rawurldecode(substr($target, 1))), $anchors, true)) {
                $failures[] = sprintf('%s  links to an anchor with no matching heading: %s', $relative, $target);
            }

            continue;
        }

        if (preg_match($externalPattern, $target) === 1) {
            continue;
        }

        // Links are resolved relative to the file that contains them.
        $directory = dirname($path);
        $resolved = $directory . '/' . rawurldecode((string) preg_replace('/#.*$/', '', $target));
        $checked++;

        if (!file_exists($resolved)) {
            $failures[] = sprintf('%s  links to a path that does not exist: %s', $relative, $target);
        }
    }
}

printf("Checked %d document(s) and %d local link(s).%s", count($documents), $checked, PHP_EOL);

if ($failures === []) {
    echo 'Documentation links and encoding are clean.' . PHP_EOL;

    exit(0);
}

echo PHP_EOL . 'Problems:' . PHP_EOL;

foreach ($failures as $failure) {
    echo '  - ' . $failure . PHP_EOL;
}

exit(1);
