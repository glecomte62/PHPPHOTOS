<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/FlickrAPI.php';
require_once __DIR__ . '/lib/Slug.php';

// Résolution slug → ID si URL friendly
if (isset($_GET['slug']) && !isset($_GET['id'])) {
    $api = new FlickrAPI(FLICKR_API_KEY, FLICKR_USER_ID);
    try {
        foreach ($api->getCollections() as $col) {
            if (Slug::slugify($col['title']) === $_GET['slug']) {
                $_GET['id'] = $col['id'];
                break;
            }
        }
    } catch (RuntimeException) {}
    if (!isset($_GET['id'])) {
        header('Location: /', true, 301);
        exit;
    }
}

$collectionId = $_GET['id'] ?? '';
if (!$collectionId) {
    header('Location: index.php');
    exit;
}

$api        = new FlickrAPI(FLICKR_API_KEY, FLICKR_USER_ID);
$error      = null;
$collection = null;
$photosets  = [];
$colSlug    = '';

try {
    $collections = $api->getCollections();
    foreach ($collections as $col) {
        if ($col['id'] === $collectionId) {
            $collection = $col;
            break;
        }
    }

    if (!$collection) {
        throw new RuntimeException('Collection introuvable.');
    }
    $colSlug = Slug::slugify($collection['title']);

    $ids = array_column($collection['sets'] ?? [], 'id');
    $infoMap = $api->getPhotosetsInfo($ids);

    // Conserver l'ordre original des sets
    foreach ($collection['sets'] ?? [] as $set) {
        if (isset($infoMap[$set['id']])) {
            $photosets[] = $infoMap[$set['id']];
        }
    }
} catch (RuntimeException $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-N3XTKBXL');</script>
    <!-- End Google Tag Manager -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php $colTitle = htmlspecialchars($collection['title'] ?? 'Collection'); ?>
    <title><?= $colTitle ?> — Guillaume LECOMTE</title>
    <meta name="description" content="Collection « <?= $colTitle ?> » — photographies de Guillaume Lecomte, photographe amateur né à Arras, vivant en Belgique.">
    <meta name="author" content="Guillaume Lecomte">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://guillaumelecomte.net/<?= htmlspecialchars($colSlug) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://guillaumelecomte.net/<?= htmlspecialchars($colSlug) ?>">
    <meta property="og:title" content="<?= $colTitle ?> — Guillaume LECOMTE">
    <meta property="og:description" content="Collection « <?= $colTitle ?> » — photographies de Guillaume Lecomte, photographe amateur né à Arras, vivant en Belgique.">
    <meta property="og:locale" content="fr_FR">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-N3XTKBXL" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->

<header class="site-header">
    <a class="site-title" href="index.php"><span class="site-title-first">Guillaume</span> <span class="site-title-last">LECOMTE</span></a>
    <span class="site-title-section"><?= htmlspecialchars($collection['title'] ?? 'Collection') ?></span>
    <?php if (!empty($collection['description'])): ?>
    <span class="site-subtitle"><?= htmlspecialchars($collection['description']) ?></span>
    <?php endif; ?>
    <nav class="site-nav">
        <a class="site-nav-link" href="map.php">Carte</a>
        <a class="site-nav-link" href="guestbook.php">Livre d'or</a>
    </nav>
</header>

<main class="container">

    <?php if ($error): ?>
    <div class="error-box">
        <strong>Impossible de charger la collection.</strong><br>
        <?= htmlspecialchars($error) ?>
    </div>

    <?php elseif (empty($photosets)): ?>
    <div class="error-box">
        Aucun album dans cette collection.
    </div>

    <?php else: ?>
    <div class="galleries-grid">
        <?php foreach ($photosets as $set): ?>
        <a class="gallery-card" href="/<?= htmlspecialchars($colSlug) ?>/<?= htmlspecialchars(Slug::slugify($set['title'])) ?>">
            <?php if ($set['cover_url']): ?>
            <img
                class="gallery-card-cover"
                src="<?= htmlspecialchars($set['cover_url']) ?>"
                alt="<?= htmlspecialchars($set['title']) ?>"
                loading="lazy"
            >
            <?php else: ?>
            <div class="gallery-card-cover gallery-card-no-cover"></div>
            <?php endif; ?>
            <div class="gallery-card-info">
                <div class="gallery-card-title"><?= htmlspecialchars($set['title']) ?></div>
                <div class="gallery-card-count"><?= $set['photo_count'] ?> photo<?= $set['photo_count'] > 1 ? 's' : '' ?></div>
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
