<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/FlickrAPI.php';

$api         = new FlickrAPI(FLICKR_API_KEY, FLICKR_USER_ID);
$error       = null;
$collections = [];

try {
    $collections = $api->getCollections();
} catch (RuntimeException $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collections Photo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="site-header">
    <span class="site-title">Collections</span>
    <span class="site-subtitle">Collection photographique</span>
</header>

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
    <div class="galleries-grid">
        <?php foreach ($collections as $col): ?>
        <a class="gallery-card" href="collection.php?id=<?= htmlspecialchars($col['id']) ?>">
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

</body>
</html>
