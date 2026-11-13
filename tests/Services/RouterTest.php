<?php
declare(strict_types=1);

namespace Tests\Services;

use App\Services\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->router->get('/', fn () => 'home');
        $this->router->get('/carte', fn () => 'map');
        $this->router->get('/admin/albums/{id:\d+}', fn (string $id) => "album $id");
        $this->router->post('/admin/albums/{id:\d+}', fn (string $id) => "update $id");
        $this->router->get('/{theme}/{album}/photo/{photo:\d+}', fn (string $theme, string $album, string $photo) => "$theme/$album/$photo");
        $this->router->get('/{theme}/{album}', fn (string $theme, string $album) => "$theme/$album");
        $this->router->get('/{theme}', fn (string $theme) => "theme $theme");
    }

    private function call(string $method, string $path): ?string
    {
        $found = $this->router->match($method, $path);
        return $found === null ? null : ($found[0])(...$found[1]);
    }

    public function testMatchesStaticAndDynamicRoutesInOrder(): void
    {
        self::assertSame('home', $this->call('GET', '/'));
        self::assertSame('map', $this->call('GET', '/carte'));
        self::assertSame('map', $this->call('GET', '/carte/'));
        self::assertSame('album 12', $this->call('GET', '/admin/albums/12'));
        self::assertSame('update 12', $this->call('POST', '/admin/albums/12'));
        self::assertSame('vues-du-ciel/arras/34', $this->call('GET', '/vues-du-ciel/arras/photo/34'));
        self::assertSame('vues-du-ciel/arras', $this->call('GET', '/vues-du-ciel/arras'));
        self::assertSame('theme paysages', $this->call('GET', '/paysages'));
    }

    public function testHeadIsTreatedAsGet(): void
    {
        self::assertSame('map', $this->call('HEAD', '/carte'));
    }

    public function testNoMatch(): void
    {
        self::assertNull($this->router->match('GET', '/admin/albums/abc'));
        self::assertNull($this->router->match('POST', '/carte'));
        self::assertNull($this->router->match('GET', '/Majuscules'));
        self::assertNull($this->router->match('GET', '/a/b/c/d'));
    }

    public function testDispatchCallsNotFound(): void
    {
        $called = false;
        $this->router->dispatch('GET', '/x/y/z/w?q=1', function () use (&$called) { $called = true; });
        self::assertTrue($called);
    }
}
