<?php
declare(strict_types=1);

namespace Tests\Services;

use App\Services\License;
use PHPUnit\Framework\TestCase;

final class LicenseTest extends TestCase
{
    public function testUnknownCodeFallsBackToAllRightsReserved(): void
    {
        self::assertSame('arr', License::get('n-importe-quoi')['code']);
        self::assertSame('arr', License::get(null)['code']);
        self::assertNull(License::get('arr')['url']);
    }

    public function testCreativeCommonsLicensesPointToTheFrenchDeed(): void
    {
        $l = License::get('cc-by-nc');
        self::assertSame('CC BY-NC 4.0', $l['short']);
        self::assertSame('https://creativecommons.org/licenses/by-nc/4.0/deed.fr', $l['url']);
        foreach (License::ALL as $code => $license) {
            if ($code !== 'arr') {
                self::assertStringStartsWith('https://creativecommons.org/licenses/', License::get($code)['url']);
            }
        }
    }

    public function testNoticeUsesTheShootingYear(): void
    {
        self::assertSame('© 2016 Guillaume Lecomte · Tous droits réservés', License::notice(License::get('arr'), '2016-01-17 10:31:20'));
        self::assertSame('© Guillaume Lecomte · CC BY 4.0', License::notice(License::get('cc-by'), null));
    }

    public function testOnlyCreativeCommonsAllowsReuse(): void
    {
        self::assertFalse(License::get('arr')['reuse']);
        self::assertTrue(License::get('cc-by-nd')['reuse']);
    }
}
