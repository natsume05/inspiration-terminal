<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Build a one-line preview from a Markdown document.
 *
 * Blog entries are stored as Markdown and rendered in the browser. A listing
 * needs plain text instead, and the obvious `strip_tags()` is not enough: it
 * removes HTML but leaves every Markdown marker, so an index page ends up showing
 * `## 起因` and an opening code fence as if the renderer had failed. Everything
 * from the syntax down to the punctuation is removed here, and the result is one
 * line that can be truncated without cutting a marker in half.
 *
 * What is produced is not the entry: code blocks are dropped rather than shown,
 * because a fence's contents presented as prose reads as a corruption rather than
 * as an excerpt.
 */
final class Excerpt
{
    /**
     * Reduce Markdown to a single line of plain text.
     *
     * @param string $markdown Source document.
     * @return string Plain text with no Markdown syntax.
     */
    public static function plainText(string $markdown): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $markdown);

        // Fenced code blocks first: their contents are code, and any marker inside
        // them is part of the code rather than syntax.
        $text = (string) preg_replace('/^```[^\n]*\n.*?^```/ms', ' ', $text);
        $text = (string) preg_replace('/^~~~[^\n]*\n.*?^~~~/ms', ' ', $text);

        // Indented code blocks.
        $text = (string) preg_replace('/^(?: {4}|\t).*$/m', ' ', $text);

        // Images lose their target but keep their alt text; links lose their target.
        $text = (string) preg_replace('/!\[([^\]]*)\]\([^)]*\)/u', '$1', $text);
        $text = (string) preg_replace('/\[([^\]]*)\]\([^)]*\)/u', '$1', $text);
        $text = (string) preg_replace('/\[([^\]]*)\]\[[^\]]*\]/u', '$1', $text);

        // Inline code keeps its text without the backticks.
        $text = (string) preg_replace('/`+([^`]*)`+/u', '$1', $text);

        // Line-leading block syntax: headings, quotes, list markers, rules.
        $text = (string) preg_replace('/^ {0,3}#{1,6}[ \t]*/m', '', $text);
        $text = (string) preg_replace('/^ {0,3}>[ \t]?/m', '', $text);
        $text = (string) preg_replace('/^ {0,3}(?:[-*+]|\d{1,9}[.)])[ \t]+/m', '', $text);
        $text = (string) preg_replace('/^ {0,3}(?:[-*_] *){3,}$/m', ' ', $text);

        // Inline emphasis and strikethrough markers.
        $text = (string) preg_replace('/(\*\*|__|~~)(.+?)\1/su', '$2', $text);
        $text = (string) preg_replace('/(?<![\w*])\*([^*\n]+)\*(?![\w*])/u', '$1', $text);
        $text = (string) preg_replace('/(?<![\w_])_([^_\n]+)_(?![\w_])/u', '$1', $text);

        // Any HTML the author embedded, then the usual entities.
        $text = (string) preg_replace('/<[^>]*>/u', ' ', $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Table pipes and stray marker characters left on their own.
        $text = str_replace('|', ' ', $text);

        // Collapse to a single line.
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    /**
     * Build a listing excerpt from a Markdown document.
     *
     * Truncation happens on a character count rather than a word count, because
     * Chinese text has no spaces to break on, and an ellipsis is appended so a
     * shortened sentence cannot be mistaken for a complete one.
     *
     * `$length` is the maximum length of the result, not the number of characters
     * kept before the ellipsis. Those differ by one, and the difference matters:
     * the value goes into `blog_posts.excerpt`, which is `VARCHAR(320)`, so a
     * result of 321 characters is a `Data too long for column` error on MySQL and
     * perfectly acceptable on SQLite — a failure that only appears in production.
     *
     * @param string $markdown Source document.
     * @param int $length Maximum characters in the result.
     * @return string Plain-text excerpt, never longer than `$length`.
     */
    public static function from(string $markdown, int $length = 160): string
    {
        $text = self::plainText($markdown);

        if ($length <= 0) {
            return '';
        }

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        // One character is given up to the ellipsis, so the result still fits.
        return rtrim(mb_substr($text, 0, $length - 1), " \t　") . '…';
    }
}
