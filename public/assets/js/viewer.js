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
  let opener = null;
  const baseTitle = document.title;
  const albumName = baseTitle.split(' · ')[0];

  const root = document.createElement('div');
  root.className = 'viewer';
  root.hidden = true;
  root.setAttribute('role', 'dialog');
  root.setAttribute('aria-modal', 'true');
  root.setAttribute('aria-label', 'Visionneuse de photos');
  root.innerHTML = `
    <nav class="viewer__path" aria-label="Fil d'Ariane">
      <a class="viewer__path-home" href="/">Accueil</a><span class="viewer__path-sep viewer__path-home" aria-hidden="true">›</span>
      <a class="viewer__path-theme" href="/"></a><span class="viewer__path-sep viewer__path-theme" aria-hidden="true">›</span>
      <a class="viewer__path-album" href="#"></a><span class="viewer__path-sep" aria-hidden="true">›</span>
      <span class="viewer__path-photo" aria-current="page"></span>
      <span class="viewer__path-count"></span>
    </nav>
    <button class="viewer__btn viewer__close" type="button" aria-label="Fermer">×</button>
    <button class="viewer__btn viewer__info-toggle" type="button" aria-label="Informations sur la photo" aria-expanded="false">i</button>
    <button class="viewer__btn viewer__play" type="button" aria-label="Lancer le diaporama" aria-pressed="false">
      <svg class="icon-play" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M7 4.5v15l12-7.5z" fill="currentColor"/></svg>
      <svg class="icon-pause" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M6.5 4.5h4v15h-4zM13.5 4.5h4v15h-4z" fill="currentColor"/></svg>
    </button>
    <button class="viewer__btn viewer__round viewer__fullscreen" type="button" aria-label="Plein écran" aria-pressed="false" title="Plein écran (F)">
      <svg class="icon-expand" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M4 9V4h5M15 4h5v5M20 15v5h-5M9 20H4v-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <svg class="icon-shrink" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M9 4v5H4M20 9h-5V4M15 20v-5h5M4 15h5v5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <button class="viewer__btn viewer__round viewer__zoom" type="button" aria-label="Zoomer" aria-pressed="false" title="Zoom (double-clic, molette, + et −)">
      <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M15.5 15.5 21 21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path class="zoom-plus" d="M10.5 7.5v6M7.5 10.5h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
    </button>
    <button class="viewer__btn viewer__prev" type="button" aria-label="Photo précédente">‹</button>
    <button class="viewer__btn viewer__next" type="button" aria-label="Photo suivante">›</button>
    <figure class="viewer__stage">
      <picture><source type="image/webp"><img alt=""></picture>
      <figcaption class="viewer__caption"></figcaption>
      <div class="viewer__actions">
        <span class="viewer__share-wrap">
          <button class="viewer__action viewer__share" type="button" aria-haspopup="true" aria-expanded="false">Partager</button>
          <span class="viewer__share-menu" role="menu" hidden>
            <button type="button" role="menuitem" data-share="copy">Copier le lien</button>
            <a role="menuitem" data-share="mail" href="#">E-mail</a>
            <a role="menuitem" data-share="whatsapp" href="#" target="_blank" rel="noopener">WhatsApp</a>
            <a role="menuitem" data-share="facebook" href="#" target="_blank" rel="noopener">Facebook</a>
            <a role="menuitem" data-share="x" href="#" target="_blank" rel="noopener">X</a>
          </span>
        </span>
        <button class="viewer__action viewer__like" type="button" aria-pressed="false" aria-label="J'aime cette photo">
          <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path d="M12 20.5s-7.5-4.6-9.3-9.2C1.4 8 3.4 4.5 7 4.5c2.1 0 3.6 1.2 5 3 1.4-1.8 2.9-3 5-3 3.6 0 5.6 3.5 4.3 6.8-1.8 4.6-9.3 9.2-9.3 9.2z" fill="currentColor" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
          <span class="viewer__like-count"></span>
        </button>
        <button class="viewer__action viewer__comments-link" type="button">Commentaires</button>
        <a class="viewer__action viewer__download" href="#" download hidden>Télécharger</a>
        <a class="viewer__contact" href="/contact">Cette photo vous intéresse ? Contactez-moi</a>
      </div>
      <p class="viewer__credit"></p>
    </figure>
    <aside class="viewer__info" hidden>
      <h2 class="viewer__title"></h2>
      <p class="viewer__text"></p>
      <ul class="viewer__tags" aria-label="Mots-clés" hidden></ul>
      <dl class="viewer__exif"></dl>
      <a class="button viewer__contact-button" href="/contact">Me contacter pour cette photo</a>
      <div class="viewer__map" hidden></div>
      <a class="viewer__map-link" href="/carte" hidden>Voir sur la grande carte →</a>
      <section class="viewer__nearby" hidden>
        <h3 class="viewer__subtitle">À proximité</h3>
        <ul></ul>
      </section>
      <section class="viewer__comments" id="viewer-comments">
        <h3 class="viewer__subtitle">Commentaires</h3>
        <ol class="viewer__comment-list"></ol>
        <form class="viewer__comment-form" novalidate>
          <label>Votre nom <input type="text" name="name" maxlength="100" required autocomplete="name"></label>
          <label>Votre commentaire <textarea name="message" maxlength="2000" rows="3" required></textarea></label>
          <span class="hp" aria-hidden="true"><label>Ne pas remplir <input type="text" name="website" tabindex="-1" autocomplete="off"></label></span>
          <input type="hidden" name="ts">
          <button class="viewer__action" type="submit">Publier</button>
          <p class="viewer__comment-status" aria-live="polite"></p>
          <p class="viewer__comment-hint">Les commentaires sont publiés après relecture.</p>
        </form>
      </section>
    </aside>`;
  document.body.appendChild(root);

  const $ = (sel) => root.querySelector(sel);
  const img = $('img');
  const source = $('source');
  const info = $('.viewer__info');
  const toggle = $('.viewer__info-toggle');
  const mapBox = $('.viewer__map');
  const mapLink = $('.viewer__map-link');
  const large = () => window.innerWidth * (window.devicePixelRatio || 1) > 1400;
  const urlFor = (i) => `${data.base}/photo/${photos[i].id}`;

  const updateMap = (p) => {
    const hasGps = p.lat !== null && p.lng !== null;
    mapBox.hidden = !hasGps || !window.L;
    mapLink.hidden = mapBox.hidden;
    if (!hasGps || info.hidden || !window.L) return;
    if (!miniMap) {
      miniMap = L.map(mapBox, { scrollWheelZoom: false, zoomSnap: 0.5 });
      if (window.GLMap) {
        window.GLMap.baseLayers(miniMap, { control: false });
        miniMap.attributionControl.setPrefix(false);
      } else {
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}', {
          attribution: '© Esri, © OpenStreetMap', maxZoom: 16,
        }).addTo(miniMap);
      }
      // Les autres photos de l'album, en petits points cliquables
      photos.forEach((q, i) => {
        if (q.lat === null || q.lng === null) return;
        L.circleMarker([q.lat, q.lng], { radius: 4, color: '#1b1b1a', weight: 1, fillColor: '#f4f1ec', fillOpacity: 0.9 })
          .bindTooltip(q.title || '', { direction: 'top' })
          .on('click', () => go(i - index))
          .addTo(miniMap);
      });
      marker = window.GLMap
        ? L.marker([p.lat, p.lng], { icon: window.GLMap.photoIcon(p.thumb, { size: 46, active: true }), zIndexOffset: 1000, interactive: false })
        : L.circleMarker([p.lat, p.lng], { radius: 7, color: '#1b1b1a', weight: 2, fillColor: '#f4f1ec', fillOpacity: 1 });
      marker.addTo(miniMap);
    }
    marker.setLatLng([p.lat, p.lng]);
    if (window.GLMap && marker.setIcon) marker.setIcon(window.GLMap.photoIcon(p.thumb, { size: 46, active: true }));
    miniMap.setView([p.lat, p.lng], Math.max(miniMap.getZoom() || 0, 13));
    mapLink.href = `/carte?photo=${p.id}`;
    setTimeout(() => miniMap.invalidateSize(), 50);
  };

  // Données complémentaires d'une photo (photos à proximité…), chargées à la demande et gardées en mémoire
  const details = new Map();
  let pending = null;
  const fetchDetails = (p) => {
    if (details.has(p.id)) return Promise.resolve(details.get(p.id));
    if (pending) pending.abort();
    pending = new AbortController();
    return fetch(`/api/photos/${p.id}`, { signal: pending.signal, headers: { Accept: 'application/json' } })
      .then((r) => (r.ok ? r.json() : null))
      .then((d) => { if (d) details.set(p.id, d); return d; })
      .catch(() => null);
  };

  const renderNearby = (d) => {
    const box = $('.viewer__nearby');
    const list = box.querySelector('ul');
    list.replaceChildren();
    const items = (d && d.nearby) || [];
    box.hidden = items.length === 0;
    items.forEach((n) => {
      const li = document.createElement('li');
      const a = document.createElement('a');
      a.href = n.url;
      const pic = document.createElement('picture');
      const src = document.createElement('source');
      src.type = 'image/webp';
      src.srcset = n.thumb;
      const im = document.createElement('img');
      im.src = n.thumbJpg;
      im.alt = '';
      im.loading = 'lazy';
      pic.append(src, im);
      const text = document.createElement('span');
      const strong = document.createElement('strong');
      strong.textContent = n.title;
      const small = document.createElement('small');
      small.textContent = `${n.album} · ${n.distance}`;
      text.append(strong, small);
      a.append(pic, text);
      li.append(a);
      list.append(li);
    });
  };

  const renderTags = (d) => {
    const list = $('.viewer__tags');
    list.replaceChildren();
    const tags = (d && d.tags) || [];
    list.hidden = tags.length === 0;
    tags.forEach((t) => {
      const li = document.createElement('li');
      const a = document.createElement('a');
      a.href = t.url;
      a.textContent = t.name;
      li.append(a);
      list.append(li);
    });
  };

  // --- Stockage du navigateur (peut être indisponible : navigation privée, cookies bloqués) ---
  const store = {
    get(key, fallback) {
      try { const v = localStorage.getItem(key); return v === null ? fallback : JSON.parse(v); } catch { return fallback; }
    },
    set(key, value) {
      try { localStorage.setItem(key, JSON.stringify(value)); } catch { /* sans conséquence */ }
    },
  };
  // Jeton anonyme du navigateur : évite qu'un même visiteur aime deux fois la même photo
  const voterToken = () => {
    let t = store.get('gl-voter', null);
    if (typeof t !== 'string' || !/^[a-f0-9]{32}$/.test(t)) {
      t = Array.from(crypto.getRandomValues(new Uint8Array(16)), (b) => b.toString(16).padStart(2, '0')).join('');
      store.set('gl-voter', t);
    }
    return t;
  };
  const likedIds = () => new Set(store.get('gl-liked', []));

  // --- « J'aime » ---
  const likeBtn = $('.viewer__like');
  const renderLike = (p, d) => {
    const liked = likedIds().has(p.id);
    const count = d ? d.likes : null;
    likeBtn.setAttribute('aria-pressed', String(liked));
    likeBtn.setAttribute('aria-label', liked ? 'Je n\'aime plus cette photo' : 'J\'aime cette photo');
    likeBtn.querySelector('.viewer__like-count').textContent = count ? String(count) : '';
  };
  likeBtn.addEventListener('click', () => {
    const p = photos[index];
    const ids = likedIds();
    const like = !ids.has(p.id);
    if (like) ids.add(p.id); else ids.delete(p.id);
    store.set('gl-liked', [...ids]);
    const d = details.get(p.id);
    if (d) d.likes = Math.max(0, d.likes + (like ? 1 : -1));
    renderLike(p, d);
    const body = new FormData();
    body.append('token', voterToken());
    body.append('like', like ? '1' : '0');
    fetch(`/api/photos/${p.id}/aime`, { method: 'POST', body, headers: { Accept: 'application/json' } })
      .then((r) => (r.ok ? r.json() : null))
      .then((res) => {
        if (!res || !details.has(p.id)) return;
        details.get(p.id).likes = res.likes;
        if (photos[index] && photos[index].id === p.id) renderLike(p, details.get(p.id));
      })
      .catch(() => {});
  });

  // --- Vues : une par photo et par session, si la photo reste affichée un instant ---
  let viewTimer = null;
  const countView = (p) => {
    clearTimeout(viewTimer);
    viewTimer = setTimeout(() => {
      let seen = [];
      try { seen = JSON.parse(sessionStorage.getItem('gl-viewed') || '[]'); } catch { /* idem */ }
      if (seen.includes(p.id)) return;
      seen.push(p.id);
      try { sessionStorage.setItem('gl-viewed', JSON.stringify(seen.slice(-500))); } catch { /* idem */ }
      if (navigator.sendBeacon) navigator.sendBeacon(`/api/photos/${p.id}/vue`);
      else fetch(`/api/photos/${p.id}/vue`, { method: 'POST', keepalive: true }).catch(() => {});
    }, 1500);
  };

  // --- Commentaires ---
  const commentList = $('.viewer__comment-list');
  const commentForm = $('.viewer__comment-form');
  const commentStatus = $('.viewer__comment-status');
  const commentsLink = $('.viewer__comments-link');
  commentForm.elements.ts.value = data.ts || '';
  const renderComments = (d) => {
    const comments = (d && d.comments) || [];
    commentList.replaceChildren();
    comments.forEach((c) => {
      const li = document.createElement('li');
      const head = document.createElement('p');
      const strong = document.createElement('strong');
      strong.textContent = c.name;
      const date = document.createElement('small');
      date.textContent = ` · ${c.date}`;
      head.append(strong, date);
      const text = document.createElement('p');
      text.textContent = c.message;
      li.append(head, text);
      commentList.append(li);
    });
    commentList.hidden = comments.length === 0;
    commentsLink.textContent = comments.length ? `Commentaires (${comments.length})` : 'Commenter';
  };
  commentsLink.addEventListener('click', () => {
    if (info.hidden) toggle.click();
    $('.viewer__comments').scrollIntoView({ behavior: 'smooth', block: 'start' });
    setTimeout(() => commentForm.elements.name.focus({ preventScroll: true }), 400);
  });
  commentForm.addEventListener('submit', (ev) => {
    ev.preventDefault();
    const p = photos[index];
    const btn = commentForm.querySelector('button[type="submit"]');
    btn.disabled = true;
    commentStatus.textContent = 'Envoi…';
    fetch(`/api/photos/${p.id}/commentaires`, { method: 'POST', body: new FormData(commentForm), headers: { Accept: 'application/json' } })
      .then((r) => r.json().then((res) => ({ ok: r.ok, res })))
      .then(({ ok, res }) => {
        if (ok) {
          commentStatus.textContent = res.message;
          commentForm.elements.message.value = '';
        } else {
          commentStatus.textContent = Object.values(res.errors || { e: 'L\'envoi a échoué. Réessayez.' }).join(' ');
        }
      })
      .catch(() => { commentStatus.textContent = 'L\'envoi a échoué. Réessayez.'; })
      .finally(() => { btn.disabled = false; });
  });

  const renderStats = (d) => {
    const dl = $('.viewer__exif');
    dl.querySelectorAll('.is-stat').forEach((el) => el.remove());
    if (!d || !d.views) return;
    const dt = document.createElement('dt');
    dt.className = 'is-stat';
    dt.textContent = 'Vues';
    const dd = document.createElement('dd');
    dd.className = 'is-stat';
    dd.textContent = String(d.views);
    dl.append(dt, dd);
  };

  const renderDetails = (d) => {
    const p = photos[index];
    renderTags(d);
    renderNearby(d);
    renderComments(d);
    renderStats(d);
    if (p) renderLike(p, d);
  };

  const loadDetails = (p) => {
    renderDetails(details.get(p.id) || null);
    fetchDetails(p).then((d) => {
      if (index < 0 || photos[index].id !== p.id) return; // l'utilisateur est déjà passé à une autre photo
      renderDetails(d);
    });
  };

  // --- Partage ---
  const shareBtn = $('.viewer__share');
  const shareMenu = $('.viewer__share-menu');
  const closeShare = () => {
    shareMenu.hidden = true;
    shareBtn.setAttribute('aria-expanded', 'false');
  };
  const shareInfo = () => {
    const p = photos[index];
    return { url: new URL(urlFor(index), location.origin).href, title: p.title || albumName };
  };
  shareBtn.addEventListener('click', () => {
    const { url, title } = shareInfo();
    // Sur mobile : la feuille de partage du système (Messages, WhatsApp, AirDrop…)
    if (navigator.share && matchMedia('(pointer: coarse)').matches) {
      navigator.share({ title, url }).catch(() => {});
      return;
    }
    const text = `${title} — Guillaume Lecomte`;
    const enc = encodeURIComponent;
    shareMenu.querySelector('[data-share="mail"]').href = `mailto:?subject=${enc(text)}&body=${enc(url)}`;
    shareMenu.querySelector('[data-share="whatsapp"]').href = `https://wa.me/?text=${enc(`${text} ${url}`)}`;
    shareMenu.querySelector('[data-share="facebook"]').href = `https://www.facebook.com/sharer/sharer.php?u=${enc(url)}`;
    shareMenu.querySelector('[data-share="x"]').href = `https://x.com/intent/post?text=${enc(text)}&url=${enc(url)}`;
    const open = shareMenu.hidden;
    shareMenu.hidden = !open;
    shareBtn.setAttribute('aria-expanded', String(open));
    if (open) shareMenu.querySelector('[role="menuitem"]').focus();
  });
  shareMenu.querySelector('[data-share="copy"]').addEventListener('click', (ev) => {
    const btn = ev.currentTarget;
    navigator.clipboard.writeText(shareInfo().url).then(() => {
      btn.textContent = 'Lien copié ✓';
      setTimeout(() => { btn.textContent = 'Copier le lien'; closeShare(); }, 1200);
    }).catch(() => { window.prompt('Copiez le lien :', shareInfo().url); });
  });
  shareMenu.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => setTimeout(closeShare, 100)));
  document.addEventListener('click', (ev) => {
    if (!shareMenu.hidden && !ev.target.closest('.viewer__share-wrap')) closeShare();
  });

  // --- Diaporama ---
  const SLIDE_MS = 5000;
  const playBtn = $('.viewer__play');
  let timer = null;
  const playing = () => timer !== null;
  const schedule = () => {
    clearTimeout(timer);
    timer = setTimeout(() => { go(1); }, SLIDE_MS);
  };
  const setPlaying = (on) => {
    clearTimeout(timer);
    timer = on ? 0 : null;
    if (on) schedule();
    playBtn.setAttribute('aria-pressed', String(on));
    playBtn.setAttribute('aria-label', on ? 'Mettre le diaporama en pause' : 'Lancer le diaporama');
    root.classList.toggle('is-playing', on);
  };
  playBtn.addEventListener('click', () => setPlaying(!playing()));

  // --- Zoom : double-clic ou double tape, molette, pincement, + et − ; glisser pour se déplacer ---
  const MAX_ZOOM = 4;
  const zoomBtn = $('.viewer__zoom');
  const stage = $('.viewer__stage');
  let scale = 1;
  let tx = 0;
  let ty = 0;
  let base = null; // position et taille de l'image non zoomée
  let hiRes = false;
  const zoomed = () => scale > 1.01;
  const applyZoom = () => {
    img.style.transform = zoomed() ? `translate(${tx}px, ${ty}px) scale(${scale})` : '';
    root.classList.toggle('is-zoomed', zoomed());
    zoomBtn.setAttribute('aria-pressed', String(zoomed()));
    zoomBtn.setAttribute('aria-label', zoomed() ? 'Revenir à la taille normale' : 'Zoomer');
  };
  const resetZoom = () => {
    scale = 1; tx = 0; ty = 0; base = null;
    applyZoom();
  };
  // L'image ne doit jamais laisser de vide au milieu de l'écran : on borne le déplacement
  const clamp = () => {
    const r = stage.getBoundingClientRect(); // zone de la photo (sans le panneau d'informations)
    const cx = base.left + base.width / 2;
    const cy = base.top + base.height / 2;
    const a = r.right - cx - (scale * base.width) / 2;
    const b = r.left + (scale * base.width) / 2 - cx;
    const c = r.bottom - cy - (scale * base.height) / 2;
    const d = r.top + (scale * base.height) / 2 - cy;
    tx = Math.min(Math.max(tx, Math.min(a, b)), Math.max(a, b));
    ty = Math.min(Math.max(ty, Math.min(c, d)), Math.max(c, d));
  };
  // Zoome vers newScale en gardant immobile le point de l'écran (px, py)
  const zoomTo = (newScale, px, py) => {
    if (!base) {
      if (newScale <= 1.01) return;
      base = img.getBoundingClientRect();
    }
    const s = Math.min(MAX_ZOOM, Math.max(1, newScale));
    const cx = base.left + base.width / 2;
    const cy = base.top + base.height / 2;
    const x = px ?? cx;
    const y = py ?? cy;
    tx = x - cx - (s * (x - cx - tx)) / scale;
    ty = y - cy - (s * (y - cy - ty)) / scale;
    scale = s;
    if (!zoomed()) { resetZoom(); return; }
    clamp();
    applyZoom();
    if (playing()) setPlaying(false);
    // Version 2560 px pour garder une image nette
    if (!hiRes && index >= 0) {
      hiRes = true;
      source.srcset = photos[index].webp;
      img.src = photos[index].jpg;
    }
  };
  const toggleZoom = (px, py) => (zoomed() ? resetZoom() : zoomTo(2.5, px, py));
  zoomBtn.addEventListener('click', () => toggleZoom());

  stage.addEventListener('wheel', (ev) => {
    if (root.hidden) return;
    ev.preventDefault();
    zoomTo(scale * Math.exp(-ev.deltaY * 0.0015), ev.clientX, ev.clientY);
  }, { passive: false });

  const pointers = new Map();
  let pinch = null;
  let lastTap = null;
  let moved = false;
  img.addEventListener('pointerdown', (ev) => {
    img.setPointerCapture(ev.pointerId);
    pointers.set(ev.pointerId, { x: ev.clientX, y: ev.clientY });
    moved = false;
    if (pointers.size === 2) {
      const [p1, p2] = [...pointers.values()];
      pinch = { dist: Math.hypot(p1.x - p2.x, p1.y - p2.y), scale };
    }
  });
  img.addEventListener('pointermove', (ev) => {
    const prev = pointers.get(ev.pointerId);
    if (!prev) return;
    const cur = { x: ev.clientX, y: ev.clientY };
    pointers.set(ev.pointerId, cur);
    if (Math.hypot(cur.x - prev.x, cur.y - prev.y) > 2) moved = true;
    if (pinch && pointers.size === 2) {
      const [p1, p2] = [...pointers.values()];
      zoomTo(pinch.scale * (Math.hypot(p1.x - p2.x, p1.y - p2.y) / pinch.dist), (p1.x + p2.x) / 2, (p1.y + p2.y) / 2);
    } else if (zoomed() && pointers.size === 1) {
      tx += cur.x - prev.x;
      ty += cur.y - prev.y;
      clamp();
      applyZoom();
    }
  });
  const endPointer = (ev) => {
    if (!pointers.delete(ev.pointerId)) return;
    if (pointers.size < 2) pinch = null;
    if (ev.type !== 'pointerup' || moved || pointers.size) return;
    // Deux appuis rapprochés au même endroit : zoom ou retour
    const now = Date.now();
    if (lastTap && now - lastTap.t < 320 && Math.hypot(ev.clientX - lastTap.x, ev.clientY - lastTap.y) < 30) {
      toggleZoom(ev.clientX, ev.clientY);
      lastTap = null;
    } else {
      lastTap = { t: now, x: ev.clientX, y: ev.clientY };
    }
  };
  img.addEventListener('pointerup', endPointer);
  img.addEventListener('pointercancel', endPointer);
  img.addEventListener('dragstart', (ev) => ev.preventDefault());
  window.addEventListener('resize', () => { if (!root.hidden) resetZoom(); });

  // --- Plein écran : la photo seule, sur fond noir ---
  const fsBtn = $('.viewer__fullscreen');
  const immersive = () => root.classList.contains('is-immersive');
  const setImmersive = (on) => {
    if (on === immersive()) return;
    resetZoom();
    root.classList.toggle('is-immersive', on);
    fsBtn.setAttribute('aria-pressed', String(on));
    fsBtn.setAttribute('aria-label', on ? 'Quitter le plein écran' : 'Plein écran');
    if (on && root.requestFullscreen && !document.fullscreenElement) {
      root.requestFullscreen().catch(() => { /* refusé : le mode immersif suffit */ });
    } else if (!on && document.fullscreenElement) {
      document.exitFullscreen().catch(() => {});
    }
    // Sur grand écran, la version 2560 px
    if (on && index >= 0 && !hiRes) {
      hiRes = true;
      source.srcset = photos[index].webp;
      img.src = photos[index].jpg;
    }
  };
  fsBtn.addEventListener('click', () => setImmersive(!immersive()));
  document.addEventListener('fullscreenchange', () => {
    if (!document.fullscreenElement && immersive()) setImmersive(false); // sortie par Échap
  });

  const preload = (i) => {
    const p = photos[(i + photos.length) % photos.length];
    const im = new Image();
    im.src = large() ? p.webp : p.webpM;
  };

  const show = (i) => {
    index = (i + photos.length) % photos.length;
    const p = photos[index];
    resetZoom();
    hiRes = large() || immersive();
    source.srcset = hiRes ? p.webp : p.webpM;
    img.src = hiRes ? p.jpg : p.jpgM;
    img.alt = p.title || '';
    $('.viewer__caption').textContent = p.title || '';
    $('.viewer__path-photo').textContent = p.title || `Photo ${index + 1}`;
    $('.viewer__path-count').textContent = `${index + 1} / ${photos.length}`;
    $('.viewer__title').textContent = p.title || '';
    $('.viewer__text').textContent = p.caption || '';
    const dl = $('.viewer__exif');
    dl.replaceChildren();
    $('.viewer__contact').href = p.contact;
    $('.viewer__contact-button').href = p.contact;
    const credit = $('.viewer__credit');
    credit.replaceChildren();
    if (p.licenseUrl) {
      const [owner, licence] = p.credit.split(' · ');
      const a = document.createElement('a');
      a.href = p.licenseUrl;
      a.rel = 'license noopener';
      a.target = '_blank';
      a.textContent = licence;
      credit.append(`${owner} · `, a);
    } else {
      credit.textContent = p.credit || '';
    }
    const download = $('.viewer__download');
    download.hidden = !p.download;
    if (p.download) download.href = p.download;
    closeShare();
    [['Fichier', p.file], ['Date', p.taken], ['Appareil', p.camera], ['Objectif', p.lens], ['Focale', p.focal],
      ['Ouverture', p.aperture], ['Vitesse', p.shutter], ['ISO', p.iso]]
      .filter(([, v]) => v)
      .forEach(([k, v]) => {
        const dt = document.createElement('dt');
        dt.textContent = k;
        const dd = document.createElement('dd');
        dd.textContent = v;
        dl.append(dt, dd);
      });
    document.title = `${p.title || albumName} · Guillaume Lecomte`;
    updateMap(p);
    commentStatus.textContent = '';
    loadDetails(p);
    countView(p);
    if (playing()) schedule(); // une navigation manuelle relance le délai
    preload(index + 1);
    preload(index - 1);
    if (root.hidden) {
      root.hidden = false;
      document.body.classList.add('viewer-open');
      $('.viewer__close').focus();
    }
  };

  const hide = () => {
    setImmersive(false);
    resetZoom();
    setPlaying(false);
    closeShare();
    clearTimeout(viewTimer);
    root.hidden = true;
    document.body.classList.remove('viewer-open');
    document.title = baseTitle;
    index = -1;
    if (opener && document.contains(opener)) opener.focus();
    opener = null;
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
    opener = a;
    open(parseInt(a.dataset.index, 10));
  }));
  $('.viewer__close').addEventListener('click', close);

  // Fil d'Ariane : Accueil › Thème › Album › Photo
  const path = data.path || {};
  const themeLink = $('.viewer__path-theme');
  themeLink.textContent = path.theme || '';
  themeLink.href = path.themeUrl || '/';
  const albumLink = $('.viewer__path-album');
  albumLink.textContent = path.album || albumName;
  albumLink.href = data.base;
  albumLink.addEventListener('click', (ev) => {
    if (ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.button !== 0) return;
    ev.preventDefault();
    close(); // l'album est déjà là, sous la visionneuse
  });
  $('.viewer__prev').addEventListener('click', () => go(-1));
  $('.viewer__next').addEventListener('click', () => go(1));
  toggle.addEventListener('click', () => {
    info.hidden = !info.hidden;
    toggle.setAttribute('aria-expanded', String(!info.hidden));
    updateMap(photos[index]);
  });
  document.addEventListener('keydown', (ev) => {
    if (root.hidden || ev.metaKey || ev.ctrlKey || ev.altKey) return;
    if (ev.target.closest('input, textarea, select')) return;
    if (ev.key === 'Escape') {
      if (!shareMenu.hidden) closeShare();
      else if (zoomed()) resetZoom();
      else if (immersive()) setImmersive(false);
      else close();
    } else if (ev.key === 'ArrowLeft') go(-1);
    else if (ev.key === 'ArrowRight') go(1);
    else if (ev.key === 'i') toggle.click();
    else if (ev.key === 'f') setImmersive(!immersive());
    else if (ev.key === '+' || ev.key === '=') zoomTo(scale * 1.5);
    else if (ev.key === '-') zoomTo(scale / 1.5);
    else if (ev.key === '0') resetZoom();
    else if (ev.key === ' ' && !ev.target.closest('button, a')) {
      ev.preventDefault();
      playBtn.click();
    }
  });

  // Bouton « Diaporama » de la page d'album
  document.querySelectorAll('[data-slideshow]').forEach((btn) => {
    btn.hidden = false;
    btn.addEventListener('click', () => {
      opener = btn;
      open(0);
      setPlaying(true);
    });
  });
  let startX = null;
  // Glisser d'un doigt pour changer de photo, sauf pendant un zoom ou un pincement
  $('.viewer__stage').addEventListener('touchstart', (ev) => {
    startX = ev.touches.length === 1 && !zoomed() ? ev.touches[0].clientX : null;
  }, { passive: true });
  $('.viewer__stage').addEventListener('touchend', (ev) => {
    if (startX === null || zoomed() || ev.touches.length > 0) { startX = null; return; }
    const dx = ev.changedTouches[0].clientX - startX;
    if (Math.abs(dx) > 50) go(dx < 0 ? 1 : -1);
    startX = null;
  });

  if (Number.isInteger(data.open)) show(data.open);
})();
