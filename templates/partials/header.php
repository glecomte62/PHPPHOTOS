<?php
$current = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$links = ['/#galeries' => 'Galeries', '/photos' => 'Photos', '/carte' => 'Carte', '/presentation' => 'Présentation', '/contact' => 'Contact'];
?>
<header class="site-header">
  <a class="site-header__logo" href="/" aria-label="Accueil — Guillaume Lecomte">
    <img src="<?= e(asset('img/logo.png')) ?>" width="1400" height="384" alt="Guillaume Lecomte — Photographe">
  </a>
  <nav class="site-nav" aria-label="Navigation principale">
    <?php foreach ($links as $href => $label): ?>
      <a href="<?= e($href) ?>"<?= $current === $href || ($href === '/photos' && str_starts_with($current, '/photos/')) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
    <a class="site-nav__search" href="/recherche"<?= $current === '/recherche' ? ' aria-current="page"' : '' ?>>
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5 21 21"/></svg>
      <span class="visually-hidden">Recherche</span>
    </a>
  </nav>
</header>
