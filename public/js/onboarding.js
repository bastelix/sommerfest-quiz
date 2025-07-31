/* global UIkit */
(function () {
  const steps = ['login', 'step1', 'step2', 'step3', 'step4', 'success'];
  const data = {
    name: '',
    subdomain: '',
    plan: '',
    payment: '',
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

  const RESERVED_SUBDOMAINS = new Set(['public']);

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
    const tasks = [
      { key: 'tenant', label: 'Mandant erstellen' },
      { key: 'import', label: 'Standardinhalte importieren' },
      { key: 'user', label: 'Admin-Passwort setzen' }
    ];
    if (reloadToken) {
      tasks.push({ key: 'reload', label: 'Proxy neu laden' });
    }
    tasks.push({ key: 'ssl', label: 'Container starten (SSL)' });

    function initTaskList() {
      if (!taskStatusEl || taskStatusEl.children.length > 0) return;
      tasks.forEach(t => {
        const li = document.createElement('li');
        li.id = `task-${t.key}`;
        li.innerHTML = `<span class="status">⏳</span> ${t.label}`;
        taskStatusEl.appendChild(li);
      });
    }

    function setTaskStatus(key, status) {
      const li = document.getElementById(`task-${key}`);
      if (!li) return;
      const span = li.querySelector('.status');
      if (!span) return;
      if (status === 'done') {
        span.textContent = '✓';
        li.classList.add('uk-text-success');
      } else if (status === 'failed') {
        span.textContent = '✗';
        li.classList.add('uk-text-danger');
      } else {
        span.textContent = '⏳';
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
    const subdomainPreview = document.getElementById('subdomain-preview');
    const next1 = document.getElementById('next1');
    const next2 = document.getElementById('next2');
    const next3 = document.getElementById('next3');
    const createBtn = document.getElementById('create');
    const adminPassInput = document.getElementById('admin-pass');
    const successDomain = document.getElementById('success-domain');
    const successPass = document.getElementById('success-pass');
    const successInfo = document.getElementById('success-info');
    const successScript = document.getElementById('success-script');

    async function waitForHttps(url) {
      for (let i = 0; i < 30; i++) {
        try {
          await fetch(url, { method: 'HEAD', mode: 'no-cors' });
          return true;
        } catch (e) {
          // ignore errors until certificate is ready
        }
        await new Promise(resolve => setTimeout(resolve, 5000));
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
      next1.disabled = data.name === '' || RESERVED_SUBDOMAINS.has(data.subdomain);
    });

    next1.addEventListener('click', async () => {
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
          body: JSON.stringify({ uid: data.subdomain, schema: data.subdomain })
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
          credentials: 'include'
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

        const successEl = document.getElementById('success');
        if (successEl && !document.getElementById('success-link')) {
          const link = document.createElement('a');
          link.id = 'success-link';
          link.className = 'uk-button uk-button-primary uk-margin-top';
          link.href = 'https://' + data.subdomain + '.' + mainDomain;
          link.textContent = 'Zu Ihrem QuizRace';
          link.hidden = true;
          successEl.appendChild(link);
          const sslReady = await waitForHttps(link.href);
          if (sslReady) {
            link.hidden = false;
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
