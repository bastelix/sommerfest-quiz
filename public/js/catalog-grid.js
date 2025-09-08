document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.catalog-card').forEach(card => {
    const url = card.getAttribute('data-start-url');
    if (!url) {
      return;
    }
    const activate = () => {
      window.location.href = url;
    };
    card.addEventListener('click', activate);
    card.addEventListener('keypress', e => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        activate();
      }
    });
  });
});
