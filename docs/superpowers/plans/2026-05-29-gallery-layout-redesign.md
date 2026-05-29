# Gallery Layout Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remplacer le layout galerie par une version professionnelle et rapide — masonry immersif, lightbox plein écran avec panneau infos latéral escamotable, et chargement parallèle des albums.

**Architecture:** 4 fichiers modifiés. `FlickrAPI.php` gagne une méthode `getPhotosetsInfo()` avec `curl_multi`. `collection.php` l'utilise. `css/style.css` est réécrit pour la grille et la lightbox. `gallery.php` restructure le HTML lightbox. `js/lightbox.js` est réécrit pour gérer le panneau.

**Tech Stack:** PHP 8 / curl_multi, CSS columns masonry, Leaflet 1.9.4 (déjà présent), JS vanilla ES5-compatible.

---

## Fichiers modifiés

| Fichier | Rôle |
|---|---|
| `lib/FlickrAPI.php` | Ajout `getPhotosetsInfo(array $ids): array` — requêtes parallèles |
| `collection.php` | Utilise `getPhotosetsInfo()` à la place de la boucle séquentielle |
| `css/style.css` | Refonte grille photos + lightbox plein écran + panneau latéral |
| `gallery.php` | Nouveau HTML lightbox (structure panneau) |
| `js/lightbox.js` | Réécriture — toggle panneau, chargement EXIF différé |

---

## Task 1: Appels parallèles dans FlickrAPI

**Files:**
- Modify: `lib/FlickrAPI.php`

- [ ] **Étape 1 : Ajouter la méthode `getPhotosetsInfo()` dans `FlickrAPI.php`**

Ajouter après la méthode `getPhotosetInfo()` (ligne 125) :

```php
/**
 * Retourne les infos de plusieurs photosets en parallèle via curl_multi.
 * Retourne un tableau indexé par photoset id : ['id', 'title', 'description', 'photo_count', 'cover_url']
 * Les IDs introuvables sont silencieusement ignorés.
 *
 * @param string[] $ids
 * @return array<string, array>
 */
public function getPhotosetsInfo(array $ids): array
{
    if (empty($ids)) return [];

    $mh      = curl_multi_init();
    $handles = [];

    foreach ($ids as $id) {
        $params = [
            'method'      => 'flickr.photosets.getInfo',
            'photoset_id' => $id,
            'user_id'     => $this->userId,
            'api_key'     => $this->apiKey,
            'format'      => 'json',
            'nojsoncallback' => 1,
        ];
        $url = $this->baseUrl . '?' . http_build_query($params);
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$id] = $ch;
    }

    // Exécuter tous les handles en parallèle
    do {
        $status = curl_multi_exec($mh, $active);
        if ($active) curl_multi_select($mh);
    } while ($active && $status === CURLM_OK);

    $results = [];
    foreach ($handles as $id => $ch) {
        $response = curl_multi_getcontent($ch);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        if (!$response) continue;
        $data = json_decode($response, true);
        if (!$data || ($data['stat'] ?? '') !== 'ok') continue;

        $ps = $data['photoset'];
        $coverUrl = isset($ps['farm'], $ps['server'], $ps['primary'], $ps['secret'])
            ? $this->buildPhotoUrl($ps['farm'], $ps['server'], $ps['primary'], $ps['secret'], 'z')
            : '';

        $results[$id] = [
            'id'          => $ps['id'],
            'title'       => $ps['title']['_content'] ?? '',
            'description' => $ps['description']['_content'] ?? '',
            'photo_count' => (int)($ps['photos'] ?? 0),
            'cover_url'   => $coverUrl,
        ];
    }

    curl_multi_close($mh);
    return $results;
}
```

- [ ] **Étape 2 : Vérifier la syntaxe PHP**

```bash
php -l lib/FlickrAPI.php
```

Résultat attendu : `No syntax errors detected in lib/FlickrAPI.php`

- [ ] **Étape 3 : Commit**

```bash
git add lib/FlickrAPI.php
git commit -m "perf: getPhotosetsInfo() — chargement parallèle via curl_multi"
```

---

## Task 2: Mise à jour de collection.php

**Files:**
- Modify: `collection.php`

- [ ] **Étape 1 : Remplacer la boucle séquentielle par l'appel parallèle**

Remplacer dans `collection.php` le bloc `foreach ($collection['sets'] as $set)` (lignes 29–35) par :

