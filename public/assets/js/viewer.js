(() => {
  const dataEl = document.getElementById('viewer-data');
  if (!dataEl) return;
  const data = JSON.parse(dataEl.textContent);
  const photos = data.photos;
  if (!photos.length) return;

  let index = -1;
  let pushed = false;
  let miniMap = null;
  let marker = null;

  const root = document.createElement('div');
  root.className = 'viewer';
  root.hidden = true;
  root.setAttribute('role', 'dialog');
  root.setAttribute('aria-modal', 'true');
  root.setAttribute('aria-label', 'Visionneuse de photos');
  root.innerHTML = `
    <button class="viewer__btn viewer__close" type="button" aria-label="Fermer">×</button>
    <button class="viewer__btn viewer__info-toggle" type="button" aria-label="Informations sur la photo" aria-expanded="false">i</button>
    <button class="viewer__btn viewer__prev" type="button" aria-label="Photo précédente">‹</button>
    <button class="viewer__btn viewer__next" type="button" aria-label="Photo suivante">›</button>
    <figure class="viewer__stage">
      <picture><source type="image/webp"><img alt=""></picture>
      <figcaption class="viewer__caption"></figcaption>
    </figure>
    <aside class="viewer__info" hidden>
      <h2 class="viewer__title"></h2>
      <p class="viewer__text"></p>
      <dl class="viewer__exif"></dl>
      <div class="viewer__map" hidden></div>
    </aside>`;
  document.body.appendChild(root);

  const $ = (sel) => root.querySelector(sel);
  const img = $('img');
  const source = $('source');
  const info = $('.viewer__info');
  const toggle = $('.viewer__info-toggle');
  const mapBox = $('.viewer__map');
  const large = () => window.innerWidth * (window.devicePixelRatio || 1) > 1400;
  const urlFor = (i) => `${data.base}/photo/${photos[i].id}`;

  const updateMap = (p) => {
    const hasGps = p.lat !== null && p.lng !== null;
    mapBox.hidden = !hasGps;
    if (!hasGps || info.hidden || !window.L) return;
    if (!miniMap) {
      miniMap = L.map(mapBox, { zoomControl: false, scrollWheelZoom: false });
      L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '© OpenStreetMap, © CARTO', maxZoom: 18,
      }).addTo(miniMap);
      marker = L.circleMarker([p.lat, p.lng], { radius: 7, color: '#1b1b1a', weight: 2, fillColor: '#f4f1ec', fillOpacity: 1 }).addTo(miniMap);
    }
    marker.setLatLng([p.lat, p.lng]);
    miniMap.setView([p.lat, p.lng], 11);
    setTimeout(() => miniMap.invalidateSize(), 50);
  };

  const preload = (i) => {
    const p = photos[(i + photos.length) % photos.length];
    const im = new Image();
    im.src = large() ? p.webp : p.webpM;
  };

  const show = (i) => {
    index = (i + photos.length) % photos.length;
    const p = photos[index];
    source.srcset = large() ? p.webp : p.webpM;
    img.src = large() ? p.jpg : p.jpgM;
    img.alt = p.title || '';
    $('.viewer__caption').textContent = p.title || '';
    $('.viewer__title').textContent = p.title || '';
    $('.viewer__text').textContent = p.caption || '';
    const dl = $('.viewer__exif');
    dl.replaceChildren();
    [['Date', p.taken], ['Appareil', p.camera], ['Objectif', p.lens], ['Focale', p.focal],
      ['Ouverture', p.aperture], ['Vitesse', p.shutter], ['ISO', p.iso]]
      .filter(([, v]) => v)
      .forEach(([k, v]) => {
        const dt = document.createElement('dt');
        dt.textContent = k;
        const dd = document.createElement('dd');
        dd.textContent = v;
        dl.append(dt, dd);
      });
    updateMap(p);
    preload(index + 1);
    preload(index - 1);
    if (root.hidden) {
      root.hidden = false;
      document.body.classList.add('viewer-open');
      $('.viewer__close').focus();
    }
  };

  const hide = () => {
    root.hidden = true;
    document.body.classList.remove('viewer-open');
    index = -1;
  };

  const open = (i) => {
    show(i);
    history.pushState({ viewer: true }, '', urlFor(index));
    pushed = true;
  };

  const go = (delta) => {
    show(index + delta);
    history.replaceState({ viewer: true }, '', urlFor(index));
  };

  const close = () => {
    if (pushed) {
      pushed = false;
      history.back();
    } else {
      hide();
      history.replaceState({}, '', data.base);
    }
  };

  window.addEventListener('popstate', () => {
    const m = location.pathname.match(/\/photo\/(\d+)$/);
    const i = m ? photos.findIndex((p) => String(p.id) === m[1]) : -1;
    if (i >= 0) {
      show(i);
    } else {
      hide();
      pushed = false;
    }
  });

  document.querySelectorAll('.mosaic__item').forEach((a) => a.addEventListener('click', (ev) => {
    if (ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.button !== 0) return;
    ev.preventDefault();
    open(parseInt(a.dataset.index, 10));
  }));
  $('.viewer__close').addEventListener('click', close);
  $('.viewer__prev').addEventListener('click', () => go(-1));
  $('.viewer__next').addEventListener('click', () => go(1));
  toggle.addEventListener('click', () => {
    info.hidden = !info.hidden;
    toggle.setAttribute('aria-expanded', String(!info.hidden));
    updateMap(photos[index]);
  });
  document.addEventListener('keydown', (ev) => {
    if (root.hidden) return;
    if (ev.key === 'Escape') close();
    else if (ev.key === 'ArrowLeft') go(-1);
    else if (ev.key === 'ArrowRight') go(1);
    else if (ev.key === 'i') toggle.click();
  });
  let startX = null;
  $('.viewer__stage').addEventListener('touchstart', (ev) => { startX = ev.touches[0].clientX; }, { passive: true });
  $('.viewer__stage').addEventListener('touchend', (ev) => {
    if (startX === null) return;
    const dx = ev.changedTouches[0].clientX - startX;
    if (Math.abs(dx) > 50) go(dx < 0 ? 1 : -1);
    startX = null;
  });

  if (Number.isInteger(data.open)) show(data.open);
})();
