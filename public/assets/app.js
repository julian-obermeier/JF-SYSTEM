(() => {
  'use strict';

  const body = document.body;

  const closeMenu = () => body.classList.remove('nav-open');
  document.querySelector('[data-menu]')?.addEventListener('click', (event) => {
    event.preventDefault();
    body.classList.toggle('nav-open');
  });
  document.querySelector('[data-overlay]')?.addEventListener('click', closeMenu);

  const populateDialog = (dialog, button) => {
    const form = dialog.querySelector('form');
    form?.reset();
    dialog.querySelectorAll('input[type="hidden"][name="id"]').forEach((field) => { field.value = ''; });

    const payload = button.dataset.payload;
    if (!payload) return;

    try {
      const data = JSON.parse(payload);
      Object.entries(data).forEach(([key, value]) => {
        const field = dialog.querySelector('[name="' + CSS.escape(key) + '"]');
        if (!field) return;
        if (field.type === 'checkbox') field.checked = Boolean(Number(value));
        else field.value = value ?? '';
      });
    } catch (error) {
      console.warn('JF-SYSTEM: Dialogdaten konnten nicht geladen werden.', error);
    }
  };

  document.querySelectorAll('[data-dialog-open]').forEach((button) => {
    button.type = 'button';
    button.addEventListener('click', (event) => {
      event.preventDefault();
      const dialog = document.getElementById(button.dataset.dialogOpen);
      if (!dialog) {
        console.warn('JF-SYSTEM: Dialog nicht gefunden:', button.dataset.dialogOpen);
        return;
      }
      populateDialog(dialog, button);
      if (typeof dialog.showModal === 'function') dialog.showModal();
      else dialog.setAttribute('open', '');
    });
  });

  document.querySelectorAll('[data-dialog-close]').forEach((button) => {
    button.type = 'button';
    button.addEventListener('click', (event) => {
      event.preventDefault();
      const dialog = button.closest('dialog');
      if (dialog?.open) dialog.close();
      else dialog?.removeAttribute('open');
    });
  });

  document.querySelectorAll('dialog').forEach((dialog) => {
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) {
        if (dialog.open && typeof dialog.close === 'function') dialog.close();
        else dialog.removeAttribute('open');
      }
    });
  });

  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const message = form.dataset.confirm || 'Aktion wirklich ausführen?';
      if (!window.confirm(message)) event.preventDefault();
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
