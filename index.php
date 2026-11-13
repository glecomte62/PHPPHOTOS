<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/FlickrAPI.php';
require_once __DIR__ . '/lib/Slug.php';

$api         = new FlickrAPI(FLICKR_API_KEY, FLICKR_USER_ID);
$error       = null;
$collections = [];

try {
    $collections = $api->getCollections();
} catch (RuntimeException $e) {
    $error = $e->getMessage();
}

$recentAlbums = [];
$setToColSlug = [];

// Construire le map set_id → colSlug à partir des collections déjà chargées
foreach ($collections as $col) {
    $colSlug = Slug::slugify($col['title']);
    foreach ($col['sets'] as $set) {
        $setToColSlug[$set['id']] = $colSlug;
    }
}

if ($error === null) {
    try {
        $recentAlbums = $api->getPhotosets(6);
    } catch (RuntimeException $e) {
        // silencieux : la section est absente si l'API échoue
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-N3XTKBXL');</script>
    <!-- End Google Tag Manager -->
    <title>Guillaume LECOMTE — Photographe amateur</title>
    <meta name="description" content="Galerie photographique de Guillaume Lecomte, photographe amateur né à Arras, ayant vécu à Aubigny-en-Artois, vivant désormais en Belgique.">
    <meta name="author" content="Guillaume Lecomte">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://guillaumelecomte.net/">
    <link rel="sitemap" type="application/xml" href="https://guillaumelecomte.net/sitemap.xml">
    <meta property="og:type" content="profile">
    <meta property="og:url" content="https://guillaumelecomte.net/">
    <meta property="og:title" content="Guillaume LECOMTE — Photographe amateur">
    <meta property="og:description" content="Galerie photographique de Guillaume Lecomte, photographe amateur né à Arras, ayant vécu à Aubigny-en-Artois, vivant désormais en Belgique.">
    <meta property="og:locale" content="fr_FR">
    <meta property="profile:first_name" content="Guillaume">
    <meta property="profile:last_name" content="Lecomte">
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "ProfilePage",
      "url": "https://guillaumelecomte.net/",
      "name": "Guillaume LECOMTE — Photographe amateur",
      "description": "Galerie photographique de Guillaume Lecomte, photographe amateur né à Arras, ayant vécu à Aubigny-en-Artois, vivant désormais en Belgique.",
      "mainEntity": {
        "@type": "Person",
        "name": "Guillaume Lecomte",
        "givenName": "Guillaume",
        "familyName": "Lecomte",
        "description": "Photographe amateur né à Arras (Pas-de-Calais), ayant vécu à Aubigny-en-Artois, vivant désormais en Belgique.",
        "birthPlace": {
          "@type": "Place",
          "name": "Arras",
          "address": {
            "@type": "PostalAddress",
            "addressLocality": "Arras",
            "addressRegion": "Pas-de-Calais",
            "addressCountry": "FR"
          }
        },
        "homeLocation": {
          "@type": "Place",
          "address": {
            "@type": "PostalAddress",
            "addressCountry": "BE"
          }
        },
        "url": "https://guillaumelecomte.net/"
      }
    }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <!-- Matomo -->
    <script>
      var _paq = window._paq = window._paq || [];
      _paq.push(['trackPageView']);
      _paq.push(['enableLinkTracking']);
      (function() {
        var u="//stats.moncarnetulm.fr/";
        _paq.push(['setTrackerUrl', u+'matomo.php']);
        _paq.push(['setSiteId', '3']);
        var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
        g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
      })();
    </script>
    <!-- End Matomo Code -->
</head>
<body>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-N3XTKBXL" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->

<section class="hero">
    <nav class="hero-nav site-nav">
        <a class="site-nav-link" href="map.php">Carte</a>
        <a class="site-nav-link" href="guestbook.php">Livre d'or</a>
    </nav>
    <div class="hero-identity">
        <p class="hero-name-first">Guillaume</p>
        <p class="hero-name-last">LECOMTE</p>
        <p class="hero-tagline">Photographies</p>
    </div>
    <div class="hero-scroll">&#8595;</div>
</section>

<main class="container">

    <?php if ($error): ?>
    <div class="error-box">
        <strong>Impossible de charger les collections.</strong><br>
        <?= htmlspecialchars($error) ?>
    </div>

    <?php elseif (empty($collections)): ?>
    <div class="error-box">
        Aucune collection trouvée pour ce compte Flickr.
    </div>

    <?php else: ?>
    <?php if (!empty($recentAlbums)): ?>
    <section class="recent-albums">
        <h2 class="recent-albums-title">Derniers albums</h2>
        <div class="recent-albums-grid">
            <?php foreach ($recentAlbums as $album):
                $albumSlug = Slug::slugify($album['title']);
                $colSlug   = $setToColSlug[$album['id']] ?? null;
                $href      = $colSlug
                    ? '/' . htmlspecialchars($colSlug) . '/' . htmlspecialchars($albumSlug)
                    : 'gallery.php?id=' . htmlspecialchars($album['id']);
            ?>
            <a class="gallery-card" href="<?= $href ?>">
                <?php if ($album['cover_url']): ?>
                <img
                    class="gallery-card-cover"
                    src="<?= htmlspecialchars($album['cover_url']) ?>"
                    alt="<?= htmlspecialchars($album['title']) ?>"
                    loading="lazy"
                >
                <?php else: ?>
                <div class="gallery-card-cover gallery-card-no-cover"></div>
                <?php endif; ?>
                <div class="gallery-card-info">
                    <div class="gallery-card-title"><?= htmlspecialchars($album['title']) ?></div>
                    <div class="gallery-card-count"><?= $album['photo_count'] ?> photo<?= $album['photo_count'] > 1 ? 's' : '' ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    <div class="galleries-grid">
        <?php foreach ($collections as $col): ?>
        <a class="gallery-card" href="/<?= htmlspecialchars(Slug::slugify($col['title'])) ?>">
            <?php if ($col['cover_url']): ?>
            <img
                class="gallery-card-cover"
                src="<?= htmlspecialchars($col['cover_url']) ?>"
                alt="<?= htmlspecialchars($col['title']) ?>"
                loading="lazy"
            >
            <?php else: ?>
            <div class="gallery-card-cover gallery-card-no-cover"></div>
            <?php endif; ?>
            <div class="gallery-card-info">
                <div class="gallery-card-title"><?= htmlspecialchars($col['title']) ?></div>
                <div class="gallery-card-count"><?= count($col['sets']) ?> album<?= count($col['sets']) > 1 ? 's' : '' ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</main>

<footer class="site-footer">
    <p class="site-footer-copy">&copy; <?= date('Y') ?> Guillaume LECOMTE — Toutes les photographies présentées sur ce site sont protégées par le droit d'auteur. Toute reproduction, diffusion ou utilisation, même partielle, sans autorisation écrite préalable est strictement interdite.</p>
</footer>

</body>
</html>
