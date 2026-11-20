// Diaporama de l'accueil : fondus enchaînés avec un lent zoom (effet Ken Burns, en CSS).
(() => {
  const hero = document.querySelector('.hero--reel');
  if (!hero) return;
  const slides = Array.from(hero.querySelectorAll('.hero__slide'));
  const toggle = hero.querySelector('.hero__toggle');
  if (slides.length < 2) return;

  const DURATION = 6500;
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  let index = 0;
  let timer = null;
  let paused = reduced.matches;

  const imgOf = (slide) => slide.querySelector('img');
  const ready = (slide) => {
    const img = imgOf(slide);
    return !img || (img.complete && img.naturalWidth > 0);
  };

  function show(next) {
    const current = slides[index];
    const target = slides[next];
    current.classList.remove('is-active');
    current.classList.add('is-leaving');
    current.setAttribute('aria-hidden', 'true');
    current.tabIndex = -1;
    setTimeout(() => current.classList.remove('is-leaving'), 2000);
    // Relance l'animation de zoom depuis le début.
    const img = imgOf(target);
    if (img) { img.style.animation = 'none'; void img.offsetWidth; img.style.animation = ''; }
    target.classList.add('is-active');
    target.removeAttribute('aria-hidden');
    target.removeAttribute('tabindex');
    index = next;
  }

  function tick() {
    const next = (index + 1) % slides.length;
    if (ready(slides[next])) {
      show(next);
      schedule(DURATION);
    } else {
      // L'image suivante n'est pas encore là : on réessaie un peu plus tard.
      schedule(500);
    }
  }

  function schedule(delay) {
    clearTimeout(timer);
    timer = paused || document.hidden ? null : setTimeout(tick, delay);
  }

  function setPaused(value) {
    paused = value;
    hero.classList.toggle('is-paused', paused);
    if (toggle) {
      toggle.setAttribute('aria-label', paused ? 'Lire le diaporama' : 'Mettre le diaporama en pause');
    }
    schedule(DURATION);
  }

  // Les images suivantes sont chargées tôt mais sans gêner la première.
  slides.slice(1).forEach((slide) => {
    const img = imgOf(slide);
    if (img) img.loading = 'eager';
  });

  if (toggle) {
    toggle.hidden = false;
    toggle.addEventListener('click', () => setPaused(!paused));
  }
  document.addEventListener('visibilitychange', () => schedule(DURATION));
  setPaused(paused);
})();
