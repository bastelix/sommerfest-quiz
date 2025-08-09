/* global UIkit */
(function () {
  const steps = ['login', 'step1', 'step2', 'step3', 'step4', 'step5', 'success'];
  const data = {
    name: '',
    subdomain: '',
    email: '',
    emailConfirmed: false,
    plan: '',
    payment: '',
    imprintName: '',
    imprintStreet: '',
    imprintZip: '',
    imprintCity: '',
    imprintEmail: '',
    adminPass: ''
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

  const RESERVED_SUBDOMAINS = new Set([
    'public',
    'www',
    'so',
    'sa',
    'ss',
    'ns',
    'nsdap',
    'nazi',
    'nazis',
    'hitler',
    'adolf',
    'hj',
    'bdm',
    'kz'
  ]);

  document.addEventListener('DOMContentLoaded', () => {
    const loginBtn = document.getElementById('login-btn');
    const loginUser = document.getElementById('login-user');
    const loginPass = document.getElementById('login-pass');
    const loginError = document.getElementById('login-error');

    const loggedIn = window.loggedIn === true || window.loggedIn === 'true';
    show(loggedIn ? 'step1' : 'login');

    const basePath = window.basePath || '';
    const mainDomain = window.mainDomain || '';
    const reloadToken = window.reloadToken || '';
    const withBase = p => basePath + p;

    const logMessages = [];
    const taskLogEl = document.getElementById('task-log');
    const taskStatusEl = document.getElementById('task-status');
    let waitProgress;
    const tasks = [
      { key: 'tenant', label: 'Mandant erstellen' },
      { key: 'import', label: 'Standardinhalte importieren' },
      { key: 'user', label: 'Admin-Passwort setzen' }
    ];
    if (reloadToken) {
      tasks.push({ key: 'reload', label: 'Proxy neu laden' });
    }
    tasks.push({ key: 'ssl', label: 'Container starten (SSL)' });
    tasks.push({
      key: 'wait',
      label: 'Wir bauen deine App. Bitte ein wenig Geduld.'
    });

    function initTaskList() {
      if (!taskStatusEl || taskStatusEl.children.length > 0) return;
      tasks.forEach(t => {
        const li = document.createElement('li');
        li.id = `task-${t.key}`;
        if (t.key === 'wait') {
          li.innerHTML = `<span class="status"></span> ${t.label}<progress id="wait-progress" class="uk-progress uk-margin-small-top" value="0" max="100" hidden></progress>`;
        } else {
          li.innerHTML = `<span class="status"></span> ${t.label}`;
        }
        taskStatusEl.appendChild(li);
      });
    }

    function setTaskStatus(key, status) {
      const li = document.getElementById(`task-${key}`);
      if (!li) return;
      const span = li.querySelector('.status');
      if (!span) return;
      const progress = key === 'wait' ? document.getElementById('wait-progress') : null;
      if (status === 'done') {
        span.textContent = '✓';
        li.classList.add('uk-text-success');
        if (progress) progress.hidden = true;
      } else if (status === 'failed') {
        span.textContent = '✗';
        li.classList.add('uk-text-danger');
        if (progress) progress.hidden = true;
      } else {
        span.innerHTML = '<span uk-spinner></span>';
        if (progress) {
          progress.hidden = false;
          progress.value = 0;
        }
      }
    }

    function logMessage(msg) {
      logMessages.push(msg);
      if (taskLogEl) {
        const li = document.createElement('li');
        li.textContent = msg;
        taskLogEl.appendChild(li);
      }
    }

    const nameInput = document.getElementById('customer-name');
    const emailInput = document.getElementById('customer-email');
    const emailHint = document.getElementById('email-hint');
    const subdomainPreview = document.getElementById('subdomain-preview');
    const next1 = document.getElementById('next1');
    const next2 = document.getElementById('next2');
    const next3 = document.getElementById('next3');
    const next4 = document.getElementById('next4');
    const planSelect = document.getElementById('plan');
    const paymentSelect = document.getElementById('payment');
    const payBtn = document.getElementById('payBtn');
    const paymentInfo = document.getElementById('payment-info');
    const imprintNameInput = document.getElementById('imprint-name');
    const imprintStreetInput = document.getElementById('imprint-street');
    const imprintZipInput = document.getElementById('imprint-zip');
    const imprintCityInput = document.getElementById('imprint-city');
    const imprintEmailInput = document.getElementById('imprint-email');
    const createBtn = document.getElementById('create');
    const adminPassInput = document.getElementById('admin-pass');
    const successDomain = document.getElementById('success-domain');
    const successPass = document.getElementById('success-pass');
    const successInfo = document.getElementById('success-info');
    const successScript = document.getElementById('success-script');
    const successLink = document.getElementById('success-link');

    function updateNext1() {
      next1.disabled =
        data.name === '' ||
        RESERVED_SUBDOMAINS.has(data.subdomain) ||
        data.email === '' ||
        !data.emailConfirmed;
    }

    const params = new URLSearchParams(window.location.search);
    const emailParam = params.get('email');
    const verifiedParam = params.get('verified');
    if (emailParam) {
      data.email = emailParam;
      if (emailInput) emailInput.value = emailParam;
    }
    if (verifiedParam === '1') {
      data.emailConfirmed = true;
      if (emailHint) {
        emailHint.textContent = 'E-Mail bestätigt.';
        emailHint.hidden = false;
      }
    }
    updateNext1();

    async function waitForHttps(url, onProgress) {
      const maxAttempts = 30;
      for (let i = 0; i < maxAttempts; i++) {
        try {
          await fetch(url, { method: 'HEAD', mode: 'no-cors' });
          if (typeof onProgress === 'function') onProgress(1);
          return true;
        } catch (e) {
          logMessage('Noch nicht erreichbar, neuer Versuch in 5s...');
        }
        await new Promise(resolve => setTimeout(resolve, 5000));
        if (typeof onProgress === 'function') onProgress((i + 1) / maxAttempts);
      }
      return false;
    }

    if (loginBtn) {
      loginBtn.addEventListener('click', async () => {
        loginError.hidden = true;
        try {
          const res = await fetch(withBase('/login'), {
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
    }

    nameInput.addEventListener('input', () => {
      data.name = nameInput.value.trim();
      data.subdomain = slugify(data.name);
      subdomainPreview.textContent = data.subdomain || '-';
      updateNext1();
    });

    emailInput.addEventListener('input', () => {
      data.email = emailInput.value.trim();
      data.emailConfirmed = false;
      updateNext1();
    });

    emailInput.addEventListener('blur', async () => {
      if (data.email === '') return;
      try {
        await fetch(withBase('/onboarding/email'), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email: data.email })
        });
        if (emailHint) {
          emailHint.textContent = 'Bestätigungs-E-Mail gesendet.';
          emailHint.hidden = false;
        }
        if (typeof UIkit !== 'undefined') {
          UIkit.notification({ message: 'Bestätigungs-E-Mail gesendet', status: 'primary' });
        }
      } catch (e) {
        // ignore
      }
    });

    next1.addEventListener('click', async () => {
      if (!data.emailConfirmed) {
        if (typeof UIkit !== 'undefined') {
          UIkit.notification({ message: 'Bitte bestätige deine E-Mail-Adresse', status: 'danger' });
        } else {
          alert('Bitte bestätige deine E-Mail-Adresse');
        }
        return;
      }
      try {
        const checkRes = await fetch(withBase('/tenants/' + encodeURIComponent(data.subdomain)), {
          credentials: 'include'
        });
        if (checkRes.ok) {
          if (typeof UIkit !== 'undefined') {
            UIkit.notification({ message: 'Subdomain bereits vergeben', status: 'danger' });
          } else {
            alert('Subdomain bereits vergeben');
          }
          return;
        }
      } catch (e) {
        // ignore errors and continue to next step
      }
      show('step2');
    });

      const allowedPlans = ['starter', 'standard', 'professional'];
      const allowedPayments = ['invoice', 'credit', 'paypal'];

      paymentSelect?.addEventListener('change', () => {
        const credit = paymentSelect.value === 'credit';
        if (payBtn) payBtn.hidden = !credit;
        if (paymentInfo) paymentInfo.hidden = !credit;
      });
      paymentSelect?.dispatchEvent(new Event('change'));

      next2.addEventListener('click', () => {
        const planValue = planSelect?.value || '';
        data.plan = allowedPlans.includes(planValue) ? planValue : '';
        show('step3');
      });

      next3.addEventListener('click', () => {
        const paymentValue = paymentSelect?.value || '';
        data.payment = allowedPayments.includes(paymentValue) ? paymentValue : '';
        document.getElementById('summary-name').textContent = data.name;
        document.getElementById('summary-subdomain').textContent = data.subdomain;
        document.getElementById('summary-plan').textContent = data.plan;
        document.getElementById('summary-payment').textContent = data.payment;
        show('step4');
      });

      payBtn?.addEventListener('click', async () => {
        const res = await fetch(withBase('/onboarding/checkout'), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ plan: data.plan })
        });
        const json = await res.json().catch(() => ({}));
        if (json.url) {
          window.location.href = json.url;
        }
      });

    next4.addEventListener('click', () => {
      data.imprintName = imprintNameInput.value.trim();
      data.imprintStreet = imprintStreetInput.value.trim();
      data.imprintZip = imprintZipInput.value.trim();
      data.imprintCity = imprintCityInput.value.trim();
      data.imprintEmail = imprintEmailInput.value.trim();
      document.getElementById('summary-imprint-name').textContent = data.imprintName;
      document.getElementById('summary-imprint-street').textContent = data.imprintStreet;
      document.getElementById('summary-imprint-zip').textContent = data.imprintZip;
      document.getElementById('summary-imprint-city').textContent = data.imprintCity;
      document.getElementById('summary-imprint-email').textContent = data.imprintEmail;
      show('step5');
    });

    createBtn.addEventListener('click', async () => {
      if (!adminPassInput) {
        return;
      }

      if (data.subdomain === '' || RESERVED_SUBDOMAINS.has(data.subdomain)) {
        if (typeof UIkit !== 'undefined') {
          UIkit.notification({ message: 'Ungültige Subdomain', status: 'danger' });
        }
        return;
      }

      let pass = adminPassInput.value;
      if (pass === '') {
        const charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        const array = new Uint32Array(16);
        crypto.getRandomValues(array);
        pass = Array.from(array, x => charset[x % charset.length]).join('');
        adminPassInput.value = pass;
      }

      data.adminPass = pass;

      show('success');
      initTaskList();
      waitProgress = document.getElementById('wait-progress');
      if (taskLogEl) taskLogEl.innerHTML = '';
      logMessages.length = 0;
      tasks.forEach(t => setTaskStatus(t.key, 'pending'));

      try {
        logMessage('Pruefe Subdomain...');
        const checkRes = await fetch(withBase('/tenants/' + encodeURIComponent(data.subdomain)), {
          credentials: 'include'
        });
        if (checkRes.ok) {
          logMessage('Subdomain bereits vergeben');
          setTaskStatus('tenant', 'failed');
          if (typeof UIkit !== 'undefined') {
            UIkit.notification({ message: 'Subdomain bereits vergeben', status: 'danger' });
          }
          return;
        }

        logMessage('Mandant wird erstellt...');
        const tenantRes = await fetch(withBase('/tenants'), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ uid: data.subdomain, schema: data.subdomain, plan: data.plan || null, billing: data.payment || null })
        });
        if (!tenantRes.ok) {
          const text = await tenantRes.text();
          if (tenantRes.status === 409) {
            logMessage('Subdomain bereits vergeben');
            if (typeof UIkit !== 'undefined') {
              UIkit.notification({ message: 'Subdomain bereits vergeben', status: 'danger' });
            }
          } else {
            logMessage('Fehler Mandant: ' + text);
          }
          setTaskStatus('tenant', 'failed');
          throw new Error(text || 'tenant');
        }
        setTaskStatus('tenant', 'done');
        logMessage('Mandant erstellt');

        logMessage('Importiere Standardinhalte...');
        const importRes = await fetch(withBase('/restore-default'), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ schema: data.subdomain })
        });
        if (!importRes.ok) {
          const text = await importRes.text();
          logMessage('Fehler Import: ' + text);
          setTaskStatus('import', 'failed');
          throw new Error(text || 'import');
        }
        setTaskStatus('import', 'done');
        logMessage('Standardinhalte importiert');

        logMessage('Setze Admin-Passwort...');
        const userRes = await fetch(withBase('/tenant-admin'), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ schema: data.subdomain, password: data.adminPass })
        });
        if (!userRes.ok) {
          const text = await userRes.text();
          logMessage('Fehler Benutzer: ' + text);
          setTaskStatus('user', 'failed');
          throw new Error(text || 'user');
        }
        setTaskStatus('user', 'done');
        logMessage('Admin-Passwort gesetzt');

        if (reloadToken) {
          logMessage('Proxy wird neu geladen...');
          const reloadRes = await fetch(withBase('/nginx-reload'), {
            method: 'POST',
            headers: { 'X-Token': reloadToken },
            credentials: 'include'
          });
          const json = await reloadRes.json().catch(() => ({}));
          if (!reloadRes.ok) {
            logMessage('Fehler Reload: ' + (json.details || json.error || ''));
            setTaskStatus('reload', 'failed');
            throw new Error(json.error || 'reload');
          }
          setTaskStatus('reload', 'done');
          logMessage(json.status || 'Proxy neu geladen');
        }

        logMessage('Container wird gestartet und SSL-Zertifikat angefordert...');
        const boardRes = await fetch(
          withBase('/api/tenants/' + encodeURIComponent(data.subdomain) + '/onboard'),
          {
            method: 'POST',
            credentials: 'include'
          }
        );
        const boardJson = await boardRes.json().catch(() => ({}));
        if (!boardRes.ok) {
          logMessage('Fehler Onboard: ' + (boardJson.details || boardJson.error || ''));
          setTaskStatus('ssl', 'failed');
          throw new Error(boardJson.error || 'onboard');
        }
        setTaskStatus('ssl', 'done');
        logMessage(boardJson.status || 'Container gestartet');

        if (successDomain) {
          successDomain.textContent = data.subdomain + '.' + mainDomain;
          successDomain.hidden = false;
        }
        if (successPass) {
          successPass.textContent = 'Ihr Admin-Login lautet: admin / ' + data.adminPass;
          successPass.hidden = false;
        }

        if (successScript) {
          successScript.textContent =
            'Die Subdomain wird gestartet und das SSL-Zertifikat beantragt.';
          successScript.hidden = false;
        }

        if (successLink) {
          successLink.href = 'https://' + data.subdomain + '.' + mainDomain;
          successLink.textContent = 'Warte auf Start...';
          successLink.classList.add('uk-disabled');
          successLink.hidden = false;
          successLink.addEventListener('click', e => {
            if (successLink.classList.contains('uk-disabled')) e.preventDefault();
          });
        }

        setTaskStatus('wait', 'pending');
        logMessage('Warte bis die Seite aktiv ist...');
        if (successInfo) {
          successInfo.textContent = 'Die Subdomain wird gestartet. Bitte warten...';
        }
        if (typeof UIkit !== 'undefined') {
          UIkit.notification({ message: 'Warte auf Start der Instanz', status: 'primary' });
        }
        const ready = successLink
          ? await waitForHttps(successLink.href, p => {
              if (waitProgress) waitProgress.value = p * 100;
            })
          : false;
        if (ready) {
          logMessage('Subdomain erreichbar');
          successLink.classList.remove('uk-disabled');
          successLink.textContent = 'Zu Ihrem QuizRace';
          if (successInfo) {
            successInfo.textContent = 'Die Subdomain ist jetzt erreichbar.';
          }
          setTaskStatus('wait', 'done');
          if (typeof UIkit !== 'undefined') {
            UIkit.notification({ message: 'Instanz ist bereit', status: 'success' });
          }
        } else {
          logMessage('Subdomain nach Wartezeit nicht erreichbar');
          setTaskStatus('wait', 'failed');
          if (typeof UIkit !== 'undefined') {
            UIkit.notification({ message: 'Instanz ist noch nicht erreichbar', status: 'warning' });
          }
        }
      } catch (err) {
        logMessage('Fehler beim Anlegen: ' + (err.message || err));
        if (typeof UIkit !== 'undefined') {
          UIkit.notification({ message: 'Fehler beim Anlegen', status: 'danger' });
        } else {
          alert('Fehler beim Anlegen');
        }
      }
    });
  });
})();
