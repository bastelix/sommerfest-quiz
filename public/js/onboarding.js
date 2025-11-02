document.addEventListener('DOMContentLoaded', async () => {
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
  let sessionData = {};
  try {
    const res = await fetch(withBase('/onboarding/session'), {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'fetch' }
    });
    if (res.ok) {
      sessionData = await res.json();
    }
  } catch (e) {}

  const storedEmail = sessionData.email || '';
  let subdomainStored = sessionData.subdomain || '';
  const imprintData = sessionData.imprint || {};
  const imprintDone = imprintData.done === true;
  const storedImprintName = imprintData.name || '';
  const storedImprintStreet = imprintData.street || '';
  const storedImprintZip = imprintData.zip || '';
  const storedImprintCity = imprintData.city || '';
  const storedImprintEmail = imprintData.email || '';
  let verified = params.get('verified') === '1' || sessionData.verified === true;
  if (params.get('verified') === '1') {
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
    useAsImprintCheckbox.checked = imprintData.use_as_imprint === true;
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

  if (stepParam === '5' && !sessionId) {
    alert('Stripe-Sitzung nicht gefunden – bitte Zahlung erneut abschließen');
    const url = new URL(window.location);
    url.searchParams.delete('session_id');
    url.searchParams.set('step', '4');
    window.history.replaceState({}, '', url);
    showStep(4);
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
        await fetch(withBase('/onboarding/session'), {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.csrfToken || '',
            'X-Requested-With': 'fetch'
          },
          body: JSON.stringify({ subdomain })
        });
        sessionData.subdomain = subdomain;
        subdomainStored = subdomain;
        showStep(3);
      });
    }

  if (saveImprintBtn) {
    saveImprintBtn.addEventListener('click', async () => {
      const name = imprintNameInput.value.trim();
      const street = imprintStreetInput.value.trim();
      const zip = imprintZipInput.value.trim();
      const city = imprintCityInput.value.trim();
      const email = imprintEmailInput.value.trim();
      if (!name || !street || !zip || !city || !isValidEmail(email)) {
        alert('Bitte alle Felder ausfüllen.');
        return;
      }
      await fetch(withBase('/onboarding/session'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.csrfToken || '',
          'X-Requested-With': 'fetch'
        },
        body: JSON.stringify({
          imprint: {
            name,
            street,
            zip,
            city,
            email,
            use_as_imprint: useAsImprintCheckbox.checked,
            done: true
          }
        })
      });
      sessionData.imprint = {
        name,
        street,
        zip,
        city,
        email,
        use_as_imprint: useAsImprintCheckbox.checked,
        done: true
      };
      subdomainStored = sessionData.subdomain || subdomainStored;
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
    restartBtn.addEventListener('click', async () => {
      await fetch(withBase('/onboarding/session'), {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
          'X-CSRF-Token': window.csrfToken || '',
          'X-Requested-With': 'fetch'
        }
      });
      sessionData = {};
      const onboardingPath = withBase('/onboarding');
      if (isAllowed(onboardingPath, [onboardingPath])) {
        window.location.href = escape(onboardingPath);
      } else {
        console.error('Blocked redirect to untrusted URL:', onboardingPath);
      }
    });
  }

  async function finalizeTenant() {
      if (!sessionId || tenantFinalizing) { return; }
      tenantFinalizing = true;
      const subdomain = sessionData.subdomain || '';
      const email = sessionData.email || '';
      let plan = '';
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
          : 'Zahlung nicht bestätigt – bitte kontaktiere uns.';
        alert(msg);
        await fetch(withBase('/onboarding/checkout/' + encodeURIComponent(sessionId) + '/cancel'), {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'X-CSRF-Token': window.csrfToken || '',
            'X-Requested-With': 'fetch'
          }
        });
        const url = new URL(window.location);
        url.searchParams.delete('session_id');
        url.searchParams.set('step', '4');
        window.history.replaceState({}, '', url);
        showStep(4);
        tenantFinalizing = false;
        return;
      }
      const imprintName = (sessionData.imprint && sessionData.imprint.name) || '';
      const imprintStreet = (sessionData.imprint && sessionData.imprint.street) || '';
      const imprintZip = (sessionData.imprint && sessionData.imprint.zip) || '';
      const imprintCity = (sessionData.imprint && sessionData.imprint.city) || '';
      const imprintEmail = (sessionData.imprint && sessionData.imprint.email) || '';
      const useImprint = !!(sessionData.imprint && sessionData.imprint.use_as_imprint);
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
    const copyTaskLogBtn = document.getElementById('copyTaskLog');

    const tasks = [
      { id: 'create', label: 'Mandant anlegen' },
      { id: 'import', label: 'Inhalte importieren' },
      { id: 'proxy', label: 'Proxy neu laden' },
      { id: 'ssl', label: 'SSL aktivieren' },
      { id: 'wait', label: 'Warten auf Verfügbarkeit' }
    ];
    const taskEls = {};
    const taskMap = {};

    const updateCopyButtonVisibility = () => {
      if (!copyTaskLogBtn || !taskLog) return;
      copyTaskLogBtn.hidden = taskLog.children.length === 0;
    };

    const addLog = msg => {
      if (!taskLog) return;
      const li = document.createElement('li');
      li.textContent = msg;
      taskLog.appendChild(li);
      taskLog.scrollTop = taskLog.scrollHeight;
      updateCopyButtonVisibility();
      if (taskLogDetails) {
        taskLogDetails.open = true;
      }
    };

    if (copyTaskLogBtn && taskLog) {
      updateCopyButtonVisibility();
      const defaultLabel = copyTaskLogBtn.textContent;

      const showFeedback = (text, delay = 2000) => {
        copyTaskLogBtn.textContent = text;
        setTimeout(() => {
          copyTaskLogBtn.textContent = defaultLabel;
        }, delay);
      };

      copyTaskLogBtn.addEventListener('click', async () => {
        const items = Array.from(taskLog.querySelectorAll('li')).map(li => li.textContent).filter(Boolean);
        if (!items.length) {
          return;
        }
        const text = items.join('\n');
        let success = false;
        if (typeof navigator !== 'undefined' && navigator.clipboard && navigator.clipboard.writeText) {
          try {
            await navigator.clipboard.writeText(text);
            success = true;
          } catch (e) {
            success = false;
          }
        }
        if (!success) {
          const textarea = document.createElement('textarea');
          textarea.value = text;
          textarea.setAttribute('readonly', '');
          textarea.style.position = 'fixed';
          textarea.style.top = '-9999px';
          document.body.appendChild(textarea);
          textarea.select();
          try {
            success = document.execCommand('copy');
          } catch (e) {
            success = false;
          }
          document.body.removeChild(textarea);
        }
        if (success) {
          showFeedback('Kopiert!');
        } else {
          showFeedback('Kopieren fehlgeschlagen', 2500);
        }
      });
    }

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
      if (!res.ok) {
        const msg = data && data.error ? data.error : body || 'onboard';
        addLog('Fehler beim Onboarding: ' + msg);
        throw new Error(msg);
      }
      if (data && data.log) {
        addLog(data.log);
      }
      addLog('Onboarding abgeschlossen …');
      return data || { status: 'completed' };
    };

    const computeTenantOrigin = slug => {
      const baseDomain = (window.mainDomain || window.location.hostname || '').trim();
      if (!baseDomain) {
        return null;
      }

      const matchesCurrentHost = baseDomain.toLowerCase() === window.location.hostname.toLowerCase();
      const protocol = matchesCurrentHost && window.location.protocol === 'http:'
        ? 'http'
        : 'https';
      const port = matchesCurrentHost && window.location.port ? `:${window.location.port}` : '';

      return `${protocol}://${slug}.${baseDomain}${port}`;
    };

    const waitForTenant = async slug => {
      const origin = computeTenantOrigin(slug);
      if (!origin) {
        addLog('Überspringe Verfügbarkeitsprüfung – keine Domain konfiguriert.');
        return;
      }

      // directly probe endpoint and retry on transient errors until certificates are issued
      const url = `${origin}/healthz`;
      // allow longer waiting periods for SSL certificate issuance
      const attempts = Number(window.waitForTenantRetries ?? 180);
      const delay = Number(window.waitForTenantDelay ?? 2000);
      const transientStatuses = new Set([404, 502, 503, 504]);
      for (let i = 0; i < attempts; i++) {
        try {
          const res = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'omit'
          });
          const ct = res.headers.get('Content-Type') || '';
          if (res.ok) {
            if (ct.includes('application/json')) {
              const data = await res.json();
              if (data.error) throw new Error(data.error);
              if (data.status === 'ok') return;
            }
          } else if (transientStatuses.has(res.status)) {
            addLog(`Tenant noch nicht erreichbar (HTTP ${res.status})`);
          } else {
            let msg = 'Tenant nicht verfügbar';
            try {
              if (ct.includes('application/json')) {
                const data = await res.json();
                msg = data.error || msg;
              } else {
                msg = await res.text();
              }
            } catch (_) {}
            throw new Error(msg || `HTTP ${res.status}`);
          }
        } catch (e) {
          const message = typeof e === 'object' && e !== null && 'message' in e
            ? String(e.message)
            : typeof e === 'string'
              ? e
              : '';
          if (/certificate|tls|ssl/i.test(message)) {
            addLog('HTTPS-Zertifikat noch nicht verfügbar');
          } else if (/fetch|network|net::|load failed|dns/i.test(message)) {
            addLog('Verbindung zum Tenant fehlgeschlagen – versuche es erneut');
          } else {
            throw e instanceof Error ? e : new Error(message || String(e));
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
        const ct = tRes.headers.get('Content-Type');
        let tData;
        let tText = '';
        try {
          if (ct && ct.includes('application/json')) {
            tData = await tRes.json();
          } else {
            tText = await tRes.text();
          }
        } catch (_) {}
        if (!tRes.ok || (tData && tData.error)) {
          mark('create', false);
          let msg = '';
          if (!tRes.ok) {
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
                msg = (tData && tData.error) || tText || 'Mandant anlegen fehlgeschlagen';
            }
          } else {
            msg = tData && tData.error ? tData.error : 'Mandant anlegen fehlgeschlagen';
          }
          throw new Error(msg);
        }
        mark('create', true);
        let onboardingResult = null;
        start('import');
        try {
          onboardingResult = await onboardTenant(subdomain);
          if (!onboardingResult || onboardingResult.status !== 'completed') {
            throw new Error('Onboarding konnte nicht gestartet werden.');
          }
          mark('import', true);
        } catch (e) {
          mark('import', false);
          throw e;
        }
        start('proxy');
        await wait(0);
        mark('proxy', true);
        start('ssl');
        await wait(0);
        mark('ssl', true);

        const isSingleContainer = onboardingResult && onboardingResult.mode === 'single-container';

        start('wait');
        if (isSingleContainer) {
          addLog('Single-Container-Modus aktiv – überspringe Wartezeit.');
        } else {
          await waitForTenant(subdomain);
        }
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

        await fetch(withBase('/onboarding/session'), {
          method: 'DELETE',
          credentials: 'same-origin',
          headers: {
            'X-CSRF-Token': window.csrfToken || '',
            'X-Requested-With': 'fetch'
          }
        });
        sessionData = {};
        const targetOrigin = computeTenantOrigin(subdomain);
        const targetUrl = targetOrigin ? `${targetOrigin}/` : null;
        if (targetUrl && isAllowed(targetUrl)) {
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
      const url = new URL(window.location);
      url.searchParams.delete('session_id');
      url.searchParams.set('step', '4');
      window.history.replaceState({}, '', url);
      showStep(4);
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
      const subdomain = sessionData.subdomain || '';
      const email = sessionData.email || '';

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

