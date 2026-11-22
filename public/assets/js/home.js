// Accueil : les compteurs défilent jusqu'à leur valeur quand ils apparaissent à l'écran
(() => {
  const counters = document.querySelectorAll('.stats [data-count]');
  if (!counters.length || matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) return;
  const fmt = new Intl.NumberFormat('fr-FR');
  const run = (el) => {
    const target = Number(el.dataset.count) || 0;
    const start = performance.now();
    const duration = 1400;
    const step = (now) => {
      const t = Math.min(1, (now - start) / duration);
      const eased = 1 - (1 - t) ** 3;
      el.textContent = fmt.format(Math.round(target * eased));
      if (t < 1) requestAnimationFrame(step);
    };
    el.textContent = '0';
    requestAnimationFrame(step);
  };
  const io = new IntersectionObserver((entries) => {
    entries.forEach((e) => {
      if (!e.isIntersecting) return;
      io.unobserve(e.target);
      run(e.target);
    });
  }, { threshold: 0.6 });
  counters.forEach((el) => io.observe(el));
})();