```php
$ids = array_column($collection['sets'], 'id');
$infoMap = $api->getPhotosetsInfo($ids);

// Conserver l'ordre original des sets
foreach ($collection['sets'] as $set) {
    if (isset($infoMap[$set['id']])) {
        $photosets[] = $infoMap[$set['id']];
    }
}
```

- [ ] **Étape 2 : Vérifier la syntaxe PHP**

```bash
php -l collection.php
```

Résultat attendu : `No syntax errors detected in collection.php`

- [ ] **Étape 3 : Tester manuellement**

Ouvrir `collection.php?id=<un_id>` dans le navigateur. La page doit charger en ~300ms au lieu de plusieurs secondes. Les albums doivent apparaître dans le même ordre qu'avant.

- [ ] **Étape 4 : Commit**

```bash
git add collection.php
git commit -m "perf: collection.php — remplace boucle séquentielle par getPhotosetsInfo()"
```

---

## Task 3: Refonte CSS

**Files:**
- Modify: `css/style.css`

- [ ] **Étape 1 : Remplacer entièrement `css/style.css`**

```css
/* ============================
   RESET & BASE
   ============================ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg:         #000;
    --surface:    #141414;
    --border:     #2a2a2a;
    --text:       #f0f0f0;
    --text-muted: #888;
    --accent:     #c8a96e;
    --font-serif: 'Playfair Display', Georgia, serif;
    --font-sans:  'Inter', system-ui, sans-serif;
    --panel-w:    360px;
}

html { font-size: 16px; }

body {
    background: var(--bg);
    color: var(--text);
    font-family: var(--font-sans);
    min-height: 100vh;
}

a { color: inherit; text-decoration: none; }

/* ============================
   HEADER
   ============================ */
.site-header {
    padding: 2rem 2rem 1.2rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: baseline;
    gap: 1rem;
}

.site-title {
    font-family: var(--font-serif);
    font-size: 1.6rem;
    font-weight: 700;
    letter-spacing: 0.02em;
}

.site-subtitle {
    font-size: 0.8rem;
    color: var(--text-muted);
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

/* ============================
   NAVIGATION
   ============================ */
.breadcrumb {
    padding: 0.9rem 2rem;
    font-size: 0.82rem;
    color: var(--text-muted);
}

.breadcrumb a { color: var(--accent); }
.breadcrumb a:hover { text-decoration: underline; }

.site-back {
    font-size: 0.85rem;
    color: var(--accent);
    margin-right: 0.5rem;
    transition: opacity 0.2s;
}
.site-back:hover { opacity: 0.7; }

/* ============================
   CONTAINER
   ============================ */
.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 1.5rem 2rem;
}

.page-heading {
    font-family: var(--font-serif);
    font-size: 1.8rem;
    font-weight: 400;
    margin-bottom: 0.3rem;
}

.page-meta {
    font-size: 0.82rem;
    color: var(--text-muted);
    margin-bottom: 1.5rem;
}

/* ============================
   GRILLE COLLECTIONS/ALBUMS
   ============================ */
.galleries-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.2rem;
}

.gallery-card {
    position: relative;
    background: var(--surface);
    overflow: hidden;
    cursor: pointer;
    display: block;
    transition: transform 0.25s ease;
}

.gallery-card:hover { transform: translateY(-3px); }

.gallery-card-cover {
    width: 100%;
    aspect-ratio: 4 / 3;
    object-fit: cover;
    display: block;
}

.gallery-card-info {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    padding: 1.2rem 1rem 0.9rem;
    background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, transparent 100%);
}

.gallery-card-title {
    font-family: var(--font-serif);
    font-size: 1rem;
    font-weight: 600;
    color: #fff;
    margin-bottom: 0.15rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.gallery-card-count {
    font-size: 0.72rem;
    color: rgba(255,255,255,0.55);
    letter-spacing: 0.05em;
}

.gallery-card-no-cover {
    background: var(--surface);
    aspect-ratio: 4 / 3;
}

/* ============================
   GRILLE PHOTOS (masonry)
   ============================ */
.photos-masonry {
    columns: 4;
    column-gap: 4px;
}

.photo-item {
    break-inside: avoid;
    margin-bottom: 4px;
    cursor: pointer;
    overflow: hidden;
    display: block;
}

.photo-item img {
    width: 100%;
    display: block;
    transition: filter 0.3s ease;
}

.photo-item:hover img { filter: brightness(1.08); }

/* ============================
   LIGHTBOX — OVERLAY
   ============================ */
.lightbox-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: #000;
    z-index: 1000;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.lightbox-overlay.active  { display: block; }
.lightbox-overlay.visible { opacity: 1; }

/* Zone image — prend tout l'écran sauf si panneau ouvert */
.lightbox-img-wrap {
    position: fixed;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: right 0.3s ease;
}

.lightbox-overlay.panel-open .lightbox-img-wrap {
    right: var(--panel-w);
}

.lightbox-img {
    max-width: 100%;
    max-height: 100vh;
    object-fit: contain;
    display: block;
    cursor: zoom-in;
    transition: transform 0.3s ease;
    user-select: none;
}

/* ============================
   LIGHTBOX — BOUTONS
   ============================ */
.lightbox-close,
.lightbox-info,
.lightbox-prev,
.lightbox-next {
    position: fixed;
    background: rgba(0,0,0,0.5);
    border: none;
    color: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1010;
    transition: background 0.2s;
}

.lightbox-close {
    top: 1rem; right: 1rem;
    width: 40px; height: 40px;
    border-radius: 50%;
    font-size: 1.1rem;
}

.lightbox-info {
    bottom: 1rem; right: 1rem;
    width: 36px; height: 36px;
    border-radius: 50%;
    font-size: 0.85rem;
    font-weight: 600;
    font-family: var(--font-serif);
    letter-spacing: 0;
}

.lightbox-info.active-info {
    background: var(--accent);
    color: #000;
}

.lightbox-prev,
.lightbox-next {
    top: 50%; transform: translateY(-50%);
    width: 48px; height: 72px;
    border-radius: 4px;
    font-size: 1.3rem;
}

.lightbox-prev { left: 0.75rem; }
.lightbox-next { right: 0.75rem; }

.lightbox-close:hover,
.lightbox-prev:hover,
.lightbox-next:hover { background: rgba(255,255,255,0.15); }

/* ============================
   LIGHTBOX — PANNEAU INFOS
   ============================ */
.lightbox-panel {
    position: fixed;
    top: 0; right: 0;
    width: var(--panel-w);
    height: 100%;
    background: var(--surface);
    border-left: 1px solid var(--border);
    transform: translateX(100%);
    transition: transform 0.3s ease;
    z-index: 1005;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.lightbox-panel.open { transform: translateX(0); }

.lightbox-panel-header {
    padding: 1.2rem 1.2rem 1rem;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}

.lightbox-panel-title {
    font-family: var(--font-serif);
    font-size: 1rem;
    font-weight: 600;
    line-height: 1.4;
    margin-bottom: 0.3rem;
    color: var(--text);
}

.lightbox-panel-date {
    font-size: 0.75rem;
    color: var(--text-muted);
    letter-spacing: 0.03em;
}

.lightbox-panel-body {
    flex: 1;
    overflow-y: auto;
    padding: 1rem 1.2rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* Tags */
.lightbox-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.lightbox-tag {
    font-size: 0.7rem;
    padding: 0.18rem 0.45rem;
    border: 1px solid var(--border);
    color: var(--text-muted);
    letter-spacing: 0.03em;
}

/* EXIF */
.lightbox-exif-loading {
    font-size: 0.78rem;
    color: var(--text-muted);
    font-style: italic;
}

.lightbox-exif-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.78rem;
}

.lightbox-exif-table td {
    padding: 0.18rem 0;
    vertical-align: top;
}

.exif-key {
    color: var(--text-muted);
    width: 110px;
    padding-right: 0.5rem;
    white-space: nowrap;
}

.exif-val { color: var(--text); }

/* Carte */
.lightbox-map {
    width: 100%;
    height: 200px;
    border: 1px solid var(--border);
    flex-shrink: 0;
}

/* ============================
   ERREUR
   ============================ */
.error-box {
    background: var(--surface);
    border: 1px solid #5c2020;
    padding: 2rem;
    color: #e08080;
    font-size: 0.9rem;
    line-height: 1.6;
    margin: 2rem 0;
}
.error-box strong { color: #e8a0a0; }

/* ============================
   RESPONSIVE
   ============================ */
@media (max-width: 1024px) {
    .galleries-grid { grid-template-columns: repeat(2, 1fr); }
    .photos-masonry { columns: 3; }
}

@media (max-width: 768px) {
    .galleries-grid { grid-template-columns: 1fr; }
    .photos-masonry { columns: 2; }
}

@media (max-width: 480px) {
    .photos-masonry { columns: 1; }
}

@media (max-width: 600px) {
    :root { --panel-w: 100vw; }

    .lightbox-overlay.panel-open .lightbox-img-wrap {
        right: 0;
    }
}
```

