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

    public function testOriginalFilenameKeepsOnlyACleanBasename(): void
    {
        self::assertSame('DSC_0081.jpg', original_filename('DSC_0081.jpg'));
        self::assertSame('DSC_0024-3.JPG', original_filename('  ../../dossier/DSC_0024-3.JPG '));
        self::assertSame('Été.jpg', original_filename("Été\0\n.jpg"));
        self::assertNull(original_filename(''));
        self::assertNull(original_filename('../'));
        self::assertSame(190, mb_strlen((string) original_filename(str_repeat('a', 300) . '.jpg')));
    }

    public function testFrenchDates(): void
    {
        self::assertSame('14 juin 2025', format_date_fr('2025-06-14 10:32:05'));
        self::assertSame('août 2024', format_month_fr('2024-08-01'));
        self::assertSame('', format_date_fr(null));
    }
}
