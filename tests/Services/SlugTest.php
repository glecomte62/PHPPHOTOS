<?php
declare(strict_types=1);

namespace Tests\Services;

use App\Services\Slug;
use PHPUnit\Framework\TestCase;

final class SlugTest extends TestCase
{
    public function testMakesReadableAsciiSlugs(): void
    {
        self::assertSame('cote-d-opale-2026', Slug::make("Côte d'Opale 2026"));
        self::assertSame('vues-du-ciel', Slug::make('  Vues du ciel !! '));
        self::assertSame('noel-a-l-ecole-oeuvre', Slug::make('Noël à l’École — Œuvre'));
        self::assertSame('sans-titre', Slug::make('???'));
    }

    public function testTruncatesTo80Characters(): void
    {
        $slug = Slug::make(str_repeat('abc ', 40));
        self::assertLessThanOrEqual(80, strlen($slug));
        self::assertStringEndsNotWith('-', $slug);
    }

    public function testUniqueAvoidsReservedAndExisting(): void
    {
        self::assertSame('carte-2', Slug::unique('Carte', fn () => false, true));
        self::assertSame('carte', Slug::unique('Carte', fn () => false, false));
        $taken = ['paysages', 'paysages-2'];
        self::assertSame('paysages-3', Slug::unique('Paysages', fn (string $s) => in_array($s, $taken, true)));
    }
}
