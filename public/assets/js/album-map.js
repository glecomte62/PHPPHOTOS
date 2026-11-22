// Carte de l'album : où ont été prises les photos ; un clic ouvre la photo dans la visionneuse
(() => {
  const el = document.querySelector('[data-album-map]');
  const dataEl = document.getElementById('viewer-data');
  if (!el || !dataEl || !window.L || !L.markerClusterGroup || !window.GLMap) return;
  const { baseLayers, photoIcon, clusterGroup } = window.GLMap;
  const photos = JSON.parse(dataEl.textContent).photos
    .map((p, i) => ({ ...p, index: i }))
    .filter((p) => p.lat !== null && p.lng !== null);
  if (!photos.length) return;

  let map = null;
  const init = () => {
    if (map) return;
    map = L.map(el, { scrollWheelZoom: false, zoomSnap: 0.5 });
    baseLayers(map);
    const cluster = clusterGroup({ maxClusterRadius: 40 });
    photos.forEach((p) => {
      const m = L.marker([p.lat, p.lng], { icon: photoIcon(p.thumb, { size: 40 }), thumb: p.thumb, title: p.title, riseOnHover: true });
      m.on('click', () => document.querySelector(`.mosaic__item[data-index="${p.index}"]`)?.click());
      cluster.addLayer(m);
    });
    map.addLayer(cluster);
    map.fitBounds(cluster.getBounds(), { padding: [40, 40], maxZoom: 15 });
    // La molette ne zoome qu'après un clic sur la carte, pour ne pas gêner le défilement de la page
    map.on('click', () => map.scrollWheelZoom.enable());
    map.on('mouseout', () => map.scrollWheelZoom.disable());
  };

  // Chargée seulement quand on arrive à la carte
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      if (entries.some((e) => e.isIntersecting)) { io.disconnect(); init(); }
    }, { rootMargin: '300px' });
    io.observe(el);
  } else {
    init();
  }
})();
