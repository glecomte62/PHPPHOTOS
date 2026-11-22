<header class="page-header">
  <h1 class="page-title">Carte</h1>
  <p class="page-intro">Chaque point est une photo prise à cet endroit. Zoomez, cliquez, partez en balade.</p>
  <?= $view->partial('partials/trace') ?>
</header>
<section class="map-page">
  <div class="map-filters" data-map-filters hidden>
    <div class="map-chips" role="group" aria-label="Thèmes"></div>
    <label class="map-album"><span class="visually-hidden">Album</span>
      <select data-map-album><option value="">Tous les albums</option></select>
    </label>
  </div>
  <div id="map" role="region" aria-label="Carte des photos"></div>
  <noscript><p class="empty">La carte nécessite JavaScript.</p></noscript>
</section>
<section class="map-strip-wrap" aria-live="polite">
  <h2 class="map-strip__title" data-map-count></h2>
  <div class="map-strip" data-map-strip></div>
</section>
