(() => {
  'use strict';

  const toast = (message) => {
    const element = document.getElementById('toast');
    if (!element) return;
    element.textContent = message;
    element.hidden = false;
    window.clearTimeout(element._timer);
    element._timer = window.setTimeout(() => { element.hidden = true; }, 2600);
  };

  document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-dialog-open]');
    if (opener) {
      event.preventDefault();
      const dialog = document.getElementById(opener.dataset.dialogOpen);
      if (dialog instanceof HTMLDialogElement) dialog.showModal();
      else toast('Der Dialog konnte nicht geöffnet werden.');
      return;
    }

    const closer = event.target.closest('[data-dialog-close]');
    if (closer) {
      event.preventDefault();
      closer.closest('dialog')?.close();
      return;
    }

    const dismiss = event.target.closest('[data-dismiss]');
    if (dismiss) {
      dismiss.parentElement?.remove();
      return;
    }

    const comingSoon = event.target.closest('[data-coming-soon]');
    if (comingSoon) {
      event.preventDefault();
      toast('Dieses Modul folgt in der nächsten v2-Ausbaustufe.');
      return;
    }

    const menuToggle = event.target.closest('[data-menu-toggle]');
    if (menuToggle) {
      const menu = document.getElementById(menuToggle.dataset.menuToggle);
      if (menu) menu.hidden = !menu.hidden;
      return;
    }

    const sidebarToggle = event.target.closest('[data-sidebar-toggle]');
    if (sidebarToggle) {
      document.getElementById('sidebar')?.classList.toggle('is-open');
      return;
    }

    const row = event.target.closest('[data-row-link]');
    if (row && !event.target.closest('a,button,input,select')) {
      window.location.href = row.dataset.rowLink;
    }
  });

  document.addEventListener('change', (event) => {
    const input = event.target.closest('[data-auto-submit]');
    if (input?.form) input.form.requestSubmit();
  });

  document.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      document.querySelector('.global-search input')?.focus();
    }
    if (event.key === 'Escape') document.getElementById('sidebar')?.classList.remove('is-open');
  });
})();
