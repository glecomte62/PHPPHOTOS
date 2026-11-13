(() => {
  const el = document.getElementById('map');
  if (!el || !window.L) return;
  const map = L.map(el).setView([46.6, 2.4], 6);
  L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>, © <a href="https://carto.com/attributions">CARTO</a>',
    maxZoom: 19,
  }).addTo(map);
  const cluster = L.markerClusterGroup({ showCoverageOnHover: false, maxClusterRadius: 50 });

  fetch('/carte.json')
    .then((r) => r.json())
    .then((photos) => {
      photos.forEach((p) => {
        const marker = L.circleMarker([p.lat, p.lng], { radius: 7, color: '#1b1b1a', weight: 2, fillColor: '#f4f1ec', fillOpacity: 1 });
        const box = document.createElement('a');
        box.className = 'map-popup';
        box.href = p.url;
        const img = document.createElement('img');
        img.src = p.thumb;
        img.alt = p.title || '';
        img.loading = 'lazy';
        const title = document.createElement('span');
        title.textContent = p.title || p.album;
        const album = document.createElement('small');
        album.textContent = p.album;
        box.append(img, title, album);
        marker.bindPopup(box, { maxWidth: 240 });
        cluster.addLayer(marker);
      });
      map.addLayer(cluster);
      if (photos.length) map.fitBounds(cluster.getBounds(), { padding: [40, 40], maxZoom: 12 });
    });
})();
