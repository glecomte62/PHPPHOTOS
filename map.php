<?php
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carte — Guillaume LECOMTE</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="site-header">
    <a class="site-title" href="index.php"><span class="site-title-first">Guillaume</span> <span class="site-title-last">LECOMTE</span></a>
    <nav class="site-nav">
        <a class="site-nav-link active" href="map.php">Carte</a>
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
                    ? '<img src="' + p.url_thumb + '" alt="' + escHtml(p.title) + '">'
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
