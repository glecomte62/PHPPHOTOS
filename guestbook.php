<?php
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Livre d'or — Guillaume LECOMTE</title>
    <meta name="description" content="Livre d'or de Guillaume Lecomte, photographe amateur né à Arras, vivant en Belgique. Laissez un message !">
    <meta name="author" content="Guillaume Lecomte">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://guillaumelecomte.net/guestbook.php">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://guillaumelecomte.net/guestbook.php">
    <meta property="og:title" content="Livre d'or — Guillaume LECOMTE">
    <meta property="og:description" content="Laissez un message dans le livre d'or de Guillaume Lecomte, photographe amateur.">
    <meta property="og:locale" content="fr_FR">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="site-header">
    <a class="site-title" href="index.php"><span class="site-title-first">Guillaume</span> <span class="site-title-last">LECOMTE</span></a>
    <nav class="site-nav">
        <a class="site-nav-link" href="map.php">Carte</a>
        <a class="site-nav-link active" href="guestbook.php">Livre d'or</a>
    </nav>
</header>

<nav class="breadcrumb">
    <a href="index.php">Collections</a>
    &nbsp;/&nbsp;Livre d'or
</nav>

<main class="container">
    <h1 class="page-heading">Livre d'or</h1>
    <p class="page-description">Vous avez visité la galerie ? Laissez un message, c'est toujours un plaisir de lire vos impressions.</p>

    <?php
    $commentContextType = 'guestbook';
    $commentContextId   = '';
    $commentReturnUrl   = '/guestbook.php';
    $commentError       = isset($_GET['error']);
    require __DIR__ . '/includes/comment_list.php';
    require __DIR__ . '/includes/comment_form.php';
    ?>
</main>

<footer class="site-footer">
    <p class="site-footer-copy">&copy; <?= date('Y') ?> Guillaume LECOMTE — Toutes les photographies présentées sur ce site sont protégées par le droit d'auteur. Toute reproduction, diffusion ou utilisation, même partielle, sans autorisation écrite préalable est strictement interdite.</p>
</footer>

</body>
</html>
