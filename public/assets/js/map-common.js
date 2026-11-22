// Outils communs aux cartes du site (grande carte, carte d'album, mini-carte de la visionneuse)
window.GLMap = (() => {
  const esri = 'https://server.arcgisonline.com/ArcGIS/rest/services/';
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  /** Fonds « Plan » (gris clair + noms de lieux) et « Satellite » (imagerie + noms), avec sélecteur. */
  const baseLayers = (map, { control = true } = {}) => {
    const plan = L.layerGroup([
      L.tileLayer(`${esri}Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}`, {
        attribution: 'Fond © Esri — Esri, HERE, Garmin, © contributeurs OpenStreetMap', maxZoom: 16,
      }),
      L.tileLayer(`${esri}Canvas/World_Light_Gray_Reference/MapServer/tile/{z}/{y}/{x}`, { maxZoom: 16 }),
    ]);
    const satellite = L.layerGroup([
      L.tileLayer(`${esri}World_Imagery/MapServer/tile/{z}/{y}/{x}`, {
        attribution: 'Imagerie © Esri, Maxar, Earthstar Geographics', maxZoom: 18,
      }),
      L.tileLayer(`${esri}Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}`, { maxZoom: 18 }),
    ]);
    let saved = 'plan';
    try { saved = localStorage.getItem('gl-map-base') || 'plan'; } catch { /* stockage indisponible */ }
    (saved === 'satellite' ? satellite : plan).addTo(map);
    if (control) {
      L.control.layers({ Plan: plan, Satellite: satellite }, null, { position: 'topright' }).addTo(map);
      map.on('baselayerchange', (e) => {
        try { localStorage.setItem('gl-map-base', e.layer === satellite ? 'satellite' : 'plan'); } catch { /* idem */ }
      });
    }
    return { plan, satellite };
  };

  /** Marqueur rond avec la vignette de la photo. */
  const photoIcon = (thumb, { size = 44, active = false } = {}) => L.divIcon({
    className: 'map-pin-wrap',
    html: `<span class="map-pin${active ? ' is-active' : ''}" style="width:${size}px;height:${size}px"><img src="${esc(thumb)}" alt="" loading="lazy"></span>`,
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
    popupAnchor: [0, -size / 2],
  });

  /** Groupe de marqueurs : le regroupement montre la première photo et le nombre de photos. */
  const clusterGroup = (options = {}) => L.markerClusterGroup({
    showCoverageOnHover: false,
    maxClusterRadius: 56,
    spiderfyDistanceMultiplier: 1.6,
    iconCreateFunction: (cluster) => {
      const first = cluster.getAllChildMarkers()[0];
      const n = cluster.getChildCount();
      const size = n < 10 ? 52 : n < 100 ? 58 : 64;
      return L.divIcon({
        className: 'map-pin-wrap',
        html: `<span class="map-cluster" style="width:${size}px;height:${size}px"><img src="${esc(first.options.thumb)}" alt=""><b>${n}</b></span>`,
        iconSize: [size, size],
      });
    },
    ...options,
  });

  return { baseLayers, photoIcon, clusterGroup, esc };
})();
