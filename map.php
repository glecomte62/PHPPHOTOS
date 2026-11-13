<?php
require_once __DIR__ . '/config.php';
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
    <title>Carte des photos — Guillaume LECOMTE</title>
    <meta name="description" content="Carte interactive des photographies géolocalisées de Guillaume Lecomte, photographe amateur né à Arras, vivant en Belgique.">
    <meta name="author" content="Guillaume Lecomte">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://guillaumelecomte.net/map.php">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://guillaumelecomte.net/map.php">
    <meta property="og:title" content="Carte des photos — Guillaume LECOMTE">
    <meta property="og:description" content="Carte interactive des photographies géolocalisées de Guillaume Lecomte, photographe amateur né à Arras, vivant en Belgique.">
    <meta property="og:locale" content="fr_FR">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
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
        <a class="site-nav-link active" href="map.php">Carte</a>
        <a class="site-nav-link" href="guestbook.php">Livre d'or</a>
    </nav>
</header>

<div id="map" class="map-fullscreen" data-user="<?= htmlspecialchars(FLICKR_USER_ID) ?>">
    <div class="map-loading" id="map-loading">Chargement de la carte…</div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script>
(function () {
    'use strict';

    var mapEl   = document.getElementById('map');
    var loading = document.getElementById('map-loading');
    var userId  = mapEl.dataset.user;

    var map = L.map('map', { zoomControl: true }).setView([20, 0], 2);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap',
        maxZoom: 19,
    }).addTo(map);

    var markers = L.markerClusterGroup();

    fetch('map-data.php')
        .then(function (r) {
            if (!r.ok) throw new Error('Erreur serveur');
            return r.json();
        })
        .then(function (photos) {
            if (photos.length === 0) {
                loading.textContent = 'Aucune photo géolocalisée trouvée.';
                return;
            }
            loading.style.display = 'none';

            photos.forEach(function (p) {
                var marker = L.circleMarker([p.lat, p.lng], {
                    radius:      7,
                    fillColor:   '#c8a96e',
                    color:       '#fff',
                    weight:      2,
                    opacity:     1,
                    fillOpacity: 0.9,
                });

                var thumb = p.url_thumb
                    ? '<img src="' + escHtml(p.url_thumb) + '" alt="' + escHtml(p.title) + '">'
                    : '';

                marker.bindPopup(
                    '<div class="map-popup">' +
                        thumb +
                        '<div class="map-popup-info">' +
                            '<p class="map-popup-title">' + escHtml(p.title || '—') + '</p>' +
                            '<a href="https://www.flickr.com/photos/' + escHtml(userId) + '/' + escHtml(p.id) + '" ' +
                               'target="_blank" rel="noopener">Voir sur Flickr →</a>' +
                        '</div>' +
                    '</div>',
                    { maxWidth: 220 }
                );

                markers.addLayer(marker);
            });

            map.addLayer(markers);
        })
        .catch(function (err) {
            loading.textContent = 'Impossible de charger les données carte.';
        });

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
})();
</script>
</body>
</html>
