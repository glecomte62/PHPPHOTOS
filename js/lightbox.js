(function () {
    'use strict';

    let photos = [];
    let current = 0;
    let overlay, img, title, desc, date, tags, exifEl, mapEl;
    let leafletMap = null;
    let leafletMarker = null;

    function init() {
        overlay = document.getElementById('lightbox-overlay');
        if (!overlay) return;

        img    = overlay.querySelector('.lightbox-img');
        title  = overlay.querySelector('.lightbox-title');
        desc   = overlay.querySelector('.lightbox-desc');
        date   = overlay.querySelector('.lightbox-date');
        tags   = overlay.querySelector('.lightbox-tags');
        exifEl = overlay.querySelector('.lightbox-exif');
        mapEl  = document.getElementById('lightbox-map');

        overlay.querySelector('.lightbox-close').addEventListener('click', close);
        overlay.querySelector('.lightbox-btn-prev').addEventListener('click', prev);
        overlay.querySelector('.lightbox-btn-next').addEventListener('click', next);

        var lightboxMain = overlay.querySelector('.lightbox-main');
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay || e.target === lightboxMain) close();
        });

        document.addEventListener('keydown', function (e) {
            if (!overlay.classList.contains('active')) return;
            if (e.key === 'Escape')     close();
            if (e.key === 'ArrowLeft')  prev();
            if (e.key === 'ArrowRight') next();
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
                tags:  el.dataset.tags  ? JSON.parse(el.dataset.tags) : [],
            };
        });
    }

    function open(index) {
        current = index;
        overlay.classList.add('active');
        requestAnimationFrame(function () { overlay.classList.add('visible'); });
        render();
    }

    function close() {
        overlay.classList.remove('visible');
        setTimeout(function () { overlay.classList.remove('active'); }, 250);
    }

    function prev() {
        current = (current - 1 + photos.length) % photos.length;
        render();
    }

    function next() {
        current = (current + 1) % photos.length;
        render();
    }

    function render() {
        const p = photos[current];
        img.src           = p.large;
        title.textContent = p.title || '—';
        desc.textContent  = p.desc  || '';
        date.textContent  = p.date  ? formatDate(p.date) : '';

        tags.innerHTML = '';
        p.tags.forEach(function (t) {
            const span = document.createElement('span');
            span.className   = 'lightbox-tag';
            span.textContent = t;
            tags.appendChild(span);
        });

        exifEl.innerHTML = '<p class="lightbox-exif-loading">Chargement…</p>';
        mapEl.style.display = 'none';

        if (p.id) loadPhotoInfo(p.id);
    }

    function loadPhotoInfo(photoId) {
        fetch('photo-info.php?id=' + encodeURIComponent(photoId))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                renderExif(data.exif || {});
                renderMap(data.location || null);
            })
            .catch(function () {
                exifEl.innerHTML = '';
            });
    }

    // Ordre d'affichage et traductions des tags EXIF connus
    const EXIF_LABELS = [
        ['Make',                      'Fabricant'],
        ['Model',                     'Appareil'],
        ['LensModel',                 'Objectif'],
        ['LensInfo',                  'Info objectif'],
        ['FocalLength',               'Focale'],
        ['FocalLengthIn35mmFormat',   'Focale (35mm)'],
        ['FNumber',                   'Ouverture'],
        ['ExposureTime',              'Vitesse'],
        ['ISO',                       'ISO'],
        ['ISOSpeedRatings',           'ISO'],
        ['ExposureBiasValue',         'Correction expo'],
        ['ExposureProgram',           'Programme'],
        ['MeteringMode',              'Mesure'],
        ['WhiteBalance',              'Balance blancs'],
        ['Flash',                     'Flash'],
        ['SceneCaptureType',          'Type de scène'],
        ['Contrast',                  'Contraste'],
        ['Saturation',                'Saturation'],
        ['Sharpness',                 'Netteté'],
        ['DigitalZoomRatio',          'Zoom numérique'],
        ['SubjectDistanceRange',      'Distance sujet'],
        ['LightSource',               'Source lumière'],
        ['ColorSpace',                'Espace couleur'],
        ['ExifImageWidth',            'Largeur'],
        ['ExifImageHeight',           'Hauteur'],
        ['Orientation',               'Orientation'],
        ['Software',                  'Logiciel'],
        ['DateTime',                  'Date modif.'],
        ['DateTimeOriginal',          'Date prise de vue'],
        ['Artist',                    'Artiste'],
        ['Copyright',                 'Copyright'],
        ['GPSAltitude',               'Altitude GPS'],
    ];

    function renderExif(exif) {
        exifEl.innerHTML = '';
        if (!Object.keys(exif).length) return;

        const rows = [];
        const seen = new Set();

        // Tags connus dans l'ordre défini
        EXIF_LABELS.forEach(function ([tag, label]) {
            if (exif[tag] && !seen.has(tag)) {
                seen.add(tag);
                rows.push([label, exif[tag]]);
            }
        });

        // Tags inconnus restants (triés alphabétiquement)
        Object.keys(exif).sort().forEach(function (tag) {
            if (!seen.has(tag)) {
                rows.push([tag, exif[tag]]);
            }
        });

        if (!rows.length) return;

        const table = document.createElement('table');
        table.className = 'lightbox-exif-table';
        rows.forEach(function (row) {
            const tr = document.createElement('tr');
            const key = document.createElement('td');
            const val = document.createElement('td');
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
            leafletMap = L.map('lightbox-map', { zoomControl: true, attributionControl: false });
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(leafletMap);
            leafletMarker = L.marker([location.lat, location.lng]).addTo(leafletMap);
        } else {
            leafletMarker.setLatLng([location.lat, location.lng]);
        }

        leafletMap.setView([location.lat, location.lng], 13);
        // force recalcul taille après affichage
        setTimeout(function () { leafletMap.invalidateSize(); }, 100);
    }

    function formatDate(str) {
        if (!str) return '';
        const d = new Date(str);
        if (isNaN(d)) return str;
        return d.toLocaleDateString('fr-FR', { year: 'numeric', month: 'long', day: 'numeric' });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
