<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/FlickrAPI.php';

$photosetId = $_GET['id'] ?? '';

if (empty($photosetId) || !preg_match('/^\d+$/', $photosetId)) {
    header('Location: index.php');
    exit;
}

$collectionId = $_GET['col'] ?? '';

$api    = new FlickrAPI(FLICKR_API_KEY, FLICKR_USER_ID);
$error  = null;
$photos = [];
$galleryTitle       = 'Galerie';
$galleryDescription = '';
$collectionTitle    = '';

try {
    $result             = $api->getPhotos($photosetId);
    $photos             = $result['photos'];
    $galleryTitle       = $result['title'] ?: $galleryTitle;
    $info               = $api->getPhotosetInfo($photosetId);
    $galleryDescription = $info['description'] ?? '';

    if ($collectionId) {
        $collections = $api->getCollections();
        foreach ($collections as $col) {
            if ($col['id'] === $collectionId) {
                $collectionTitle = $col['title'];
                break;
            }
        }
    }
} catch (RuntimeException $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
        $galTitleEsc = htmlspecialchars($galleryTitle);
        $galDescClean = strip_tags($galleryDescription);
        $metaDesc = $galDescClean
            ? htmlspecialchars(mb_strimwidth($galDescClean, 0, 155, '…'))
            : "Galerie « {$galTitleEsc} » — photographies de Guillaume Lecomte, photographe amateur né à Arras, vivant en Belgique.";
        $canonicalId  = htmlspecialchars($_GET['id'] ?? '');
    ?>
    <title><?= $galTitleEsc ?> — Guillaume LECOMTE</title>
    <meta name="description" content="<?= $metaDesc ?>">
    <meta name="author" content="Guillaume Lecomte">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://guillaumelecomte.net/gallery.php?id=<?= $canonicalId ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://guillaumelecomte.net/gallery.php?id=<?= $canonicalId ?>">
    <meta property="og:title" content="<?= $galTitleEsc ?> — Guillaume LECOMTE">
    <meta property="og:description" content="<?= $metaDesc ?>">
    <meta property="og:locale" content="fr_FR">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body>

<header class="site-header">
    <a class="site-title" href="index.php"><span class="site-title-first">Guillaume</span> <span class="site-title-last">LECOMTE</span></a>
    <?php if ($collectionTitle): ?>
    <span class="site-title-section"><?= htmlspecialchars($collectionTitle) ?></span>
    <span class="site-title-section"><?= htmlspecialchars($galleryTitle) ?></span>
    <?php else: ?>
    <span class="site-title-section"><?= htmlspecialchars($galleryTitle) ?></span>
    <?php endif; ?>
    <nav class="site-nav">
        <a class="site-nav-link" href="map.php">Carte</a>
        <a class="site-nav-link" href="guestbook.php">Livre d'or</a>
    </nav>
</header>

<nav class="breadcrumb">
    <a href="index.php">Collections</a>
    <?php if ($collectionTitle && $collectionId): ?>
    &nbsp;/&nbsp;<a href="collection.php?id=<?= htmlspecialchars($collectionId) ?>"><?= htmlspecialchars($collectionTitle) ?></a>
    <?php endif; ?>
    &nbsp;/&nbsp;<?= htmlspecialchars($galleryTitle) ?>
</nav>

<main class="container">

    <?php if ($error): ?>
    <div class="error-box">
        <strong>Impossible de charger cette galerie.</strong><br>
        <?= htmlspecialchars($error) ?>
    </div>

    <?php elseif (empty($photos)): ?>
    <div class="error-box">
        Cette galerie ne contient aucune photo.
    </div>

    <?php else: ?>
    <h1 class="page-heading"><?= htmlspecialchars($galleryTitle) ?></h1>
    <?php if ($galleryDescription): ?>
    <div class="page-description"><?= nl2br(htmlspecialchars(strip_tags($galleryDescription))) ?></div>
    <?php endif; ?>
    <p class="page-meta"><?= count($photos) ?> photo<?= count($photos) > 1 ? 's' : '' ?></p>

    <div class="photos-masonry">
        <?php foreach ($photos as $photo): ?>
        <div
            class="photo-item"
            data-id="<?= htmlspecialchars($photo['id']) ?>"
            data-large="<?= htmlspecialchars($photo['url_large']) ?>"
            data-title="<?= htmlspecialchars($photo['title']) ?>"
            data-desc="<?= htmlspecialchars($photo['description']) ?>"
            data-date="<?= htmlspecialchars($photo['date_taken']) ?>"
            data-tags="<?= htmlspecialchars(json_encode(array_values($photo['tags']))) ?>"
        >
            <img
                src="<?= htmlspecialchars($photo['url_thumb']) ?>"
                alt="<?= htmlspecialchars($photo['title']) ?>"
                loading="lazy"
            >
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php
    $commentContextType = 'album';
    $commentContextId   = $photosetId;
    $commentReturnUrl   = '/gallery.php?id=' . urlencode($photosetId) . ($collectionId ? '&col=' . urlencode($collectionId) : '');
    $commentError       = isset($_GET['error']);
    require __DIR__ . '/includes/comment_list.php';
    require __DIR__ . '/includes/comment_form.php';
    ?>

</main>

<footer class="site-footer">
    <p class="site-footer-copy">&copy; <?= date('Y') ?> Guillaume LECOMTE — Toutes les photographies présentées sur ce site sont protégées par le droit d'auteur. Toute reproduction, diffusion ou utilisation, même partielle, sans autorisation écrite préalable est strictement interdite.</p>
</footer>

<!-- Lightbox -->
<div id="lightbox-overlay" class="lightbox-overlay">

    <div class="lightbox-img-wrap">
        <img class="lightbox-img" src="" alt="">
        <button class="lightbox-prev" aria-label="Photo précédente">&#8592;</button>
        <button class="lightbox-next" aria-label="Photo suivante">&#8594;</button>
    </div>

    <div class="lightbox-panel" id="lightbox-panel">
        <div class="lightbox-panel-header">
            <p class="lightbox-panel-title"></p>
            <p class="lightbox-panel-date"></p>
        </div>
        <div class="lightbox-panel-body">
            <div class="lightbox-tags"></div>
            <div class="lightbox-exif"></div>
            <div id="lightbox-map" class="lightbox-map" style="display:none"></div>
        </div>
    </div>

    <button class="lightbox-close" aria-label="Fermer">&#10005;</button>
    <button class="lightbox-info" id="lightbox-info-btn" aria-label="Informations" aria-expanded="false" aria-controls="lightbox-panel">i</button>

</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="js/lightbox.js"></script>
</body>
</html>
