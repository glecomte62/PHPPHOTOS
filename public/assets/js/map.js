(() => {
  const el = document.getElementById('map');
  if (!el || !window.L || !L.markerClusterGroup) return;
  const map = L.map(el).setView([46.6, 2.4], 6);
  // Fond Esri « Light Gray Canvas » (gratuit, sans clé) + couche des noms de lieux
  const esri = 'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/';
  L.tileLayer(`${esri}World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}`, {
    attribution: 'Fond de carte © Esri — Esri, HERE, Garmin, © contributeurs OpenStreetMap',
    maxZoom: 16,
  }).addTo(map);
  L.tileLayer(`${esri}World_Light_Gray_Reference/MapServer/tile/{z}/{y}/{x}`, { maxZoom: 16 }).addTo(map);
  const cluster = L.markerClusterGroup({ showCoverageOnHover: false, maxClusterRadius: 50 });

  fetch('/carte.json')
    .then((r) => {
      if (!r.ok) throw new Error(String(r.status));
      return r.json();
    })
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
    })
    .catch(() => { el.textContent = 'Impossible de charger la carte pour le moment.'; });
})();
