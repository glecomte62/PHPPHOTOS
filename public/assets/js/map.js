(() => {
  const el = document.getElementById('map');
  if (!el || !window.L || !L.markerClusterGroup || !window.GLMap) return;
  const { baseLayers, photoIcon, clusterGroup, esc } = window.GLMap;

  const map = L.map(el, { zoomSnap: 0.5 }).setView([46.6, 2.4], 6);
  baseLayers(map);
  L.control.scale({ imperial: false, position: 'bottomleft' }).addTo(map);
  let cluster = clusterGroup();
  map.addLayer(cluster);

  const filtersBox = document.querySelector('[data-map-filters]');
  const chips = filtersBox.querySelector('.map-chips');
  const albumSelect = filtersBox.querySelector('[data-map-album]');
  const strip = document.querySelector('[data-map-strip]');
  const countEl = document.querySelector('[data-map-count]');
  const STRIP_MAX = 48;

  const params = new URLSearchParams(location.search);
  const state = { theme: params.get('theme') || '', album: params.get('album') || '' };
  let all = [];
  let shown = [];
  const markers = new Map(); // id de photo -> marqueur

  const popupFor = (p) => {
    const box = document.createElement('div');
    box.className = 'map-popup';
    box.innerHTML = `<a href="${esc(p.url)}"><img src="${esc(p.thumb)}" alt=""><span>${esc(p.title)}</span></a>`
      + `<small><a href="${esc(p.albumUrl)}">${esc(p.album)}</a>${p.taken ? ' · ' + esc(p.taken) : ''}</small>`;
    return box;
  };

  const syncUrl = () => {
    const q = new URLSearchParams();
    if (state.theme) q.set('theme', state.theme);
    if (state.album) q.set('album', state.album);
    history.replaceState(null, '', `/carte${q.toString() ? '?' + q : ''}`);
  };

  // Bandeau des photos visibles dans la zone affichée
  const updateStrip = () => {
    const bounds = map.getBounds();
    const visible = shown.filter((p) => bounds.contains([p.lat, p.lng]));
    countEl.textContent = visible.length
      ? `${visible.length} photo${visible.length > 1 ? 's' : ''} dans cette zone`
      : 'Aucune photo dans cette zone : dézoomez ou déplacez la carte.';
    strip.replaceChildren();
    visible.slice(0, STRIP_MAX).forEach((p) => {
      const a = document.createElement('a');
      a.className = 'map-strip__item';
      a.href = p.url;
      a.title = `${p.title} · ${p.album}`;
      a.innerHTML = `<img src="${esc(p.thumb)}" alt="${esc(p.title)}" loading="lazy">`;
      a.addEventListener('mouseenter', () => markers.get(p.id)?.getElement()?.classList.add('is-hover'));
      a.addEventListener('mouseleave', () => markers.get(p.id)?.getElement()?.classList.remove('is-hover'));
      strip.append(a);
    });
    if (visible.length > STRIP_MAX) {
      const more = document.createElement('span');
      more.className = 'map-strip__more';
      more.textContent = `+ ${visible.length - STRIP_MAX}`;
      strip.append(more);
    }
  };

  const render = ({ fit = true } = {}) => {
    shown = all.filter((p) => (!state.theme || p.theme === state.theme) && (!state.album || p.albumKey === state.album));
    map.removeLayer(cluster);
    cluster = clusterGroup();
    markers.clear();
    shown.forEach((p) => {
      const m = L.marker([p.lat, p.lng], { icon: photoIcon(p.thumb), thumb: p.thumb, title: p.title, alt: p.title, riseOnHover: true });
      m.bindPopup(() => popupFor(p), { maxWidth: 260, minWidth: 220 });
      markers.set(p.id, m);
      cluster.addLayer(m);
    });
    map.addLayer(cluster);
    if (fit && shown.length) map.fitBounds(cluster.getBounds(), { padding: [40, 40], maxZoom: 13 });
    chips.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.theme === state.theme)));
    albumSelect.value = state.album;
    syncUrl();
    updateStrip();
  };

  const buildFilters = () => {
    const themes = new Map();
    const albums = new Map();
    all.forEach((p) => {
      themes.set(p.theme, { title: p.themeTitle, n: (themes.get(p.theme)?.n || 0) + 1 });
      albums.set(p.albumKey, { title: p.album, theme: p.theme, n: (albums.get(p.albumKey)?.n || 0) + 1 });
    });
    const chip = (value, label) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.dataset.theme = value;
      b.textContent = label;
      b.addEventListener('click', () => {
        state.theme = value;
        if (state.album && albums.get(state.album)?.theme !== value && value) state.album = '';
        fillAlbums();
        render();
      });
      chips.append(b);
    };
    chip('', `Tout (${all.length})`);
    themes.forEach((t, slug) => chip(slug, `${t.title} (${t.n})`));
    const fillAlbums = () => {
      albumSelect.querySelectorAll('option:not(:first-child)').forEach((o) => o.remove());
      [...albums].filter(([, a]) => !state.theme || a.theme === state.theme)
        .sort((x, y) => x[1].title.localeCompare(y[1].title, 'fr'))
        .forEach(([key, a]) => albumSelect.add(new Option(`${a.title} (${a.n})`, key)));
    };
    fillAlbums();
    albumSelect.addEventListener('change', () => { state.album = albumSelect.value; render(); });
    filtersBox.hidden = false;
  };

  map.on('moveend', updateStrip);

  fetch('/carte.json')
    .then((r) => {
      if (!r.ok) throw new Error(String(r.status));
      return r.json();
    })
    .then((photos) => {
      all = photos;
      buildFilters();
      const focus = Number(params.get('photo'));
      const target = focus ? all.find((p) => p.id === focus) : null;
      if (target) {
        // Arrivée depuis une photo : on la montre de près, avec sa bulle ouverte
        state.theme = '';
        state.album = '';
        render({ fit: false });
        map.setView([target.lat, target.lng], 15);
        cluster.zoomToShowLayer(markers.get(target.id), () => markers.get(target.id).openPopup());
        history.replaceState(null, '', `/carte?photo=${target.id}`);
      } else {
        render();
      }
    })
    .catch(() => { el.textContent = 'Impossible de charger la carte pour le moment.'; });
})();