- [ ] **Étape 2 : Vérifier visuellement dans le navigateur**

Ouvrir `index.php` : grille collections OK, fond noir. Ouvrir un album : grille masonry sans bordure, gap 4px.

- [ ] **Étape 3 : Commit**

```bash
git add css/style.css
git commit -m "style: refonte CSS — grille immersive, lightbox plein écran, panneau latéral"
```

---

## Task 4: Restructuration HTML lightbox dans gallery.php

**Files:**
- Modify: `gallery.php`

- [ ] **Étape 1 : Remplacer le bloc `<!-- Lightbox -->` (lignes 90–114) par la nouvelle structure**

Remplacer tout le bloc lightbox (de `<!-- Lightbox -->` jusqu'à `</div>` inclus) par :

```html
<!-- Lightbox -->
<div id="lightbox-overlay" class="lightbox-overlay">

    <div class="lightbox-img-wrap">
        <img class="lightbox-img" src="" alt="">
        <button class="lightbox-prev" aria-label="Photo précédente">&#8592;</button>
        <button class="lightbox-next" aria-label="Photo suivante">&#8594;</button>
    </div>

    <div class="lightbox-panel" id="lightbox-panel">
        <div class="lightbox-panel-header">
            <p class="lightbox-panel-title"></p>
            <p class="lightbox-panel-date"></p>
        </div>
        <div class="lightbox-panel-body">
            <div class="lightbox-tags"></div>
            <div class="lightbox-exif"></div>
            <div id="lightbox-map" class="lightbox-map" style="display:none"></div>
        </div>
    </div>

    <button class="lightbox-close" aria-label="Fermer">&#10005;</button>
    <button class="lightbox-info" id="lightbox-info-btn" aria-label="Informations">i</button>

</div>
```

- [ ] **Étape 2 : Vérifier la syntaxe PHP**

```bash
php -l gallery.php
```

Résultat attendu : `No syntax errors detected in gallery.php`

- [ ] **Étape 3 : Commit**

```bash
git add gallery.php
git commit -m "feat: gallery.php — nouvelle structure HTML lightbox avec panneau latéral"
```

---

## Task 5: Réécriture de lightbox.js

**Files:**
- Modify: `js/lightbox.js`

- [ ] **Étape 1 : Remplacer entièrement `js/lightbox.js`**

```javascript
(function () {
    'use strict';

    var photos   = [];
    var current  = 0;
    var infoLoaded = {};   // index -> true une fois EXIF/carte chargés
    var zoomed   = false;

    var overlay, imgWrap, img, panel, panelTitle, panelDate, tagsEl, exifEl, mapEl;
    var btnClose, btnInfo, btnPrev, btnNext;
    var leafletMap = null;
    var leafletMarker = null;

    function init() {
        overlay   = document.getElementById('lightbox-overlay');
        if (!overlay) return;

        imgWrap    = overlay.querySelector('.lightbox-img-wrap');
        img        = overlay.querySelector('.lightbox-img');
        panel      = document.getElementById('lightbox-panel');
        panelTitle = panel.querySelector('.lightbox-panel-title');
        panelDate  = panel.querySelector('.lightbox-panel-date');
        tagsEl     = panel.querySelector('.lightbox-tags');
        exifEl     = panel.querySelector('.lightbox-exif');
        mapEl      = document.getElementById('lightbox-map');

        btnClose = overlay.querySelector('.lightbox-close');
        btnInfo  = document.getElementById('lightbox-info-btn');
        btnPrev  = overlay.querySelector('.lightbox-prev');
        btnNext  = overlay.querySelector('.lightbox-next');

        btnClose.addEventListener('click', close);
        btnInfo.addEventListener('click', togglePanel);
        btnPrev.addEventListener('click', function () { if (!zoomed) prev(); });
        btnNext.addEventListener('click', function () { if (!zoomed) next(); });
        img.addEventListener('click', toggleZoom);

        document.addEventListener('keydown', function (e) {
            if (!overlay.classList.contains('active')) return;
            if (e.key === 'Escape')    { if (zoomed) unzoom(); else if (panelOpen()) closePanel(); else close(); }
            if (e.key === 'ArrowLeft'  && !zoomed) prev();
            if (e.key === 'ArrowRight' && !zoomed) next();
        });

        document.querySelectorAll('.photo-item').forEach(function (el, index) {
            el.addEventListener('click', function () { open(index); });
        });

        photos = Array.from(document.querySelectorAll('.photo-item')).map(function (el) {
            return {
                id:    el.dataset.id    || '',
                large: el.dataset.large || '',
                title: el.dataset.title || '',
                desc:  el.dataset.desc  || '',
                date:  el.dataset.date  || '',
                tags:  el.dataset.tags ? JSON.parse(el.dataset.tags) : [],
            };
        });
    }

    function open(index) {
        current = index;
        unzoom();
        closePanel();
        overlay.classList.add('active');
        requestAnimationFrame(function () { overlay.classList.add('visible'); });
        renderImage();
    }

    function close() {
        unzoom();
        closePanel();
        overlay.classList.remove('visible');
        setTimeout(function () { overlay.classList.remove('active'); }, 200);
    }

    function prev() {
        current = (current - 1 + photos.length) % photos.length;
        closePanel();
        unzoom();
        renderImage();
    }

    function next() {
        current = (current + 1) % photos.length;
        closePanel();
        unzoom();
        renderImage();
    }

    function renderImage() {
        var p = photos[current];
        img.src          = p.large;
        img.alt          = p.title || '';
        img.style.cursor = 'zoom-in';
    }

    /* ---- Panneau ---- */
    function panelOpen() {
        return panel.classList.contains('open');
    }

    function togglePanel() {
        if (panelOpen()) {
            closePanel();
        } else {
            openPanel();
        }
    }

    function openPanel() {
        var p = photos[current];

        // Remplir titre + date immédiatement
        panelTitle.textContent = p.title || '—';
        panelDate.textContent  = p.date ? formatDate(p.date) : '';

        // Tags
        tagsEl.innerHTML = '';
        (p.tags || []).forEach(function (t) {
            var span = document.createElement('span');
            span.className   = 'lightbox-tag';
            span.textContent = t;
            tagsEl.appendChild(span);
        });

        // Charger EXIF/carte une seule fois par photo
        if (!infoLoaded[current]) {
            exifEl.innerHTML = '<p class="lightbox-exif-loading">Chargement…</p>';
            mapEl.style.display = 'none';
            if (p.id) loadPhotoInfo(p.id, current);
        }

        panel.classList.add('open');
        overlay.classList.add('panel-open');
        btnInfo.classList.add('active-info');
    }

    function closePanel() {
        panel.classList.remove('open');
        overlay.classList.remove('panel-open');
        if (btnInfo) btnInfo.classList.remove('active-info');
    }

    /* ---- Zoom ---- */
    function toggleZoom(e) {
        if (zoomed) {
            unzoom();
        } else {
            var rect = img.getBoundingClientRect();
            var ox = ((e.clientX - rect.left) / rect.width  * 100).toFixed(1) + '%';
            var oy = ((e.clientY - rect.top)  / rect.height * 100).toFixed(1) + '%';
            img.style.transformOrigin = ox + ' ' + oy;
            img.style.transform       = 'scale(2.5)';
            img.style.cursor          = 'zoom-out';
            imgWrap.style.overflow    = 'auto';
            btnPrev.style.opacity     = '0.15';
            btnNext.style.opacity     = '0.15';
            zoomed = true;
        }
    }

    function unzoom() {
        if (!img) return;
        img.style.transform       = '';
        img.style.transformOrigin = '';
        img.style.cursor          = 'zoom-in';
        if (imgWrap) imgWrap.style.overflow = '';
        if (btnPrev) btnPrev.style.opacity = '';
        if (btnNext) btnNext.style.opacity = '';
        zoomed = false;
    }

    /* ---- EXIF + Carte ---- */
    function loadPhotoInfo(photoId, index) {
        fetch('photo-info.php?id=' + encodeURIComponent(photoId))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (current !== index) return; // navigation entre-temps
                renderExif(data.exif || {});
                renderMap(data.location || null);
                infoLoaded[index] = true;
            })
            .catch(function () {
                exifEl.innerHTML = '';
            });
    }

    var EXIF_LABELS = [
        ['Make',                    'Fabricant'],
        ['Model',                   'Appareil'],
        ['LensModel',               'Objectif'],
        ['LensInfo',                'Info objectif'],
        ['FocalLength',             'Focale'],
        ['FocalLengthIn35mmFormat', 'Focale 35mm'],
        ['FNumber',                 'Ouverture'],
        ['ExposureTime',            'Vitesse'],
        ['ISO',                     'ISO'],
        ['ISOSpeedRatings',         'ISO'],
        ['ExposureBiasValue',       'Correction expo'],
        ['ExposureProgram',         'Programme'],
        ['MeteringMode',            'Mesure'],
        ['WhiteBalance',            'Balance blancs'],
        ['Flash',                   'Flash'],
        ['SceneCaptureType',        'Type de scène'],
        ['ColorSpace',              'Espace couleur'],
        ['ExifImageWidth',          'Largeur'],
        ['ExifImageHeight',         'Hauteur'],
        ['Software',                'Logiciel'],
        ['DateTimeOriginal',        'Date prise de vue'],
        ['Artist',                  'Artiste'],
        ['Copyright',               'Copyright'],
        ['GPSAltitude',             'Altitude GPS'],
    ];

    function renderExif(exif) {
        exifEl.innerHTML = '';
        if (!Object.keys(exif).length) return;

        var rows = [];
        var seen = {};

        EXIF_LABELS.forEach(function (pair) {
            var tag = pair[0], label = pair[1];
            if (exif[tag] && !seen[tag]) {
                seen[tag] = true;
                rows.push([label, exif[tag]]);
            }
        });

        Object.keys(exif).sort().forEach(function (tag) {
            if (!seen[tag]) rows.push([tag, exif[tag]]);
        });

        if (!rows.length) return;

        var table = document.createElement('table');
        table.className = 'lightbox-exif-table';
        rows.forEach(function (row) {
            var tr  = document.createElement('tr');
            var key = document.createElement('td');
            var val = document.createElement('td');
            key.className   = 'exif-key';
            val.className   = 'exif-val';
            key.textContent = row[0];
            val.textContent = row[1];
            tr.appendChild(key);
            tr.appendChild(val);
            table.appendChild(tr);
        });
        exifEl.appendChild(table);
    }

    function renderMap(location) {
        if (!location || !location.lat || !location.lng) {
            mapEl.style.display = 'none';
            return;
        }

        mapEl.style.display = 'block';

        if (!leafletMap) {
            leafletMap    = L.map('lightbox-map', { zoomControl: true, attributionControl: false });
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(leafletMap);
            leafletMarker = L.marker([location.lat, location.lng]).addTo(leafletMap);
        } else {
            leafletMarker.setLatLng([location.lat, location.lng]);
        }

        leafletMap.setView([location.lat, location.lng], 13);
        setTimeout(function () { leafletMap.invalidateSize(); }, 150);
    }

    function formatDate(str) {
        if (!str) return '';
        var d = new Date(str);
        if (isNaN(d)) return str;
        return d.toLocaleDateString('fr-FR', { year: 'numeric', month: 'long', day: 'numeric' });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
```

- [ ] **Étape 2 : Tester manuellement la lightbox**

- Ouvrir une galerie, cliquer une photo → image plein écran, fond noir
- Cliquer "i" → panneau glisse depuis la droite, bouton "i" devient doré
- Vérifier que EXIF charge (spinner puis table)
- Cliquer flèches → panneau se ferme, nouvelle photo
- Cliquer "i" de nouveau → panneau EXIF déjà chargé (pas de re-fetch)
- Touche Echap → ferme panneau d'abord, puis lightbox au 2ème appui
- Cliquer image → zoom 2.5x au point de clic
- Vérifier sur mobile (≤600px) : panneau prend toute la largeur

- [ ] **Étape 3 : Commit**

```bash
git add js/lightbox.js
git commit -m "feat: lightbox — panneau infos latéral escamotable, EXIF différé"
```

---

## Task 6: Déploiement

**Files:**
- Run: `deploy-ssh.sh`

- [ ] **Étape 1 : Dry-run**

```bash
./deploy-ssh.sh --dry-run
```

Résultat attendu : liste des fichiers qui seraient transférés, pas d'erreur SSH.

- [ ] **Étape 2 : Déploiement réel**

```bash
./deploy-ssh.sh
```

- [ ] **Étape 3 : Vérifier en production**

Ouvrir le site en production. Tester : page collections (rapide), galerie photos (masonry propre), lightbox (plein écran + panneau "i").
