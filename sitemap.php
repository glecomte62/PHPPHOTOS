<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/FlickrAPI.php';
require_once __DIR__ . '/lib/Slug.php';

$base = 'https://guillaumelecomte.net';
$api  = new FlickrAPI(FLICKR_API_KEY, FLICKR_USER_ID);

$urls = [];

// Pages statiques
$urls[] = ['loc' => $base . '/',               'changefreq' => 'weekly',  'priority' => '1.0'];
$urls[] = ['loc' => $base . '/map.php',        'changefreq' => 'monthly', 'priority' => '0.6'];
$urls[] = ['loc' => $base . '/guestbook.php',  'changefreq' => 'monthly', 'priority' => '0.5'];

// Collections et galeries depuis Flickr
try {
    $collections = $api->getCollections();
    foreach ($collections as $col) {
        $colSlug = Slug::slugify($col['title']);
        $urls[] = [
            'loc'        => $base . '/' . $colSlug,
            'changefreq' => 'monthly',
            'priority'   => '0.8',
        ];
        foreach ($col['sets'] ?? [] as $set) {
            $urls[] = [
                'loc'        => $base . '/' . $colSlug . '/' . Slug::slugify($set['title']),
                'changefreq' => 'monthly',
                'priority'   => '0.7',
            ];
        }
    }
} catch (RuntimeException $e) {
    // En cas d'erreur API on génère quand même le sitemap avec les pages statiques
}

header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $u): ?>
  <url>
    <loc><?= htmlspecialchars($u['loc']) ?></loc>
    <changefreq><?= $u['changefreq'] ?></changefreq>
    <priority><?= $u['priority'] ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
