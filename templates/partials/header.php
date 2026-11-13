<?php
$current = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$links = ['/#galeries' => 'Galeries', '/carte' => 'Carte', '/presentation' => 'Présentation', '/contact' => 'Contact'];
?>
<header class="site-header">
  <a class="site-header__logo" href="/" aria-label="Accueil — Guillaume Lecomte">
    <img src="<?= e(asset('img/logo.png')) ?>" width="1400" height="384" alt="Guillaume Lecomte — Photographe">
  </a>
  <nav class="site-nav" aria-label="Navigation principale">
    <?php foreach ($links as $href => $label): ?>
      <a href="<?= e($href) ?>"<?= $current === $href ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
</header>
