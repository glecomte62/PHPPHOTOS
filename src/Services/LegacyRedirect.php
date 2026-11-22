<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Anciennes adresses de l'ancien site (galeries Flickr) encore connues de Google :
 * on renvoie le visiteur, avec une redirection permanente, vers l'album ou la page la plus proche.
 */
final class LegacyRedirect
{
    /** Anciennes catégories → page de repli. */
    public const CATEGORIES = [
        'evennements' => '/evenements',
        'voyages' => '/voyages',
        'aviation' => '/aviation',
        'trains' => '/ferroviaire',
        'industrie' => '/mots-cles/patrimoine-industriel',
        'animaux' => '/mots-cles/oiseaux',
        'nature' => '/photos',
    ];

    /** Anciens albums → album ou page équivalente (relevés dans les journaux du serveur). */
    public const ALBUMS = [
        // Événements
        '/evennements/defile-du-14-juillet-2010' => '/evenements/defile-du-14-juillet-2010',
        '/evennements/souvenirs-d-un-grand-duc-2024' => '/evenements/souvenir-d-un-grand-duc-edition-2024',
        '/evennements/souvenirs-d-un-grand-duc-2023' => '/mots-cles/grand-duc',
        // Voyages
        '/voyages/mur-de-l-atlantique' => '/villes-patrimoine/mur-de-l-atlantique',
        '/voyages/ile-de-re' => '/au-fil-des-regions/ile-de-re',
        '/voyages/cote-d-opale' => '/au-fil-des-regions/cote-d-opale',
        '/voyages/normandie-2025' => '/au-fil-des-regions/normandie',
        '/voyages/paris' => '/villes-patrimoine/paris',
        '/voyages/arras' => '/villes-patrimoine/arras',
        '/voyages/honfleur' => '/villes-patrimoine/honfleur',
        '/voyages/fort-de-bregancon' => '/villes-patrimoine/fort-de-bregancon',
        '/voyages/merveilles-de-bretagne' => '/au-fil-des-regions/bretagne',
        '/voyages/camaret-sur-mer' => '/au-fil-des-regions/bretagne',
        '/voyages/pointe-du-decolle-st-lunaire' => '/au-fil-des-regions/bretagne',
        '/voyages/catene-de-containers' => '/au-fil-des-regions/normandie',
        '/voyages/ry' => '/au-fil-des-regions/normandie',
        '/voyages/cabanes-de-gouville-sur-mer' => '/au-fil-des-regions/normandie',
        '/voyages/macon' => '/au-fil-des-regions/de-la-seine-au-canal-de-bourgogne',
        '/voyages/pont-canal-de-briare' => '/au-fil-des-regions/de-la-seine-au-canal-de-bourgogne',
        '/voyages/chateau-de-rambouillet' => '/mots-cles/chateau',
        '/voyages/espelette' => '/au-fil-des-regions',
        '/voyages/cap-breton' => '/au-fil-des-regions',
        '/voyages/la-france-eternelle' => '/au-fil-des-regions',
        // Aviation
        '/aviation/patrouille-de-france' => '/aviation/patrouille-de-france-ba-701',
        '/aviation/un-avion-dans-ma-ville-2025' => '/aviation/un-avion-dans-ma-ville-edition-2025',
        '/aviation/brussels-airport-spotting' => '/aviation/rackham-brussels-airlines',
        '/aviation/meeting-arras-2023' => '/mots-cles/meeting-aerien',
        '/aviation/meeting-lens-2014' => '/mots-cles/meeting-aerien',
        '/aviation/base-aerienne-de-cazaux' => '/mots-cles/aviation-militaire',
        '/aviation/club-ulm-evasion-maubeuge-lfqj' => '/mots-cles/ulm',
        // Animaux
        '/animaux/jardin-des-hiboux' => '/mots-cles/oiseaux',
        '/animaux/oiseaux-a-aubigny' => '/mots-cles/oiseaux',
        '/animaux/chiens' => '/mots-cles/chat',
        '/animaux/phoques' => '/au-fil-des-regions/cote-d-opale',
    ];

    /**
     * Nouvelle adresse pour une ancienne, ou null si ce n'est pas une adresse de l'ancien site.
     *
     * @param callable(string): ?string $findAlbum adresse actuelle d'un album d'après son ancien identifiant
     */
    public static function resolve(string $path, callable $findAlbum): ?string
    {
        $path = '/' . trim(strtolower($path), '/');
        if (isset(self::ALBUMS[$path])) {
            return self::ALBUMS[$path];
        }
        if (!preg_match('#^/([a-z]+)(?:/([a-z0-9-]+(?:\.php)?))?$#', $path, $m) || !isset(self::CATEGORIES[$m[1]])) {
            return null;
        }
        $category = self::CATEGORIES[$m[1]];
        $slug = $m[2] ?? '';
        return match (true) {
            $slug === '', $slug === 'index.php' => $category,
            $slug === 'map.php' => '/carte',
            $slug === 'guestbook.php' => '/contact',
            str_ends_with($slug, '.php') => $category,
            default => $findAlbum($slug) ?? $category,
        };
    }
}
