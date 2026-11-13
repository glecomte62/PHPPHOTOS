<?php
declare(strict_types=1);

namespace Tests\Services;

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function testEscapesHtml(): void
    {
        self::assertSame('&lt;b&gt;&quot;Été&quot;&lt;/b&gt;', e('<b>"Été"</b>'));
    }

    public function testMediaUrlUsesKeyPrefixFolder(): void
    {
        self::assertSame('/media/ab/abcdef-1280.jpg', media_url('abcdef', 1280, 'jpg'));
    }

    public function testPhotoLabelIgnoresCameraFilenames(): void
    {
        self::assertSame('Arras', photo_label('GLE 1070', 'Arras'));
        self::assertSame('Arras', photo_label('DSC_29571', 'Arras'));
        self::assertSame('Arras', photo_label('', 'Arras'));
        self::assertSame('Le beffroi au crépuscule', photo_label('Le beffroi au crépuscule', 'Arras'));
    }

    public function testFrenchDates(): void
    {
        self::assertSame('14 juin 2025', format_date_fr('2025-06-14 10:32:05'));
        self::assertSame('août 2024', format_month_fr('2024-08-01'));
        self::assertSame('', format_date_fr(null));
    }
}
