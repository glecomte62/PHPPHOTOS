<header class="page-header">
  <h1 class="page-title">Carte</h1>
  <p class="page-intro">Chaque point est une photo prise à cet endroit. Zoomez, cliquez, partez en balade.</p>
  <?= $view->partial('partials/trace') ?>
</header>
<section class="map-page">
  <div id="map" role="region" aria-label="Carte des photos"></div>
  <noscript><p class="empty">La carte nécessite JavaScript.</p></noscript>
</section>
