(() => {
  const GAP = 8;

  const layout = (container) => {
    const items = Array.from(container.querySelectorAll('.mosaic__item'));
    const cs = getComputedStyle(container);
    const width = Math.floor(container.getBoundingClientRect().width - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight) - 1);
    if (!width || items.length === 0) return;
    const target = width < 640 ? 160 : 280;
    let row = [];
    let ratios = 0;

    const place = (height) => {
      row.forEach((el) => {
        el.style.width = `${Math.floor(height * (parseFloat(el.dataset.ratio) || 1))}px`;
        el.style.height = `${Math.floor(height)}px`;
      });
      row = [];
      ratios = 0;
    };

    items.forEach((el) => {
      row.push(el);
      ratios += parseFloat(el.dataset.ratio) || 1;
      const height = (width - GAP * (row.length - 1)) / ratios;
      if (height <= target) place(height);
    });
    if (row.length) place(Math.min(target, (width - GAP * (row.length - 1)) / ratios));
  };

  const containers = document.querySelectorAll('[data-mosaic]');
  const run = () => containers.forEach((c) => { c.classList.add('is-justified'); layout(c); });
  run();
  if ('ResizeObserver' in window) {
    const ro = new ResizeObserver((entries) => entries.forEach((en) => layout(en.target)));
    containers.forEach((c) => ro.observe(c));
  } else {
    let timer;
    window.addEventListener('resize', () => { clearTimeout(timer); timer = setTimeout(run, 120); });
  }
})();
