<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$commentError   = isset($_GET['error']);
$commentSuccess = isset($_GET['success']);

$pdo  = get_pdo();
$stmt = $pdo->prepare(
    'SELECT author_name, author_city, message, created_at
     FROM comments
     WHERE context_type = ? AND context_id = ?
     ORDER BY created_at DESC'
);
$stmt->execute(['guestbook', '']);
$entries = $stmt->fetchAll();
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
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400;1,600&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
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

<header class="site-header">
    <a class="site-title" href="index.php"><span class="site-title-first">Guillaume</span> <span class="site-title-last">LECOMTE</span></a>
    <nav class="site-nav">
        <a class="site-nav-link" href="map.php">Carte</a>
        <a class="site-nav-link active" href="guestbook.php">Livre d'or</a>
    </nav>
</header>

<!-- Hero livre d'or -->
<div class="gb-hero">
    <p class="gb-hero-eyebrow">✦ &nbsp; ✦ &nbsp; ✦</p>
    <h1 class="gb-hero-title">Livre d'or</h1>
    <p class="gb-hero-sub">Vous avez visité la galerie ? Votre mot compte.</p>
</div>

<main class="gb-main">

    <!-- Formulaire -->
    <div class="gb-form-outer">
        <?php if ($commentSuccess): ?>
        <div class="gb-notice gb-notice--success">
            ✓ &nbsp; Merci pour votre message, il a bien été enregistré.
        </div>
        <?php endif; ?>
        <?php if ($commentError): ?>
        <div class="gb-notice gb-notice--error">
            Merci de renseigner votre nom et un message.
        </div>
        <?php endif; ?>

        <form class="gb-form" method="post" action="/comment_submit.php" novalidate>
            <input type="hidden" name="context_type" value="guestbook">
            <input type="hidden" name="context_id"   value="">
            <input type="hidden" name="return_url"   value="/guestbook.php">
            <div style="display:none"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>

            <div class="gb-row">
                <div class="gb-field" id="field-name">
                    <label class="gb-label" for="gb_name">Nom <span class="gb-req">*</span></label>
                    <input class="gb-input" type="text" id="gb_name" name="author_name" maxlength="100" autocomplete="name" required>
                </div>
                <div class="gb-field" id="field-city">
                    <label class="gb-label" for="gb_city">Ville / Pays</label>
                    <input class="gb-input" type="text" id="gb_city" name="author_city" maxlength="100" autocomplete="address-level2">
                </div>
            </div>

            <div class="gb-field" id="field-message">
                <label class="gb-label" for="gb_message">Message <span class="gb-req">*</span></label>
                <textarea class="gb-input gb-textarea" id="gb_message" name="message" maxlength="2000" rows="6" required></textarea>
                <span class="gb-counter" id="gb_counter">0 / 2000</span>
            </div>

            <div class="gb-form-footer">
                <span class="gb-hint"><span class="gb-req">*</span> champs obligatoires</span>
                <button class="gb-btn" type="submit">
                    Signer le livre d'or
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
            </div>
        </form>
    </div>

    <!-- Messages -->
    <?php if (!empty($entries)): ?>
    <div class="gb-entries">
        <p class="gb-entries-count"><?= count($entries) ?> message<?= count($entries) > 1 ? 's' : '' ?></p>
        <ul class="gb-list">
            <?php foreach ($entries as $e): ?>
            <li class="gb-entry">
                <svg class="gb-entry-quote" width="28" height="20" viewBox="0 0 28 20" fill="none" aria-hidden="true">
                    <path d="M0 20V12.4C0 8.93333 0.866667 6.06667 2.6 3.8C4.4 1.53333 6.93333 0.133333 10.2 0L11 2.6C8.86667 3.13333 7.26667 4.13333 6.2 5.6C5.2 7.06667 4.73333 8.6 4.8 10.2H9.2V20H0ZM16.8 20V12.4C16.8 8.93333 17.6667 6.06667 19.4 3.8C21.2 1.53333 23.7333 0.133333 27 0L27.8 2.6C25.6667 3.13333 24.0667 4.13333 23 5.6C22 7.06667 21.5333 8.6 21.6 10.2H26V20H16.8Z" fill="currentColor"/>
                </svg>
                <p class="gb-entry-message"><?= nl2br(htmlspecialchars($e['message'])) ?></p>
                <div class="gb-entry-byline">
                    <span class="gb-entry-name"><?= htmlspecialchars($e['author_name']) ?></span>
                    <?php if (!empty($e['author_city'])): ?>
                    <span class="gb-entry-city"><?= htmlspecialchars($e['author_city']) ?></span>
                    <?php endif; ?>
                    <span class="gb-entry-date"><?= date('d/m/Y', strtotime($e['created_at'])) ?></span>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php else: ?>
    <p class="gb-empty">Soyez le premier à signer le livre d'or.</p>
    <?php endif; ?>

</main>

<footer class="site-footer">
    <p class="site-footer-copy">&copy; <?= date('Y') ?> Guillaume LECOMTE — Toutes les photographies présentées sur ce site sont protégées par le droit d'auteur. Toute reproduction, diffusion ou utilisation, même partielle, sans autorisation écrite préalable est strictement interdite.</p>
</footer>

<script>
(function () {
    var ta = document.getElementById('gb_message');
    var counter = document.getElementById('gb_counter');
    if (ta && counter) {
        ta.addEventListener('input', function () { counter.textContent = ta.value.length + ' / 2000'; });
    }
})();
</script>
</body>
</html>
