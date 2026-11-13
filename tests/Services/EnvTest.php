<?php
declare(strict_types=1);

namespace Tests\Services;

use App\Services\Env;
use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase
{
    public function testParsesValuesCommentsAndQuotes(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($file, "# commentaire\nAPP_ENV=dev\nDB_PASS=\"a b=c\"\nEMPTY=\nQUOTED='x'\n\nBROKEN_LINE\n");

        Env::load($file);

        self::assertSame('dev', Env::get('APP_ENV'));
        self::assertSame('a b=c', Env::get('DB_PASS'));
        self::assertSame('', Env::get('EMPTY'));
        self::assertSame('x', Env::get('QUOTED'));
        self::assertNull(Env::get('BROKEN_LINE'));
        self::assertSame('defaut', Env::get('ABSENT', 'defaut'));
        unlink($file);
    }

    public function testMissingFileIsIgnored(): void
    {
        Env::load('/nonexistent/.env');
        self::assertSame('x', Env::get('NOPE', 'x'));
    }
}
