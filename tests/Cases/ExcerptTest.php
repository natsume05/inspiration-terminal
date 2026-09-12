<?php

declare(strict_types=1);

namespace Tests\Cases;

use App\Support\Excerpt;
use Tests\TestCase;

/**
 * Tests for the plain-text preview built from a Markdown document.
 *
 * The listing pages render whatever this produces, so a marker it fails to
 * remove is visible to every reader: the blog index spent its first release
 * showing `## 起因` and an opening code fence as if the renderer had not run.
 */
final class ExcerptTest extends TestCase
{
    /**
     * Heading and emphasis markers must not reach the excerpt.
     */
    public function testMarkersAreRemoved(): void
    {
        $excerpt = Excerpt::from("# 起因\n\n这是 **很重要** 的一段。");

        $this->assertSame('起因 这是 很重要 的一段。', $excerpt, 'headings and emphasis are stripped');
    }

    /**
     * A fenced code block is code, and rendering it as prose reads as corruption.
     */
    public function testCodeBlocksAreDropped(): void
    {
        $excerpt = Excerpt::from("先看这段：\n\n```sql\nCREATE TABLE post_likes (\n    user_id INT\n);\n```\n\n结论在这里。");

        $this->assertSame('先看这段： 结论在这里。', $excerpt, 'a fenced block is removed with its contents');
    }

    /**
     * Inline code keeps its text but loses the backticks.
     */
    public function testInlineCodeKeepsItsText(): void
    {
        $excerpt = Excerpt::from('缺少 `STRICT_TRANS_TABLES` 时，值会被静默修正。');

        $this->assertSame('缺少 STRICT_TRANS_TABLES 时，值会被静默修正。', $excerpt, 'backticks are removed, text kept');
    }

    /**
     * Quotes and list markers are block syntax, not content.
     */
    public function testBlockSyntaxIsRemoved(): void
    {
        $excerpt = Excerpt::from("> 引用一行\n\n- 第一项\n- 第二项\n\n---\n\n1. 有序项");

        $this->assertSame('引用一行 第一项 第二项 有序项', $excerpt, 'quotes, bullets, rules and numbering are stripped');
    }

    /**
     * A link keeps its text and loses its target, which is not useful in a preview.
     */
    public function testLinksAndImagesKeepTheirText(): void
    {
        $excerpt = Excerpt::from('见 [文档](https://example.com/doc) 与 ![封面](/cover.png)。');

        $this->assertSame('见 文档 与 封面。', $excerpt, 'link and image targets are dropped, their text kept');
    }

    /**
     * Embedded HTML must not survive into an attribute-free text node.
     */
    public function testEmbeddedHtmlIsRemoved(): void
    {
        $excerpt = Excerpt::from('正文 <img src=x onerror=alert(1)> 结束');

        $this->assertFalse(str_contains($excerpt, '<'), 'no angle brackets survive');
        $this->assertFalse(str_contains($excerpt, 'onerror'), 'the handler text is gone with the tag');
    }

    /**
     * The result must be one line: the templates print it inside a paragraph.
     */
    public function testResultIsASingleLine(): void
    {
        $excerpt = Excerpt::from("第一行\n\n第二行\n\n\n第三行");

        $this->assertSame('第一行 第二行 第三行', $excerpt, 'newlines collapse to single spaces');
    }

    /**
     * A shortened excerpt must say so, so it cannot be mistaken for the whole
     * entry, and it must not exceed the length it was given: the value goes into
     * a `VARCHAR(320)` column, and one character over is a write error on MySQL
     * that SQLite accepts without complaint.
     */
    public function testTruncationIsMarkedAndWithinTheLength(): void
    {
        $excerpt = Excerpt::from(str_repeat('字', 400), 20);

        $this->assertSame(20, mb_strlen($excerpt), 'the ellipsis is counted within the limit');
        $this->assertTrue(str_ends_with($excerpt, '…'), 'the ellipsis marks the truncation');
    }

    /**
     * The column width BlogRepository passes must still fit after an ellipsis.
     */
    public function testExcerptFitsTheColumnWidth(): void
    {
        $excerpt = Excerpt::from(str_repeat('字', 5000), 320);

        $this->assertTrue(mb_strlen($excerpt) <= 320, 'the excerpt never exceeds the column width');
    }

    /**
     * Text shorter than the limit is returned whole and unmarked.
     */
    public function testShortTextIsNotMarked(): void
    {
        $excerpt = Excerpt::from('很短的一段。', 160);

        $this->assertSame('很短的一段。', $excerpt, 'no ellipsis is added to text that fits');
    }

    /**
     * Documented entities are decoded rather than shown as source.
     */
    public function testEntitiesAreDecoded(): void
    {
        $excerpt = Excerpt::from('A &amp; B &lt;tag&gt;');

        $this->assertSame('A & B <tag>', $excerpt, 'entities are decoded');
    }

    /**
     * A zero length must produce an empty string rather than an ellipsis alone.
     */
    public function testZeroLengthProducesNothing(): void
    {
        $this->assertSame('', Excerpt::from('内容', 0), 'a zero-length request yields no text');
    }
}
