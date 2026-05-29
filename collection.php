<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/FlickrAPI.php';

$collectionId = $_GET['id'] ?? '';
if (!$collectionId) {
    header('Location: index.php');
    exit;
}

$api        = new FlickrAPI(FLICKR_API_KEY, FLICKR_USER_ID);
$error      = null;
$collection = null;
$photosets  = [];

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

    foreach ($collection['sets'] as $set) {
        try {
            $photosets[] = $api->getPhotosetInfo($set['id']);
        } catch (RuntimeException) {
            // album inaccessible, on passe
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
    <title><?= htmlspecialchars($collection['title'] ?? 'Collection') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="site-header">
    <a class="site-back" href="index.php">← Collections</a>
    <span class="site-title"><?= htmlspecialchars($collection['title'] ?? 'Collection') ?></span>
    <?php if (!empty($collection['description'])): ?>
    <span class="site-subtitle"><?= htmlspecialchars($collection['description']) ?></span>
    <?php endif; ?>
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
        <a class="gallery-card" href="gallery.php?id=<?= htmlspecialchars($set['id']) ?>">
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

</body>
</html>
