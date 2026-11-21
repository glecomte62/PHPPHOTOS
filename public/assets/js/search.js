(() => {
  const form = document.querySelector('[data-search-form]');
  if (!form) return;

  // Les champs vides ne figurent pas dans l'adresse : /recherche?appareil=… plutôt que ?q=&objectif=&…
  form.addEventListener('submit', () => {
    form.querySelectorAll('input[name], select[name]').forEach((field) => {
      if (field.value === '') field.disabled = true;
    });
  });

  // Retour arrière : le navigateur peut restaurer la page avec les champs encore désactivés
  window.addEventListener('pageshow', () => {
    form.querySelectorAll('[disabled]').forEach((field) => { field.disabled = false; });
  });

  // Un filtre choisi s'applique tout de suite
  form.querySelectorAll('select').forEach((select) => {
    select.addEventListener('change', () => form.requestSubmit());
  });
})();
