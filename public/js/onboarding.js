/* global UIkit */
(function () {
  const steps = ['login', 'step1', 'step2', 'step3', 'step4', 'success'];
  const data = {
    name: '',
    subdomain: '',
    plan: '',
    payment: ''
  };

  function show(step) {
    steps.forEach(id => {
      const el = document.getElementById(id);
      if (el) el.hidden = id !== step;
    });
  }

  function slugify(text) {
    return text
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/ß/g, 'ss')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }

  document.addEventListener('DOMContentLoaded', () => {
    const loginBtn = document.getElementById('login-btn');
    const loginUser = document.getElementById('login-user');
    const loginPass = document.getElementById('login-pass');
    const loginError = document.getElementById('login-error');

    show('login');

    const nameInput = document.getElementById('customer-name');
    const subdomainPreview = document.getElementById('subdomain-preview');
    const next1 = document.getElementById('next1');
    const next2 = document.getElementById('next2');
    const next3 = document.getElementById('next3');
    const createBtn = document.getElementById('create');

    loginBtn.addEventListener('click', async () => {
      loginError.hidden = true;
      try {
        const res = await fetch('/login', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ username: loginUser.value, password: loginPass.value })
        });
        if (!res.ok) throw new Error('login');
        show('step1');
      } catch (err) {
        loginError.hidden = false;
      }
    });

    nameInput.addEventListener('input', () => {
      data.name = nameInput.value.trim();
      data.subdomain = slugify(data.name);
      subdomainPreview.textContent = data.subdomain || '-';
      next1.disabled = data.name === '';
    });

    next1.addEventListener('click', () => {
      show('step2');
    });

    next2.addEventListener('click', () => {
      data.plan = document.getElementById('plan').value;
      show('step3');
    });

    next3.addEventListener('click', () => {
      data.payment = document.getElementById('payment').value;
      document.getElementById('summary-name').textContent = data.name;
      document.getElementById('summary-subdomain').textContent = data.subdomain;
      document.getElementById('summary-plan').textContent = data.plan;
      document.getElementById('summary-payment').textContent = data.payment;
      show('step4');
    });

    createBtn.addEventListener('click', async () => {
      try {
        const tenantRes = await fetch('/tenants', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ uid: data.subdomain, schema: data.subdomain })
        });
        if (!tenantRes.ok) throw new Error('tenant');

        const importRes = await fetch('/restore-default', {
          method: 'POST',
          credentials: 'include'
        });
        if (!importRes.ok) throw new Error('import');

        document.getElementById('success-domain').textContent =
          data.subdomain + '.quizrace.app';
        show('success');
      } catch (err) {
        if (typeof UIkit !== 'undefined') {
          UIkit.notification({ message: 'Fehler beim Anlegen', status: 'danger' });
        } else {
          alert('Fehler beim Anlegen');
        }
      }
    });
  });
})();
