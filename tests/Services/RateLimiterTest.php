<?php
declare(strict_types=1);

namespace Tests\Services;

use App\Services\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/rl-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
    }

    public function testBlocksAfterMaxHitsWithinWindow(): void
    {
        $rl = new RateLimiter($this->dir);
        $now = 1_000_000;
        for ($i = 0; $i < 3; $i++) {
            self::assertFalse($rl->tooMany('contact:1.2.3.4', 3, 3600, $now));
            $rl->hit('contact:1.2.3.4', 3600, $now);
        }
        self::assertTrue($rl->tooMany('contact:1.2.3.4', 3, 3600, $now + 10));
        self::assertFalse($rl->tooMany('contact:5.6.7.8', 3, 3600, $now + 10));
    }

    public function testOldHitsExpire(): void
    {
        $rl = new RateLimiter($this->dir);
        $rl->hit('k', 60, 1000);
        $rl->hit('k', 60, 1001);
        self::assertTrue($rl->tooMany('k', 2, 60, 1030));
        self::assertFalse($rl->tooMany('k', 2, 60, 1100));
    }

    public function testClear(): void
    {
        $rl = new RateLimiter($this->dir);
        $rl->hit('k', 60, 1000);
        $rl->clear('k');
        self::assertFalse($rl->tooMany('k', 1, 60, 1001));
    }
}
