(() => {
  const body = document.body;
  document.querySelector('[data-menu]')?.addEventListener('click', () => body.classList.toggle('nav-open'));
  document.querySelector('[data-overlay]')?.addEventListener('click', () => body.classList.remove('nav-open'));

  document.querySelectorAll('[data-dialog-open]').forEach((button) => {
    button.addEventListener('click', () => {
      const dialog = document.getElementById(button.dataset.dialogOpen);
      if (!dialog) return;
      if (button.dataset.payload) {
        try {
          const payload = JSON.parse(button.dataset.payload);
          Object.entries(payload).forEach(([key, value]) => {
            const field = dialog.querySelector('[name="' + key + '"]');
            if (field) field.value = value ?? '';
          });
        } catch (_) {}
      }
      dialog.showModal();
    });
  });

  document.querySelectorAll('[data-dialog-close]').forEach((button) => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
  });

  document.querySelectorAll('dialog').forEach((dialog) => {
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) dialog.close();
    });
  });

  const search = document.querySelector('[data-table-search]');
  search?.addEventListener('input', () => {
    const term = search.value.trim().toLowerCase();
    document.querySelectorAll('[data-search-row]').forEach((row) => {
      row.hidden = !row.textContent.toLowerCase().includes(term);
    });
  });

  window.setTimeout(() => document.querySelector('.flash')?.classList.add('flash-hide'), 4200);
})();
