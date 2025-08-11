export function initSeoForm() {
  const form = document.querySelector('.seo-form');
  if (!form) return;

  const inputs = form.querySelectorAll('[data-maxlength]');

  inputs.forEach(input => {
    const max = parseInt(input.dataset.maxlength, 10);
    const counter = form.querySelector(`.char-count[data-for="${input.id}"]`);
    if (counter) {
      input.addEventListener('input', () => {
        const len = input.value.length;
        counter.textContent = `${len}/${max}`;
        counter.classList.toggle('uk-text-danger', len > max);
      });
      input.dispatchEvent(new Event('input'));
    }
  });

  form.addEventListener('submit', e => {
    let valid = true;
    inputs.forEach(input => {
      const max = parseInt(input.dataset.maxlength, 10);
      if (max && input.value.length > max) {
        input.classList.add('uk-form-danger');
        valid = false;
      } else {
        input.classList.remove('uk-form-danger');
      }
    });
    form.querySelectorAll('[required]').forEach(field => {
      if (!field.value.trim()) {
        field.classList.add('uk-form-danger');
        valid = false;
      } else {
        field.classList.remove('uk-form-danger');
      }
    });
    if (!valid) {
      e.preventDefault();
    }
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initSeoForm);
} else {
  initSeoForm();
}
