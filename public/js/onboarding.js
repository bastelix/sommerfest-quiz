document.addEventListener('DOMContentLoaded', () => {
  const step1 = document.getElementById('step1');
  const step2 = document.getElementById('step2');
  const step3 = document.getElementById('step3');
  const step4 = document.getElementById('step4');
  const step5 = document.getElementById('step5');
  const imprintNameInput = document.getElementById('imprintName');
  const imprintStreetInput = document.getElementById('imprintStreet');
  const imprintZipInput = document.getElementById('imprintZip');
  const imprintCityInput = document.getElementById('imprintCity');
  const imprintEmailInput = document.getElementById('imprintEmail');
  const useAsImprintCheckbox = document.getElementById('useAsImprint');
  const saveImprintBtn = document.getElementById('saveImprint');
  const emailInput = document.getElementById('email');
  const sendEmailBtn = document.getElementById('sendEmail');
  const emailStatus = document.getElementById('emailStatus');
  const subdomainInput = document.getElementById('subdomain');
  const subdomainPreview = document.getElementById('subdomainPreview');
  const subdomainStatus = document.getElementById('subdomainStatus');
  const saveSubdomainBtn = document.getElementById('saveSubdomain');
  const verifiedHint = document.getElementById('verifiedHint');
  const basePath = window.basePath || '';
  const withBase = p => basePath + p;
  const timelineSteps = document.querySelectorAll('.timeline-step');
  const restartBtn = document.getElementById('restartOnboarding');
  const hostWhitelist = ['stripe.com', 'payments.stripe.com'];
  let tenantFinalizing = false;


  function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  function isValidSubdomain(subdomain) {
    return /^[a-z0-9-]{3,63}$/.test(subdomain);
  }

  function escape(url) {
    return encodeURI(url);
  }

  function isAllowed(url, allowedPaths = []) {
    try {
      const parsed = new URL(url, window.location.origin);
      const domains = [];
      if (window.location.hostname) domains.push(window.location.hostname.toLowerCase());
      if (window.mainDomain) domains.push(window.mainDomain.toLowerCase());
      const host = parsed.hostname.toLowerCase();
      const domainMatch = domains.some(d => host === d || host.endsWith('.' + d));
      const whitelistMatch = hostWhitelist.some(d => host === d || host.endsWith('.' + d));
      const domainOk = parsed.protocol === 'https:' && (domainMatch || whitelistMatch);
      const pathOk = !allowedPaths.length || allowedPaths.some(p => parsed.pathname.startsWith(p));
      return domainOk && pathOk;
    } catch (e) {
      return false;
    }
  }

  const params = new URLSearchParams(window.location.search);
  let sessionId = params.get('session_id');
  const stepParam = params.get('step');
  const emailParam = params.get('email');
  const storedEmail = localStorage.getItem('onboard_email') || '';
  let subdomainStored = localStorage.getItem('onboard_subdomain') || '';
  const imprintDone = localStorage.getItem('onboard_imprint_done') === '1';
  const storedImprintName = localStorage.getItem('onboard_imprint_name') || '';
  const storedImprintStreet = localStorage.getItem('onboard_imprint_street') || '';
  const storedImprintZip = localStorage.getItem('onboard_imprint_zip') || '';
  const storedImprintCity = localStorage.getItem('onboard_imprint_city') || '';
  const storedImprintEmail = localStorage.getItem('onboard_imprint_email') || '';
  let verified = params.get('verified') === '1' || localStorage.getItem('onboard_verified') === '1';
  if (params.get('verified') === '1') {
    localStorage.setItem('onboard_verified', '1');
    verified = true;
  }

  if (emailInput) {
    if (emailParam) {
      emailInput.value = emailParam;
    } else if (storedEmail) {
      emailInput.value = storedEmail;
    }
  }

  if (imprintNameInput) imprintNameInput.value = storedImprintName;
  if (imprintStreetInput) imprintStreetInput.value = storedImprintStreet;
  if (imprintZipInput) imprintZipInput.value = storedImprintZip;
  if (imprintCityInput) imprintCityInput.value = storedImprintCity;
  if (imprintEmailInput) imprintEmailInput.value = storedImprintEmail;
  if (useAsImprintCheckbox) {
    useAsImprintCheckbox.checked = localStorage.getItem('onboard_use_as_imprint') === '1';
  }

  let currentStep = 1;
  function showStep(step) {
    currentStep = step;
    step1.hidden = step !== 1;
    step2.hidden = step !== 2;
    if (step3) step3.hidden = step !== 3;
    if (step4) step4.hidden = step !== 4;
    if (step5) step5.hidden = step !== 5;
    if (verifiedHint) verifiedHint.hidden = !(step === 2 && verified);
    if (subdomainPreview && subdomainStored) {
      subdomainPreview.textContent = subdomainStored;
    }
    timelineSteps.forEach(el => {
      const s = parseInt(el.dataset.step, 10);
      el.classList.toggle('active', s === step);
      el.classList.toggle('completed', s < step);
    });
  }
  if (verified) {
    currentStep = subdomainStored ? (imprintDone ? 4 : 3) : 2;
  }
  if (stepParam === '4' || stepParam === '5') {
    const s4 = document.querySelector('.timeline-step[data-step="4"]');
    if (s4) s4.classList.remove('inactive');
    if (stepParam === '5') {
      const s5 = document.querySelector('.timeline-step[data-step="5"]');
      if (s5) s5.classList.remove('inactive');
      showStep(5);
    } else {
      showStep(4);
    }
  } else {
    showStep(currentStep);
  }

  if (sendEmailBtn) {
    sendEmailBtn.addEventListener('click', async () => {
      const email = emailInput.value.trim();
      if (!email) return;
      if (!isValidEmail(email)) {
        emailStatus.textContent = 'Ungültige E-Mail-Adresse.';
        emailStatus.hidden = false;
        return;
      }
      const res = await fetch(withBase('/onboarding/email'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.csrfToken || '',
          'X-Requested-With': 'fetch'
        },
        body: JSON.stringify({ email })
      });
      if (res.ok) {
        localStorage.setItem('onboard_email', email);
        emailStatus.textContent = 'E-Mail versendet. Bitte prüfe dein Postfach.';
        emailStatus.hidden = false;
      }
    });
  }

  if (subdomainInput) {
    subdomainInput.addEventListener('input', () => {
      subdomainPreview.textContent = subdomainInput.value.trim().toLowerCase();
    });
  }

    if (saveSubdomainBtn) {
      saveSubdomainBtn.addEventListener('click', async () => {
        const subdomain = subdomainInput.value.trim().toLowerCase();
        if (!subdomain) return;
        if (subdomainStatus) {
          subdomainStatus.textContent = '';
          subdomainStatus.hidden = true;
          subdomainStatus.classList.remove('uk-text-danger', 'uk-text-success');
        }
        subdomainInput.classList.remove('uk-form-danger', 'uk-form-success');
        if (!isValidSubdomain(subdomain)) {
          if (subdomainStatus) {
            subdomainStatus.textContent = 'Ungültige Subdomain.';
            subdomainStatus.classList.add('uk-text-danger');
            subdomainStatus.hidden = false;
          }
          subdomainInput.classList.add('uk-form-danger');
          return;
        }
        const res = await fetch(withBase('/onboarding/tenants/' + encodeURIComponent(subdomain)), {
          credentials: 'same-origin',
          headers: { 'X-Requested-With': 'fetch' }
        });
        if (res.ok) {
          if (subdomainStatus) {
            subdomainStatus.textContent = 'Subdomain bereits vergeben.';
            subdomainStatus.classList.add('uk-text-danger');
            subdomainStatus.hidden = false;
          }
          subdomainInput.classList.add('uk-form-danger');
          return;
        }
        if (res.status !== 404) {
          if (subdomainStatus) {
            subdomainStatus.textContent = 'Fehler bei der Prüfung der Subdomain.';
            subdomainStatus.classList.add('uk-text-danger');
            subdomainStatus.hidden = false;
          }
          subdomainInput.classList.add('uk-form-danger');
          return;
        }
        if (subdomainStatus) {
          subdomainStatus.textContent = 'Subdomain verfügbar.';
          subdomainStatus.classList.add('uk-text-success');
          subdomainStatus.hidden = false;
        }
        subdomainInput.classList.add('uk-form-success');
        localStorage.setItem('onboard_subdomain', subdomain);
        subdomainStored = subdomain;
        showStep(3);
      });
    }

  if (saveImprintBtn) {
    saveImprintBtn.addEventListener('click', () => {
      const name = imprintNameInput.value.trim();
      const street = imprintStreetInput.value.trim();
      const zip = imprintZipInput.value.trim();
      const city = imprintCityInput.value.trim();
      const email = imprintEmailInput.value.trim();
      if (!name || !street || !zip || !city || !isValidEmail(email)) {
        alert('Bitte alle Felder ausfüllen.');
        return;
      }
      localStorage.setItem('onboard_imprint_name', name);
      localStorage.setItem('onboard_imprint_street', street);
      localStorage.setItem('onboard_imprint_zip', zip);
      localStorage.setItem('onboard_imprint_city', city);
      localStorage.setItem('onboard_imprint_email', email);
      localStorage.setItem('onboard_use_as_imprint', useAsImprintCheckbox.checked ? '1' : '0');
      localStorage.setItem('onboard_imprint_done', '1');
      const s4 = document.querySelector('.timeline-step[data-step="4"]');
      if (s4) s4.classList.remove('inactive');
      const url = new URL(window.location);
      url.searchParams.set('step', '4');
      window.history.replaceState({}, '', url);
      showStep(4);
    });
  }
  if (timelineSteps.length) {
    timelineSteps.forEach(el => {
      if (el.classList.contains('inactive')) {
        return;
      }
      el.addEventListener('click', () => {
        const target = parseInt(el.dataset.step, 10);
        if (target === 1) {
          showStep(1);
        } else if (target === 2 && verified) {
          showStep(2);
        } else if (target === 3 && verified && subdomainStored) {
          showStep(3);
        } else if (target === 4 && verified && subdomainStored && imprintDone) {
          showStep(4);
        }
      });
    });
  }

  if (restartBtn) {
    restartBtn.addEventListener('click', () => {
      localStorage.removeItem('onboard_subdomain');
      localStorage.removeItem('onboard_plan');
      localStorage.removeItem('onboard_email');
      localStorage.removeItem('onboard_verified');
      localStorage.removeItem('onboard_imprint_name');
      localStorage.removeItem('onboard_imprint_street');
      localStorage.removeItem('onboard_imprint_zip');
      localStorage.removeItem('onboard_imprint_city');
      localStorage.removeItem('onboard_imprint_email');
      localStorage.removeItem('onboard_use_as_imprint');
      localStorage.removeItem('onboard_imprint_done');
      const onboardingPath = withBase('/onboarding');
      if (isAllowed(onboardingPath, [onboardingPath])) {
        window.location.href = escape(onboardingPath);
      } else {
        console.error('Blocked redirect to untrusted URL:', onboardingPath);
      }
    });
  }

  async function finalizeTenant() {
      if (tenantFinalizing) { return; }
      tenantFinalizing = true;
      const subdomain = localStorage.getItem('onboard_subdomain') || '';
      const email = localStorage.getItem('onboard_email') || '';
      let plan = '';
      if (sessionId) {
        try {
          const res = await fetch(withBase('/onboarding/checkout/' + encodeURIComponent(sessionId)), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'fetch' }
          });
          if (!res.ok) {
            throw new Error('checkout');
          }
          const data = await res.json();
          if (!data.paid) {
            throw new Error('not paid');
          }
          plan = data.plan || '';
          if (!plan) {
            throw new Error('no plan');
          }
        } catch (e) {
          const msg = e.message === 'no plan'
            ? 'Es wurde kein Tarif übermittelt.'
            : 'Fehler bei der Zahlungsprüfung.';
          alert(msg);
          tenantFinalizing = false;
          return;
        }
      }
      const imprintName = localStorage.getItem('onboard_imprint_name') || '';
      const imprintStreet = localStorage.getItem('onboard_imprint_street') || '';
      const imprintZip = localStorage.getItem('onboard_imprint_zip') || '';
      const imprintCity = localStorage.getItem('onboard_imprint_city') || '';
      const imprintEmail = localStorage.getItem('onboard_imprint_email') || '';
      const useImprint = localStorage.getItem('onboard_use_as_imprint') === '1';
    const billingInfo = JSON.stringify({
      name: imprintName,
      street: imprintStreet,
      zip: imprintZip,
      city: imprintCity,
      email: imprintEmail
    });
    const taskStatus = document.getElementById('task-status');
    const taskLog = document.getElementById('task-log');
    const taskLogDetails = document.getElementById('task-log-details');

    const tasks = [
      { id: 'create', label: 'Mandant anlegen' },
      { id: 'import', label: 'Inhalte importieren' },
      { id: 'proxy', label: 'Proxy neu laden' },
      { id: 'ssl', label: 'SSL aktivieren' },
      { id: 'wait', label: 'Warten auf Verfügbarkeit' }
    ];
    const taskEls = {};
    const taskMap = {};

    const addLog = msg => {
      if (!taskLog) return;
      const li = document.createElement('li');
      li.textContent = msg;
      taskLog.appendChild(li);
      if (taskLogDetails) {
        taskLogDetails.open = true;
      }
    };

    const start = id => {
      const entry = taskEls[id];
      if (!entry) return;
      entry.spinner.hidden = false;
      addLog(taskMap[id] + ' …');
    };

    const mark = (id, ok) => {
      const entry = taskEls[id];
      if (!entry) return;
      entry.spinner.remove();
      const status = document.createElement('span');
      status.textContent = ok ? ' ✓' : ' ✗';
      entry.li.appendChild(status);
      addLog(taskMap[id] + (ok ? ' erledigt' : ' fehlgeschlagen'));
    };

    if (taskStatus) {
      tasks.forEach(t => {
        const li = document.createElement('li');
        li.id = 'task-' + t.id;
        li.textContent = t.label;
        const spinner = document.createElement('span');
        spinner.setAttribute('uk-spinner', 'ratio: 0.5');
        spinner.classList.add('uk-margin-small-left');
        spinner.hidden = true;
        li.appendChild(spinner);
        taskStatus.appendChild(li);
        taskEls[t.id] = { li, spinner };
        taskMap[t.id] = t.label;
      });
    }

    if (!isValidSubdomain(subdomain) || !isValidEmail(email) || !plan) {
      mark('create', false);
      addLog('Ungültige Daten für die Registrierung.');
      alert('Ungültige Daten für die Registrierung.');
      tenantFinalizing = false;
      return;
    }

    const wait = ms => new Promise(r => setTimeout(r, ms));

    const onboardTenant = async slug => {
      const res = await fetch(withBase('/api/tenants/' + encodeURIComponent(slug) + '/onboard'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.csrfToken || '',
          'X-Requested-With': 'fetch'
        },
        body: JSON.stringify({ mode: 'full' })
      });
      if (res.status === 401 || res.status === 403 || res.redirected) {
        throw new Error('unauthorized');
      }
      const body = await res.text();
      let data;
      try {
        data = JSON.parse(body);
      } catch (_) {
        data = undefined;
      }
      if (res.status === 202 && (!body || !data)) {
        addLog('Server akzeptierte die Anfrage, lieferte jedoch keine Rückmeldung.');
        return false;
      }
      if (!res.ok || !data || data.status !== 'queued') {
        const msg = data && data.error ? data.error : body || 'onboard';
        addLog('Fehler beim Onboarding: ' + msg);
        throw new Error(msg);
      }
      addLog('Onboarding gestartet …');
      return true;
    };

    const waitForTenant = async slug => {
      // check HTTP endpoint first; HTTPS might be unavailable until certificates are issued
      const httpUrl = `http://${slug}.${window.mainDomain}/healthz`;
      const httpsUrl = `https://${slug}.${window.mainDomain}/healthz`;
      // allow longer waiting periods for SSL certificate issuance
      const attempts = Number(window.waitForTenantRetries ?? 180);
      const delay = Number(window.waitForTenantDelay ?? 2000);
      for (let i = 0; i < attempts; i++) {
        try {
          // initial HTTP probe
          const res = await fetch(httpUrl, {
            headers: { Accept: 'application/json' },
            credentials: 'omit'
          });
          if (res.ok && !res.redirected) {
            const ct = res.headers.get('Content-Type') || '';
            if (ct.includes('application/json')) {
              const data = await res.json();
              if (data.status === 'ok') {
                try {
                  // verify HTTPS once HTTP responds
                  const secure = await fetch(httpsUrl, {
                    headers: { Accept: 'application/json' },
                    credentials: 'omit'
                  });
                  if (secure.ok && !secure.redirected) {
                    const ct2 = secure.headers.get('Content-Type') || '';
                    if (ct2.includes('application/json')) {
                      const data2 = await secure.json();
                      if (data2.status === 'ok') return;
                    }
                  }
                } catch (e) {
                  if (e instanceof Error && /certificate|tls|ssl/i.test(e.message)) {
                    addLog('Zertifikat noch nicht verfügbar');
                  }
                }
              }
            }
          }
        } catch (e) {
          if (e instanceof Error && /certificate|tls|ssl/i.test(e.message)) {
            addLog('Zertifikat noch nicht verfügbar');
          }
        }
        addLog('Warten auf Tenant …');
        await wait(delay);
      }
      throw new Error('timeout');
    };

      try {
        start('create');
        const tRes = await fetch(withBase('/tenants'), {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.csrfToken || '',
            'X-Requested-With': 'fetch'
          },
          body: JSON.stringify({
            uid: subdomain,
            schema: subdomain,
            plan,
            billing: billingInfo,
            email: useImprint ? imprintEmail : null,
            imprint_name: useImprint ? imprintName : null,
            imprint_street: useImprint ? imprintStreet : null,
          imprint_zip: useImprint ? imprintZip : null,
          imprint_city: useImprint ? imprintCity : null
        })
      });

      if (!tRes.ok) {
        mark('create', false);
        let msg = '';
        switch (tRes.status) {
          case 400:
            msg = 'Ungültige oder unvollständige Daten.';
            break;
          case 403:
            msg = 'Zugriff verweigert – Domäne oder Service-Login prüfen.';
            break;
          case 409:
            msg = 'Mandant existiert bereits.';
            break;
          default:
            try {
              const ct = tRes.headers.get('Content-Type');
              if (ct && ct.includes('application/json')) {
                const data = await tRes.json();
                msg = data.error || '';
              } else {
                msg = await tRes.text();
              }
            } catch (_) {
              // ignore
            }
            if (!msg) {
              msg = 'Mandant anlegen fehlgeschlagen';
            }
        }
        throw new Error(msg);
      }
      mark('create', true);
      start('import');
      const onboarded = await onboardTenant(subdomain);
      if (!onboarded) {
        mark('import', false);
        throw new Error('Onboarding konnte nicht gestartet werden.');
      }
      mark('import', true);
      start('proxy');
      await wait(0);
      mark('proxy', true);
      start('ssl');
      await wait(0);
      mark('ssl', true);
      start('wait');
      await waitForTenant(subdomain);
      mark('wait', true);

      await fetch(withBase('/tenant-welcome'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.csrfToken || '',
          'X-Requested-With': 'fetch'
        },
        body: JSON.stringify({ schema: subdomain, email })
      });

      localStorage.removeItem('onboard_subdomain');
      localStorage.removeItem('onboard_plan');
      localStorage.removeItem('onboard_email');
      localStorage.removeItem('onboard_verified');
      localStorage.removeItem('onboard_imprint_name');
      localStorage.removeItem('onboard_imprint_street');
      localStorage.removeItem('onboard_imprint_zip');
      localStorage.removeItem('onboard_imprint_city');
      localStorage.removeItem('onboard_imprint_email');
      localStorage.removeItem('onboard_use_as_imprint');
      localStorage.removeItem('onboard_imprint_done');
      const targetUrl = `https://${subdomain}.${window.mainDomain}/`;
      if (isAllowed(targetUrl)) {
        window.location.href = escape(targetUrl);
      } else {
        console.error('Blocked redirect to untrusted URL:', targetUrl);
      }
      return;
    } catch (e) {
      if (taskEls.wait && !taskEls.wait.spinner.hidden && !taskEls.wait.li.querySelector('span:not([uk-spinner])')) {
        mark('wait', false);
      }
      const msg = e.message === 'timeout'
        ? 'Mandant wurde erstellt, ist jedoch noch nicht verfügbar. Bitte später erneut versuchen.'
        : 'Fehler: ' + e.message;
      addLog(msg);
      alert(msg);
      tenantFinalizing = false;
    }
  }

  window.addEventListener('message', event => {
    try {
      const originHost = new URL(event.origin).hostname;
      const allowed = hostWhitelist.some(d => originHost === d || originHost.endsWith('.' + d));
      if (!allowed) return;
    } catch (_) {
      return;
    }
    const data = event.data || {};
    if (data.type !== 'checkout.session.completed') return;
    const url = new URL(window.location);
    sessionId = data.sessionId || data.session_id || '';
    if (sessionId) {
      const table = document.getElementById('pricingTable');
      if (table) table.remove();
      const s5 = document.querySelector('.timeline-step[data-step="5"]');
      if (s5) s5.classList.remove('inactive');
      url.searchParams.set('session_id', sessionId);
      url.searchParams.set('step', '5');
      window.history.replaceState({}, '', url);
      showStep(5);
      finalizeTenant();
    } else {
      const msg = 'Fehler: Keine Session-ID erhalten.';
      addLog(msg);
      alert(msg);
      url.searchParams.set('step', '4');
      window.history.replaceState({}, '', url);
      showStep(4);
    }
  });

  if (sessionId) {
      const subdomain = localStorage.getItem('onboard_subdomain') || '';
      const email = localStorage.getItem('onboard_email') || '';

      if (isValidSubdomain(subdomain) && isValidEmail(email)) {
        const s5 = document.querySelector('.timeline-step[data-step="5"]');
        if (s5) s5.classList.remove('inactive');
        const url = new URL(window.location);
        url.searchParams.set('step', '5');
        window.history.replaceState({}, '', url);
        showStep(5);
        finalizeTenant();
      } else {
        const url = new URL(window.location);
        url.searchParams.delete('session_id');
        let step = 1;
        if (isValidEmail(email)) {
          step = isValidSubdomain(subdomain) ? 3 : 2;
        }
        url.searchParams.set('step', String(step));
        window.history.replaceState({}, '', url);
        showStep(step);
      }
    }
});

