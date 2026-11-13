<?php
declare(strict_types=1);

namespace Tests\Services;

use App\Services\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

final class HtmlSanitizerTest extends TestCase
{
    public function testRemovesDangerousContent(): void
    {
        $dirty = '<p onclick="x()">Été <script>alert(1)</script><a href="javascript:alert(1)">lien</a> <span>ok</span></p><!-- c -->';
        self::assertSame('<p>Été <a>lien</a> ok</p>', HtmlSanitizer::clean($dirty));
    }

    public function testKeepsAllowedTagsAndSafeLinks(): void
    {
        $html = '<p><strong>Gras</strong> <em>it</em> <a href="https://exemple.fr" target="_blank">x</a> <a href="/contact">c</a></p><ul><li>un</li></ul>';
        self::assertSame(
            '<p><strong>Gras</strong> <em>it</em> <a href="https://exemple.fr" rel="noopener">x</a> <a href="/contact">c</a></p><ul><li>un</li></ul>',
            HtmlSanitizer::clean($html)
        );
    }

    public function testParagraphsFromPlainText(): void
    {
        self::assertSame("<p>Un</p>\n<p>Deux<br>trois</p>", HtmlSanitizer::paragraphs("Un\n\nDeux\ntrois"));
        self::assertSame('<p>Déjà</p>', HtmlSanitizer::paragraphs('<p>Déjà</p>'));
        self::assertSame('', HtmlSanitizer::paragraphs("  \n "));
    }

    public function testEditableRoundTrip(): void
    {
        $stored = "<p>Un <strong>a</strong></p>\n<p>Deux<br>trois</p>";
        $editable = HtmlSanitizer::toEditable($stored);
        self::assertSame("Un <strong>a</strong>\n\nDeux\ntrois", $editable);
        self::assertSame($stored, HtmlSanitizer::clean(HtmlSanitizer::paragraphs($editable)));
    }
}
