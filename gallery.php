<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/FlickrAPI.php';

$photosetId = $_GET['id'] ?? '';

if (empty($photosetId) || !preg_match('/^\d+$/', $photosetId)) {
    header('Location: index.php');
    exit;
}

$api    = new FlickrAPI(FLICKR_API_KEY, FLICKR_USER_ID);
$error  = null;
$photos = [];
$galleryTitle = 'Galerie';

try {
    $result       = $api->getPhotos($photosetId);
    $photos       = $result['photos'];
    $galleryTitle = $result['title'] ?: $galleryTitle;
} catch (RuntimeException $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($galleryTitle) ?> — Galeries Photo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body>

<header class="site-header">
    <span class="site-title">Galeries</span>
    <span class="site-subtitle">Collection photographique</span>
</header>

<nav class="breadcrumb">
    <a href="index.php">← Toutes les galeries</a>
    &nbsp;/&nbsp;
    <?= htmlspecialchars($galleryTitle) ?>
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

</main>

<!-- Lightbox -->
<div id="lightbox-overlay" class="lightbox-overlay">
    <button class="lightbox-close" aria-label="Fermer">&#10005;</button>

    <div class="lightbox-image-wrap">
        <button class="lightbox-btn lightbox-btn-prev" aria-label="Photo précédente">&#8592;</button>
        <div class="lightbox-img-container">
            <img class="lightbox-img" src="" alt="">
        </div>
        <button class="lightbox-btn lightbox-btn-next" aria-label="Photo suivante">&#8594;</button>
    </div>

    <div class="lightbox-info">
        <div class="lightbox-info-left">
            <p class="lightbox-title"></p>
            <p class="lightbox-desc"></p>
            <p class="lightbox-date"></p>
            <div class="lightbox-tags"></div>
            <div class="lightbox-exif"></div>
        </div>
        <div class="lightbox-info-right">
            <div id="lightbox-map" class="lightbox-map" style="display:none"></div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="js/lightbox.js"></script>
</body>
</html>
