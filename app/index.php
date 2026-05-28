<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/FlickrAPI.php';

$api   = new FlickrAPI(FLICKR_API_KEY, FLICKR_USER_ID);
$error = null;
$photosets = [];

try {
    $photosets = $api->getPhotosets();
} catch (RuntimeException $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galeries Photo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="site-header">
    <span class="site-title">Galeries</span>
    <span class="site-subtitle">Collection photographique</span>
</header>

<main class="container">

    <?php if ($error): ?>
    <div class="error-box">
        <strong>Impossible de charger les galeries.</strong><br>
        <?= htmlspecialchars($error) ?>
    </div>

    <?php elseif (empty($photosets)): ?>
    <div class="error-box">
        Aucune galerie trouvée pour ce compte Flickr.
    </div>

    <?php else: ?>
    <div class="galleries-grid">
        <?php foreach ($photosets as $set): ?>
        <a class="gallery-card" href="gallery.php?id=<?= htmlspecialchars($set['id']) ?>">
            <img
                class="gallery-card-cover"
                src="<?= htmlspecialchars($set['cover_url']) ?>"
                alt="<?= htmlspecialchars($set['title']) ?>"
                loading="lazy"
            >
            <div class="gallery-card-info">
                <div class="gallery-card-title"><?= htmlspecialchars($set['title']) ?></div>
                <div class="gallery-card-count"><?= $set['photo_count'] ?> photo<?= $set['photo_count'] > 1 ? 's' : '' ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</main>

</body>
</html>
