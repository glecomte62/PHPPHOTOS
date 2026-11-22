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

    /** Anciennes collections Flickr (collection.php?id=…, gallery.php?col=…) → ancienne catégorie. */
    public const FLICKR_COLLECTIONS = [
        '196254507-72157725150442283' => 'nature',
        '196254507-72157724979216123' => 'evennements',
        '196254507-72157724990641182' => 'industrie',
        '196254507-72157724979213378' => 'animaux',
        '196254507-72157724984122496' => 'voyages',
        '196254507-72157724978039568' => 'aviation',
    ];

    /** Anciens albums Flickr (gallery.php?id=…) → ancienne adresse /catégorie/slug, relevés sur Flickr le 2026-11-22. */
    public const FLICKR_ALBUMS = [
        '72177720334730934' => '/nature/apres-le-feu',
        '72177720333932541' => '/evennements/defile-du-14-juillet-2010',
        '72177720311439009' => '/evennements/souvenirs-d-un-grand-duc-2023',
        '72177720320648025' => '/evennements/souvenirs-d-un-grand-duc-2024',
        '72177720319118755' => '/industrie/fonderie-de-cloches-cornille-havard',
        '72177720310476460' => '/animaux/oiseaux-a-aubigny',
        '72177720309104236' => '/animaux/phoques',
        '72177720310445059' => '/animaux/chiens',
        '72177720333918682' => '/animaux/jardin-des-hiboux',
        '72177720334821042' => '/voyages/espelette',
        '72177720334818583' => '/voyages/cap-breton',
        '72177720333926772' => '/voyages/honfleur',
        '72177720333926792' => '/voyages/cote-d-opale',
        '72177720333941273' => '/voyages/arras',
        '72177720333920285' => '/voyages/paris',
        '72177720309988021' => '/voyages/cabanes-de-gouville-sur-mer',
        '72177720309118524' => '/voyages/mur-de-l-atlantique',
        '72177720309114119' => '/voyages/la-france-eternelle',
        '72177720309885521' => '/voyages/pont-canal-de-briare',
        '72177720310210016' => '/voyages/macon',
        '72177720310202815' => '/voyages/jacques-prevert',
        '72177720310481188' => '/voyages/chateau-de-rambouillet',
        '72177720310537535' => '/voyages/fort-de-bregancon',
        '72177720310539970' => '/voyages/ile-de-re',
        '72177720310548291' => '/voyages/new-york',
        '72177720319060921' => '/voyages/ry',
        '72177720319157913' => '/voyages/pointe-du-decolle-st-lunaire',
        '72177720319264264' => '/voyages/camaret-sur-mer',
        '72177720319456335' => '/voyages/merveilles-de-bretagne',
        '72177720321569823' => '/voyages/catene-de-containers',
        '72177720328263878' => '/voyages/normandie-2025',
        '72177720326243047' => '/aviation/un-avion-dans-ma-ville-2025',
        '72177720310499545' => '/aviation/patrouille-de-france',
        '72177720310543591' => '/aviation/base-aerienne-de-cazaux',
        '72177720314803729' => '/aviation/meeting-lens-2014',
        '72177720310321906' => '/aviation/musee-aeroscopia-toulouse-blagnac',
        '72177720308292124' => '/aviation/meeting-arras-2023',
        '72177720301300532' => '/aviation/club-ulm-evasion-maubeuge-lfqj',
        '72177720310515828' => '/aviation/brussels-airport-spotting',
    ];

    /** Anciens scripts à la racine du site Flickr. */
    private const ROOT_SCRIPTS = ['/index.php' => '/', '/map.php' => '/carte', '/guestbook.php' => '/contact'];

    /**
     * Nouvelle adresse pour une ancienne, ou null si ce n'est pas une adresse de l'ancien site.
     *
     * @param callable(string): ?string $findAlbum adresse actuelle d'un album d'après son ancien identifiant
     * @param string $query chaîne de requête (gallery.php?id=…, collection.php?id=…)
     */
    public static function resolve(string $path, callable $findAlbum, string $query = ''): ?string
    {
        $path = '/' . trim(strtolower($path), '/');
        if (isset(self::ROOT_SCRIPTS[$path])) {
            return self::ROOT_SCRIPTS[$path];
        }
        if ($path === '/gallery.php' || $path === '/collection.php') {
            parse_str($query, $params);
            $id = is_string($params['id'] ?? null) ? $params['id'] : '';
            $col = is_string($params['col'] ?? null) ? $params['col'] : '';
            if ($path === '/gallery.php' && isset(self::FLICKR_ALBUMS[$id])) {
                return self::resolve(self::FLICKR_ALBUMS[$id], $findAlbum);
            }
            $category = self::FLICKR_COLLECTIONS[$path === '/collection.php' ? $id : $col] ?? null;
            return $category !== null ? self::CATEGORIES[$category] : '/';
        }
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
