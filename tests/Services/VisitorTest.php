<?php
declare(strict_types=1);

namespace Tests\Services;

use App\Services\Visitor;
use PHPUnit\Framework\TestCase;

final class VisitorTest extends TestCase
{
    private const KEY = '0123456789abcdef0123456789abcdef';

    public function testRecognisesRobotsAndScripts(): void
    {
        foreach ([
            null, '',
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'facebookexternalhit/1.1',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 HeadlessChrome/120.0 Safari/537.36',
            'curl/8.4.0', 'python-requests/2.31',
        ] as $ua) {
            self::assertTrue(Visitor::isBot($ua), (string) $ua);
        }
        self::assertFalse(Visitor::isBot('Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 Version/17.1 Mobile/15E148 Safari/604.1'));
    }

    public function testVoterIdIsAKeyedHashOfAValidToken(): void
    {
        $token = str_repeat('ab', 16);
        $id = Visitor::voterId($token, self::KEY);

        self::assertSame(64, strlen((string) $id));
        self::assertSame($id, Visitor::voterId($token, self::KEY));
        self::assertNotSame($id, Visitor::voterId($token, strrev(self::KEY)));
        self::assertNotSame($token, $id);
    }

    public function testRejectsMalformedTokens(): void
    {
        foreach ([null, '', 'abc', str_repeat('z', 32), str_repeat('ab', 17), ['x']] as $bad) {
            self::assertNull(Visitor::voterId($bad, self::KEY));
        }
    }
}
