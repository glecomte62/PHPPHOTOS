(() => {
  const zone = document.querySelector('[data-upload]');
  if (!zone) return;
  const input = zone.querySelector('input[type=file]');
  const queueEl = document.querySelector('.upload-queue');
  const grid = document.querySelector('[data-photo-grid]');
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const queue = [];
  let busy = false;

  const fail = (item, message, canRetry) => {
    const { row } = item;
    row.classList.add('is-error');
    row.querySelector('.upload-item__status').textContent = message;
    if (!canRetry) return;
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'link-button';
    btn.textContent = 'Réessayer';
    btn.addEventListener('click', () => {
      btn.remove();
      row.classList.remove('is-error');
      row.querySelector('progress').value = 0;
      row.querySelector('.upload-item__status').textContent = 'En attente';
      queue.push(item);
      next();
    });
    row.appendChild(btn);
  };

  const send = (item) => new Promise((resolve) => {
    const { file, row } = item;
    const status = row.querySelector('.upload-item__status');
    const bar = row.querySelector('progress');
    if (!/^image\/jpe?g$/i.test(file.type)) {
      fail(item, 'Format refusé (JPEG uniquement)', false);
      resolve();
      return;
    }
    const xhr = new XMLHttpRequest();
    const data = new FormData();
    data.append('photo', file);
    xhr.open('POST', zone.dataset.upload);
    xhr.setRequestHeader('X-CSRF-Token', csrf);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.upload.onprogress = (ev) => {
      if (!ev.lengthComputable) return;
      bar.value = Math.round((ev.loaded / ev.total) * 90);
      status.textContent = 'Envoi…';
    };
    xhr.upload.onload = () => { status.textContent = 'Traitement…'; };
    xhr.onload = () => {
      let res = {};
      try { res = JSON.parse(xhr.responseText); } catch (e) { /* réponse non JSON (erreur serveur) */ }
      if (xhr.status === 201 && res.html) {
        bar.value = 100;
        status.textContent = 'Ajoutée ✓';
        row.classList.add('is-done');
        grid.insertAdjacentHTML('beforeend', res.html);
      } else {
        fail(item, res.error || `Erreur ${xhr.status}`, xhr.status >= 500 || xhr.status === 0);
      }
      resolve();
    };
    xhr.onerror = () => { fail(item, 'Connexion interrompue', true); resolve(); };
    xhr.send(data);
  });

  const next = () => {
    if (busy || queue.length === 0) return;
    busy = true;
    send(queue.shift()).finally(() => { busy = false; next(); });
  };

  const add = (files) => {
    Array.from(files).forEach((file) => {
      const row = document.createElement('li');
      row.className = 'upload-item';
      row.innerHTML = '<span class="upload-item__name"></span><progress max="100" value="0"></progress><span class="upload-item__status">En attente</span>';
      row.querySelector('.upload-item__name').textContent = file.name;
      queueEl.appendChild(row);
      queue.push({ file, row });
    });
    next();
  };

  input.addEventListener('change', () => { add(input.files); input.value = ''; });
  ['dragenter', 'dragover'].forEach((type) => zone.addEventListener(type, (ev) => {
    ev.preventDefault();
    zone.classList.add('is-over');
  }));
  ['dragleave', 'drop'].forEach((type) => zone.addEventListener(type, (ev) => {
    ev.preventDefault();
    zone.classList.remove('is-over');
  }));
  zone.addEventListener('drop', (ev) => add(ev.dataTransfer.files));
})();
