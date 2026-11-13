(() => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  // Confirmation avant les actions destructrices : <form data-confirm="…">
  document.addEventListener('submit', (ev) => {
    const message = ev.target.dataset.confirm;
    if (message && !window.confirm(message)) ev.preventDefault();
  });

  // Tri par glisser-déposer : conteneur [data-sortable="/url"], enfants [data-id]
  document.querySelectorAll('[data-sortable]').forEach((list) => {
    if (!window.Sortable) return;
    Sortable.create(list, {
      animation: 150,
      handle: list.dataset.handle || null,
      onEnd: () => {
        const ids = Array.from(list.children).map((el) => el.dataset.id).filter(Boolean);
        fetch(list.dataset.sortable, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, Accept: 'application/json' },
          body: JSON.stringify({ ids }),
        })
          .then((r) => {
            if (!r.ok) throw new Error(String(r.status));
            list.classList.add('is-saved');
            setTimeout(() => list.classList.remove('is-saved'), 800);
          })
          .catch(() => alert("L'ordre n'a pas pu être enregistré. Rechargez la page."));
      },
    });
  });

  // Enregistrement automatique : <form data-autosave> (titre et légende des photos)
  document.addEventListener('change', (ev) => {
    const form = ev.target.closest('form[data-autosave]');
    if (!form) return;
    const status = form.querySelector('.save-status');
    if (status) status.textContent = 'Enregistrement…';
    fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } })
      .then((r) => (r.ok ? r.json() : Promise.reject(r.status)))
      .then(() => { if (status) status.textContent = 'Enregistré ✓'; })
      .catch(() => { if (status) status.textContent = 'Erreur, réessayez'; });
  });

  // Barre gras / italique / lien au-dessus d'une zone de texte : [data-toolbar="id-du-textarea"]
  document.querySelectorAll('[data-toolbar]').forEach((bar) => {
    const area = document.getElementById(bar.dataset.toolbar);
    if (!area) return;
    bar.addEventListener('click', (ev) => {
      const btn = ev.target.closest('button[data-wrap]');
      if (!btn) return;
      ev.preventDefault();
      let open; let close;
      if (btn.dataset.wrap === 'link') {
        const href = window.prompt('Adresse du lien (https://…)');
        if (!href) return;
        open = `<a href="${href.replace(/"/g, '&quot;')}">`;
        close = '</a>';
      } else {
        [open, close] = btn.dataset.wrap.split('|');
      }
      const { selectionStart: s, selectionEnd: e, value } = area;
      area.value = value.slice(0, s) + open + value.slice(s, e) + close + value.slice(e);
      area.focus();
      area.selectionStart = s + open.length;
      area.selectionEnd = e + open.length;
    });
  });
})();
