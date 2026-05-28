(function () {
    'use strict';

    let photos = [];
    let current = 0;
    let overlay, img, title, desc, date, tags;

    function init() {
        overlay = document.getElementById('lightbox-overlay');
        if (!overlay) return;

        img   = overlay.querySelector('.lightbox-img');
        title = overlay.querySelector('.lightbox-title');
        desc  = overlay.querySelector('.lightbox-desc');
        date  = overlay.querySelector('.lightbox-date');
        tags  = overlay.querySelector('.lightbox-tags');

        overlay.querySelector('.lightbox-close').addEventListener('click', close);
        overlay.querySelector('.lightbox-btn-prev').addEventListener('click', prev);
        overlay.querySelector('.lightbox-btn-next').addEventListener('click', next);

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay || e.target === overlay.querySelector('.lightbox-main')) {
                close();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (!overlay.classList.contains('active')) return;
            if (e.key === 'Escape')      close();
            if (e.key === 'ArrowLeft')   prev();
            if (e.key === 'ArrowRight')  next();
        });

        document.querySelectorAll('.photo-item').forEach(function (el, index) {
            el.addEventListener('click', function () { open(index); });
        });

        photos = Array.from(document.querySelectorAll('.photo-item')).map(function (el) {
            return {
                large: el.dataset.large,
                title: el.dataset.title   || '',
                desc:  el.dataset.desc    || '',
                date:  el.dataset.date    || '',
                tags:  el.dataset.tags    ? el.dataset.tags.split(',').filter(Boolean) : [],
            };
        });
    }

    function open(index) {
        current = index;
        overlay.classList.add('active');
        requestAnimationFrame(function () {
            overlay.classList.add('visible');
        });
        render();
    }

    function close() {
        overlay.classList.remove('visible');
        setTimeout(function () {
            overlay.classList.remove('active');
        }, 250);
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
        img.src      = p.large;
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
    }

    function formatDate(str) {
        if (!str) return '';
        const d = new Date(str);
        if (isNaN(d)) return str;
        return d.toLocaleDateString('fr-FR', { year: 'numeric', month: 'long', day: 'numeric' });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
