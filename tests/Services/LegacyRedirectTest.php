<?php
declare(strict_types=1);

namespace Tests\Services;

use App\Services\LegacyRedirect;
use PHPUnit\Framework\TestCase;

final class LegacyRedirectTest extends TestCase
{
    private static function finder(): callable
    {
        $albums = ['mur-de-l-atlantique' => '/villes-patrimoine/mur-de-l-atlantique', 'honfleur' => '/villes-patrimoine/honfleur'];
        return static fn (string $slug): ?string => $albums[$slug] ?? null;
    }

    public function testKnownOldAlbumsGoToTheirNewAddress(): void
    {
        self::assertSame('/evenements/souvenir-d-un-grand-duc-edition-2024', LegacyRedirect::resolve('/evennements/souvenirs-d-un-grand-duc-2024', self::finder()));
        self::assertSame('/au-fil-des-regions/bretagne', LegacyRedirect::resolve('/voyages/camaret-sur-mer', self::finder()));
    }

    public function testUnlistedOldAlbumIsFoundByItsSlug(): void
    {
        self::assertSame('/villes-patrimoine/honfleur', LegacyRedirect::resolve('/voyages/honfleur/', self::finder()));
    }

    public function testUnknownAlbumFallsBackToItsCategory(): void
    {
        self::assertSame('/voyages', LegacyRedirect::resolve('/voyages/inconnu', self::finder()));
        self::assertSame('/ferroviaire', LegacyRedirect::resolve('/trains', self::finder()));
        self::assertSame('/evenements', LegacyRedirect::resolve('/evennements', self::finder()));
    }

    public function testOldScriptsOfTheFlickrSite(): void
    {
        self::assertSame('/carte', LegacyRedirect::resolve('/aviation/map.php', self::finder()));
        self::assertSame('/contact', LegacyRedirect::resolve('/voyages/guestbook.php', self::finder()));
        self::assertSame('/aviation', LegacyRedirect::resolve('/aviation/index.php', self::finder()));
    }

    public function testOldScriptsAtTheRoot(): void
    {
        self::assertSame('/carte', LegacyRedirect::resolve('/map.php', self::finder()));
        self::assertSame('/contact', LegacyRedirect::resolve('/guestbook.php', self::finder()));
        self::assertSame('/', LegacyRedirect::resolve('/index.php', self::finder()));
        self::assertSame('/', LegacyRedirect::resolve('/gallery.php', self::finder()));
    }

    public function testOldFlickrAlbumIdsGoThroughTheirOldAddress(): void
    {
        // Album listé dans la table des anciens albums
        self::assertSame('/mots-cles/aviation-militaire', LegacyRedirect::resolve('/gallery.php', self::finder(), 'id=72177720310543591'));
        // Album retrouvé par son ancien slug, avec un paramètre col en plus
        self::assertSame('/villes-patrimoine/honfleur', LegacyRedirect::resolve('/gallery.php', self::finder(), 'id=72177720333926772&col=196254507-72157724984122496'));
        // Album inconnu de Flickr : catégorie de la collection, sinon l'accueil
        self::assertSame('/voyages', LegacyRedirect::resolve('/gallery.php', self::finder(), 'id=1&col=196254507-72157724984122496'));
        self::assertSame('/', LegacyRedirect::resolve('/gallery.php', self::finder(), 'id=1'));
    }

    public function testOldFlickrCollectionIds(): void
    {
        self::assertSame('/aviation', LegacyRedirect::resolve('/collection.php', self::finder(), 'id=196254507-72157724978039568'));
        self::assertSame('/', LegacyRedirect::resolve('/collection.php', self::finder(), 'id=x'));
    }

    public function testOtherAddressesAreLeftAlone(): void
    {
        self::assertNull(LegacyRedirect::resolve('/credentials.json', self::finder()));
        self::assertNull(LegacyRedirect::resolve('/wp-login.php', self::finder()));
        self::assertNull(LegacyRedirect::resolve('/voyages/../../etc/passwd', self::finder()));
    }
}
