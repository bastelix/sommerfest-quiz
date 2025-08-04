const currentScript = document.currentScript;
const basePath = currentScript ? currentScript.dataset.base || '' : '';

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.toggle-publish').forEach((btn) => {
    btn.addEventListener('click', () => {
      const uid = btn.dataset.uid;
      const published = btn.dataset.published === 'true';
      fetch(`${basePath}/events/${uid}/publish`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ published: !published })
      }).then((resp) => {
        if (resp.ok) {
          btn.dataset.published = (!published).toString();
          btn.textContent = !published ? 'Nicht veröffentlichen' : 'Veröffentlichen';
        }
      });
    });
  });
  document.querySelectorAll('.copy-link').forEach((btn) => {
    btn.addEventListener('click', () => {
      const link = btn.dataset.link;
      navigator.clipboard.writeText(link).then(() => {
        if (typeof UIkit !== 'undefined') {
          UIkit.notification({ message: 'Link kopiert', status: 'success' });
        }
      });
    });
  });
});
