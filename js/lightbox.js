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

        panel   = document.getElementById('lightbox-panel');
        btnInfo = document.getElementById('lightbox-info-btn');
        if (!panel || !btnInfo) return;

        imgWrap    = overlay.querySelector('.lightbox-img-wrap');
        img        = overlay.querySelector('.lightbox-img');
        panelTitle = panel.querySelector('.lightbox-panel-title');
        panelDate  = panel.querySelector('.lightbox-panel-date');
        tagsEl     = panel.querySelector('.lightbox-tags');
        exifEl     = panel.querySelector('.lightbox-exif');
        mapEl      = document.getElementById('lightbox-map');

        btnClose = overlay.querySelector('.lightbox-close');
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

        var items = document.querySelectorAll('.photo-item');
        items.forEach(function (el, index) {
            el.addEventListener('click', function () { open(index); });
        });

        photos = Array.from(items).map(function (el) {
            return {
                id:    el.dataset.id    || '',
                large: el.dataset.large || '',
                title: el.dataset.title || '',
                desc:  el.dataset.desc  || '',
                date:  el.dataset.date  || '',
                tags: (function () {
                    try { return el.dataset.tags ? JSON.parse(el.dataset.tags) : []; }
                    catch (e) { return []; }
                }()),
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
        btnInfo.setAttribute('aria-expanded', 'true');
    }

    function closePanel() {
        panel.classList.remove('open');
        overlay.classList.remove('panel-open');
        if (btnInfo) {
            btnInfo.classList.remove('active-info');
            btnInfo.setAttribute('aria-expanded', 'false');
        }
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
        var capturedExifEl = exifEl;
        var capturedMapEl  = mapEl;
        fetch('photo-info.php?id=' + encodeURIComponent(photoId))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (current !== index) return; // navigation entre-temps
                renderExif(data.exif || {}, capturedExifEl);
                renderMap(data.location || null, capturedMapEl);
                infoLoaded[index] = true;
            })
            .catch(function () {
                capturedExifEl.innerHTML = '';
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

    function renderExif(exif, el) {
        el.innerHTML = '';
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
        el.appendChild(table);
    }

    function renderMap(location, el) {
        if (typeof L === 'undefined') {
            el.style.display = 'none';
            return;
        }

        if (!location || !location.lat || !location.lng) {
            el.style.display = 'none';
            return;
        }

        el.style.display = 'block';

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
        var d = new Date(str.replace(' ', 'T'));
        if (isNaN(d)) return str;
        return d.toLocaleDateString('fr-FR', { year: 'numeric', month: 'long', day: 'numeric' });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
